<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegistrarFirmaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_firma' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'archivo_pdf' => [
                'sometimes',
                'nullable',
                'file',
                'mimes:pdf',
                'max:20480',
            ],
            'latitud' => ['prohibited'],
            'longitud' => ['prohibited'],
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
