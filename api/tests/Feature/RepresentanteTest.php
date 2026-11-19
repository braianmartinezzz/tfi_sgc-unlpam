<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Persona;
use App\Models\Representante;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepresentanteTest extends TestCase
{
    use RefreshDatabase;

    private int $personNumber = 10000000000;

    public function test_all_representante_endpoints_require_authentication(): void
    {
        $this->getJson('/api/representantes')->assertUnauthorized();
        $this->getJson('/api/representantes/1')->assertUnauthorized();
        $this->postJson('/api/representantes', [])->assertUnauthorized();
        $this->putJson('/api/representantes/1', [])->assertUnauthorized();
        $this->deleteJson('/api/representantes/1')->assertMethodNotAllowed();
    }

    public function test_index_paginates_filters_and_searches_person_data(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution();
        $otherInstitution = $this->createInstitution();

        for ($index = 1; $index <= 16; $index++) {
            $this->createRepresentative([
                'nombre' => 'Persona Paginada',
                'apellido' => 'Ejemplo '.$index,
            ], ['id_institucion' => $institution->id_institucion]);
        }

        $target = $this->createRepresentative([
            'nombre' => 'Ana Exacta',
            'apellido' => 'Apellido Buscable',
            'dni' => '987654321',
            'cuil' => '20987654321',
        ], ['id_institucion' => $otherInstitution->id_institucion]);

        $response = $this->getJson(
            '/api/representantes?'.http_build_query([
                'id_institucion' => $institution->id_institucion,
                'buscar' => 'Persona Paginada',
            ])
        );

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 16);

        parse_str(
            (string) parse_url($response->json('next_page_url'), PHP_URL_QUERY),
            $pageFilters
        );
        $this->assertSame((string) $institution->id_institucion, $pageFilters['id_institucion']);
        $this->assertSame('Persona Paginada', $pageFilters['buscar']);

        $this->getJson('/api/representantes?'.http_build_query($pageFilters))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2);

        $this->getJson('/api/representantes?buscar=Buscable')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $target->id_persona);

        $this->getJson('/api/representantes?buscar=987654321')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $target->id_persona);

        $this->getJson('/api/representantes?buscar=20987654321')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $target->id_persona);

        $this->getJson('/api/representantes?'.http_build_query([
            'id_institucion' => $otherInstitution->id_institucion,
            'buscar' => 'Ana Exacta',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.persona.apellido', 'Apellido Buscable');
    }

    public function test_show_returns_person_and_institution_and_404_for_missing_records(): void
    {
        $this->authenticate();
        $representante = $this->createRepresentative([
            'nombre' => 'María',
            'apellido' => 'Pérez',
            'dni' => '123456789',
            'cuil' => null,
        ], ['cargo' => 'Directora']);

        $this->getJson("/api/representantes/{$representante->id_persona}")
            ->assertOk()
            ->assertJsonPath('data.id_persona', $representante->id_persona)
            ->assertJsonPath('data.persona.nombre', 'María')
            ->assertJsonPath('data.persona.apellido', 'Pérez')
            ->assertJsonPath('data.persona.dni', '123456789')
            ->assertJsonPath('data.persona.cuil', null)
            ->assertJsonPath('data.cargo', 'Directora')
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.institucion_firmante.razon_social', 'Institución de prueba')
            ->assertJsonPath('data.institucion_firmante.activo', true)
            ->assertJsonPath('data.institucion_firmante.es_propia', false);

        $this->getJson('/api/representantes/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Representante legal no encontrado.');
    }

    public function test_store_creates_person_and_reuses_people_with_other_profiles(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution();
        $cuil = $this->nextCuil();

        $this->postJson('/api/representantes', [
            'nombre' => 'Persona Nueva',
            'apellido' => 'Representante',
            'dni' => $this->nextDni(),
            'cuil' => $cuil,
            'cargo' => 'Apoderada',
            'id_institucion' => $institution->id_institucion,
        ])
            ->assertCreated()
            ->assertJsonPath('data.persona.nombre', 'Persona Nueva')
            ->assertJsonPath('data.persona.cuil', $cuil)
            ->assertJsonPath('data.cargo', 'Apoderada');

        $this->assertDatabaseCount('persona', 1);
        $this->assertDatabaseCount('representante', 1);

        $docente = $this->createPersona();
        Docente::query()->create(['id_persona' => $docente->id_persona]);

        $estudiante = $this->createPersona();
        Estudiante::query()->create([
            'id_persona' => $estudiante->id_persona,
            'carrera' => 'Ingeniería',
        ]);

        foreach ([$docente, $estudiante] as $persona) {
            $this->postJson('/api/representantes', [
                'nombre' => 'No reemplazar',
                'apellido' => 'Datos existentes',
                'dni' => $persona->dni,
                'id_institucion' => $institution->id_institucion,
            ])
                ->assertCreated()
                ->assertJsonPath('data.id_persona', $persona->id_persona);
        }

        $this->assertDatabaseCount('persona', 3);
        $this->assertDatabaseCount('representante', 3);
        $this->assertDatabaseHas('persona', [
            'id_persona' => $docente->id_persona,
            'nombre' => $docente->nombre,
        ]);
    }

    public function test_store_rejects_duplicate_profile_and_cuil_mismatch_with_conflict(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution();
        $alreadyRepresentative = $this->createRepresentative();
        $otherPersona = $this->createPersona(['cuil' => '20765432109']);

        $this->postJson('/api/representantes', [
            'nombre' => 'Ignorado',
            'apellido' => 'Ignorado',
            'dni' => $alreadyRepresentative->persona->dni,
            'cuil' => $otherPersona->cuil,
            'id_institucion' => $institution->id_institucion,
        ])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'La persona ya posee un perfil de representante legal registrado'
            );

        $existing = $this->createPersona(['cuil' => '20123456789']);
        $this->postJson('/api/representantes', [
            'nombre' => 'Ignorado',
            'apellido' => 'Ignorado',
            'dni' => $existing->dni,
            'cuil' => '20987654321',
            'id_institucion' => $institution->id_institucion,
        ])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El CUIL enviado no coincide con los datos registrados para este DNI'
            );

        $this->postJson('/api/representantes', [
            'nombre' => 'No reemplazar',
            'apellido' => 'Persona existente',
            'dni' => $existing->dni,
            'cuil' => $existing->cuil,
            'id_institucion' => $institution->id_institucion,
        ])
            ->assertCreated()
            ->assertJsonPath('data.persona.cuil', $existing->cuil);
    }

    public function test_store_fills_missing_cuil_and_validates_cuil_institution_and_fields(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution();
        $persona = $this->createPersona(['cuil' => null]);
        $cuil = $this->nextCuil();

        $this->postJson('/api/representantes', [
            'nombre' => 'No reemplazar',
            'apellido' => 'Persona existente',
            'dni' => $persona->dni,
            'cuil' => $cuil,
            'id_institucion' => $institution->id_institucion,
        ])
            ->assertCreated()
            ->assertJsonPath('data.persona.cuil', $cuil);

        $other = $this->createPersona(['cuil' => '20111222333']);

        $this->postJson('/api/representantes', [
            'nombre' => 'Otra',
            'apellido' => 'Persona',
            'dni' => $this->nextDni(),
            'cuil' => $other->cuil,
            'id_institucion' => $institution->id_institucion,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuil']);

        $this->postJson('/api/representantes', [
            'nombre' => 'Institución',
            'apellido' => 'Inexistente',
            'dni' => $this->nextDni(),
            'id_institucion' => 999999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_institucion']);

        $this->postJson('/api/representantes', [
            'nombre' => str_repeat('n', 101),
            'apellido' => str_repeat('a', 101),
            'dni' => str_repeat('1', 16),
            'cuil' => str_repeat('2', 14),
            'cargo' => str_repeat('c', 101),
            'id_institucion' => $institution->id_institucion,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'apellido', 'dni', 'cuil', 'cargo']);

        $this->postJson('/api/representantes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'apellido', 'dni', 'id_institucion']);
    }

    public function test_update_changes_person_and_representante_data_partially_and_clears_nullable_fields(): void
    {
        $this->authenticate();
        $representante = $this->createRepresentative([
            'nombre' => 'Nombre Inicial',
            'apellido' => 'Apellido Inicial',
            'cuil' => '20123456789',
        ], ['cargo' => 'Cargo Inicial']);
        Docente::query()->create(['id_persona' => $representante->id_persona]);
        Estudiante::query()->create([
            'id_persona' => $representante->id_persona,
            'carrera' => 'Derecho',
        ]);
        $institution = $this->createInstitution();

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'nombre' => 'Nombre Nuevo',
            'apellido' => 'Apellido Nuevo',
            'dni' => '887766554',
            'cuil' => '20999888777',
            'cargo' => 'Cargo Nuevo',
            'id_institucion' => $institution->id_institucion,
        ])
            ->assertOk()
            ->assertJsonPath('data.persona.nombre', 'Nombre Nuevo')
            ->assertJsonPath('data.persona.apellido', 'Apellido Nuevo')
            ->assertJsonPath('data.persona.dni', '887766554')
            ->assertJsonPath('data.persona.cuil', '20999888777')
            ->assertJsonPath('data.cargo', 'Cargo Nuevo')
            ->assertJsonPath('data.id_institucion', $institution->id_institucion);

        $this->assertSame(
            'Nombre Nuevo',
            Persona::query()->findOrFail($representante->id_persona)->nombre
        );
        $this->assertSame(
            'Nombre Nuevo',
            Docente::query()->findOrFail($representante->id_persona)->persona->nombre
        );
        $this->assertSame(
            'Apellido Nuevo',
            Estudiante::query()->findOrFail($representante->id_persona)->persona->apellido
        );

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'cargo' => 'Cargo Parcial',
            'activo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.persona.nombre', 'Nombre Nuevo')
            ->assertJsonPath('data.persona.cuil', '20999888777')
            ->assertJsonPath('data.cargo', 'Cargo Parcial')
            ->assertJsonPath('data.activo', true);

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'cargo' => null,
            'cuil' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.cargo', null)
            ->assertJsonPath('data.persona.cuil', null);

        $this->assertDatabaseHas('persona', [
            'id_persona' => $representante->id_persona,
            'cuil' => null,
        ]);
    }

    public function test_update_rejects_duplicate_unique_fields_invalid_institution_and_empty_payload(): void
    {
        $this->authenticate();
        $representante = $this->createRepresentative();
        $other = $this->createPersona(['cuil' => '20777777777']);

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'dni' => $other->dni,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dni']);

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'cuil' => $other->cuil,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuil']);

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'id_institucion' => 999999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_institucion']);

        $this->putJson("/api/representantes/{$representante->id_persona}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['representante']);

        $this->putJson('/api/representantes/999999', ['cargo' => 'Nuevo'])
            ->assertNotFound();
    }

    public function test_update_allows_institution_change_without_history_and_same_institution_with_history(): void
    {
        $this->authenticate();
        $representante = $this->createRepresentative();
        $newInstitution = $this->createInstitution();

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'id_institucion' => $newInstitution->id_institucion,
        ])
            ->assertOk()
            ->assertJsonPath('data.id_institucion', $newInstitution->id_institucion);

        $this->createSignature($representante);

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'id_institucion' => $newInstitution->id_institucion,
        ])
            ->assertOk()
            ->assertJsonPath('data.id_institucion', $newInstitution->id_institucion);
    }

    public function test_update_rejects_reassignment_when_representante_has_signatures_or_pasantias(): void
    {
        $this->authenticate();
        $signatureRepresentative = $this->createRepresentative();
        $signatureInstitution = $this->createInstitution();
        $this->createSignature($signatureRepresentative);

        $this->putJson("/api/representantes/{$signatureRepresentative->id_persona}", [
            'id_institucion' => $signatureInstitution->id_institucion,
        ])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se puede cambiar la institución porque el representante tiene relaciones históricas asociadas.'
            );

        $internshipRepresentative = $this->createRepresentative();
        $internshipInstitution = $this->createInstitution();
        $this->createPasantia($internshipRepresentative);

        $this->putJson("/api/representantes/{$internshipRepresentative->id_persona}", [
            'id_institucion' => $internshipInstitution->id_institucion,
        ])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se puede cambiar la institución porque el representante tiene relaciones históricas asociadas.'
            );

        $this->assertDatabaseHas('representante', [
            'id_persona' => $signatureRepresentative->id_persona,
            'id_institucion' => $signatureRepresentative->id_institucion,
        ]);
    }

    public function test_representante_state_is_soft_and_requires_active_parent_for_reactivation(): void
    {
        $this->authenticate();
        $representante = $this->createRepresentative();
        $institutionId = $representante->id_institucion;

        $this->patchJson("/api/representantes/{$representante->id_persona}/estado", [
            'activo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.activo', false)
            ->assertJsonPath('resumen.representantes_desactivados', 1);

        $this->assertDatabaseHas('representante', [
            'id_persona' => $representante->id_persona,
            'activo' => false,
        ]);
        $this->assertDatabaseHas('persona', [
            'id_persona' => $representante->id_persona,
        ]);

        $this->patchJson("/api/instituciones-firmantes/{$institutionId}/estado", [
            'activo' => false,
        ])->assertOk();

        $this->patchJson("/api/representantes/{$representante->id_persona}/estado", [
            'activo' => true,
        ])->assertStatus(409);

        $this->patchJson("/api/instituciones-firmantes/{$institutionId}/estado", [
            'activo' => true,
        ])->assertOk();

        $this->assertDatabaseHas('representante', [
            'id_persona' => $representante->id_persona,
            'activo' => false,
        ]);
        $this->patchJson("/api/representantes/{$representante->id_persona}/estado", [
            'activo' => true,
        ])->assertOk()->assertJsonPath('data.activo', true);
    }

    public function test_representative_cannot_be_created_under_an_inactive_institution_and_active_filter_works(): void
    {
        $this->authenticate();
        $representante = $this->createRepresentative();
        $representante->activo = false;
        $representante->save();
        $institution = InstitucionFirmante::query()->findOrFail($representante->id_institucion);
        $institution->activo = false;
        $institution->save();

        $this->postJson('/api/representantes', [
            'nombre' => 'Nuevo',
            'apellido' => 'Representante',
            'dni' => $this->nextDni(),
            'id_institucion' => $institution->id_institucion,
        ])->assertStatus(409);

        $this->getJson('/api/representantes?activo=false')
            ->assertOk()
            ->assertJsonFragment(['id_persona' => $representante->id_persona])
            ->assertJsonPath('data.0.activo', false);
    }

    public function test_management_can_edit_representatives_only_in_its_institution(): void
    {
        $this->authenticate();
        $representante = $this->createRepresentative();
        $otraInstitucion = $this->createInstitution();
        $unitId = $this->createUnit($representante->institucionFirmante);
        $roleId = DB::table('rol')->insertGetId(['nombre' => 'Gestión']);
        $usuario = Usuario::query()->create([
            'mail' => 'gestion-representante@example.com',
            'nombre' => 'Gestión propia',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $roleId,
            'id_unidad' => $unitId,
        ]);
        $this->withToken($usuario->createToken('gestion-representante')->plainTextToken);

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'cargo' => 'Cargo actualizado',
        ])->assertOk();

        $this->patchJson("/api/representantes/{$representante->id_persona}/estado", [
            'activo' => false,
        ])->assertForbidden();

        $this->putJson("/api/representantes/{$representante->id_persona}", [
            'id_institucion' => $otraInstitucion->id_institucion,
        ])->assertForbidden();

        $this->postJson('/api/representantes', [
            'nombre' => 'Representante',
            'apellido' => 'Propio',
            'dni' => $this->nextDni(),
            'id_institucion' => $otraInstitucion->id_institucion,
        ])->assertForbidden();

        foreach (['Revisión', 'Consulta'] as $rolNombre) {
            $rolId = DB::table('rol')->insertGetId(['nombre' => $rolNombre]);
            $lector = Usuario::query()->create([
                'mail' => strtolower($rolNombre).'-representante@example.com',
                'nombre' => $rolNombre,
                'password' => Hash::make('password-segura'),
                'activo' => true,
                'id_rol' => $rolId,
                'id_unidad' => $unitId,
            ]);
            Sanctum::actingAs($lector);

            $this->getJson('/api/representantes')->assertOk();
            $this->putJson("/api/representantes/{$representante->id_persona}", [
                'cargo' => 'No autorizado',
            ])->assertForbidden();
            $this->postJson('/api/representantes', [
                'nombre' => 'No autorizado',
                'apellido' => 'Representante',
                'dni' => $this->nextDni(),
                'id_institucion' => $representante->id_institucion,
            ])->assertForbidden();
        }
    }

    public function test_delete_route_is_removed(): void
    {
        $this->authenticate();
        $representante = $this->createRepresentative();

        $this->deleteJson("/api/representantes/{$representante->id_persona}")
            ->assertMethodNotAllowed();
    }

    private function authenticate(): Usuario
    {
        $institution = $this->createInstitution();
        $unitId = $this->createUnit($institution);
        $roleId = DB::table('rol')->insertGetId([
            'nombre' => 'Administración',
        ]);
        $usuario = Usuario::query()->create([
            'mail' => uniqid('representante-', true).'@example.com',
            'nombre' => 'Usuario de prueba',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $roleId,
            'id_unidad' => $unitId,
        ]);

        $this->withToken($usuario->createToken('test')->plainTextToken);

        return $usuario;
    }

    private function createInstitution(array $attributes = []): InstitucionFirmante
    {
        return InstitucionFirmante::query()->create(array_merge([
            'razon_social' => 'Institución de prueba',
            'cuit' => null,
            'domicilio_legal' => null,
            'tipo_institucion' => null,
        ], $attributes));
    }

    private function createUnit(InstitucionFirmante $institution): int
    {
        return DB::table('unidad_academica')->insertGetId([
            'sigla' => uniqid('UA'),
            'nombre' => 'Unidad de prueba',
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createPersona(array $attributes = []): Persona
    {
        return Persona::query()->create(array_merge([
            'nombre' => 'Persona',
            'apellido' => 'Existente',
            'dni' => $this->nextDni(),
            'cuil' => null,
        ], $attributes));
    }

    private function createRepresentative(
        array $personaAttributes = [],
        array $representanteAttributes = []
    ): Representante {
        $institution = $this->createInstitution();
        $persona = $this->createPersona($personaAttributes);

        return Representante::query()->create(array_merge([
            'id_persona' => $persona->id_persona,
            'cargo' => null,
            'id_institucion' => $institution->id_institucion,
        ], $representanteAttributes));
    }

    private function createSignature(Representante $representante): void
    {
        $institution = InstitucionFirmante::query()->findOrFail(
            $representante->id_institucion
        );
        $unitId = $this->createUnit($institution);
        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio con firma',
            'estado' => 'Borrador',
        ]);
        Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);

        DB::table('firma')->insert([
            'id_instrumento' => $instrumento->id_instrumento,
            'id_persona' => $representante->id_persona,
        ]);
    }

    private function createPasantia(Representante $representante): int
    {
        $institution = $this->createInstitution();
        $unitId = $this->createUnit($institution);
        $instrumentoMarco = Instrumento::query()->create([
            'titulo' => 'Convenio marco',
            'estado' => 'Borrador',
        ]);
        Convenio::query()->create([
            'id_instrumento' => $instrumentoMarco->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);
        DB::table('marco')->insert([
            'id_instrumento' => $instrumentoMarco->id_instrumento,
        ]);

        $instrumentoEspecifico = Instrumento::query()->create([
            'titulo' => 'Convenio específico',
            'estado' => 'Borrador',
        ]);
        Convenio::query()->create([
            'id_instrumento' => $instrumentoEspecifico->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);
        DB::table('especifico')->insert([
            'id_instrumento' => $instrumentoEspecifico->id_instrumento,
            'id_convenio_marco' => $instrumentoMarco->id_instrumento,
        ]);

        $estudiante = $this->createPersona();
        Estudiante::query()->create([
            'id_persona' => $estudiante->id_persona,
            'carrera' => 'Ingeniería',
        ]);
        $docente = $this->createPersona();
        Docente::query()->create(['id_persona' => $docente->id_persona]);

        return DB::table('pasantia')->insertGetId([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'carga_horaria' => 20,
            'asignacion_estimulo' => 1000,
            'porcentaje_gasto_admin' => null,
            'lugar' => null,
            'tareas' => null,
            'seguro' => null,
            'id_convenio_especifico' => $instrumentoEspecifico->id_instrumento,
            'id_estudiante' => $estudiante->id_persona,
            'id_docente_guia' => $docente->id_persona,
            'id_tutor_laboral' => $representante->id_persona,
        ], 'id_pasantia');
    }

    private function nextDni(): string
    {
        return (string) ++$this->personNumber;
    }

    private function nextCuil(): string
    {
        return '20'.str_pad($this->nextDni(), 11, '0', STR_PAD_LEFT);
    }
}
