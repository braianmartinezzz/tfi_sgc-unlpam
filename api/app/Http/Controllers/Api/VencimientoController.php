<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VencimientoIndexRequest;
use App\Services\VencimientoService;
use Illuminate\Http\JsonResponse;

class VencimientoController extends Controller
{
    public function index(VencimientoIndexRequest $request, VencimientoService $service): JsonResponse
    {
        $resultado = $service->proximos($request->validated());

        return response()->json([
            'data' => $resultado['items'],
            'meta' => [
                'dias' => $resultado['dias'],
                'desde' => $resultado['desde'],
                'hasta' => $resultado['hasta'],
                'total' => count($resultado['items']),
            ],
        ]);
    }
}