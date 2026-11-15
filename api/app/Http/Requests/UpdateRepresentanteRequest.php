<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateRepresentanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'apellido' => ['sometimes', 'required', 'string', 'max:100'],
            'dni' => [
                'sometimes',
                'required',
                'string',
                'max:15',
                Rule::unique('persona', 'dni')->ignore($id, 'id_persona'),
            ],
            'cuil' => [
                'sometimes',
                'nullable',
                'string',
                'max:13',
                Rule::unique('persona', 'cuil')->ignore($id, 'id_persona'),
            ],
            'cargo' => ['sometimes', 'nullable', 'string', 'max:100'],
            'id_institucion' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('institucion_firmante', 'id_institucion'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $fields = array_intersect_key(
                $this->all(),
                array_flip([
                    'nombre',
                    'apellido',
                    'dni',
                    'cuil',
                    'cargo',
                    'id_institucion',
                ])
            );

            if ($fields === []) {
                $validator->errors()->add(
                    'representante',
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
