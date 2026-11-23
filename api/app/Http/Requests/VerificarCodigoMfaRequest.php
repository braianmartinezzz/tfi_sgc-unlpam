<?php

namespace App\Http\Requests;

class VerificarCodigoMfaRequest extends AppAuthRequest
{
    public function rules(): array
    {
        return [
            'mfa_token' => ['required', 'string'],
            'codigo' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.digits' => 'El código debe tener 6 dígitos.',
        ];
    }
}
