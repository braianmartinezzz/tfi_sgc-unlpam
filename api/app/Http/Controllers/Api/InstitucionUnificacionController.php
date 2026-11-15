<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnificarInstitucionesRequest;
use App\Services\InstitucionUnificacionService;
use Illuminate\Http\JsonResponse;

class InstitucionUnificacionController extends Controller
{
    public function unificar(
        UnificarInstitucionesRequest $request,
        InstitucionUnificacionService $service
    ): JsonResponse {
        $data = $request->validated();

        $resultado = $service->unificar(
            (int) $data['id_principal'],
            array_map('intval', $data['ids_duplicadas']),
            (int) $request->user()->id_usuario
        );

        return response()->json([
            'message' => 'Instituciones unificadas correctamente.',
            'data' => $resultado,
        ]);
    }
}
