<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UnificarInstitucionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_principal' => [
                'required',
                'integer',
                'exists:institucion_firmante,id_institucion',
            ],
            'ids_duplicadas' => ['required', 'array', 'min:1', 'max:20'],
            'ids_duplicadas.*' => [
                'integer',
                'distinct',
                'exists:institucion_firmante,id_institucion',
                Rule::notIn([$this->input('id_principal')]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ids_duplicadas.*.not_in' => 'La institución principal no puede figurar entre las duplicadas.',
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
