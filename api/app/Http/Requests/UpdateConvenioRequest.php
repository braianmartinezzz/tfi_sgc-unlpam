<?php

namespace App\Http\Requests;

use App\Models\Convenio;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateConvenioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $esMarco = $id !== null
            && Convenio::query()->whereKey($id)->whereHas('marco')->exists();

        return [
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'objeto' => ['sometimes', 'nullable', 'string'],
            'expediente' => ['sometimes', 'nullable', 'string', 'max:100'],
            'resolucion' => ['sometimes', 'nullable', 'string', 'max:100'],
            'fecha_firma' => ['sometimes', 'nullable', 'date'],
            'inicio' => ['sometimes', 'nullable', 'date'],
            'vencimiento' => ['sometimes', 'nullable', 'date'],
            'dias_preaviso' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notas' => ['sometimes', 'nullable', 'string'],
            'tipo' => ['sometimes', 'required', 'string', 'max:50'],
            'alcance' => ['sometimes', 'nullable', 'string'],
            'obligaciones' => ['sometimes', 'nullable', 'string'],
            'condiciones_recision' => ['sometimes', 'nullable', 'string'],
            'marco_legal' => ['sometimes', 'nullable', 'string'],
            'condicion_renovacion' => ['sometimes', 'nullable', 'string'],
            'confidencialidad' => ['sometimes', 'nullable', 'boolean'],
            'id_unidad_academica' => [
                'sometimes',
                'required',
                'integer',
                'exists:unidad_academica,id_unidad_academica',
            ],
            'id_convenio_marco' => $esMarco
                ? ['missing']
                : ['sometimes', 'integer', 'exists:marco,id_instrumento'],
            'instituciones_ids' => ['sometimes', 'array'],
            'instituciones_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:institucion_firmante,id_institucion',
            ],
            'id_instrumento' => ['missing'],
            'estado' => ['missing'],
            'version_registro' => ['missing'],
            'es_marco' => ['missing'],
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
