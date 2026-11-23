<?php

namespace App\Http\Requests;

use App\Services\MfaService;
use Illuminate\Validation\Rule;

class EnviarCodigoMfaRequest extends AppAuthRequest
{
    public function rules(): array
    {
        return [
            'mfa_token' => ['required', 'string'],
            'canal' => ['required', Rule::in(MfaService::CANALES)],
            'push_token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
