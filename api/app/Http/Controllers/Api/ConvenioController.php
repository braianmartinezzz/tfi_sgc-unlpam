<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnviarARevisionRequest;
use App\Http\Requests\ObservacionRequest;
use App\Http\Requests\RegistrarFirmaRequest;
use App\Http\Requests\RescindirConvenioRequest;
use App\Http\Requests\StoreConvenioRequest;
use App\Http\Requests\StoreRespuestaObservacionRequest;
use App\Http\Requests\UpdateConvenioRequest;
use App\Models\Observacion;
use App\Services\AuditoriaService;
use App\Services\ConvenioService;
use App\Services\DocumentoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ConvenioController extends Controller
{
    public function index(Request $request, ConvenioService $service): JsonResponse
    {
        $convenios = $service->paginate($request->only([
            'estado',
            'tipo',
            'es_marco',
            'id_unidad_academica',
            'buscar',
        ]), $request->user());

        return response()->json($convenios);
    }

    public function show(
        Request $request,
        string $id,
        ConvenioService $service
    ): JsonResponse {
        try {
            $convenio = $service->findById($id, $request->user());
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para consultar este convenio.',
            ], 403);
        }

        if ($convenio === null) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $convenio,
        ]);
    }

    public function observaciones(
        Request $request,
        string $id,
        ConvenioService $service
    ): JsonResponse {
        try {
            $observaciones = $service->listarObservaciones($id, $request->user());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para consultar las observaciones de este convenio.',
            ], 403);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al consultar las observaciones.',
            ], 500);
        }

        return response()->json([
            'data' => $observaciones->map(
                fn (Observacion $observacion): array => $this->observacionData($observacion)
            )->values(),
        ]);
    }

    public function crearObservacion(
        ObservacionRequest $request,
        string $id,
        ConvenioService $service,
        AuditoriaService $auditoriaService
    ): JsonResponse {
        try {
            $observacion = $service->crearObservacion(
                $id,
                $request->validated()['contenido'],
                $request->user(),
                $auditoriaService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para crear observaciones en este convenio.',
            ], 403);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al crear la observación.',
            ], 500);
        }

        return response()->json([
            'message' => 'Observación creada correctamente.',
            'data' => $this->observacionData($observacion),
        ], 201);
    }

    public function actualizarObservacion(
        ObservacionRequest $request,
        string $idObservacion,
        ConvenioService $service,
        AuditoriaService $auditoriaService
    ): JsonResponse {
        try {
            $observacion = $service->actualizarObservacion(
                $idObservacion,
                $request->validated()['contenido'],
                $request->user(),
                $auditoriaService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Observación o convenio asociado no encontrados.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para modificar esta observación.',
            ], 403);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al modificar la observación.',
            ], 500);
        }

        return response()->json([
            'message' => 'Observación modificada correctamente.',
            'data' => $this->observacionData($observacion),
        ]);
    }

    public function eliminarObservacion(
        Request $request,
        string $idObservacion,
        ConvenioService $service,
        AuditoriaService $auditoriaService
    ): JsonResponse {
        try {
            $service->eliminarObservacion(
                $idObservacion,
                $request->user(),
                $auditoriaService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Observación o convenio asociado no encontrados.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para eliminar esta observación.',
            ], 403);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al eliminar la observación.',
            ], 500);
        }

        return response()->json([
            'message' => 'Observación eliminada correctamente.',
        ]);
    }

    public function responderObservacion(
        StoreRespuestaObservacionRequest $request,
        string $id,
        string $idObservacion,
        ConvenioService $service
    ): JsonResponse {
        try {
            $observacion = $service->responderObservacion(
                $id,
                $idObservacion,
                $request->validated()['respuesta'],
                $request->user()
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio u observación no encontrados.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para responder observaciones de este convenio.',
            ], 403);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al responder la observación.',
            ], 500);
        }

        return response()->json([
            'message' => 'Observación respondida correctamente.',
            'data' => [
                'id_observacion' => $observacion->id_observacion,
                'respuesta' => $observacion->respuesta,
                'estado_tramite' => $observacion->estado_tramite,
                'fecha_resolucion' => $observacion->fecha_resolucion,
            ],
        ]);
    }

    private function observacionData(Observacion $observacion): array
    {
        $observacion->loadMissing('usuario:id_usuario,nombre');

        return [
            'id_observacion' => $observacion->id_observacion,
            'contenido' => $observacion->contenido,
            'fecha' => $observacion->fecha,
            'estado_tramite' => $observacion->estado_tramite,
            'resolucion' => $observacion->resolucion,
            'respuesta' => $observacion->respuesta,
            'fecha_resolucion' => $observacion->fecha_resolucion,
            'usuario' => [
                'id_usuario' => $observacion->usuario->id_usuario,
                'nombre' => $observacion->usuario->nombre,
            ],
        ];
    }

    public function registrarFirma(
        RegistrarFirmaRequest $request,
        string $id,
        ConvenioService $service,
        DocumentoService $documentoService
    ): JsonResponse {
        try {
            $resultado = $service->registrarFirma(
                $id,
                $request->validated()['fecha_firma'],
                $request->file('archivo_pdf'),
                $request->user(),
                $documentoService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para registrar la firma de este convenio.',
            ], 403);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al registrar la firma.',
            ], 500);
        }

        return response()->json([
            'message' => 'Firma registrada correctamente.',
            'data' => $resultado,
        ]);
    }

    public function rescindir(
        RescindirConvenioRequest $request,
        string $id,
        ConvenioService $service,
        DocumentoService $documentoService
    ): JsonResponse {
        try {
            $resultado = $service->rescindir(
                $id,
                $request->validated()['motivo'],
                $request->file('documento_resolucion'),
                $request->user(),
                $documentoService
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para rescindir este convenio.',
            ], 403);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al rescindir el convenio.',
            ], 500);
        }

        return response()->json([
            'message' => 'Convenio rescindido correctamente.',
            'data' => $resultado,
        ]);
    }

    public function update(
        UpdateConvenioRequest $request,
        string $id,
        ConvenioService $service
    ): JsonResponse {
        try {
            $convenio = $service->update($id, $request->validated());
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
                'message' => 'Ocurrió un error inesperado al actualizar el convenio.',
            ], 500);
        }

        return response()->json([
            'message' => 'Convenio actualizado correctamente.',
            'data' => [
                'instrumento' => $convenio->instrumento,
                'convenio' => $convenio,
                'subtipo' => $convenio->marco ?? $convenio->especifico,
                'instituciones_firmantes' => $convenio->institucionesFirmantes,
            ],
        ]);
    }

    public function destroy(string $id, ConvenioService $service): JsonResponse
    {
        try {
            $service->destroy($id);
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
                'message' => 'Ocurrió un error inesperado al eliminar el convenio.',
            ], 500);
        }

        return response()->json([
            'message' => 'Convenio eliminado correctamente.',
        ]);
    }

    public function store(StoreConvenioRequest $request, ConvenioService $service): JsonResponse
    {
        try {
            $instrumento = $service->store(
                $request->validated(),
                $request->user()
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al registrar el convenio.',
            ], 500);
        }

        $convenio = $instrumento->convenio;

        return response()->json([
            'message' => 'Convenio registrado correctamente.',
            'data' => [
                'instrumento' => $instrumento,
                'convenio' => $convenio,
                'subtipo' => $convenio->marco ?? $convenio->especifico,
                'instituciones_firmantes' => $convenio->institucionesFirmantes,
            ],
        ], 201);
    }

    public function enviarRevision(
        EnviarARevisionRequest $request,
        string $id,
        ConvenioService $service
    ): JsonResponse {
        try {
            $resultado = $service->enviarARevision(
                $id,
                $request->validated()['motivo'] ?? null,
                $request->user()
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para enviar este convenio a revisión.',
            ], 403);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al enviar el convenio a revisión.',
            ], 500);
        }

        return response()->json([
            'message' => 'Convenio enviado a revisión correctamente.',
            'estado' => 'Revisión',
            'historial' => $resultado,
        ]);
    }
}
