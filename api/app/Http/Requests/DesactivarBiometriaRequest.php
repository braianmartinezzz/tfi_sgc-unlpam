<?php

namespace App\Http\Requests;

class DesactivarBiometriaRequest extends AppAuthRequest
{
    public function rules(): array
    {
        return [
            'token_dispositivo' => ['nullable', 'string'],
        ];
    }
}
