<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UnidadAcademicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // En POST todos los campos son obligatorios; en PUT, solo se validan los que lleguen.
        $presencia = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'sigla' => [
                $presencia,
                'string',
                'max:20',
                Rule::unique('unidad_academica', 'sigla')
                    ->ignore($this->route('id'), 'id_unidad_academica'),
            ],
            'nombre' => [$presencia, 'string', 'max:150'],
            'id_institucion' => [
                $presencia,
                'integer',
                'exists:institucion_firmante,id_institucion',
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