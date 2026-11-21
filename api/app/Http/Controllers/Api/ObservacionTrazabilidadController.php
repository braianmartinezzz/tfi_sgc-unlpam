<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ObservacionTrazabilidadService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObservacionTrazabilidadController extends Controller
{
    public function show(
        Request $request,
        string $id,
        ObservacionTrazabilidadService $service
    ): JsonResponse {
        try {
            $trazabilidad = $service->consultar($id, $request->user());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para consultar la trazabilidad de las observaciones de este convenio.',
            ], 403);
        }

        return response()->json([
            'data' => $trazabilidad,
        ]);
    }
}
