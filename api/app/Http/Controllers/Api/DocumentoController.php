<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentoRequest;
use App\Http\Requests\UpdateDocumentoRequest;
use App\Models\Documento;
use App\Models\Version;
use App\Services\AuditoriaService;
use App\Services\ConvenioService;
use App\Services\DocumentoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DocumentoController extends Controller
{
    public function destroy(string $id, DocumentoService $service): JsonResponse
    {
        try {
            $service->destroy($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Documento o convenio asociado no encontrado.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al eliminar el documento.',
            ], 500);
        }

        return response()->json([
            'message' => 'Documento eliminado correctamente.',
        ]);
    }

    public function update(
        UpdateDocumentoRequest $request,
        string $id,
        DocumentoService $service
    ): JsonResponse {
        try {
            $documento = $service->update(
                $id,
                $request->validated(),
                $request->file('archivo'),
                $request->user()
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Documento o convenio asociado no encontrado.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al actualizar el documento.',
            ], 500);
        }

        return response()->json([
            'message' => 'Documento actualizado correctamente.',
            'data' => [
                'documento' => $this->documentoData($documento),
                'version' => $documento->ultimaVersion === null
                    ? null
                    : $this->versionData($documento->ultimaVersion),
            ],
        ]);
    }

    public function store(
        StoreDocumentoRequest $request,
        string $id,
        DocumentoService $service
    ): JsonResponse {
        try {
            $documento = $service->storeInitial(
                $id,
                $request->validated(),
                $request->file('archivo'),
                $request->user()
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al cargar el documento.',
            ], 500);
        }

        return response()->json([
            'message' => 'Documento cargado correctamente.',
            'data' => [
                'documento' => $this->documentoData($documento),
                'version' => $this->versionData($documento->ultimaVersion),
            ],
        ], 201);
    }

    public function index(
        Request $request,
        string $id,
        DocumentoService $service,
        ConvenioService $convenioService
    ): JsonResponse {
        try {
            $documentos = $service->listForConvenio(
                $id,
                $request->user(),
                $convenioService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }

        return response()->json([
            'data' => $documentos->map(fn (Documento $documento) => [
                'documento' => $this->documentoData($documento),
                'ultima_version' => $documento->ultimaVersion === null
                    ? null
                    : $this->versionData($documento->ultimaVersion),
            ]),
        ]);
    }

    public function show(
        Request $request,
        string $id,
        DocumentoService $service,
        ConvenioService $convenioService
    ): JsonResponse {
        try {
            $documento = $service->findWithHistory(
                $id,
                $request->user(),
                $convenioService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Documento no encontrado.',
            ], 404);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }

        return response()->json([
            'data' => [
                'documento' => $this->documentoData($documento),
                'versiones' => $documento->versiones->map(
                    fn (Version $version) => $this->versionData($version)
                ),
            ],
        ]);
    }

    public function downloadLatest(
        Request $request,
        string $id,
        DocumentoService $service,
        ConvenioService $convenioService
    ): StreamedResponse|JsonResponse {
        try {
            $documento = $service->findWithLatestVersion(
                $id,
                $request->user(),
                $convenioService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Documento no encontrado.',
            ], 404);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }

        if ($documento->ultimaVersion === null) {
            return response()->json([
                'message' => 'El documento no tiene versiones para descargar.',
            ], 404);
        }

        return $this->downloadVersion($documento->ultimaVersion);
    }

    public function verifyHash(
        Request $request,
        string $id,
        DocumentoService $service,
        ConvenioService $convenioService,
        AuditoriaService $auditoriaService
    ): JsonResponse {
        try {
            $resultado = $service->verifyIntegrityHash(
                $id,
                $request->user(),
                $convenioService,
                $auditoriaService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'No se encontró el documento, su última versión o el archivo asociado.',
            ], 404);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        } catch (\RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo completar la verificación de integridad.',
            ], 500);
        }

        return response()->json($resultado);
    }

    public function downloadSpecific(
        Request $request,
        string $idVersion,
        DocumentoService $service,
        ConvenioService $convenioService
    ): StreamedResponse|JsonResponse {
        try {
            $version = $service->findVersion(
                $idVersion,
                $request->user(),
                $convenioService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Versión no encontrada.',
            ], 404);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }

        return $this->downloadVersion($version);
    }

    private function downloadVersion(Version $version): StreamedResponse|JsonResponse
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($version->ruta_interna)) {
            return response()->json([
                'message' => 'El archivo de la versión no está disponible.',
            ], 404);
        }

        return $disk->download($version->ruta_interna, $version->nombre_archivo, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function documentoData(Documento $documento): array
    {
        return [
            'id_documento' => $documento->id_documento,
            'tipo' => $documento->tipo,
            'titulo' => $documento->titulo,
            'id_instrumento' => $documento->id_instrumento,
        ];
    }

    private function versionData(Version $version): array
    {
        return [
            'id_version' => $version->id_version,
            'numero' => $version->numero,
            'nombre_archivo' => $version->nombre_archivo,
            'tamanio_bytes' => $version->tamanio_bytes,
            'hash_sha256' => $version->hash_sha256,
            'fecha_carga' => $version->fecha_carga,
            'id_documento' => $version->id_documento,
            'id_usuario' => $version->id_usuario,
        ];
    }
}
