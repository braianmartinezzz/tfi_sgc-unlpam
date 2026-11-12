<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\Instrumento;
use App\Models\Marco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ConvenioController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'titulo' => ['required', 'string', 'max:255'],
            'objeto' => ['nullable', 'string'],
            'id_unidad_academica' => [
                'required',
                'integer',
                'exists:unidad_academica,id_unidad_academica',
            ],
            'tipo' => ['required', 'string', 'max:50'],
            'es_marco' => ['required', 'boolean'],
            'id_convenio_marco' => [
                Rule::requiredIf(fn () => ! $request->boolean('es_marco')),
                'integer',
                'exists:marco,id_instrumento',
            ],
            'instituciones_ids' => ['required', 'array', 'min:1'],
            'instituciones_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:institucion_firmante,id_institucion',
            ],
            'dias_preaviso' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $instrumento = DB::transaction(function () use ($validated) {
                $instrumento = Instrumento::create([
                    'titulo' => $validated['titulo'],
                    'objeto' => $validated['objeto'] ?? null,
                    'estado' => 'Borrador',
                    'version_registro' => 1,
                    'dias_preaviso' => $validated['dias_preaviso'] ?? 30,
                ]);

                $convenio = Convenio::create([
                    'id_instrumento' => $instrumento->id_instrumento,
                    'tipo' => $validated['tipo'],
                    'id_unidad_academica' => $validated['id_unidad_academica'],
                ]);

                if ($validated['es_marco']) {
                    Marco::create([
                        'id_instrumento' => $instrumento->id_instrumento,
                    ]);
                } else {
                    Especifico::create([
                        'id_instrumento' => $instrumento->id_instrumento,
                        'id_convenio_marco' => $validated['id_convenio_marco'],
                    ]);
                }

                $convenio->institucionesFirmantes()->attach($validated['instituciones_ids']);

                return $instrumento->load([
                    'convenio',
                    'convenio.marco',
                    'convenio.especifico',
                    'convenio.institucionesFirmantes',
                ]);
            });
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
