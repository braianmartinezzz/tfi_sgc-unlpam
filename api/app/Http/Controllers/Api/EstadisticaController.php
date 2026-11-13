<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadisticaIndexRequest;
use App\Services\EstadisticaService;
use Illuminate\Http\JsonResponse;

class EstadisticaController extends Controller
{
    public function __construct(private EstadisticaService $service)
    {
    }

    public function index(EstadisticaIndexRequest $request): JsonResponse
    {
        $data = $this->service->resumen(
            (int) $request->input('dias', 90),
            $request->filled('id_unidad_academica') ? (int) $request->input('id_unidad_academica') : null
        );

        return response()->json([
            'message' => 'Estadísticas obtenidas correctamente.',
            'data' => $data,
        ]);
    }
}