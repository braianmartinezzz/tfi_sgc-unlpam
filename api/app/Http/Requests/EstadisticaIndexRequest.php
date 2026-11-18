<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class EstadisticaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario instanceof Usuario
            && in_array($usuario->rol?->nombre, [
                'Administración',
                'Gestión',
                'Revisión',
                'Consulta',
            ], true);
    }

    public function rules(): array
    {
        $fechaHastaRules = ['nullable', 'date_format:Y-m-d'];
        if ($this->filled('fecha_desde')) {
            $fechaHastaRules[] = 'after_or_equal:fecha_desde';
        }

        return [
            'dias' => ['nullable', 'integer', 'min:1', 'max:365'],
            'id_unidad_academica' => ['nullable', 'integer', 'exists:unidad_academica,id_unidad_academica'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => $fechaHastaRules,
            'tipo' => [
                'nullable',
                'string',
                Rule::in(['Colaboración', 'Pasantía']),
            ],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
