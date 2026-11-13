<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // En POST los datos principales son obligatorios; en PUT solo se validan los que lleguen.
        $presencia = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'mail' => [
                $presencia,
                'string',
                'email',
                'max:150',
                Rule::unique('usuario', 'mail')
                    ->ignore($this->route('id'), 'id_usuario'),
            ],
            'nombre' => [$presencia, 'string', 'max:100'],
            'password' => [$presencia, 'string', 'min:8', 'max:255'],
            'activo' => ['sometimes', 'boolean'],
            'id_rol' => [
                $presencia,
                'integer',
                'exists:rol,id_rol',
            ],
            'id_unidad' => [
                $presencia,
                'integer',
                'exists:unidad_academica,id_unidad_academica',
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
