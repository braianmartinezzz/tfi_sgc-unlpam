<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\HistorialEstado;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Notificacion;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/panel')->assertUnauthorized();
    }

    public function test_gestion_sees_the_summary_of_its_own_unit_only(): void
    {
        $ctx = $this->createScenario();
        $this->authenticate($ctx['gestion']);

        $this->getJson('/api/panel')
            ->assertOk()
            ->assertJsonPath('data.resumen.por_estado.Borrador', 1)
            ->assertJsonPath('data.resumen.por_estado.Revisión', 1)
            ->assertJsonPath('data.resumen.por_estado.Aprobado', 1)
            ->assertJsonPath('data.resumen.por_estado.Firmado', 2)
            ->assertJsonPath('data.resumen.por_estado.Rescindido', 1)
            ->assertJsonPath('data.resumen.en_tramite', 3)
            ->assertJsonPath('data.resumen.vigentes', 1)
            ->assertJsonPath('data.resumen.vencidos', 1)
            ->assertJsonPath('data.resumen.en_preaviso', 1)
            ->assertJsonPath('data.resumen.firmados_ultimo_anio', 2)
            ->assertJsonCount(1, 'data.por_unidad')
            ->assertJsonPath('data.por_unidad.0.id_unidad_academica', $ctx['unitA']->id_unidad_academica)
            ->assertJsonPath('data.por_unidad.0.total', 6);
    }

    public function test_consulta_only_sees_final_states_of_its_unit(): void
    {
        $ctx = $this->createScenario();
        $this->authenticate($ctx['consulta']);

        $this->getJson('/api/panel')
            ->assertOk()
            ->assertJsonPath('data.resumen.por_estado.Firmado', 2)
            ->assertJsonPath('data.resumen.por_estado.Rescindido', 1)
            ->assertJsonMissingPath('data.resumen.por_estado.Borrador')
            ->assertJsonPath('data.resumen.en_tramite', 0)
            ->assertJsonPath('data.pendientes_firma', []);
    }

    public function test_administracion_sees_all_units(): void
    {
        $ctx = $this->createScenario();
        $this->authenticate($ctx['admin']);

        $this->getJson('/api/panel')
            ->assertOk()
            ->assertJsonPath('data.resumen.por_estado.Firmado', 3)
            ->assertJsonCount(2, 'data.por_unidad')
            ->assertJsonPath('data.por_unidad.0.total', 6);
    }

    public function test_lists_the_latest_movement_of_each_convenio_most_recent_first(): void
    {
        $ctx = $this->createScenario();
        $ctx = array_merge($ctx, [
            'a' => $this->createConvenio($ctx['unitA'], 'Convenio A', 'Revisión'),
            'b' => $this->createConvenio($ctx['unitA'], 'Convenio B', 'Aprobado'),
        ]);

        $this->createMovimiento($ctx['a'], 'Borrador', 'Revisión', '2026-11-01 10:00:00', $ctx['gestion']);
        $this->createMovimiento($ctx['a'], 'Revisión', 'EnCorreccion', '2026-11-03 10:00:00', $ctx['gestion']);
        $this->createMovimiento($ctx['b'], 'Revisión', 'Aprobado', '2026-11-02 10:00:00', $ctx['gestion']);

        $this->authenticate($ctx['gestion']);

        $this->getJson('/api/panel')
            ->assertOk()
            ->assertJsonCount(2, 'data.ultimos_movimientos')
            ->assertJsonPath('data.ultimos_movimientos.0.titulo', 'Convenio A')
            ->assertJsonPath('data.ultimos_movimientos.0.estado_nuevo', 'EnCorreccion')
            ->assertJsonPath('data.ultimos_movimientos.0.usuario', $ctx['gestion']->nombre)
            ->assertJsonPath('data.ultimos_movimientos.1.titulo', 'Convenio B');
    }

    public function test_limits_the_latest_movements_to_ten(): void
    {
        $ctx = $this->createScenario();

        for ($i = 1; $i <= 12; $i++) {
            $convenio = $this->createConvenio($ctx['unitA'], 'Convenio '.$i, 'Revisión');
            $this->createMovimiento(
                $convenio,
                'Borrador',
                'Revisión',
                sprintf('2026-10-%02d 09:00:00', $i),
                $ctx['gestion']
            );
        }

        $this->authenticate($ctx['gestion']);

        $this->getJson('/api/panel')
            ->assertOk()
            ->assertJsonCount(10, 'data.ultimos_movimientos')
            ->assertJsonPath('data.ultimos_movimientos.0.titulo', 'Convenio 12');
    }

    public function test_pending_signatures_are_ordered_from_oldest_and_flag_delayed_ones(): void
    {
        $ctx = $this->createScenario();
        $reciente = $this->createConvenio($ctx['unitA'], 'Aprobado reciente', 'Aprobado');
        $viejo = $this->createConvenio($ctx['unitA'], 'Aprobado viejo', 'Aprobado');
        $otraUnidad = $this->createConvenio($ctx['unitB'], 'Aprobado de otra unidad', 'Aprobado');

        $this->createMovimiento($reciente, 'Revisión', 'Aprobado', now()->subHours(5)->toDateTimeString(), $ctx['gestion']);
        $this->createMovimiento($viejo, 'Revisión', 'Aprobado', now()->subHours(100)->toDateTimeString(), $ctx['gestion']);
        $this->createMovimiento($otraUnidad, 'Revisión', 'Aprobado', now()->subHours(200)->toDateTimeString(), $ctx['gestion']);

        $this->authenticate($ctx['gestion']);

        $response = $this->getJson('/api/panel')->assertOk();
        $titulos = collect($response->json('data.pendientes_firma'))->pluck('titulo')->all();

        // El aprobado base no tiene historial y debe quedar al final; la otra unidad no es visible.
        $this->assertSame(['Aprobado viejo', 'Aprobado reciente', 'Convenio Aprobado'], $titulos);
        $response
            ->assertJsonPath('data.pendientes_firma.0.demorado', true)
            ->assertJsonPath('data.pendientes_firma.1.demorado', false);

        $tipos = collect($response->json('data.alertas'))->pluck('tipo')->all();
        $this->assertContains('FIRMA_DEMORADA', $tipos);
        $this->assertContains('PREAVISO', $tipos);
    }

    public function test_returns_only_own_notifications_newest_first(): void
    {
        $ctx = $this->createScenario();
        $propias = [
            $this->createNotificacion($ctx['gestion'], 'Primera', '2026-11-10 09:00:00', false),
            $this->createNotificacion($ctx['gestion'], 'Segunda', '2026-11-12 09:00:00', false),
            $this->createNotificacion($ctx['gestion'], 'Leída', '2026-11-11 09:00:00', true),
        ];
        $this->createNotificacion($ctx['admin'], 'De otro usuario', '2026-11-13 09:00:00', false);

        $this->authenticate($ctx['gestion']);

        $this->getJson('/api/panel')
            ->assertOk()
            ->assertJsonPath('data.notificaciones.no_leidas', 2)
            ->assertJsonCount(3, 'data.notificaciones.items')
            ->assertJsonPath('data.notificaciones.items.0.mensaje', 'Segunda')
            ->assertJsonPath('data.notificaciones.items.2.mensaje', 'Primera');

        $this->assertCount(3, $propias);
    }

    /**
     * Unidad A: Borrador, Revisión, Aprobado, Firmado vigente en preaviso, Firmado vencido y Rescindido.
     * Unidad B: un Firmado.
     */
    private function createScenario(): array
    {
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();

        $this->createConvenio($unitA, 'Convenio Borrador', 'Borrador');
        $this->createConvenio($unitA, 'Convenio Revisión', 'Revisión');
        $this->createConvenio($unitA, 'Convenio Aprobado', 'Aprobado');
        $this->createConvenio($unitA, 'Firmado en preaviso', 'Firmado', [
            'fecha_firma' => now()->subDays(30)->toDateString(),
            'vencimiento' => now()->addDays(20)->toDateString(),
            'dias_preaviso' => 30,
        ]);
        $this->createConvenio($unitA, 'Firmado vencido', 'Firmado', [
            'fecha_firma' => now()->subDays(400)->toDateString(),
            'vencimiento' => now()->subDays(5)->toDateString(),
            'dias_preaviso' => 30,
        ]);
        $this->createConvenio($unitA, 'Convenio rescindido', 'Rescindido', [
            'fecha_firma' => now()->subDays(10)->toDateString(),
        ]);
        $this->createConvenio($unitB, 'Firmado de otra unidad', 'Firmado', [
            'fecha_firma' => now()->subDays(20)->toDateString(),
            'vencimiento' => now()->addDays(200)->toDateString(),
        ]);

        return [
            'unitA' => $unitA,
            'unitB' => $unitB,
            'gestion' => $this->createUser('Gestión', $unitA->id_unidad_academica),
            'consulta' => $this->createUser('Consulta', $unitA->id_unidad_academica),
            'admin' => $this->createUser('Administración', $unitB->id_unidad_academica),
        ];
    }

    private function createConvenio(UnidadAcademica $unit, string $titulo, string $estado, array $extra = []): Convenio
    {
        $instrumento = Instrumento::query()->create(array_merge([
            'titulo' => $titulo,
            'estado' => $estado,
        ], $extra));

        return Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);
    }

    private function createMovimiento(
        Convenio $convenio,
        string $anterior,
        string $nuevo,
        string $fechaHora,
        Usuario $usuario
    ): void {
        HistorialEstado::query()->create([
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'fecha_hora' => $fechaHora,
            'motivo' => null,
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    private function createNotificacion(Usuario $usuario, string $mensaje, string $fecha, bool $leida): Notificacion
    {
        $convenio = Convenio::query()->firstOrFail();

        return Notificacion::query()->create([
            'tipo' => 'PRUEBA',
            'mensaje' => $mensaje,
            'fecha_generacion' => $fecha,
            'leida' => $leida,
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    private function createUnit(): UnidadAcademica
    {
        $institution = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number++,
        ]);

        return UnidadAcademica::query()->create([
            'sigla' => 'PAN-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(string $roleName, int $unitId): Usuario
    {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'panel-'.$this->number++.'@example.com',
            'nombre' => 'Usuario '.$roleName.' '.$this->number,
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
            'alcance_global' => false,
        ]);
    }

    private function authenticate(Usuario $usuario): void
    {
        Auth::forgetGuards();
        $this->withToken($usuario->createToken('test')->plainTextToken);
    }
}
