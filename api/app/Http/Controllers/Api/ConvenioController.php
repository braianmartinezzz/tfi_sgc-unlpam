<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConvenioRequest;
use App\Services\ConvenioService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ConvenioController extends Controller
{
    public function store(StoreConvenioRequest $request, ConvenioService $service): JsonResponse
    {
        try {
            $instrumento = $service->store($request->validated());
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
}
