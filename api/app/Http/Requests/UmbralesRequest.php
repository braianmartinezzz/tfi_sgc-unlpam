<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UmbralesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Se puede mandar uno solo o ambos, pero al menos uno.
        return [
            'dias_preaviso_defecto' => [
                'required_without:dias_ventana_vencimientos',
                'integer',
                'min:1',
                'max:365',
            ],
            'dias_ventana_vencimientos' => [
                'required_without:dias_preaviso_defecto',
                'integer',
                'min:1',
                'max:365',
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
