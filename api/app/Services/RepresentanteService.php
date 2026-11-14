<?php

namespace App\Services;

use App\Models\InstitucionFirmante;
use App\Models\Persona;
use App\Models\Representante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RepresentanteService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Representante::query()->with([
            'persona:id_persona,nombre,apellido,dni,cuil',
            'institucionFirmante:id_institucion,razon_social,cuit,domicilio_legal,tipo_institucion',
        ]);

        if (isset($filters['id_institucion']) && $filters['id_institucion'] !== '') {
            $query->where('id_institucion', $filters['id_institucion']);
        }

        if (isset($filters['buscar']) && trim($filters['buscar']) !== '') {
            $buscar = trim($filters['buscar']);

            $query->whereHas('persona', function ($personaQuery) use ($buscar) {
                $personaQuery->where(function ($searchQuery) use ($buscar) {
                    $searchQuery
                        ->where('nombre', 'like', '%'.$buscar.'%')
                        ->orWhere('apellido', 'like', '%'.$buscar.'%')
                        ->orWhere('dni', $buscar)
                        ->orWhere('cuil', $buscar);
                });
            });
        }

        return $query->paginate(15)->appends($filters);
    }

    public function findById(string $id): Representante
    {
        return Representante::query()
            ->with([
                'persona:id_persona,nombre,apellido,dni,cuil',
                'institucionFirmante:id_institucion,razon_social,cuit,domicilio_legal,tipo_institucion',
            ])
            ->findOrFail($id);
    }

    public function store(array $data): Representante
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return DB::transaction(function () use ($data) {
                    InstitucionFirmante::query()->findOrFail($data['id_institucion']);

                    $persona = Persona::query()
                        ->where('dni', $data['dni'])
                        ->lockForUpdate()
                        ->first();

                    if ($persona === null) {
                        $this->ensureCuilAvailable($data['cuil'] ?? null);
                        $persona = Persona::query()->create([
                            'nombre' => $data['nombre'],
                            'apellido' => $data['apellido'],
                            'dni' => $data['dni'],
                            'cuil' => $data['cuil'] ?? null,
                        ]);
                    } else {
                        if ($persona->representante()->exists()) {
                            throw new \DomainException(
                                'La persona ya posee un perfil de representante legal registrado'
                            );
                        }

                        $cuil = $data['cuil'] ?? null;

                        if ($persona->cuil !== null && $cuil !== null && $persona->cuil !== $cuil) {
                            throw new \DomainException(
                                'El CUIL enviado no coincide con los datos registrados para este DNI'
                            );
                        }

                        if ($persona->cuil === null && $cuil !== null) {
                            $this->ensureCuilAvailable($cuil, $persona->id_persona);
                            $persona->cuil = $cuil;
                            $persona->save();
                        }
                    }

                    return $persona->representante()->create([
                        'cargo' => $data['cargo'] ?? null,
                        'id_institucion' => $data['id_institucion'],
                    ])->load([
                        'persona:id_persona,nombre,apellido,dni,cuil',
                        'institucionFirmante:id_institucion,razon_social,cuit,domicilio_legal,tipo_institucion',
                    ]);
                });
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $persona = Persona::query()
                    ->where('dni', $data['dni'])
                    ->first();

                if ($persona !== null) {
                    if ($persona->representante()->exists()) {
                        throw new \DomainException(
                            'La persona ya posee un perfil de representante legal registrado'
                        );
                    }

                    $cuil = $data['cuil'] ?? null;

                    if ($persona->cuil !== null && $cuil !== null && $persona->cuil !== $cuil) {
                        throw new \DomainException(
                            'El CUIL enviado no coincide con los datos registrados para este DNI'
                        );
                    }

                    if ($persona->cuil === null && $cuil !== null) {
                        $this->ensureCuilAvailable($cuil, $persona->id_persona);
                    }

                    if ($attempt === 0) {
                        continue;
                    }
                } else {
                    $this->ensureCuilAvailable($data['cuil'] ?? null);
                }

                throw $exception;
            }
        }

        throw new RuntimeException('No se pudo registrar el representante.');
    }

    public function update(string $id, array $data): Representante
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $persona = Persona::query()->lockForUpdate()->findOrFail($id);
                $representante = Representante::query()->lockForUpdate()->findOrFail($id);

                $personaFields = array_intersect_key(
                    $data,
                    array_flip(['nombre', 'apellido', 'dni', 'cuil'])
                );

                $representanteFields = array_intersect_key(
                    $data,
                    array_flip(['cargo', 'id_institucion'])
                );

                if (array_key_exists('id_institucion', $representanteFields)) {
                    InstitucionFirmante::query()->findOrFail(
                        $representanteFields['id_institucion']
                    );

                    $institutionChanged = (int) $representanteFields['id_institucion']
                        !== (int) $representante->id_institucion;

                    if ($institutionChanged && (
                        $persona->conveniosFirmados()->lockForUpdate()->exists()
                        || $representante->pasantiasComoTutorLaboral()
                            ->lockForUpdate()
                            ->exists()
                    )) {
                        throw new \DomainException(
                            'No se puede cambiar la institución porque el representante tiene relaciones históricas asociadas.'
                        );
                    }
                }

                if ($personaFields !== []) {
                    $persona->fill($personaFields)->save();
                }

                if ($representanteFields !== []) {
                    $representante->fill($representanteFields)->save();
                }

                return $representante->load([
                    'persona:id_persona,nombre,apellido,dni,cuil',
                    'institucionFirmante:id_institucion,razon_social,cuit,domicilio_legal,tipo_institucion',
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $this->throwUniqueFieldValidation($exception, $data, (int) $id);
            throw $exception;
        }
    }

    public function destroy(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $persona = Persona::query()->lockForUpdate()->findOrFail($id);
                $representante = Representante::query()->lockForUpdate()->findOrFail($id);

                if (
                    $persona->conveniosFirmados()->lockForUpdate()->exists()
                    || $representante->pasantiasComoTutorLaboral()
                        ->lockForUpdate()
                        ->exists()
                ) {
                    throw new \DomainException(
                        'No se puede eliminar el representante porque tiene firmas o pasantías asociadas.'
                    );
                }

                if (! $representante->delete()) {
                    throw new RuntimeException('No se pudo eliminar el representante.');
                }

                if (! $persona->docente()->exists() && ! $persona->estudiante()->exists()) {
                    if (! $persona->delete()) {
                        throw new RuntimeException('No se pudo eliminar la persona.');
                    }
                }
            });
        } catch (QueryException $exception) {
            if ($this->isForeignKeyViolation($exception)) {
                throw new \DomainException(
                    'No se puede eliminar el representante porque tiene relaciones históricas asociadas.'
                );
            }

            throw $exception;
        }
    }

    private function ensureCuilAvailable(?string $cuil, ?int $exceptPersonaId = null): void
    {
        if ($cuil === null) {
            return;
        }

        $query = Persona::query()->where('cuil', $cuil);

        if ($exceptPersonaId !== null) {
            $query->where('id_persona', '!=', $exceptPersonaId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'cuil' => ['El CUIL ya está registrado.'],
            ]);
        }
    }

    private function throwUniqueFieldValidation(
        QueryException $exception,
        array $data,
        int $personaId
    ): void {
        foreach (['dni', 'cuil'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }

            if (Persona::query()
                ->where($field, $data[$field])
                ->where('id_persona', '!=', $personaId)
                ->exists()) {
                throw ValidationException::withMessages([
                    $field => [$field === 'dni'
                        ? 'El DNI ya está registrado.'
                        : 'El CUIL ya está registrado.'],
                ]);
            }
        }

        throw $exception;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return $driverCode === 1062
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry');
    }

    private function isForeignKeyViolation(QueryException $exception): bool
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return $driverCode === 1451
            || str_contains($message, 'foreign key constraint failed');
    }
}
