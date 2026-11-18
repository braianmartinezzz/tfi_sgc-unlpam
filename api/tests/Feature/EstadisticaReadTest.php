<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EstadisticaReadTest extends TestCase
{
    use RefreshDatabase;

    private int $userCounter = 0;

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/estadisticas')->assertUnauthorized();
    }

    public function test_administracion_can_view_global_and_select_a_unit(): void
    {
        $units = $this->createStatisticsData();
        $admin = $this->createUser('Administración', $units['first']);

        $global = $this->getStatistics($admin)->assertOk();
        $this->assertSame(16, $global->json('data.totales.convenios'));
        $this->assertSame(8, $global->json('data.totales.marco'));
        $this->assertSame(8, $global->json('data.totales.especificos'));
        $this->assertGroupedTotal($global, 'por_estado', 'estado', 'Borrador', 2);
        $this->assertGroupedTotal($global, 'por_estado', 'estado', 'Archivado', 2);
        $this->assertGroupedTotal($global, 'por_tipo', 'tipo', 'Colaboración', 8);
        $this->assertGroupedTotal($global, 'por_tipo', 'tipo', 'Pasantía', 8);
        $this->assertGroupedTotal($global, 'por_anio', 'anio', '2025', 10);
        $this->assertCount(2, $global->json('data.por_unidad_academica'));
        $this->assertSame(2, $global->json('data.vencimientos.vencidos'));
        $this->assertSame(10, $global->json('data.vencimientos.proximos'));
        $this->assertSame(6, $global->json('data.vencimientos.en_preaviso'));

        $selected = $this->getStatistics(
            $admin,
            '?id_unidad_academica='.$units['first']
        )->assertOk();
        $this->assertSame(8, $selected->json('data.totales.convenios'));
        $this->assertSame(4, $selected->json('data.totales.marco'));
        $this->assertCount(1, $selected->json('data.por_unidad_academica'));
        $this->assertSame(1, $selected->json('data.vencimientos.vencidos'));
        $this->assertSame(5, $selected->json('data.vencimientos.proximos'));
        $this->assertSame(3, $selected->json('data.vencimientos.en_preaviso'));
    }

    public function test_gestion_is_limited_to_its_unit_and_visible_states(): void
    {
        $units = $this->createStatisticsData();
        $gestion = $this->createUser('Gestión', $units['first']);

        $withoutFilter = $this->getStatistics($gestion)->assertOk();
        $this->assertSame(6, $withoutFilter->json('data.totales.convenios'));
        $this->assertSame(3, $withoutFilter->json('data.totales.marco'));
        $this->assertSame(3, $withoutFilter->json('data.totales.especificos'));
        $this->assertGroupedTotal(
            $withoutFilter,
            'por_estado',
            'estado',
            'Borrador',
            1
        );
        $this->assertGroupedTotal(
            $withoutFilter,
            'por_estado',
            'estado',
            'Archivado',
            0
        );
        $this->assertGroupedTotal(
            $withoutFilter,
            'por_tipo',
            'tipo',
            'Colaboración',
            3
        );
        $this->assertGroupedTotal(
            $withoutFilter,
            'por_tipo',
            'tipo',
            'Pasantía',
            3
        );
        $this->assertCount(1, $withoutFilter->json('data.por_unidad_academica'));
        $this->assertSame(
            $units['first'],
            $withoutFilter->json('data.por_unidad_academica.0.id_unidad_academica')
        );
        $this->assertSame(1, $withoutFilter->json('data.vencimientos.vencidos'));
        $this->assertSame(4, $withoutFilter->json('data.vencimientos.proximos'));
        $this->assertSame(2, $withoutFilter->json('data.vencimientos.en_preaviso'));

        $sameUnit = $this->getStatistics(
            $gestion,
            '?id_unidad_academica='.$units['first']
        )->assertOk();
        $this->assertSame($withoutFilter->json('data'), $sameUnit->json('data'));

        $this->getStatistics(
            $gestion,
            '?id_unidad_academica='.$units['second']
        )->assertForbidden();
        $this->getStatistics(
            $gestion,
            '?id_unidad_academica='.$units['second']
                .'&fecha_desde=2025-01-01&tipo=Pasantía'
        )->assertForbidden();
    }

    public function test_revision_scope_limits_units_and_preserves_visible_states(): void
    {
        $units = $this->createStatisticsData();
        $localReviewer = $this->createUser('Revisión', $units['first'], false);

        $local = $this->getStatistics($localReviewer)->assertOk();
        $this->assertSame(6, $local->json('data.totales.convenios'));
        $this->assertGroupedTotal($local, 'por_estado', 'estado', 'Borrador', 0);
        $this->assertGroupedTotal($local, 'por_estado', 'estado', 'Firmado', 0);
        $this->assertGroupedTotal($local, 'por_estado', 'estado', 'Revisión', 1);
        $this->assertCount(1, $local->json('data.por_unidad_academica'));
        $this->assertSame(0, $local->json('data.vencimientos.vencidos'));
        $this->assertSame(4, $local->json('data.vencimientos.proximos'));
        $this->assertSame(2, $local->json('data.vencimientos.en_preaviso'));
        $this->getStatistics(
            $localReviewer,
            '?id_unidad_academica='.$units['second']
        )->assertForbidden();

        $globalReviewer = $this->createUser('Revisión', $units['first'], true);
        $global = $this->getStatistics($globalReviewer)->assertOk();
        $this->assertSame(12, $global->json('data.totales.convenios'));
        $this->assertGroupedTotal($global, 'por_estado', 'estado', 'Borrador', 0);
        $this->assertGroupedTotal($global, 'por_estado', 'estado', 'Firmado', 0);
        $this->assertCount(2, $global->json('data.por_unidad_academica'));

        $selected = $this->getStatistics(
            $globalReviewer,
            '?id_unidad_academica='.$units['second']
        )->assertOk();
        $this->assertSame(6, $selected->json('data.totales.convenios'));
        $this->assertCount(1, $selected->json('data.por_unidad_academica'));
    }

    public function test_consulta_scope_limits_units_and_preserves_visible_states(): void
    {
        $units = $this->createStatisticsData();
        $localUser = $this->createUser('Consulta', $units['first'], false);

        $local = $this->getStatistics($localUser)->assertOk();
        $this->assertSame(3, $local->json('data.totales.convenios'));
        $this->assertGroupedTotal($local, 'por_estado', 'estado', 'Firmado', 1);
        $this->assertGroupedTotal($local, 'por_estado', 'estado', 'Rescindido', 1);
        $this->assertGroupedTotal($local, 'por_estado', 'estado', 'Archivado', 1);
        $this->assertGroupedTotal($local, 'por_estado', 'estado', 'Borrador', 0);
        $this->assertCount(1, $local->json('data.por_unidad_academica'));
        $this->assertSame(0, $local->json('data.vencimientos.vencidos'));
        $this->assertSame(1, $local->json('data.vencimientos.proximos'));
        $this->assertSame(1, $local->json('data.vencimientos.en_preaviso'));
        $this->getStatistics(
            $localUser,
            '?id_unidad_academica='.$units['second']
        )->assertForbidden();

        $globalUser = $this->createUser('Consulta', $units['first'], true);
        $global = $this->getStatistics($globalUser)->assertOk();
        $this->assertSame(6, $global->json('data.totales.convenios'));
        $this->assertGroupedTotal($global, 'por_estado', 'estado', 'Firmado', 2);
        $this->assertGroupedTotal($global, 'por_estado', 'estado', 'Borrador', 0);
        $this->assertCount(2, $global->json('data.por_unidad_academica'));

        $selected = $this->getStatistics(
            $globalUser,
            '?id_unidad_academica='.$units['second']
        )->assertOk();
        $this->assertSame(3, $selected->json('data.totales.convenios'));
        $this->assertCount(1, $selected->json('data.por_unidad_academica'));
    }

    public function test_unknown_roles_and_restricted_users_without_a_unit_are_forbidden(): void
    {
        $units = $this->createStatisticsData();
        $users = [
            $this->createUser('Otro rol', $units['first']),
            $this->createUser('Gestión', $units['first']),
            $this->createUser('Revisión', $units['first'], false),
            $this->createUser('Consulta', $units['first'], false),
        ];

        $users[1]->id_unidad = null;
        $users[2]->id_unidad = null;
        $users[3]->id_unidad = null;
        foreach ($users as $index => $user) {
            $this->getStatistics($user)->assertForbidden();
        }
    }

    public function test_validates_unit_existence_and_dias(): void
    {
        $admin = $this->createUser('Administración', $this->createStatisticsData()['first']);

        $this->getStatistics($admin, '?id_unidad_academica=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_unidad_academica']);
        $this->getStatistics($admin, '?dias=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dias']);
        $this->getStatistics($admin, '?fecha_desde=2025-1-1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_desde']);
        $this->getStatistics(
            $admin,
            '?fecha_desde=2025-12-31&fecha_hasta=2025-01-01'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_hasta']);
    }

    public function test_signature_date_filters_are_inclusive_and_exclude_unsigned_convenios(): void
    {
        $units = $this->createStatisticsData();
        $admin = $this->createUser(
            'Administración',
            $units['first']
        );

        $withoutDates = $this->getStatistics($admin)->assertOk();
        $this->assertSame(16, $withoutDates->json('data.totales.convenios'));
        $this->assertGroupedTotal($withoutDates, 'por_anio', 'anio', '2024', 2);
        $this->assertGroupedTotal($withoutDates, 'por_anio', 'anio', '2025', 10);
        $this->assertGroupedTotal($withoutDates, 'por_anio', 'anio', '2026', 2);

        $fromDate = $this->getStatistics(
            $admin,
            '?fecha_desde=2025-01-01'
        )->assertOk();
        $this->assertSame(12, $fromDate->json('data.totales.convenios'));
        $this->assertGroupedTotal($fromDate, 'por_anio', 'anio', '2025', 10);
        $this->assertGroupedTotal($fromDate, 'por_anio', 'anio', '2026', 2);

        $throughDate = $this->getStatistics(
            $admin,
            '?fecha_hasta=2025-12-31'
        )->assertOk();
        $this->assertSame(12, $throughDate->json('data.totales.convenios'));
        $this->assertGroupedTotal($throughDate, 'por_anio', 'anio', '2024', 2);
        $this->assertGroupedTotal($throughDate, 'por_anio', 'anio', '2025', 10);

        $year = $this->getStatistics(
            $admin,
            '?fecha_desde=2025-01-01&fecha_hasta=2025-12-31'
        )->assertOk();
        $this->assertSame(10, $year->json('data.totales.convenios'));
        $this->assertGroupedTotal($year, 'por_estado', 'estado', 'EnCorreccion', 2);
        $this->assertGroupedTotal($year, 'por_estado', 'estado', 'Aprobado', 2);
        $this->assertGroupedTotal($year, 'por_estado', 'estado', 'Borrador', 0);
        $this->assertCount(1, $year->json('data.por_anio'));
        $this->assertGroupedTotal($year, 'por_anio', 'anio', '2025', 10);

        $sameDay = $this->getStatistics(
            $admin,
            '?fecha_desde=2025-01-01&fecha_hasta=2025-01-01'
        )->assertOk();
        $this->assertSame(4, $sameDay->json('data.totales.convenios'));
        $this->assertGroupedTotal($sameDay, 'por_estado', 'estado', 'EnCorreccion', 2);
        $this->assertGroupedTotal($sameDay, 'por_estado', 'estado', 'Archivado', 2);
    }

    public function test_type_filter_accepts_only_existing_convenio_types(): void
    {
        $units = $this->createStatisticsData();
        $admin = $this->createUser(
            'Administración',
            $units['first']
        );

        $collaboration = $this->getStatistics($admin, '?tipo=Colaboración')
            ->assertOk();
        $this->assertSame(8, $collaboration->json('data.totales.convenios'));
        $this->assertGroupedTotal(
            $collaboration,
            'por_tipo',
            'tipo',
            'Colaboración',
            8
        );
        $this->assertGroupedTotal(
            $collaboration,
            'por_tipo',
            'tipo',
            'Pasantía',
            0
        );

        $internships = $this->getStatistics($admin, '?tipo=Pasantía')->assertOk();
        $this->assertSame(8, $internships->json('data.totales.convenios'));
        $this->assertGroupedTotal(
            $internships,
            'por_tipo',
            'tipo',
            'Pasantía',
            8
        );

        $this->getStatistics($admin, '?tipo=Otro')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_combined_filters_apply_to_every_statistics_block(): void
    {
        $units = $this->createStatisticsData();
        $admin = $this->createUser('Administración', $units['first']);

        $response = $this->getStatistics(
            $admin,
            '?fecha_desde=2025-01-01&fecha_hasta=2025-12-31'
                .'&tipo=Pasantía&id_unidad_academica='.$units['second']
        )->assertOk();

        $this->assertSame(3, $response->json('data.totales.convenios'));
        $this->assertSame(0, $response->json('data.totales.marco'));
        $this->assertSame(3, $response->json('data.totales.especificos'));
        $this->assertGroupedTotal(
            $response,
            'por_estado',
            'estado',
            'EnCorreccion',
            1
        );
        $this->assertGroupedTotal(
            $response,
            'por_estado',
            'estado',
            'Aprobado',
            1
        );
        $this->assertGroupedTotal(
            $response,
            'por_estado',
            'estado',
            'Archivado',
            1
        );
        $this->assertGroupedTotal(
            $response,
            'por_tipo',
            'tipo',
            'Pasantía',
            3
        );
        $this->assertCount(1, $response->json('data.por_tipo'));
        $this->assertCount(1, $response->json('data.por_unidad_academica'));
        $this->assertSame(
            $units['second'],
            $response->json('data.por_unidad_academica.0.id_unidad_academica')
        );
        $this->assertSame(3, $response->json('data.por_unidad_academica.0.total'));
        $this->assertGroupedTotal($response, 'por_anio', 'anio', '2025', 3);
        $this->assertSame(0, $response->json('data.vencimientos.vencidos'));
        $this->assertSame(2, $response->json('data.vencimientos.proximos'));
        $this->assertSame(1, $response->json('data.vencimientos.en_preaviso'));
    }

    private function createStatisticsData(): array
    {
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de estadísticas',
        ]);
        $firstUnit = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-EST-01',
            'nombre' => 'Unidad de estadísticas uno',
            'id_institucion' => $institutionId,
        ]);
        $secondUnit = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-EST-02',
            'nombre' => 'Unidad de estadísticas dos',
            'id_institucion' => $institutionId,
        ]);

        $states = [
            ['Borrador', -1, 30, null],
            ['EnCorreccion', 5, 30, '2025-01-01'],
            ['Revisión', 20, 10, '2025-06-15'],
            ['Aprobado', 90, 30, '2025-12-31'],
            ['Firmado', 10, 30, '2026-01-01'],
            ['Rescindido', 200, 30, '2024-12-31'],
            ['Rechazado', 5, 30, '2025-09-10'],
            ['Archivado', 100, 30, '2025-01-01'],
        ];
        $today = Carbon::today();

        foreach ([$firstUnit, $secondUnit] as $unitId) {
            $convenios = [];
            foreach ($states as $index => [
                $state,
                $daysToExpiry,
                $noticeDays,
                $fechaFirma,
            ]) {
                $instrumento = Instrumento::query()->create([
                    'titulo' => "Estadística {$unitId} {$state}",
                    'estado' => $state,
                    'fecha_firma' => $fechaFirma,
                    'vencimiento' => $today->copy()->addDays($daysToExpiry)->toDateString(),
                    'dias_preaviso' => $noticeDays,
                ]);
                $convenio = Convenio::query()->create([
                    'id_instrumento' => $instrumento->id_instrumento,
                    'tipo' => $index % 2 === 0 ? 'Colaboración' : 'Pasantía',
                    'id_unidad_academica' => $unitId,
                ]);
                $convenios[$index] = $convenio;
            }

            foreach ($states as $index => $_state) {
                $convenio = $convenios[$index];
                if ($index % 2 === 0) {
                    Marco::query()->create([
                        'id_instrumento' => $convenio->id_instrumento,
                    ]);
                } else {
                    Especifico::query()->create([
                        'id_instrumento' => $convenio->id_instrumento,
                        'id_convenio_marco' => $convenios[0]->id_instrumento,
                    ]);
                }
            }
        }

        return ['first' => $firstUnit, 'second' => $secondUnit];
    }

    private function createUser(
        string $role,
        int $unitId,
        ?bool $alcanceGlobal = false
    ): Usuario {
        $roleId = DB::table('rol')->where('nombre', $role)->value('id_rol');
        if ($roleId === null) {
            $roleId = DB::table('rol')->insertGetId(['nombre' => $role]);
        }

        $user = Usuario::query()->create([
            'mail' => 'estadisticas-'.$this->userCounter++.'@example.com',
            'nombre' => 'Usuario de estadísticas',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $roleId,
            'id_unidad' => $unitId,
            'alcance_global' => $alcanceGlobal,
        ]);

        return $user;
    }

    private function getStatistics(
        Usuario $user,
        string $query = ''
    ): TestResponse {
        $this->actingAs($user, 'sanctum');

        return $this->getJson('/api/estadisticas'.$query);
    }

    private function assertGroupedTotal(
        TestResponse $response,
        string $group,
        string $field,
        string $value,
        int $expected
    ): void {
        $row = collect($response->json('data.'.$group))
            ->firstWhere($field, $value);

        $this->assertSame($expected, (int) ($row['total'] ?? 0));
    }
}
