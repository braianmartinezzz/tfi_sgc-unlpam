<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\Instrumento;
use App\Models\Usuario;
use App\Models\Version;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentoService
{
    public function registrarVersionFirmada(
        Instrumento $instrumento,
        ?Documento $documento,
        UploadedFile $file,
        Usuario $usuario
    ): string {
        if ($documento === null) {
            $documento = Documento::query()->create([
                'tipo' => 'Convenio',
                'titulo' => $instrumento->titulo,
                'id_instrumento' => $instrumento->id_instrumento,
                'es_documento_firmado' => true,
            ]);
        }

        return $this->registrarNuevaVersion(
            $instrumento,
            $documento,
            $file,
            $usuario
        );
    }

    public function registrarDocumentoResolucion(
        Instrumento $instrumento,
        UploadedFile $file,
        Usuario $usuario
    ): string {
        $documento = Documento::query()->create([
            'tipo' => 'resolucion',
            'titulo' => 'Resolución de rescisión',
            'id_instrumento' => $instrumento->id_instrumento,
        ]);

        return $this->registrarNuevaVersion(
            $instrumento,
            $documento,
            $file,
            $usuario
        );
    }

    public function storeInitial(
        string $convenioId,
        array $data,
        UploadedFile $file,
        Usuario $usuario
    ): Documento {
        $rutaInterna = null;

        try {
            return DB::transaction(function () use (
                $convenioId,
                $data,
                $file,
                $usuario,
                &$rutaInterna
            ) {
                $convenio = Convenio::query()
                    ->lockForUpdate()
                    ->findOrFail($convenioId);
                $instrumento = $convenio->instrumento()
                    ->lockForUpdate()
                    ->first();

                if ($instrumento === null) {
                    throw (new ModelNotFoundException)->setModel(
                        Instrumento::class,
                        [$convenio->id_instrumento]
                    );
                }

                if (! in_array($instrumento->estado, ['Borrador', 'EnCorreccion'], true)) {
                    throw new \DomainException(
                        'Solo se pueden cargar documentos en estado Borrador o EnCorreccion.'
                    );
                }

                $hashSha256 = hash_file('sha256', $file->getRealPath());

                if ($hashSha256 === false) {
                    throw new RuntimeException('No se pudo calcular el hash del archivo.');
                }

                $rutaInterna = $file->store('documentos', 'private');

                if ($rutaInterna === false) {
                    throw new RuntimeException('No se pudo almacenar el archivo.');
                }

                $documento = Documento::query()->create([
                    'tipo' => $data['tipo'],
                    'titulo' => $data['titulo'],
                    'id_instrumento' => $instrumento->id_instrumento,
                ]);

                $documento->versiones()->create([
                    'numero' => 1,
                    'nombre_archivo' => $file->getClientOriginalName(),
                    'tamanio_bytes' => $file->getSize(),
                    'hash_sha256' => $hashSha256,
                    'ruta_interna' => $rutaInterna,
                    'fecha_carga' => now(),
                    'id_usuario' => $usuario->id_usuario,
                ]);

                return $documento->load('ultimaVersion');
            });
        } catch (Throwable $exception) {
            if ($rutaInterna !== null) {
                Storage::disk('private')->delete($rutaInterna);
            }

            throw $exception;
        }
    }

    public function update(
        string $documentoId,
        array $data,
        ?UploadedFile $file,
        Usuario $usuario
    ): Documento {
        $rutaInterna = null;

        try {
            return DB::transaction(function () use (
                $documentoId,
                $data,
                $file,
                $usuario,
                &$rutaInterna
            ) {
                $documento = Documento::query()
                    ->lockForUpdate()
                    ->findOrFail($documentoId);

                if ($file !== null) {
                    $convenio = Convenio::query()
                        ->lockForUpdate()
                        ->findOrFail($documento->id_instrumento);
                    $instrumento = $convenio->instrumento()
                        ->lockForUpdate()
                        ->first();

                    if ($instrumento === null) {
                        throw (new ModelNotFoundException)->setModel(
                            Instrumento::class,
                            [$convenio->id_instrumento]
                        );
                    }

                    if (! in_array($instrumento->estado, ['Borrador', 'EnCorreccion'], true)) {
                        throw new \DomainException(
                            'Solo se pueden cargar documentos en estado Borrador o EnCorreccion.'
                        );
                    }

                    $versionAnterior = $documento->versiones()
                        ->orderByDesc('numero')
                        ->lockForUpdate()
                        ->first();
                    $numeroVersion = ($versionAnterior?->numero ?? 0) + 1;

                    $hashSha256 = hash_file('sha256', $file->getRealPath());

                    if ($hashSha256 === false) {
                        throw new RuntimeException('No se pudo calcular el hash del archivo.');
                    }

                    $rutaInterna = $file->store('documentos', 'private');

                    if ($rutaInterna === false) {
                        throw new RuntimeException('No se pudo almacenar el archivo.');
                    }

                    $documento->fill(array_intersect_key(
                        $data,
                        array_flip(['tipo', 'titulo'])
                    ))->save();

                    $documento->versiones()->create([
                        'numero' => $numeroVersion,
                        'nombre_archivo' => $file->getClientOriginalName(),
                        'tamanio_bytes' => $file->getSize(),
                        'hash_sha256' => $hashSha256,
                        'ruta_interna' => $rutaInterna,
                        'fecha_carga' => now(),
                        'id_usuario' => $usuario->id_usuario,
                    ]);
                } else {
                    $documento->fill(array_intersect_key(
                        $data,
                        array_flip(['tipo', 'titulo'])
                    ))->save();
                }

                return $documento->load('ultimaVersion');
            });
        } catch (Throwable $exception) {
            if ($rutaInterna !== null) {
                Storage::disk('private')->delete($rutaInterna);
            }

            throw $exception;
        }
    }

    public function destroy(string $documentoId): void
    {
        DB::transaction(function () use ($documentoId) {
            $documento = Documento::query()
                ->lockForUpdate()
                ->findOrFail($documentoId);
            $convenio = Convenio::query()
                ->lockForUpdate()
                ->findOrFail($documento->id_instrumento);
            $instrumento = $convenio->instrumento()
                ->lockForUpdate()
                ->first();

            if ($instrumento === null) {
                throw (new ModelNotFoundException)->setModel(
                    Instrumento::class,
                    [$convenio->id_instrumento]
                );
            }

            if (! in_array($instrumento->estado, ['Borrador', 'EnCorreccion'], true)) {
                throw new \DomainException(
                    'Solo se pueden eliminar documentos en estado Borrador o EnCorreccion.'
                );
            }

            $rutasInternas = $documento->versiones()
                ->lockForUpdate()
                ->pluck('ruta_interna')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $documento->versiones()->delete();

            if (! $documento->delete()) {
                throw new RuntimeException('No se pudo eliminar el documento.');
            }

            DB::afterCommit(function () use ($documentoId, $rutasInternas) {
                foreach ($rutasInternas as $rutaInterna) {
                    try {
                        if (! Storage::disk('private')->delete($rutaInterna)) {
                            Log::error('No se pudo eliminar un archivo de documento.', [
                                'id_documento' => $documentoId,
                            ]);
                        }
                    } catch (Throwable $exception) {
                        Log::error('Falló la eliminación de un archivo de documento.', [
                            'id_documento' => $documentoId,
                            'exception' => $exception,
                        ]);
                    }
                }
            });
        });
    }

    public function listForConvenio(
        string $convenioId,
        Usuario $usuario,
        ConvenioService $convenioService
    ) {
        $convenio = Convenio::query()->findOrFail($convenioId);
        $convenioService->assertCanView($convenio, $usuario);

        return Documento::query()
            ->where('id_instrumento', $convenio->id_instrumento)
            ->with('ultimaVersion')
            ->get();
    }

    public function findWithHistory(
        string $documentoId,
        Usuario $usuario,
        ConvenioService $convenioService
    ): Documento {
        $documento = Documento::query()
            ->with('instrumento.convenio')
            ->findOrFail($documentoId);

        $this->assertCanViewDocument($documento, $usuario, $convenioService);

        return $documento->load([
            'versiones' => fn ($query) => $query->orderByDesc('numero'),
        ]);
    }

    public function findWithLatestVersion(
        string $documentoId,
        Usuario $usuario,
        ConvenioService $convenioService
    ): Documento {
        $documento = Documento::query()
            ->with('instrumento.convenio')
            ->findOrFail($documentoId);

        $this->assertCanViewDocument($documento, $usuario, $convenioService);

        return $documento->load('ultimaVersion');
    }

    public function findVersion(
        string $versionId,
        Usuario $usuario,
        ConvenioService $convenioService
    ): Version {
        $version = Version::query()
            ->with('documento.instrumento.convenio')
            ->findOrFail($versionId);
        $documento = $version->documento;

        if ($documento === null) {
            throw (new ModelNotFoundException)->setModel(
                Documento::class,
                [$version->id_documento]
            );
        }

        $this->assertCanViewDocument($documento, $usuario, $convenioService);

        return $version;
    }

    private function assertCanViewDocument(
        Documento $documento,
        Usuario $usuario,
        ConvenioService $convenioService
    ): void {
        $convenio = $documento->instrumento?->convenio;

        if ($convenio === null) {
            throw (new ModelNotFoundException)->setModel(
                Convenio::class,
                [$documento->id_instrumento]
            );
        }

        $convenioService->assertCanView($convenio, $usuario);
    }

    public function eliminarArchivoNuevo(?string $rutaInterna, int $instrumentoId): void
    {
        if ($rutaInterna === null) {
            return;
        }

        try {
            if (! Storage::disk('private')->delete($rutaInterna)) {
                Log::error('No se pudo limpiar el archivo nuevo de una operación revertida.', [
                    'id_instrumento' => $instrumentoId,
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('Falló la limpieza de un archivo nuevo de una operación revertida.', [
                'id_instrumento' => $instrumentoId,
                'exception' => $exception,
            ]);
        }
    }

    private function registrarNuevaVersion(
        Instrumento $instrumento,
        Documento $documento,
        UploadedFile $file,
        Usuario $usuario
    ): string {
        $rutaInterna = null;

        try {
            $versionAnterior = $documento->versiones()
                ->orderByDesc('numero')
                ->lockForUpdate()
                ->first();
            $numeroVersion = ($versionAnterior?->numero ?? 0) + 1;
            $hashSha256 = hash_file('sha256', $file->getRealPath());

            if ($hashSha256 === false) {
                throw new RuntimeException('No se pudo calcular el hash del archivo.');
            }

            $rutaAlmacenada = $file->store('documentos', 'private');

            if ($rutaAlmacenada === false) {
                throw new RuntimeException('No se pudo almacenar el archivo.');
            }

            $rutaInterna = $rutaAlmacenada;
            $documento->versiones()->create([
                'numero' => $numeroVersion,
                'nombre_archivo' => $file->getClientOriginalName(),
                'tamanio_bytes' => $file->getSize(),
                'hash_sha256' => $hashSha256,
                'ruta_interna' => $rutaInterna,
                'fecha_carga' => now(),
                'id_usuario' => $usuario->id_usuario,
            ]);

            return $rutaInterna;
        } catch (Throwable $exception) {
            $this->eliminarArchivoNuevo(
                $rutaInterna,
                (int) $instrumento->id_instrumento
            );

            throw $exception;
        }
    }
}
