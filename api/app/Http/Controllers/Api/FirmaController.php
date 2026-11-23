<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DefinirFirmantesRequest;
use App\Http\Requests\FirmarConvenioRequest;
use App\Services\FirmaService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FirmaController extends Controller
{
    /** GET /api/firmas/pendientes */
    public function pendientes(Request $request, FirmaService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->pendientes($request->user()),
        ]);
    }

    /** GET /api/firmas/{id}/documento: PDF que el firmante tiene que leer. */
    public function documento(Request $request, string $id, FirmaService $service): StreamedResponse|JsonResponse
    {
        try {
            $version = $service->documentoParaFirmante($id, $request->user());
        } catch (ModelNotFoundException) {
            return $this->noEncontrado('El convenio no tiene un documento PDF disponible.');
        } catch (AuthorizationException $exception) {
            return $this->prohibido($exception);
        }

        return $this->descargar($version->ruta_interna, $version->nombre_archivo, 'application/pdf');
    }

    /** POST /api/convenios/{id}/firmar */
    public function firmar(FirmarConvenioRequest $request, string $id, FirmaService $service): JsonResponse
    {
        try {
            $resultado = $service->firmar($id, $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $exception->errors(),
            ], 422);
        } catch (ModelNotFoundException) {
            return $this->noEncontrado('Convenio no encontrado.');
        } catch (AuthorizationException $exception) {
            return $this->prohibido($exception);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => $resultado['estado'] === 'Firmado'
                ? 'Firma registrada. El convenio quedó firmado por todas las partes.'
                : 'Firma registrada correctamente.',
            'data' => $resultado,
        ]);
    }

    /** GET /api/convenios/{id}/firmas: constancia de firmas. */
    public function index(Request $request, string $id, FirmaService $service): JsonResponse
    {
        try {
            $constancia = $service->constancia($id, $request->user());
        } catch (ModelNotFoundException) {
            return $this->noEncontrado('Convenio no encontrado.');
        } catch (AuthorizationException $exception) {
            return $this->prohibido($exception);
        }

        return response()->json(['data' => $constancia]);
    }

    /** GET /api/convenios/{id}/constancia-firma */
    public function constanciaPdf(Request $request, string $id, FirmaService $service): StreamedResponse|JsonResponse
    {
        try {
            $constancia = $service->constanciaPdf($id, $request->user());
        } catch (ModelNotFoundException) {
            return $this->noEncontrado('Convenio no encontrado.');
        } catch (AuthorizationException $exception) {
            return $this->prohibido($exception);
        }

        return $this->descargar($constancia['ruta'], $constancia['nombre_archivo'], 'application/pdf');
    }

    /** PUT /api/convenios/{id}/firmantes */
    public function definirFirmantes(
        DefinirFirmantesRequest $request,
        string $id,
        FirmaService $service
    ): JsonResponse {
        try {
            $firmantes = $service->definirFirmantes(
                $id,
                $request->validated()['ids_persona'],
                $request->user()
            );
        } catch (ModelNotFoundException) {
            return $this->noEncontrado('Convenio no encontrado.');
        } catch (AuthorizationException $exception) {
            return $this->prohibido($exception);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Firmantes actualizados correctamente.',
            'data' => $firmantes,
        ]);
    }

    /** GET /api/convenios/{id}/firmas/{idPersona}/trazo */
    public function trazo(
        Request $request,
        string $id,
        string $idPersona,
        FirmaService $service
    ): StreamedResponse|JsonResponse {
        try {
            [$ruta, $nombre] = $service->trazoDe($id, $idPersona, $request->user());
        } catch (ModelNotFoundException) {
            return $this->noEncontrado('La firma no fue encontrada o todavía no fue registrada.');
        } catch (AuthorizationException $exception) {
            return $this->prohibido($exception);
        }

        return $this->descargar($ruta, $nombre, 'image/png');
    }

    private function descargar(string $ruta, string $nombre, string $contentType): StreamedResponse|JsonResponse
    {
        $disco = Storage::disk('private');

        if (! $disco->exists($ruta)) {
            return $this->noEncontrado('El archivo no está disponible.');
        }

        return $disco->download($ruta, $nombre, ['Content-Type' => $contentType]);
    }

    private function noEncontrado(string $mensaje): JsonResponse
    {
        return response()->json(['message' => $mensaje], 404);
    }

    private function prohibido(AuthorizationException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], 403);
    }
}
