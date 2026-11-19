<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoCatalogoRequest;
use App\Http\Requests\StoreRepresentanteRequest;
use App\Http\Requests\UpdateRepresentanteRequest;
use App\Models\Representante;
use App\Services\RepresentanteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class RepresentanteController extends Controller
{
    public function index(Request $request, RepresentanteService $service): JsonResponse
    {
        $representantes = $service->paginate($request->only([
            'id_institucion',
            'buscar',
            'activo',
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
        $datos = $request->validated();
        if (! Gate::allows('editar-institucion', $datos['id_institucion'])) {
            return response()->json([
                'message' => 'No tenés permiso para crear representantes en esta institución.',
            ], 403);
        }

        try {
            $representante = $service->store(
                $datos,
                (int) $request->user()->id_usuario
            );
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
        $representanteActual = Representante::query()->find($id);
        if ($representanteActual === null) {
            return response()->json([
                'message' => 'Representante legal no encontrado.',
            ], 404);
        }

        $datos = $request->validated();
        $institucionActual = (int) $representanteActual->id_institucion;
        $institucionNueva = (int) ($datos['id_institucion'] ?? $institucionActual);

        if ($request->user()->rol?->nombre === 'Gestión'
            && $institucionNueva !== $institucionActual) {
            return response()->json([
                'message' => 'No podés mover un representante a otra institución.',
            ], 403);
        }

        if (! Gate::allows('editar-institucion', $institucionActual)
            || ($institucionNueva !== $institucionActual
                && ! Gate::allows('editar-institucion', $institucionNueva))) {
            return response()->json([
                'message' => 'No tenés permiso para editar este representante.',
            ], 403);
        }

        try {
            $representante = $service->update(
                $id,
                $datos,
                (int) $request->user()->id_usuario
            );
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

    public function cambiarEstado(
        CambiarEstadoCatalogoRequest $request,
        string $id,
        RepresentanteService $service
    ): JsonResponse {
        try {
            $resultado = $service->cambiarEstado(
                $id,
                $request->boolean('activo'),
                (int) $request->user()->id_usuario
            );
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
                'message' => 'Ocurrió un error inesperado al cambiar el estado del representante legal.',
            ], 500);
        }

        return response()->json([
            'message' => $request->boolean('activo')
                ? 'Representante legal activado correctamente.'
                : 'Representante legal desactivado correctamente.',
            'data' => $this->representanteData($resultado['data']),
            'resumen' => $resultado['resumen'],
        ]);
    }

    private function representanteData(Representante $representante): array
    {
        return [
            'id_persona' => $representante->id_persona,
            'cargo' => $representante->cargo,
            'id_institucion' => $representante->id_institucion,
            'activo' => $representante->activo,
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
                'activo' => $representante->institucionFirmante->activo,
                'es_propia' => $representante->institucionFirmante->es_propia,
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
