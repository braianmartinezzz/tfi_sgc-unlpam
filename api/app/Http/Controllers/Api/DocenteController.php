<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocenteRequest;
use App\Http\Requests\UpdateDocenteRequest;
use App\Models\Docente;
use App\Services\PersonaVinculadaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocenteController extends Controller
{
    public function index(Request $request, PersonaVinculadaService $service): JsonResponse
    {
        $docentes = $service->paginateDocentes($request->only([
            'buscar',
        ]));

        $docentes->through(
            fn (Docente $docente) => $this->docenteRow($docente)
        );

        return response()->json($docentes);
    }

    public function show(string $id, PersonaVinculadaService $service): JsonResponse
    {
        try {
            $docente = $service->findDocente($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Docente no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $this->docenteData($docente),
        ]);
    }

    public function store(
        StoreDocenteRequest $request,
        PersonaVinculadaService $service
    ): JsonResponse {
        try {
            $docente = $service->storeDocente($request->validated());
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al registrar el docente.',
            ], 500);
        }

        return response()->json([
            'message' => 'Docente registrado correctamente.',
            'data' => $this->docenteData($docente),
        ], 201);
    }

    public function update(
        UpdateDocenteRequest $request,
        string $id,
        PersonaVinculadaService $service
    ): JsonResponse {
        try {
            $docente = $service->updateDocente($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Docente no encontrado.',
            ], 404);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al actualizar el docente.',
            ], 500);
        }

        return response()->json([
            'message' => 'Docente actualizado correctamente.',
            'data' => $this->docenteData($docente),
        ]);
    }

    public function destroy(string $id, PersonaVinculadaService $service): JsonResponse
    {
        try {
            $service->destroyDocente($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Docente no encontrado.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al eliminar el docente.',
            ], 500);
        }

        return response()->json([
            'message' => 'Docente eliminado correctamente.',
        ]);
    }

    private function docenteRow(Docente $docente): array
    {
        return [
            'id_persona' => $docente->id_persona,
            'nombre' => $docente->persona->nombre,
            'apellido' => $docente->persona->apellido,
            'dni' => $docente->persona->dni,
            'cuil' => $docente->persona->cuil,
        ];
    }

    private function docenteData(Docente $docente): array
    {
        return [
            'id_persona' => $docente->id_persona,
            'persona' => [
                'id_persona' => $docente->persona->id_persona,
                'nombre' => $docente->persona->nombre,
                'apellido' => $docente->persona->apellido,
                'dni' => $docente->persona->dni,
                'cuil' => $docente->persona->cuil,
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
