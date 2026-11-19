<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FirmarConvenioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Imagen PNG en base64 con el formato "data:image/png;base64,...".
            'trazo' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:1500000'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            // La huella o el rostro los valida el teléfono; la app informa el resultado.
            'biometria_confirmada' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'biometria_confirmada.accepted' => 'Hay que confirmar la identidad con huella o reconocimiento facial.',
            'trazo.starts_with' => 'El trazo de la firma debe ser una imagen PNG válida.',
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
