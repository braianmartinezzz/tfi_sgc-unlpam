<?php

namespace App\Http\Requests;

use App\Models\Persona;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreRepresentanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cuilRules = ['nullable', 'string', 'max:13'];
        $dni = $this->input('dni');

        if (is_string($dni) && $dni !== '' && is_string($this->input('cuil'))) {
            $persona = Persona::query()
                ->where('dni', $dni)
                ->first(['id_persona', 'cuil']);

            $alreadyRepresentative = $persona?->representante()->exists() ?? false;
            $cuilIsConflict = $persona !== null
                && $persona->cuil !== null
                && $persona->cuil !== $this->input('cuil');

            if (! $alreadyRepresentative && ! $cuilIsConflict) {
                $uniqueCuil = Rule::unique('persona', 'cuil');

                if ($persona !== null) {
                    $uniqueCuil->ignore($persona->id_persona, 'id_persona');
                }

                $cuilRules[] = $uniqueCuil;
            }
        } elseif (is_string($this->input('cuil'))) {
            $cuilRules[] = Rule::unique('persona', 'cuil');
        }

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'dni' => ['required', 'string', 'max:15'],
            'cuil' => $cuilRules,
            'cargo' => ['nullable', 'string', 'max:100'],
            'id_institucion' => [
                'required',
                'integer',
                Rule::exists('institucion_firmante', 'id_institucion'),
            ],
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
