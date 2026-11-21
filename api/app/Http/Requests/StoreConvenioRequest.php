<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreConvenioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
                Rule::requiredIf(fn () => ! $this->boolean('es_marco')),
                Rule::prohibitedIf(fn () => $this->boolean('es_marco')),
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
