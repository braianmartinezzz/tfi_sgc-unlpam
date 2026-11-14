<?php

namespace App\Http\Requests;

use App\Models\Persona;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreEstudianteRequest extends FormRequest
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

            $hasStudentProfile = $persona?->estudiante()->exists() ?? false;
            $cuilIsConflict = $persona !== null
                && $persona->cuil !== null
                && $persona->cuil !== $this->input('cuil');

            if (! $hasStudentProfile && ! $cuilIsConflict) {
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
            'carrera' => ['required', 'string', 'max:150'],
            'domicilio' => ['nullable', 'string', 'max:255'],
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
