<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\HistorialEstado;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Notificacion;
use App\Models\Observacion;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevolverConvenioTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    public function test_revision_of_same_unit_can_return_convenio_and_records_transition(): void
    {
        $context = $this->createConvention();
        $observation = $context['pending'];

        $this->actingAs($context['reviewer'], 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertOk()
            ->assertJsonPath('message', 'Convenio devuelto a corrección correctamente.')
            ->assertJsonPath('data.instrumento.estado', 'EnCorreccion');

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'EnCorreccion',
        ]);
        $this->assertDatabaseHas('historial_estado', [
            'estado_anterior' => 'Revisión',
            'estado_nuevo' => 'EnCorreccion',
            'motivo' => 'Falta ajustar cláusulas.',
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['reviewer']->id_usuario,
        ]);
        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'DEVOLUCION_A_CORRECCION',
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['reviewer']->id_usuario,
        ]);
        $this->assertDatabaseHas('notificacion', [
            'tipo' => 'DEVOLUCION_OBSERVACION',
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['manager']->id_usuario,
        ]);

        $history = HistorialEstado::query()->sole();
        $this->assertNotNull($history->fecha_hora);
        $this->assertStringContainsString(
            'Falta ajustar cláusulas.',
            RegistroAuditoria::query()->sole()->detalle
        );
        $this->assertSame('Pendiente', $observation->fresh()->estado_tramite);
        $this->assertSame(
            'Revisar el plazo de vigencia.',
            $observation->fresh()->contenido
        );
    }

    public function test_revision_from_another_unit_is_forbidden_without_global_scope(): void
    {
        $context = $this->createConvention();
        $otherUnit = $this->createUnit();
        $reviewer = $this->createUser(
            'Revisión',
            $otherUnit->id_unidad_academica
        );

        $this->actingAs($reviewer, 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertForbidden();

        $this->assertUnchanged($context);
    }

    public function test_revision_with_global_scope_can_return_a_convenio_in_another_unit(): void
    {
        $context = $this->createConvention();
        $otherUnit = $this->createUnit();
        $reviewer = $this->createUser(
            'Revisión',
            $otherUnit->id_unidad_academica,
            true
        );

        $this->actingAs($reviewer, 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertOk();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'EnCorreccion',
        ]);
    }

    public function test_administracion_can_return_a_convenio_in_another_unit(): void
    {
        $context = $this->createConvention();
        $otherUnit = $this->createUnit();
        $administracion = $this->createUser(
            'Administración',
            $otherUnit->id_unidad_academica
        );

        $this->actingAs($administracion, 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertOk();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'EnCorreccion',
        ]);
    }

    public function test_gestion_and_consulta_cannot_return_a_convenio(): void
    {
        $context = $this->createConvention();

        foreach (['Gestión', 'Consulta', 'Otro'] as $roleName) {
            $user = $this->createUser(
                $roleName,
                $context['unit']->id_unidad_academica
            );

            $this->actingAs($user, 'sanctum')
                ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
                ->assertForbidden();
        }

        $this->assertUnchanged($context);
    }

    public function test_endpoint_requires_authentication_and_returns_not_found_for_missing_convenio(): void
    {
        $this->postJson('/api/convenios/999999/devolver', [
            'motivo' => 'Falta ajustar cláusulas.',
        ])->assertUnauthorized();

        $context = $this->createConvention();
        $this->actingAs($context['reviewer'], 'sanctum')
            ->postJson('/api/convenios/999999/devolver', [
                'motivo' => 'Falta ajustar cláusulas.',
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    public function test_states_other_than_revision_return_conflict(): void
    {
        foreach (['Borrador', 'EnCorreccion', 'Aprobado', 'Firmado', 'Rescindido'] as $state) {
            $context = $this->createConvention($state);

            $this->actingAs($context['reviewer'], 'sanctum')
                ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
                ->assertStatus(409);

            $this->assertUnchanged($context);
        }
    }

    public function test_return_requires_a_pending_observation(): void
    {
        $context = $this->createConvention(withPending: false);

        $this->actingAs($context['reviewer'], 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El convenio no tiene observaciones pendientes.');

        $this->assertUnchanged($context);
    }

    public function test_resolved_observations_without_pending_ones_do_not_allow_return(): void
    {
        $context = $this->createConvention(withPending: false, withResolved: true);

        $this->actingAs($context['reviewer'], 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El convenio no tiene observaciones pendientes.');

        $this->assertUnchanged($context);
        $this->assertSame('Resuelta', $context['resolved']->fresh()->estado_tramite);
    }

    public function test_notifications_are_sent_only_to_active_management_users_of_the_convenio_unit(): void
    {
        $context = $this->createConvention();
        $secondManager = $this->createUser(
            'Gestión',
            $context['unit']->id_unidad_academica
        );
        $inactiveManager = $this->createUser(
            'Gestión',
            $context['unit']->id_unidad_academica,
            active: false
        );
        $this->createUser('Consulta', $context['unit']->id_unidad_academica);
        $this->createUser('Administración', $context['unit']->id_unidad_academica);
        $otherUnit = $this->createUnit();
        $otherUnitManager = $this->createUser(
            'Gestión',
            $otherUnit->id_unidad_academica
        );

        $this->actingAs($context['reviewer'], 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertOk();

        $notificaciones = Notificacion::query()
            ->where('id_instrumento', $context['convenio']->id_instrumento)
            ->orderBy('id_usuario')
            ->get();
        $expectedRecipients = [
            $context['manager']->id_usuario,
            $secondManager->id_usuario,
        ];
        sort($expectedRecipients);

        $this->assertSame(
            $expectedRecipients,
            $notificaciones->pluck('id_usuario')->all()
        );
        $this->assertSame(
            ['DEVOLUCION_OBSERVACION'],
            $notificaciones->pluck('tipo')->unique()->values()->all()
        );
        $this->assertFalse(
            $notificaciones->contains('id_usuario', $inactiveManager->id_usuario)
        );
        $this->assertFalse(
            $notificaciones->contains('id_usuario', $otherUnitManager->id_usuario)
        );
    }

    public function test_return_fails_without_active_management_recipients(): void
    {
        $context = $this->createConvention(withManager: false);

        $this->actingAs($context['reviewer'], 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No existen usuarios con rol Gestión activos en la unidad académica para recibir la notificación de devolución'
            );

        $this->assertUnchanged($context);
        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_audit_failure_rolls_back_the_return(): void
    {
        $context = $this->createConvention();
        DB::unprepared(
            'CREATE TRIGGER fail_return_audit BEFORE INSERT ON registro_auditoria '
            ."WHEN NEW.accion = 'DEVOLUCION_A_CORRECCION' "
            ."BEGIN SELECT RAISE(FAIL, 'audit write failed'); END"
        );

        $this->actingAs($context['reviewer'], 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertStatus(500);

        $this->assertUnchanged($context);
        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_notification_failure_rolls_back_state_history_and_audit(): void
    {
        $context = $this->createConvention();
        DB::unprepared(
            'CREATE TRIGGER fail_return_notification BEFORE INSERT ON notificacion '
            ."WHEN NEW.tipo = 'DEVOLUCION_OBSERVACION' "
            ."BEGIN SELECT RAISE(FAIL, 'notification write failed'); END"
        );

        $this->actingAs($context['reviewer'], 'sanctum')
            ->postJson($this->url($context), ['motivo' => 'Falta ajustar cláusulas.'])
            ->assertStatus(500);

        $this->assertUnchanged($context);
        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_motivo_is_required_and_must_contain_at_least_five_characters(): void
    {
        $context = $this->createConvention();
        $this->actingAs($context['reviewer'], 'sanctum');
        $url = $this->url($context);

        $this->postJson($url, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);
        $this->postJson($url, ['motivo' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);
        $this->postJson($url, ['motivo' => 'abcd'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);
        $this->postJson($url, ['motivo' => ['no es un texto']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);

        $this->assertUnchanged($context);
    }

    private function createConvention(
        string $state = 'Revisión',
        bool $withPending = true,
        bool $withResolved = false,
        bool $withManager = true
    ): array {
        $unit = $this->createUnit();
        $reviewer = $this->createUser('Revisión', $unit->id_unidad_academica);
        $manager = $withManager
            ? $this->createUser('Gestión', $unit->id_unidad_academica)
            : null;
        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio para devolver a corrección',
            'estado' => $state,
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);
        $context = compact('unit', 'reviewer', 'manager', 'instrumento', 'convenio');
        $context['pending'] = null;
        $context['resolved'] = null;

        if ($withPending) {
            $context['pending'] = $this->createObservation(
                $context,
                'Pendiente',
                'Revisar el plazo de vigencia.'
            );
        }

        if ($withResolved) {
            $context['resolved'] = $this->createObservation(
                $context,
                'Resuelta',
                'Observación resuelta previamente.'
            );
        }

        return $context;
    }

    private function createObservation(
        array $context,
        string $state,
        string $content
    ): Observacion {
        return Observacion::query()->create([
            'contenido' => $content,
            'fecha' => now(),
            'estado_tramite' => $state,
            'resolucion' => $state === 'Resuelta' ? 'Aceptada' : null,
            'respuesta' => $state === 'Resuelta'
                ? 'La observación fue atendida.'
                : null,
            'fecha_resolucion' => $state === 'Resuelta' ? now() : null,
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['reviewer']->id_usuario,
        ]);
    }

    private function createUnit(): UnidadAcademica
    {
        $institution = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number,
        ]);

        return UnidadAcademica::query()->create([
            'sigla' => 'DEV-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(
        string $roleName,
        int $unitId,
        bool $global = false,
        bool $active = true
    ): Usuario {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'devolucion-'.$this->number++.'@example.com',
            'nombre' => 'Usuario de devolución',
            'password' => Hash::make('password-segura'),
            'activo' => $active,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
            'alcance_global' => $global,
        ]);
    }

    private function url(array $context): string
    {
        return "/api/convenios/{$context['convenio']->id_instrumento}/devolver";
    }

    private function assertUnchanged(array $context): void
    {
        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => $context['instrumento']->estado,
        ]);
        $this->assertSame(0, HistorialEstado::query()->count());
        $this->assertSame(0, RegistroAuditoria::query()->count());
        $this->assertSame(0, Notificacion::query()->count());
    }
}
