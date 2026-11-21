<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstadisticaIndexRequest;
use App\Models\Usuario;
use App\Services\ConvenioService;
use App\Services\EstadisticaService;
use Illuminate\Http\JsonResponse;

class EstadisticaController extends Controller
{
    public function __construct(
        private EstadisticaService $service,
        private ConvenioService $convenioService
    ) {}

    public function index(EstadisticaIndexRequest $request): JsonResponse
    {
        $usuario = $request->user();
        if (! $usuario instanceof Usuario) {
            return $this->forbidden();
        }

        $scope = $this->convenioService->visibilityScope($usuario);
        if ($scope === null) {
            return $this->forbidden();
        }

        $filters = $request->validated();
        $requestedUnit = $filters['id_unidad_academica'] ?? null;
        $scopeUnit = $scope['id_unidad_academica'];
        if (
            $scopeUnit !== null
            && $requestedUnit !== null
            && (int) $requestedUnit !== $scopeUnit
        ) {
            return $this->forbidden();
        }

        if ($scopeUnit === null && $requestedUnit !== null) {
            $scope['id_unidad_academica'] = (int) $requestedUnit;
        }

        $statisticsFilters = [
            'fecha_desde' => $filters['fecha_desde'] ?? null,
            'fecha_hasta' => $filters['fecha_hasta'] ?? null,
            'tipo' => $filters['tipo'] ?? null,
        ];

        $data = $this->service->resumen(
            (int) ($filters['dias'] ?? 90),
            $scope,
            $statisticsFilters
        );

        return response()->json([
            'message' => 'Estadísticas obtenidas correctamente.',
            'data' => $data,
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'message' => 'No tiene permisos para consultar estas estadísticas.',
        ], 403);
    }
}
