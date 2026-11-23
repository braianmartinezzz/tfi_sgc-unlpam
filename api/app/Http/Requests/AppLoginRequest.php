<?php

namespace App\Http\Requests;

class AppLoginRequest extends AppAuthRequest
{
    public function rules(): array
    {
        return [
            'mail' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
