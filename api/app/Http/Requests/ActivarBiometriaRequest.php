<?php

namespace App\Http\Requests;

class ActivarBiometriaRequest extends AppAuthRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }
}
