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
use Tests\TestCase;

class PersonasVinculadasTest extends TestCase
{
    use RefreshDatabase;

    private int $personNumber = 10000000000;

    public function test_all_student_and_teacher_endpoints_require_authentication(): void
    {
        foreach (['estudiantes', 'docentes'] as $resource) {
            $this->getJson("/api/{$resource}")->assertUnauthorized();
            $this->getJson("/api/{$resource}/1")->assertUnauthorized();
            $this->postJson("/api/{$resource}", [])->assertUnauthorized();
            $this->putJson("/api/{$resource}/1", [])->assertUnauthorized();
            $this->deleteJson("/api/{$resource}/1")->assertUnauthorized();
        }
    }

    public function test_student_list_filters_searches_and_preserves_pagination_filters(): void
    {
        $this->authenticate();

        for ($index = 1; $index <= 16; $index++) {
            $this->createStudent([
                'nombre' => 'Estudiante Paginado',
                'apellido' => 'Apellido '.$index,
            ], [
                'carrera' => 'Ingeniería',
            ]);
        }

        $target = $this->createStudent([
            'nombre' => 'Ana Exacta',
            'apellido' => 'Apellido Buscable',
            'dni' => '987654321',
            'cuil' => '20987654321',
        ], [
            'carrera' => 'Derecho',
        ]);

        $response = $this->getJson('/api/estudiantes?'.http_build_query([
            'buscar' => 'Estudiante Paginado',
            'carrera' => 'Ingeniería',
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 16);

        parse_str(
            (string) parse_url($response->json('next_page_url'), PHP_URL_QUERY),
            $pageFilters
        );
        $this->assertSame('Estudiante Paginado', $pageFilters['buscar']);
        $this->assertSame('Ingeniería', $pageFilters['carrera']);

        $this->getJson('/api/estudiantes?'.http_build_query($pageFilters))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2);

        $this->getJson('/api/estudiantes?buscar=Buscable')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $target->id_persona);

        $this->getJson('/api/estudiantes?buscar=987654321')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $target->id_persona);

        $this->getJson('/api/estudiantes?buscar=20987654321')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $target->id_persona);

        $this->getJson('/api/estudiantes?carrera=Derecho&buscar=Ana Exacta')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.carrera', 'Derecho');
    }

    public function test_teacher_list_searches_and_preserves_pagination_filters(): void
    {
        $this->authenticate();

        for ($index = 1; $index <= 16; $index++) {
            $this->createTeacher([
                'nombre' => 'Docente Paginado',
                'apellido' => 'Apellido '.$index,
            ]);
        }

        $target = $this->createTeacher([
            'nombre' => 'Docente Encontrado',
            'apellido' => 'Apellido Exacto',
            'dni' => '112233445',
            'cuil' => '20112233445',
        ]);

        $response = $this->getJson('/api/docentes?buscar=Docente%20Paginado');
        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 16);

        parse_str(
            (string) parse_url($response->json('next_page_url'), PHP_URL_QUERY),
            $pageFilters
        );
        $this->assertSame('Docente Paginado', $pageFilters['buscar']);

        $this->getJson('/api/docentes?'.http_build_query($pageFilters))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2);

        $this->getJson('/api/docentes?buscar=Exacto')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $target->id_persona);

        $this->getJson('/api/docentes?buscar=112233445')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_persona', $target->id_persona);

        $this->getJson('/api/docentes?buscar=20112233445')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cuil', '20112233445');
    }

    public function test_details_return_person_data_and_not_found_for_missing_profiles(): void
    {
        $this->authenticate();
        $estudiante = $this->createStudent([
            'nombre' => 'Estudiante',
            'apellido' => 'Detalle',
            'dni' => '123456789',
            'cuil' => null,
        ], [
            'carrera' => 'Medicina',
            'domicilio' => 'Calle 123',
        ]);
        $docente = $this->createTeacher([
            'nombre' => 'Docente',
            'apellido' => 'Detalle',
            'dni' => '987654321',
        ]);

        $this->getJson("/api/estudiantes/{$estudiante->id_persona}")
            ->assertOk()
            ->assertJsonPath('data.carrera', 'Medicina')
            ->assertJsonPath('data.domicilio', 'Calle 123')
            ->assertJsonPath('data.persona.nombre', 'Estudiante')
            ->assertJsonPath('data.persona.dni', '123456789')
            ->assertJsonPath('data.persona.cuil', null);

        $this->getJson("/api/docentes/{$docente->id_persona}")
            ->assertOk()
            ->assertJsonPath('data.id_persona', $docente->id_persona)
            ->assertJsonPath('data.persona.nombre', 'Docente')
            ->assertJsonPath('data.persona.apellido', 'Detalle')
            ->assertJsonPath('data.persona.dni', '987654321');

        $this->getJson('/api/estudiantes/999999')->assertNotFound();
        $this->getJson('/api/docentes/999999')->assertNotFound();
    }

    public function test_store_creates_and_reuses_people_for_both_profiles(): void
    {
        $this->authenticate();

        $this->postJson('/api/estudiantes', [
            'nombre' => 'Estudiante Nuevo',
            'apellido' => 'Apellido',
            'dni' => $this->nextDni(),
            'cuil' => $this->nextCuil(),
            'carrera' => 'Ingeniería',
            'domicilio' => 'Domicilio',
        ])
            ->assertCreated()
            ->assertJsonPath('data.persona.nombre', 'Estudiante Nuevo')
            ->assertJsonPath('data.carrera', 'Ingeniería');

        $teacherPersona = $this->createPersona();
        Docente::query()->create(['id_persona' => $teacherPersona->id_persona]);
        $this->createRepresentative($teacherPersona);

        $this->postJson('/api/estudiantes', [
            'nombre' => 'No actualizar',
            'apellido' => 'Datos existentes',
            'dni' => $teacherPersona->dni,
            'carrera' => 'Derecho',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id_persona', $teacherPersona->id_persona)
            ->assertJsonPath('data.persona.nombre', $teacherPersona->nombre);

        $studentPersona = $this->createPersona();
        Estudiante::query()->create([
            'id_persona' => $studentPersona->id_persona,
            'carrera' => 'Arquitectura',
        ]);
        $this->createRepresentative($studentPersona);

        $this->postJson('/api/docentes', [
            'nombre' => 'No actualizar',
            'apellido' => 'Datos existentes',
            'dni' => $studentPersona->dni,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id_persona', $studentPersona->id_persona)
            ->assertJsonPath('data.persona.nombre', $studentPersona->nombre);

        $this->assertDatabaseCount('persona', 3);
        $this->assertDatabaseCount('estudiante', 3);
        $this->assertDatabaseCount('docente', 2);
        $this->assertDatabaseCount('representante', 2);
    }

    public function test_store_rejects_duplicate_profiles_and_resolves_cuil_conflicts(): void
    {
        $this->authenticate();
        $student = $this->createStudent(['cuil' => '20123456789']);
        $other = $this->createPersona(['cuil' => '20987654321']);

        $this->postJson('/api/estudiantes', [
            'nombre' => 'Ignorado',
            'apellido' => 'Ignorado',
            'dni' => $student->persona->dni,
            'cuil' => $other->cuil,
            'carrera' => 'Ingeniería',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La persona ya posee un perfil de estudiante registrado.');

        $teacher = $this->createTeacher(['cuil' => null]);
        $cuil = $this->nextCuil();
        $this->postJson('/api/docentes', [
            'nombre' => 'Ignorado',
            'apellido' => 'Ignorado',
            'dni' => $teacher->persona->dni,
            'cuil' => $cuil,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'La persona ya posee un perfil de docente registrado.');

        $persona = $this->createPersona(['cuil' => null]);
        Docente::query()->create(['id_persona' => $persona->id_persona]);

        $this->postJson('/api/estudiantes', [
            'nombre' => 'No reemplazar',
            'apellido' => 'Persona existente',
            'dni' => $persona->dni,
            'cuil' => $cuil,
            'carrera' => 'Biología',
        ])
            ->assertCreated()
            ->assertJsonPath('data.persona.cuil', $cuil);

        $mismatch = $this->createPersona(['cuil' => '20111222333']);
        $this->postJson('/api/docentes', [
            'nombre' => 'Ignorado',
            'apellido' => 'Ignorado',
            'dni' => $mismatch->dni,
            'cuil' => '20333444555',
        ])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El CUIL enviado no coincide con los datos registrados para este DNI'
            );

        $this->postJson('/api/docentes', [
            'nombre' => 'Otra',
            'apellido' => 'Persona',
            'dni' => $this->nextDni(),
            'cuil' => $other->cuil,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuil']);

        $sameCuilPersona = $this->createPersona(['cuil' => '20444555666']);
        Estudiante::query()->create([
            'id_persona' => $sameCuilPersona->id_persona,
            'carrera' => 'Historia',
        ]);
        $this->postJson('/api/docentes', [
            'nombre' => 'Ignorado',
            'apellido' => 'Ignorado',
            'dni' => $sameCuilPersona->dni,
            'cuil' => $sameCuilPersona->cuil,
        ])
            ->assertCreated()
            ->assertJsonPath('data.persona.cuil', $sameCuilPersona->cuil);
    }

    public function test_store_validates_required_and_length_limited_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/estudiantes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'apellido', 'dni', 'carrera']);

        $this->postJson('/api/estudiantes', [
            'nombre' => str_repeat('n', 101),
            'apellido' => str_repeat('a', 101),
            'dni' => str_repeat('1', 16),
            'cuil' => str_repeat('2', 14),
            'carrera' => str_repeat('c', 151),
            'domicilio' => str_repeat('d', 256),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'apellido', 'dni', 'cuil', 'carrera', 'domicilio']);

        $this->postJson('/api/docentes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'apellido', 'dni']);

        $this->postJson('/api/docentes', [
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'dni' => str_repeat('1', 16),
            'cuil' => str_repeat('2', 14),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dni', 'cuil']);
    }

    public function test_student_update_is_partial_and_updates_shared_person_data(): void
    {
        $this->authenticate();
        $student = $this->createStudent([
            'nombre' => 'Nombre Inicial',
            'apellido' => 'Apellido Inicial',
            'cuil' => '20123456789',
        ], [
            'carrera' => 'Ingeniería',
            'domicilio' => 'Domicilio inicial',
        ]);
        Docente::query()->create(['id_persona' => $student->id_persona]);
        $this->createRepresentative($student->persona);

        $this->putJson("/api/estudiantes/{$student->id_persona}", [
            'nombre' => 'Nombre Nuevo',
            'apellido' => 'Apellido Nuevo',
            'dni' => '887766554',
            'cuil' => '20999888777',
            'carrera' => 'Medicina',
        ])
            ->assertOk()
            ->assertJsonPath('data.persona.nombre', 'Nombre Nuevo')
            ->assertJsonPath('data.persona.apellido', 'Apellido Nuevo')
            ->assertJsonPath('data.persona.dni', '887766554')
            ->assertJsonPath('data.carrera', 'Medicina')
            ->assertJsonPath('data.domicilio', 'Domicilio inicial');

        $this->assertSame(
            'Nombre Nuevo',
            Docente::query()->findOrFail($student->id_persona)->persona->nombre
        );
        $this->assertSame(
            'Apellido Nuevo',
            $student->persona()->firstOrFail()->representante->persona->apellido
        );

        $this->putJson("/api/estudiantes/{$student->id_persona}", [
            'carrera' => 'Derecho',
        ])
            ->assertOk()
            ->assertJsonPath('data.persona.nombre', 'Nombre Nuevo')
            ->assertJsonPath('data.carrera', 'Derecho')
            ->assertJsonPath('data.domicilio', 'Domicilio inicial');

        $this->putJson("/api/estudiantes/{$student->id_persona}", [
            'cuil' => null,
            'domicilio' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.persona.cuil', null)
            ->assertJsonPath('data.domicilio', null);
    }

    public function test_teacher_update_is_partial_and_enforces_person_unique_fields(): void
    {
        $this->authenticate();
        $teacher = $this->createTeacher([
            'nombre' => 'Docente Inicial',
            'apellido' => 'Apellido Inicial',
            'cuil' => null,
        ]);
        $student = Estudiante::query()->create([
            'id_persona' => $teacher->id_persona,
            'carrera' => 'Biología',
        ]);
        $other = $this->createPersona(['cuil' => '20777777777']);

        $this->putJson("/api/docentes/{$teacher->id_persona}", [
            'nombre' => 'Docente Nuevo',
            'dni' => '887766554',
            'cuil' => '20999888777',
        ])
            ->assertOk()
            ->assertJsonPath('data.persona.nombre', 'Docente Nuevo')
            ->assertJsonPath('data.persona.dni', '887766554')
            ->assertJsonPath('data.persona.cuil', '20999888777');

        $this->assertSame(
            'Docente Nuevo',
            Estudiante::query()->findOrFail($student->id_persona)->persona->nombre
        );

        $this->putJson("/api/docentes/{$teacher->id_persona}", [
            'cuil' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.persona.cuil', null)
            ->assertJsonPath('data.persona.apellido', 'Apellido Inicial');

        $this->putJson("/api/docentes/{$teacher->id_persona}", [
            'dni' => $other->dni,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dni']);

        $this->putJson("/api/docentes/{$teacher->id_persona}", [
            'cuil' => $other->cuil,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuil']);

        $this->putJson("/api/docentes/{$teacher->id_persona}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['docente']);

        $this->putJson('/api/docentes/999999', ['nombre' => 'No existe'])
            ->assertNotFound();
    }

    public function test_student_delete_checks_pasantias_and_removes_person_only_when_unreferenced(): void
    {
        $this->authenticate();

        $studentWithInternship = $this->createStudent();
        $teacher = $this->createTeacher();
        $representative = $this->createRepresentative();
        $pasantiaId = $this->createPasantia(
            $studentWithInternship->id_persona,
            $teacher->id_persona,
            $representative->id_persona
        );

        $this->deleteJson("/api/estudiantes/{$studentWithInternship->id_persona}")
            ->assertStatus(409);
        $this->assertDatabaseHas('estudiante', [
            'id_persona' => $studentWithInternship->id_persona,
        ]);
        $this->assertDatabaseHas('pasantia', ['id_pasantia' => $pasantiaId]);

        $studentWithTeacher = $this->createStudent();
        Docente::query()->create(['id_persona' => $studentWithTeacher->id_persona]);
        $this->createRepresentative($studentWithTeacher->persona);
        $this->deleteJson("/api/estudiantes/{$studentWithTeacher->id_persona}")
            ->assertOk();
        $this->assertDatabaseHas('persona', [
            'id_persona' => $studentWithTeacher->id_persona,
        ]);
        $this->assertDatabaseHas('docente', [
            'id_persona' => $studentWithTeacher->id_persona,
        ]);
        $this->assertDatabaseHas('representante', [
            'id_persona' => $studentWithTeacher->id_persona,
        ]);

        $studentWithRepresentative = $this->createStudent();
        $this->createRepresentative($studentWithRepresentative->persona);
        $this->deleteJson("/api/estudiantes/{$studentWithRepresentative->id_persona}")
            ->assertOk();
        $this->assertDatabaseHas('persona', [
            'id_persona' => $studentWithRepresentative->id_persona,
        ]);
        $this->assertDatabaseHas('representante', [
            'id_persona' => $studentWithRepresentative->id_persona,
        ]);

        $studentWithSignature = $this->createStudent();
        $this->createSignature($studentWithSignature->id_persona);
        $this->deleteJson("/api/estudiantes/{$studentWithSignature->id_persona}")
            ->assertOk();
        $this->assertDatabaseHas('persona', [
            'id_persona' => $studentWithSignature->id_persona,
        ]);

        $studentAlone = $this->createStudent();
        $this->deleteJson("/api/estudiantes/{$studentAlone->id_persona}")
            ->assertOk();
        $this->assertDatabaseMissing('estudiante', [
            'id_persona' => $studentAlone->id_persona,
        ]);
        $this->assertDatabaseMissing('persona', [
            'id_persona' => $studentAlone->id_persona,
        ]);
        $this->deleteJson('/api/estudiantes/999999')->assertNotFound();
    }

    public function test_teacher_delete_checks_pasantias_and_preserves_other_profiles(): void
    {
        $this->authenticate();

        $teacherWithInternship = $this->createTeacher();
        $student = $this->createStudent();
        $representative = $this->createRepresentative();
        $pasantiaId = $this->createPasantia(
            $student->id_persona,
            $teacherWithInternship->id_persona,
            $representative->id_persona
        );

        $this->deleteJson("/api/docentes/{$teacherWithInternship->id_persona}")
            ->assertStatus(409);
        $this->assertDatabaseHas('docente', [
            'id_persona' => $teacherWithInternship->id_persona,
        ]);
        $this->assertDatabaseHas('pasantia', ['id_pasantia' => $pasantiaId]);

        $teacherWithStudent = $this->createTeacher();
        Estudiante::query()->create([
            'id_persona' => $teacherWithStudent->id_persona,
            'carrera' => 'Historia',
        ]);
        $this->createRepresentative($teacherWithStudent->persona);
        $this->deleteJson("/api/docentes/{$teacherWithStudent->id_persona}")
            ->assertOk();
        $this->assertDatabaseHas('persona', [
            'id_persona' => $teacherWithStudent->id_persona,
        ]);
        $this->assertDatabaseHas('estudiante', [
            'id_persona' => $teacherWithStudent->id_persona,
        ]);
        $this->assertDatabaseHas('representante', [
            'id_persona' => $teacherWithStudent->id_persona,
        ]);

        $teacherWithRepresentative = $this->createTeacher();
        $this->createRepresentative($teacherWithRepresentative->persona);
        $this->deleteJson("/api/docentes/{$teacherWithRepresentative->id_persona}")
            ->assertOk();
        $this->assertDatabaseHas('persona', [
            'id_persona' => $teacherWithRepresentative->id_persona,
        ]);
        $this->assertDatabaseHas('representante', [
            'id_persona' => $teacherWithRepresentative->id_persona,
        ]);

        $teacherAlone = $this->createTeacher();
        $this->deleteJson("/api/docentes/{$teacherAlone->id_persona}")
            ->assertOk();
        $this->assertDatabaseMissing('docente', [
            'id_persona' => $teacherAlone->id_persona,
        ]);
        $this->assertDatabaseMissing('persona', [
            'id_persona' => $teacherAlone->id_persona,
        ]);
        $this->deleteJson('/api/docentes/999999')->assertNotFound();
    }

    private function authenticate(): Usuario
    {
        $institution = $this->createInstitution();
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => uniqid('UA'),
            'nombre' => 'Unidad de prueba',
            'id_institucion' => $institution->id_institucion,
        ]);
        $roleId = DB::table('rol')->insertGetId([
            'nombre' => 'Personas vinculadas',
        ]);
        $usuario = Usuario::query()->create([
            'mail' => uniqid('personas-', true).'@example.com',
            'nombre' => 'Usuario de prueba',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $roleId,
            'id_unidad' => $unitId,
        ]);

        $this->withToken($usuario->createToken('test')->plainTextToken);

        return $usuario;
    }

    private function createPersona(array $attributes = []): Persona
    {
        return Persona::query()->create(array_merge([
            'nombre' => 'Persona',
            'apellido' => 'Prueba',
            'dni' => $this->nextDni(),
            'cuil' => null,
        ], $attributes));
    }

    private function createStudent(
        array $personaAttributes = [],
        array $studentAttributes = []
    ): Estudiante {
        $persona = $this->createPersona($personaAttributes);

        return Estudiante::query()->create(array_merge([
            'id_persona' => $persona->id_persona,
            'carrera' => 'Ingeniería',
            'domicilio' => null,
        ], $studentAttributes));
    }

    private function createTeacher(array $personaAttributes = []): Docente
    {
        $persona = $this->createPersona($personaAttributes);

        return Docente::query()->create(['id_persona' => $persona->id_persona]);
    }

    private function createRepresentative(?Persona $persona = null): Representante
    {
        $persona ??= $this->createPersona();
        $institution = $this->createInstitution();

        return Representante::query()->create([
            'id_persona' => $persona->id_persona,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createInstitution(): InstitucionFirmante
    {
        return InstitucionFirmante::query()->create([
            'razon_social' => 'Institución de prueba',
        ]);
    }

    private function createPasantia(
        int $studentId,
        int $teacherId,
        int $representativeId
    ): int {
        $institution = $this->createInstitution();
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => uniqid('UA'),
            'nombre' => 'Unidad de prueba',
            'id_institucion' => $institution->id_institucion,
        ]);

        $parentInstrument = Instrumento::query()->create([
            'titulo' => 'Convenio marco de prueba',
            'estado' => 'Borrador',
        ]);
        Convenio::query()->create([
            'id_instrumento' => $parentInstrument->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);
        DB::table('marco')->insert([
            'id_instrumento' => $parentInstrument->id_instrumento,
        ]);

        $specificInstrument = Instrumento::query()->create([
            'titulo' => 'Convenio específico de prueba',
            'estado' => 'Borrador',
        ]);
        Convenio::query()->create([
            'id_instrumento' => $specificInstrument->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);
        DB::table('especifico')->insert([
            'id_instrumento' => $specificInstrument->id_instrumento,
            'id_convenio_marco' => $parentInstrument->id_instrumento,
        ]);

        return DB::table('pasantia')->insertGetId([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'carga_horaria' => 20,
            'asignacion_estimulo' => 1000,
            'porcentaje_gasto_admin' => null,
            'lugar' => null,
            'tareas' => null,
            'seguro' => null,
            'id_convenio_especifico' => $specificInstrument->id_instrumento,
            'id_estudiante' => $studentId,
            'id_docente_guia' => $teacherId,
            'id_tutor_laboral' => $representativeId,
        ], 'id_pasantia');
    }

    private function createSignature(int $personaId): void
    {
        $institution = $this->createInstitution();
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => uniqid('UA'),
            'nombre' => 'Unidad de prueba',
            'id_institucion' => $institution->id_institucion,
        ]);
        $instrument = Instrumento::query()->create([
            'titulo' => 'Convenio con firma',
            'estado' => 'Borrador',
        ]);
        Convenio::query()->create([
            'id_instrumento' => $instrument->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);
        DB::table('firma')->insert([
            'id_instrumento' => $instrument->id_instrumento,
            'id_persona' => $personaId,
        ]);
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
