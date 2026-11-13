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
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DocumentoService
{
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

    public function listForConvenio(string $convenioId)
    {
        $convenio = Convenio::query()->findOrFail($convenioId);

        return Documento::query()
            ->where('id_instrumento', $convenio->id_instrumento)
            ->with('ultimaVersion')
            ->get();
    }

    public function findWithHistory(string $documentoId): Documento
    {
        return Documento::query()
            ->with(['versiones' => fn ($query) => $query->orderByDesc('numero')])
            ->findOrFail($documentoId);
    }

    public function findWithLatestVersion(string $documentoId): Documento
    {
        return Documento::query()
            ->with('ultimaVersion')
            ->findOrFail($documentoId);
    }

    public function findVersion(string $versionId): Version
    {
        return Version::query()->findOrFail($versionId);
    }
}
