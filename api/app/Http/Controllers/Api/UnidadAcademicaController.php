<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoCatalogoRequest;
use App\Http\Requests\UnidadAcademicaRequest;
use App\Services\UnidadAcademicaService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UnidadAcademicaController extends Controller
{
    public function index(Request $request, UnidadAcademicaService $service): JsonResponse
    {
        $unidades = $service->all($request->only(['id_institucion', 'buscar', 'activo']));

        return response()->json([
            'data' => $unidades,
        ]);
    }

    public function show(string $id, UnidadAcademicaService $service): JsonResponse
    {
        $unidad = $service->findById($id);

        if ($unidad === null) {
            return response()->json([
                'message' => 'Unidad académica no encontrada.',
            ], 404);
        }

        return response()->json([
            'data' => $unidad,
        ]);
    }

    public function store(UnidadAcademicaRequest $request, UnidadAcademicaService $service): JsonResponse
    {
        try {
            $unidad = $service->store(
                $request->validated(),
                (int) $request->user()->id_usuario
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Institución firmante no encontrada.',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Unidad académica creada correctamente.',
            'data' => $unidad,
        ], 201);
    }

    public function update(
        UnidadAcademicaRequest $request,
        string $id,
        UnidadAcademicaService $service
    ): JsonResponse {
        $unidadActual = $service->findById($id);
        if ($unidadActual === null) {
            return response()->json([
                'message' => 'Unidad académica no encontrada.',
            ], 404);
        }

        if (! Gate::allows('editar-unidad', $id)) {
            return response()->json([
                'message' => 'No tenés permiso para editar esta unidad académica.',
            ], 403);
        }

        $datos = $request->validated();
        if ($request->user()->rol?->nombre === 'Gestión'
            && isset($datos['id_institucion'])
            && (int) $datos['id_institucion'] !== (int) $unidadActual->id_institucion) {
            return response()->json([
                'message' => 'No podés mover una unidad académica a otra institución.',
            ], 403);
        }

        try {
            $unidad = $service->update(
                $id,
                $datos,
                (int) $request->user()->id_usuario
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Unidad académica no encontrada.',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Unidad académica actualizada correctamente.',
            'data' => $unidad,
        ]);
    }

    public function cambiarEstado(
        CambiarEstadoCatalogoRequest $request,
        string $id,
        UnidadAcademicaService $service
    ): JsonResponse {
        try {
            $resultado = $service->cambiarEstado(
                $id,
                $request->boolean('activo'),
                (int) $request->user()->id_usuario
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Unidad académica no encontrada.',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => $request->boolean('activo')
                ? 'Unidad académica activada correctamente.'
                : 'Unidad académica desactivada correctamente.',
            'data' => $resultado['data'],
            'resumen' => $resultado['resumen'],
        ]);
    }
}
