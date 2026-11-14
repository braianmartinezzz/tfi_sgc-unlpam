<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateInstitucionFirmanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'razon_social' => ['sometimes', 'required', 'string', 'max:255'],
            'cuit' => [
                'sometimes',
                'nullable',
                'string',
                'max:13',
                Rule::unique('institucion_firmante', 'cuit')->ignore($id, 'id_institucion'),
            ],
            'domicilio_legal' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tipo_institucion' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $fields = array_intersect_key(
                $this->all(),
                array_flip(['razon_social', 'cuit', 'domicilio_legal', 'tipo_institucion'])
            );

            if ($fields === []) {
                $validator->errors()->add(
                    'institucion_firmante',
                    'Debe enviar al menos un campo para actualizar.'
                );
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Los datos enviados no son válidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
