<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['sometimes', 'required', 'string', 'max:50'],
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'archivo' => ['sometimes', 'required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $validator->errors(),
        ], 422));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $fields = array_intersect_key(
                $this->all(),
                array_flip(['tipo', 'titulo', 'archivo'])
            );

            if ($fields === []) {
                $validator->errors()->add(
                    'documento',
                    'Debe enviar al menos un campo para actualizar.'
                );
            }
        });
    }
}
