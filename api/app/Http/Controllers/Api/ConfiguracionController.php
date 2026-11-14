<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UmbralesRequest;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ConfiguracionController extends Controller
{
    public function umbrales(ConfiguracionService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->umbrales(),
        ]);
    }

    public function actualizarUmbrales(
        UmbralesRequest $request,
        ConfiguracionService $service,
        AuditoriaService $auditoria
    ): JsonResponse {
        try {
            $umbrales = $service->actualizarUmbrales($request->validated());
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $auditoria->registrar(
            'Configuración de umbrales',
            (int) $request->user()->id_usuario,
            null,
            json_encode($umbrales)
        );

        return response()->json([
            'message' => 'Umbrales de alerta actualizados correctamente.',
            'data' => $umbrales,
        ]);
    }
}
