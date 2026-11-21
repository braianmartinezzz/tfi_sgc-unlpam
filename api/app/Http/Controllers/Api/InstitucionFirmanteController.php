<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoCatalogoRequest;
use App\Http\Requests\StoreInstitucionFirmanteRequest;
use App\Http\Requests\UpdateInstitucionFirmanteRequest;
use App\Services\InstitucionFirmanteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class InstitucionFirmanteController extends Controller
{
    public function index(
        Request $request,
        InstitucionFirmanteService $service
    ): JsonResponse {
        $instituciones = $service->paginate($request->only([
            'buscar',
            'tipo_institucion',
            'activo',
        ]));

        return response()->json($instituciones);
    }

    public function show(string $id, InstitucionFirmanteService $service): JsonResponse
    {
        try {
            $institucion = $service->findById($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Institución firmante no encontrada.',
            ], 404);
        }

        return response()->json([
            'data' => $institucion,
        ]);
    }

    public function store(
        StoreInstitucionFirmanteRequest $request,
        InstitucionFirmanteService $service
    ): JsonResponse {
        try {
            $institucion = $service->store(
                $request->validated(),
                (int) $request->user()->id_usuario
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al registrar la institución firmante.',
            ], 500);
        }

        return response()->json([
            'message' => 'Institución firmante registrada correctamente.',
            'data' => $institucion,
        ], 201);
    }

    public function update(
        UpdateInstitucionFirmanteRequest $request,
        string $id,
        InstitucionFirmanteService $service
    ): JsonResponse {
        try {
            $service->findById($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Institución firmante no encontrada.',
            ], 404);
        }

        if (! Gate::allows('editar-institucion', $id)) {
            return response()->json([
                'message' => 'No tenés permiso para editar esta institución.',
            ], 403);
        }

        try {
            $institucion = $service->update(
                $id,
                $request->validated(),
                (int) $request->user()->id_usuario
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Institución firmante no encontrada.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al actualizar la institución firmante.',
            ], 500);
        }

        return response()->json([
            'message' => 'Institución firmante actualizada correctamente.',
            'data' => $institucion,
        ]);
    }

    public function cambiarEstado(
        CambiarEstadoCatalogoRequest $request,
        string $id,
        InstitucionFirmanteService $service
    ): JsonResponse {
        try {
            $resultado = $service->cambiarEstado(
                $id,
                $request->boolean('activo'),
                (int) $request->user()->id_usuario
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Institución firmante no encontrada.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al cambiar el estado de la institución firmante.',
            ], 500);
        }

        return response()->json([
            'message' => $request->boolean('activo')
                ? 'Institución firmante activada correctamente.'
                : 'Institución firmante desactivada correctamente.',
            'data' => $resultado['data'],
            'resumen' => $resultado['resumen'],
        ]);
    }
}
