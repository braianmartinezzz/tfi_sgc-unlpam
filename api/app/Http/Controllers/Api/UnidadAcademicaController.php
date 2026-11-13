<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnidadAcademicaRequest;
use App\Services\UnidadAcademicaService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadAcademicaController extends Controller
{
    public function index(Request $request, UnidadAcademicaService $service): JsonResponse
    {
        $unidades = $service->all($request->only(['id_institucion', 'buscar']));

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
        $unidad = $service->store($request->validated());

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
        try {
            $unidad = $service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Unidad académica no encontrada.',
            ], 404);
        }

        return response()->json([
            'message' => 'Unidad académica actualizada correctamente.',
            'data' => $unidad,
        ]);
    }

    public function destroy(string $id, UnidadAcademicaService $service): JsonResponse
    {
        try {
            $service->destroy($id);
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
            'message' => 'Unidad académica eliminada correctamente.',
        ]);
    }
}