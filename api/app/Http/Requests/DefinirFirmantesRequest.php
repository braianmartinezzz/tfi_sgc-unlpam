<?php

namespace App\Http\Requests;

use App\Services\FirmaService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DefinirFirmantesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids_persona' => ['present', 'array', 'max:'.FirmaService::MAX_FIRMANTES],
            'ids_persona.*' => ['integer', 'distinct', 'exists:persona,id_persona'],
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
