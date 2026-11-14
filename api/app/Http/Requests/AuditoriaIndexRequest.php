<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AuditoriaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_usuario' => ['nullable', 'integer'],
            'id_instrumento' => ['nullable', 'integer'],
            'accion' => ['nullable', 'string', 'max:100'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
