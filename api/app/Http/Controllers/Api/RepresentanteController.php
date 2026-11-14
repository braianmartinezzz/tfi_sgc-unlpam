<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRepresentanteRequest;
use App\Http\Requests\UpdateRepresentanteRequest;
use App\Models\Representante;
use App\Services\RepresentanteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class RepresentanteController extends Controller
{
    public function index(Request $request, RepresentanteService $service): JsonResponse
    {
        $representantes = $service->paginate($request->only([
            'id_institucion',
            'buscar',
        ]));

        $representantes->through(
            fn (Representante $representante) => $this->representanteData($representante)
        );

        return response()->json($representantes);
    }

    public function show(string $id, RepresentanteService $service): JsonResponse
    {
        try {
            $representante = $service->findById($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Representante legal no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $this->representanteData($representante),
        ]);
    }

    public function store(
        StoreRepresentanteRequest $request,
        RepresentanteService $service
    ): JsonResponse {
        try {
            $representante = $service->store($request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Institución firmante no encontrada.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al registrar el representante legal.',
            ], 500);
        }

        return response()->json([
            'message' => 'Representante legal registrado correctamente.',
            'data' => $this->representanteData($representante),
        ], 201);
    }

    public function update(
        UpdateRepresentanteRequest $request,
        string $id,
        RepresentanteService $service
    ): JsonResponse {
        try {
            $representante = $service->update($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Representante legal no encontrado.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al actualizar el representante legal.',
            ], 500);
        }

        return response()->json([
            'message' => 'Representante legal actualizado correctamente.',
            'data' => $this->representanteData($representante),
        ]);
    }

    public function destroy(string $id, RepresentanteService $service): JsonResponse
    {
        try {
            $service->destroy($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Representante legal no encontrado.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al eliminar el representante legal.',
            ], 500);
        }

        return response()->json([
            'message' => 'Representante legal eliminado correctamente.',
        ]);
    }

    private function representanteData(Representante $representante): array
    {
        return [
            'id_persona' => $representante->id_persona,
            'cargo' => $representante->cargo,
            'id_institucion' => $representante->id_institucion,
            'persona' => [
                'id_persona' => $representante->persona->id_persona,
                'nombre' => $representante->persona->nombre,
                'apellido' => $representante->persona->apellido,
                'dni' => $representante->persona->dni,
                'cuil' => $representante->persona->cuil,
            ],
            'institucion_firmante' => [
                'id_institucion' => $representante->institucionFirmante->id_institucion,
                'razon_social' => $representante->institucionFirmante->razon_social,
                'cuit' => $representante->institucionFirmante->cuit,
                'domicilio_legal' => $representante->institucionFirmante->domicilio_legal,
                'tipo_institucion' => $representante->institucionFirmante->tipo_institucion,
            ],
        ];
    }

    private function validationError(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $exception->errors(),
        ], 422);
    }
}
