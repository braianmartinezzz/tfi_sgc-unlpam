<?php

namespace App\Services;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Persona;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PersonaVinculadaService
{
    public function paginateEstudiantes(array $filters): LengthAwarePaginator
    {
        $query = Estudiante::query()->with(
            'persona:id_persona,nombre,apellido,dni,cuil'
        );

        if (isset($filters['carrera']) && $filters['carrera'] !== '') {
            $query->where('carrera', $filters['carrera']);
        }

        $this->applySearch($query, $filters);

        return $query->paginate(15)->appends($filters);
    }

    public function paginateDocentes(array $filters): LengthAwarePaginator
    {
        $query = Docente::query()->with(
            'persona:id_persona,nombre,apellido,dni,cuil'
        );

        $this->applySearch($query, $filters);

        return $query->paginate(15)->appends($filters);
    }

    public function findEstudiante(string $id): Estudiante
    {
        return Estudiante::query()
            ->with('persona:id_persona,nombre,apellido,dni,cuil')
            ->findOrFail($id);
    }

    public function findDocente(string $id): Docente
    {
        return Docente::query()
            ->with('persona:id_persona,nombre,apellido,dni,cuil')
            ->findOrFail($id);
    }

    public function storeEstudiante(array $data): Estudiante
    {
        return $this->storeProfile($data, 'estudiante');
    }

    public function storeDocente(array $data): Docente
    {
        return $this->storeProfile($data, 'docente');
    }

    public function updateEstudiante(string $id, array $data): Estudiante
    {
        return $this->updateProfile($id, $data, 'estudiante');
    }

    public function updateDocente(string $id, array $data): Docente
    {
        return $this->updateProfile($id, $data, 'docente');
    }

    public function destroyEstudiante(string $id): void
    {
        $this->destroyProfile($id, 'estudiante');
    }

    public function destroyDocente(string $id): void
    {
        $this->destroyProfile($id, 'docente');
    }

    private function applySearch(Builder $query, array $filters): void
    {
        if (! isset($filters['buscar']) || trim($filters['buscar']) === '') {
            return;
        }

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

    private function storeProfile(array $data, string $profile): Estudiante|Docente
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $profile) {
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
                        if ($this->hasProfile($persona, $profile)) {
                            throw new \DomainException(
                                'La persona ya posee un perfil de '.($profile === 'estudiante'
                                    ? 'estudiante'
                                    : 'docente').' registrado.'
                            );
                        }

                        $this->assertCuilMatches($persona, $data);

                        $cuil = $data['cuil'] ?? null;

                        if ($persona->cuil === null && $cuil !== null) {
                            $this->ensureCuilAvailable($cuil, $persona->id_persona);
                            $persona->cuil = $cuil;
                            $persona->save();
                        }
                    }

                    if ($profile === 'estudiante') {
                        return $persona->estudiante()->create([
                            'carrera' => $data['carrera'],
                            'domicilio' => $data['domicilio'] ?? null,
                        ])->load('persona:id_persona,nombre,apellido,dni,cuil');
                    }

                    return $persona->docente()->create([])
                        ->load('persona:id_persona,nombre,apellido,dni,cuil');
                });
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                $persona = Persona::query()
                    ->where('dni', $data['dni'])
                    ->first();

                if ($persona !== null) {
                    if ($this->hasProfile($persona, $profile)) {
                        throw new \DomainException(
                            'La persona ya posee un perfil de '.($profile === 'estudiante'
                                ? 'estudiante'
                                : 'docente').' registrado.'
                        );
                    }

                    $this->assertCuilMatches($persona, $data);

                    if ($persona->cuil === null && isset($data['cuil'])) {
                        $this->ensureCuilAvailable($data['cuil'], $persona->id_persona);
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

        throw new RuntimeException('No se pudo registrar el perfil de la persona.');
    }

    private function updateProfile(
        string $id,
        array $data,
        string $profile
    ): Estudiante|Docente {
        try {
            return DB::transaction(function () use ($id, $data, $profile) {
                $persona = Persona::query()->lockForUpdate()->findOrFail($id);

                if ($profile === 'estudiante') {
                    $model = Estudiante::query()->lockForUpdate()->findOrFail($id);
                    $profileFields = array_intersect_key(
                        $data,
                        array_flip(['carrera', 'domicilio'])
                    );
                } else {
                    $model = Docente::query()->lockForUpdate()->findOrFail($id);
                    $profileFields = [];
                }

                $personaFields = array_intersect_key(
                    $data,
                    array_flip(['nombre', 'apellido', 'dni', 'cuil'])
                );

                if ($personaFields !== []) {
                    $persona->fill($personaFields)->save();
                }

                if ($profileFields !== []) {
                    $model->fill($profileFields)->save();
                }

                return $model->load('persona:id_persona,nombre,apellido,dni,cuil');
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $this->throwUniqueFieldValidation($exception, $data, (int) $id);
            throw $exception;
        }
    }

    private function destroyProfile(string $id, string $profile): void
    {
        try {
            DB::transaction(function () use ($id, $profile) {
                $persona = Persona::query()->lockForUpdate()->findOrFail($id);

                if ($profile === 'estudiante') {
                    $model = Estudiante::query()->lockForUpdate()->findOrFail($id);
                    $hasPasantias = $model->pasantias()->lockForUpdate()->exists();
                } else {
                    $model = Docente::query()->lockForUpdate()->findOrFail($id);
                    $hasPasantias = $model->pasantiasComoGuia()
                        ->lockForUpdate()
                        ->exists();
                }

                if ($hasPasantias) {
                    throw new \DomainException(
                        'No se puede eliminar el perfil porque tiene pasantías asociadas.'
                    );
                }

                if (! $model->delete()) {
                    throw new RuntimeException('No se pudo eliminar el perfil.');
                }

                $hasOtherProfiles = $profile === 'estudiante'
                    ? $persona->docente()->exists() || $persona->representante()->exists()
                    : $persona->estudiante()->exists() || $persona->representante()->exists();

                if (
                    ! $hasOtherProfiles
                    && ! $persona->conveniosFirmados()->lockForUpdate()->exists()
                    && ! $persona->delete()
                ) {
                    throw new RuntimeException('No se pudo eliminar la persona.');
                }
            });
        } catch (QueryException $exception) {
            if ($this->isForeignKeyViolation($exception)) {
                throw new \DomainException(
                    'No se puede eliminar el perfil porque tiene relaciones asociadas.'
                );
            }

            throw $exception;
        }
    }

    private function hasProfile(Persona $persona, string $profile): bool
    {
        return $profile === 'estudiante'
            ? $persona->estudiante()->exists()
            : $persona->docente()->exists();
    }

    private function assertCuilMatches(Persona $persona, array $data): void
    {
        $cuil = $data['cuil'] ?? null;

        if ($persona->cuil !== null && $cuil !== null && $persona->cuil !== $cuil) {
            throw new \DomainException(
                'El CUIL enviado no coincide con los datos registrados para este DNI'
            );
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
