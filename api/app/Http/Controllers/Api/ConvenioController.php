<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConvenioRequest;
use App\Http\Requests\UpdateConvenioRequest;
use App\Services\ConvenioService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ConvenioController extends Controller
{
    public function index(Request $request, ConvenioService $service): JsonResponse
    {
        $convenios = $service->paginate($request->only([
            'estado',
            'tipo',
            'es_marco',
            'id_unidad_academica',
            'buscar',
        ]));

        return response()->json($convenios);
    }

    public function show(string $id, ConvenioService $service): JsonResponse
    {
        $convenio = $service->findById($id);

        if ($convenio === null) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $convenio,
        ]);
    }

    public function update(
        UpdateConvenioRequest $request,
        string $id,
        ConvenioService $service
    ): JsonResponse {
        try {
            $convenio = $service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al actualizar el convenio.',
            ], 500);
        }

        return response()->json([
            'message' => 'Convenio actualizado correctamente.',
            'data' => [
                'instrumento' => $convenio->instrumento,
                'convenio' => $convenio,
                'subtipo' => $convenio->marco ?? $convenio->especifico,
                'instituciones_firmantes' => $convenio->institucionesFirmantes,
            ],
        ]);
    }

    public function store(StoreConvenioRequest $request, ConvenioService $service): JsonResponse
    {
        try {
            $instrumento = $service->store(
                $request->validated(),
                $request->user()
            );
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
