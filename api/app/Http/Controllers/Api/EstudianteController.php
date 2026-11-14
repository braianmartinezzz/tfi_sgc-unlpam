<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEstudianteRequest;
use App\Http\Requests\UpdateEstudianteRequest;
use App\Models\Estudiante;
use App\Services\PersonaVinculadaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class EstudianteController extends Controller
{
    public function index(Request $request, PersonaVinculadaService $service): JsonResponse
    {
        $estudiantes = $service->paginateEstudiantes($request->only([
            'buscar',
            'carrera',
        ]));

        $estudiantes->through(
            fn (Estudiante $estudiante) => $this->estudianteRow($estudiante)
        );

        return response()->json($estudiantes);
    }

    public function show(string $id, PersonaVinculadaService $service): JsonResponse
    {
        try {
            $estudiante = $service->findEstudiante($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Estudiante no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $this->estudianteData($estudiante),
        ]);
    }

    public function store(
        StoreEstudianteRequest $request,
        PersonaVinculadaService $service
    ): JsonResponse {
        try {
            $estudiante = $service->storeEstudiante($request->validated());
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al registrar el estudiante.',
            ], 500);
        }

        return response()->json([
            'message' => 'Estudiante registrado correctamente.',
            'data' => $this->estudianteData($estudiante),
        ], 201);
    }

    public function update(
        UpdateEstudianteRequest $request,
        string $id,
        PersonaVinculadaService $service
    ): JsonResponse {
        try {
            $estudiante = $service->updateEstudiante($id, $request->validated());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Estudiante no encontrado.',
            ], 404);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al actualizar el estudiante.',
            ], 500);
        }

        return response()->json([
            'message' => 'Estudiante actualizado correctamente.',
            'data' => $this->estudianteData($estudiante),
        ]);
    }

    public function destroy(string $id, PersonaVinculadaService $service): JsonResponse
    {
        try {
            $service->destroyEstudiante($id);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Estudiante no encontrado.',
            ], 404);
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Ocurrió un error inesperado al eliminar el estudiante.',
            ], 500);
        }

        return response()->json([
            'message' => 'Estudiante eliminado correctamente.',
        ]);
    }

    private function estudianteRow(Estudiante $estudiante): array
    {
        return [
            'id_persona' => $estudiante->id_persona,
            'nombre' => $estudiante->persona->nombre,
            'apellido' => $estudiante->persona->apellido,
            'dni' => $estudiante->persona->dni,
            'cuil' => $estudiante->persona->cuil,
            'carrera' => $estudiante->carrera,
            'domicilio' => $estudiante->domicilio,
        ];
    }

    private function estudianteData(Estudiante $estudiante): array
    {
        return [
            'id_persona' => $estudiante->id_persona,
            'carrera' => $estudiante->carrera,
            'domicilio' => $estudiante->domicilio,
            'persona' => [
                'id_persona' => $estudiante->persona->id_persona,
                'nombre' => $estudiante->persona->nombre,
                'apellido' => $estudiante->persona->apellido,
                'dni' => $estudiante->persona->dni,
                'cuil' => $estudiante->persona->cuil,
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
