<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Docente;
use App\Models\Especifico;
use App\Models\Estudiante;
use App\Models\Instrumento;
use App\Models\Pasantia;
use App\Models\Persona;
use App\Models\Representante;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EstadisticaIndicadoresTest extends TestCase
{
    use RefreshDatabase;

    private int $personNumber = 0;

    private int $userNumber = 0;

    public function test_admin_indicators_follow_their_definitions_and_count_unique_people(): void
    {
        $data = $this->createIndicatorsData();
        $admin = $this->createUser('Administración', $data['unitA']);

        $response = $this->getStatistics($admin)->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'por_anio',
                'indicadores' => [
                    'convenios_vigentes',
                    'convenios_en_tramite',
                    'firmados_ultimo_anio',
                    'personas_unicas',
                    'pasantes_unicos',
                ],
            ],
        ]);
        $response->assertJsonPath('data.indicadores.convenios_vigentes', 3);
        $response->assertJsonPath('data.indicadores.convenios_en_tramite', 5);
        $response->assertJsonPath('data.indicadores.firmados_ultimo_anio', 6);
        $response->assertJsonPath('data.indicadores.personas_unicas', 8);
        $response->assertJsonPath('data.indicadores.pasantes_unicos', 5);
    }

    public function test_new_indicators_apply_filters_and_role_visibility(): void
    {
        $data = $this->createIndicatorsData();
        $today = Carbon::today()->toDateString();
        $firstDate = Carbon::today()->subMonthsNoOverflow(12)->toDateString();
        $periodAndType = '?'.http_build_query([
            'fecha_desde' => $firstDate,
            'fecha_hasta' => $today,
            'tipo' => 'Pasantía',
        ], '', '&', PHP_QUERY_RFC3986);
        $filteredUnitA = $periodAndType
            .'&id_unidad_academica='.$data['unitA'];
        $filteredUnitB = $periodAndType
            .'&id_unidad_academica='.$data['unitB'];

        $admin = $this->createUser('Administración', $data['unitA']);
        $adminResult = $this->getStatistics($admin, $filteredUnitA)->assertOk();
        $this->assertIndicatorValues($adminResult, [
            'convenios_vigentes' => 2,
            'convenios_en_tramite' => 0,
            'firmados_ultimo_anio' => 2,
            'personas_unicas' => 2,
            'pasantes_unicos' => 2,
        ]);
        $adminResult->assertJsonPath('data.totales.convenios', 2);

        $gestion = $this->createUser('Gestión', $data['unitA']);
        $gestionResult = $this->getStatistics($gestion, $periodAndType)->assertOk();
        $this->assertIndicatorValues($gestionResult, [
            'convenios_vigentes' => 2,
            'convenios_en_tramite' => 0,
            'firmados_ultimo_anio' => 2,
            'personas_unicas' => 2,
            'pasantes_unicos' => 2,
        ]);
        $this->getStatistics($gestion, $filteredUnitB)->assertForbidden();

        $localReviewer = $this->createUser('Revisión', $data['unitA'], false);
        $this->assertIndicatorValues(
            $this->getStatistics($localReviewer)->assertOk(),
            [
                'convenios_vigentes' => 0,
                'convenios_en_tramite' => 3,
                'firmados_ultimo_anio' => 0,
                'personas_unicas' => 1,
                'pasantes_unicos' => 1,
            ]
        );

        $globalReviewer = $this->createUser('Revisión', $data['unitA'], true);
        $this->assertIndicatorValues(
            $this->getStatistics($globalReviewer)->assertOk(),
            [
                'convenios_vigentes' => 0,
                'convenios_en_tramite' => 4,
                'firmados_ultimo_anio' => 0,
                'personas_unicas' => 2,
                'pasantes_unicos' => 2,
            ]
        );

        $localConsulta = $this->createUser('Consulta', $data['unitA'], false);
        $this->assertIndicatorValues(
            $this->getStatistics($localConsulta)->assertOk(),
            [
                'convenios_vigentes' => 2,
                'convenios_en_tramite' => 0,
                'firmados_ultimo_anio' => 5,
                'personas_unicas' => 6,
                'pasantes_unicos' => 3,
            ]
        );

        $globalConsulta = $this->createUser('Consulta', $data['unitA'], true);
        $this->assertIndicatorValues(
            $this->getStatistics($globalConsulta)->assertOk(),
            [
                'convenios_vigentes' => 3,
                'convenios_en_tramite' => 0,
                'firmados_ultimo_anio' => 6,
                'personas_unicas' => 7,
                'pasantes_unicos' => 4,
            ]
        );
    }

    private function createIndicatorsData(): array
    {
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de indicadores',
        ]);
        $unitA = $this->createUnit($institutionId, 'UA-IND-01');
        $unitB = $this->createUnit($institutionId, 'UA-IND-02');
        $today = Carbon::today();
        $lowerBoundary = $today->copy()->subMonthsNoOverflow(12)->toDateString();

        $rootA = $this->createMarco($unitA, 'Archivado');
        $rootB = $this->createMarco($unitB, 'Archivado');

        $entries = [
            'boundary' => $this->createSpecific(
                $unitA,
                $rootA,
                'Firmado',
                'Pasantía',
                $lowerBoundary,
                $today->copy()->subDay()->toDateString(),
                $today->copy()->addDay()->toDateString()
            ),
            'today' => $this->createSpecific(
                $unitA,
                $rootA,
                'Firmado',
                'Pasantía',
                $today->toDateString(),
                $today->toDateString(),
                $today->toDateString()
            ),
            'futureStart' => $this->createSpecific(
                $unitA,
                $rootA,
                'Firmado',
                'Colaboración',
                $today->toDateString(),
                $today->copy()->addDay()->toDateString(),
                $today->copy()->addDays(30)->toDateString()
            ),
            'missingStart' => $this->createSpecific(
                $unitA,
                $rootA,
                'Firmado',
                'Colaboración',
                $today->toDateString(),
                null,
                $today->copy()->addDays(30)->toDateString()
            ),
            'missingExpiry' => $this->createSpecific(
                $unitA,
                $rootA,
                'Firmado',
                'Colaboración',
                $today->toDateString(),
                $today->copy()->subDay()->toDateString(),
                null
            ),
            'expired' => $this->createSpecific(
                $unitA,
                $rootA,
                'Firmado',
                'Colaboración',
                $today->copy()->subMonthsNoOverflow(12)->subDay()->toDateString(),
                $today->copy()->subDays(30)->toDateString(),
                $today->copy()->subDay()->toDateString()
            ),
            'approved' => $this->createSpecific(
                $unitA,
                $rootA,
                'Aprobado',
                'Pasantía',
                null,
                $today->copy()->subDay()->toDateString(),
                $today->copy()->addDay()->toDateString()
            ),
            'draft' => $this->createSpecific($unitA, $rootA, 'Borrador'),
            'correction' => $this->createSpecific($unitA, $rootA, 'EnCorreccion'),
            'review' => $this->createSpecific($unitA, $rootA, 'Revisión'),
            'rescinded' => $this->createSpecific($unitA, $rootA, 'Rescindido'),
            'rejected' => $this->createSpecific($unitA, $rootA, 'Rechazado'),
            'otherUnitSigned' => $this->createSpecific(
                $unitB,
                $rootB,
                'Firmado',
                'Pasantía',
                $today->toDateString(),
                $today->copy()->subDay()->toDateString(),
                $today->copy()->addDay()->toDateString()
            ),
            'otherUnitApproved' => $this->createSpecific(
                $unitB,
                $rootB,
                'Aprobado',
                'Pasantía'
            ),
        ];

        $signers = [];
        for ($index = 1; $index <= 8; $index++) {
            $signers[$index] = $this->createPerson("Firmante {$index}");
        }
        foreach ([
            'boundary' => [1, 2],
            'today' => [1],
            'futureStart' => [3],
            'missingStart' => [4],
            'missingExpiry' => [5],
            'expired' => [6],
            'approved' => [7],
            'otherUnitSigned' => [1, 8],
            'otherUnitApproved' => [8],
        ] as $key => $personIds) {
            foreach ($personIds as $personId) {
                DB::table('firma')->insert([
                    'id_instrumento' => $entries[$key]->id_instrumento,
                    'id_persona' => $signers[$personId],
                ]);
            }
        }

        $students = [];
        for ($index = 1; $index <= 5; $index++) {
            $students[$index] = $this->createStudent("Estudiante {$index}");
        }
        $teacherId = $this->createTeacher();
        $tutorId = $this->createTutor($institutionId);
        foreach ([
            'boundary' => [1],
            'today' => [1, 2],
            'expired' => [3],
            'approved' => [4],
            'otherUnitSigned' => [1, 5],
            'otherUnitApproved' => [5],
        ] as $key => $studentIds) {
            foreach ($studentIds as $studentId) {
                $this->createPasantia(
                    $entries[$key],
                    $students[$studentId],
                    $teacherId,
                    $tutorId
                );
            }
        }

        return compact('unitA', 'unitB');
    }

    private function createUnit(int $institutionId, string $sigla): int
    {
        return DB::table('unidad_academica')->insertGetId([
            'sigla' => $sigla,
            'nombre' => "Unidad {$sigla}",
            'id_institucion' => $institutionId,
        ]);
    }

    private function createMarco(int $unitId, string $state): int
    {
        $instrument = Instrumento::query()->create([
            'titulo' => "Marco {$state} {$unitId}",
            'estado' => $state,
        ]);
        Convenio::query()->create([
            'id_instrumento' => $instrument->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);
        DB::table('marco')->insert([
            'id_instrumento' => $instrument->id_instrumento,
        ]);

        return $instrument->id_instrumento;
    }

    private function createSpecific(
        int $unitId,
        int $parentMarcoId,
        string $state,
        string $type = 'Colaboración',
        ?string $fechaFirma = null,
        ?string $inicio = null,
        ?string $vencimiento = null
    ): Convenio {
        $instrument = Instrumento::query()->create([
            'titulo' => "Específico {$state} ".uniqid(),
            'estado' => $state,
            'fecha_firma' => $fechaFirma,
            'inicio' => $inicio,
            'vencimiento' => $vencimiento,
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrument->id_instrumento,
            'tipo' => $type,
            'id_unidad_academica' => $unitId,
        ]);
        Especifico::query()->create([
            'id_instrumento' => $convenio->id_instrumento,
            'id_convenio_marco' => $parentMarcoId,
        ]);

        return $convenio;
    }

    private function createPerson(string $name): int
    {
        $number = ++$this->personNumber;
        $person = Persona::query()->create([
            'nombre' => $name,
            'apellido' => 'Estadística',
            'dni' => 'IND'.str_pad((string) $number, 8, '0', STR_PAD_LEFT),
        ]);

        return $person->id_persona;
    }

    private function createStudent(string $name): int
    {
        $personId = $this->createPerson($name);
        Estudiante::query()->create([
            'id_persona' => $personId,
            'carrera' => 'Ingeniería',
        ]);

        return $personId;
    }

    private function createTeacher(): int
    {
        $personId = $this->createPerson('Docente guía');
        Docente::query()->create(['id_persona' => $personId]);

        return $personId;
    }

    private function createTutor(int $institutionId): int
    {
        $personId = $this->createPerson('Tutor laboral');
        Representante::query()->create([
            'id_persona' => $personId,
            'cargo' => 'Tutor',
            'id_institucion' => $institutionId,
        ]);

        return $personId;
    }

    private function createPasantia(
        Convenio $convenio,
        int $studentId,
        int $teacherId,
        int $tutorId
    ): void {
        Pasantia::query()->create([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'carga_horaria' => 20,
            'asignacion_estimulo' => 1000,
            'id_convenio_especifico' => $convenio->id_instrumento,
            'id_estudiante' => $studentId,
            'id_docente_guia' => $teacherId,
            'id_tutor_laboral' => $tutorId,
        ]);
    }

    private function createUser(
        string $roleName,
        int $unitId,
        bool $alcanceGlobal = false
    ): Usuario {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'indicadores-'.$this->userNumber++.'@example.com',
            'nombre' => "Usuario {$roleName}",
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
            'alcance_global' => $alcanceGlobal,
        ]);
    }

    private function getStatistics(
        Usuario $user,
        string $query = ''
    ): TestResponse {
        return $this->actingAs($user, 'sanctum')
            ->getJson('/api/estadisticas'.$query);
    }

    private function assertIndicatorValues(
        TestResponse $response,
        array $expected
    ): void {
        foreach ($expected as $name => $count) {
            $response->assertJsonPath('data.indicadores.'.$name, $count);
        }
    }
}
