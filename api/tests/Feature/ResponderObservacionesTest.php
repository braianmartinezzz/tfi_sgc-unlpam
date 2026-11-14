<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Observacion;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResponderObservacionesTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    public function test_listado_includes_pending_and_resolved_observations_in_chronological_order(): void
    {
        $context = $this->createReadyConvention();
        $latest = $this->createObservation(
            $context,
            'Pendiente',
            '2026-11-13 10:00:00'
        );
        $earliest = $this->createObservation(
            $context,
            'Resuelta',
            '2026-11-12 10:00:00'
        );
        $sameDate = $this->createObservation(
            $context,
            'Pendiente',
            '2026-11-12 10:00:00'
        );
        $this->authenticate($context['manager']);

        $response = $this->getJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/observaciones"
        );

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id_observacion', $earliest->id_observacion)
            ->assertJsonPath('data.0.estado_tramite', 'Resuelta')
            ->assertJsonPath('data.0.respuesta', 'Respuesta previa de Gestión.')
            ->assertJsonPath('data.0.usuario.id_usuario', $context['reviewer']->id_usuario)
            ->assertJsonPath('data.0.usuario.nombre', $context['reviewer']->nombre)
            ->assertJsonPath('data.1.id_observacion', $sameDate->id_observacion)
            ->assertJsonPath('data.2.id_observacion', $latest->id_observacion)
            ->assertJsonPath('data.2.estado_tramite', 'Pendiente');

        $this->assertSame('Pendiente', $latest->fresh()->estado_tramite);
        $this->assertSame('Resuelta', $earliest->fresh()->estado_tramite);
    }

    public function test_listado_returns_an_empty_collection_for_a_convenio_without_observations(): void
    {
        $context = $this->createReadyConvention();
        $this->authenticate($context['manager']);

        $this->getJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/observaciones"
        )
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_listado_returns_not_found_for_a_missing_convenio(): void
    {
        $context = $this->createReadyConvention();
        $this->authenticate($context['manager']);

        $this->getJson('/api/convenios/999999/observaciones')
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    public function test_listado_and_respuesta_require_authentication(): void
    {
        $context = $this->createReadyConvention();
        $observacion = $this->createObservation($context);
        $base = "/api/convenios/{$context['convenio']->id_instrumento}";

        $this->getJson("{$base}/observaciones")->assertUnauthorized();
        $this->postJson(
            "{$base}/observaciones/{$observacion->id_observacion}/responder",
            ['respuesta' => 'Se aplicaron los cambios solicitados.']
        )->assertUnauthorized();
    }

    public function test_gestion_from_another_unit_is_forbidden_for_both_operations(): void
    {
        $context = $this->createReadyConvention();
        $observacion = $this->createObservation($context);
        $otherUnit = $this->createUnit($this->createInstitution());
        $otherManager = $this->createUser(
            'Gestión',
            $otherUnit->id_unidad_academica
        );
        $base = "/api/convenios/{$context['convenio']->id_instrumento}";

        $this->authenticate($otherManager);
        $this->getJson("{$base}/observaciones")->assertForbidden();

        $this->authenticate($otherManager);
        $this->postJson(
            "{$base}/observaciones/{$observacion->id_observacion}/responder",
            ['respuesta' => 'Se aplicaron los cambios solicitados.']
        )->assertForbidden();
    }

    public function test_administracion_can_access_and_respond_across_units(): void
    {
        $context = $this->createReadyConvention();
        $observacion = $this->createObservation($context);
        $otherUnit = $this->createUnit($this->createInstitution());
        $administracion = $this->createUser(
            'Administración',
            $otherUnit->id_unidad_academica
        );
        $base = "/api/convenios/{$context['convenio']->id_instrumento}";

        $this->authenticate($administracion);
        $this->getJson("{$base}/observaciones")->assertOk();

        $this->authenticate($administracion);
        $this->postJson(
            "{$base}/observaciones/{$observacion->id_observacion}/responder",
            ['respuesta' => 'Se aplicaron los cambios solicitados.']
        )->assertOk();
    }

    public function test_responder_updates_only_the_pending_observation_and_registers_audit(): void
    {
        $context = $this->createReadyConvention('EnCorreccion');
        $observacion = $this->createObservation($context);
        $respuesta = rtrim(str_repeat('Se aplicaron los cambios solicitados. ', 30));
        $this->authenticate($context['manager']);

        $response = $this->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}"
                ."/observaciones/{$observacion->id_observacion}/responder",
            ['respuesta' => $respuesta]
        );

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Observación respondida correctamente.')
            ->assertJsonPath('data.id_observacion', $observacion->id_observacion)
            ->assertJsonPath('data.respuesta', $respuesta)
            ->assertJsonPath('data.estado_tramite', 'Resuelta')
            ->assertJsonMissingPath('data.usuario');

        $actualizada = $observacion->fresh();
        $this->assertSame($respuesta, $actualizada->respuesta);
        $this->assertSame('Resuelta', $actualizada->estado_tramite);
        $this->assertNotNull($actualizada->fecha_resolucion);
        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'RESPUESTA_OBSERVACION',
            'detalle' => 'Respuesta registrada para la observación #'
                .$observacion->id_observacion,
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['manager']->id_usuario,
        ]);
        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'EnCorreccion',
        ]);
    }

    public function test_respuesta_rejects_convenios_outside_en_correccion(): void
    {
        foreach ([
            'Borrador',
            'Revisión',
            'Aprobado',
            'Firmado',
            'Rescindido',
            'Archivado',
        ] as $state) {
            $context = $this->createReadyConvention($state);
            $observacion = $this->createObservation($context);
            $this->authenticate($context['manager']);

            $this->postJson(
                "/api/convenios/{$context['convenio']->id_instrumento}"
                    ."/observaciones/{$observacion->id_observacion}/responder",
                ['respuesta' => 'Se aplicaron los cambios solicitados.']
            )->assertStatus(409);

            $this->assertSame('Pendiente', $observacion->fresh()->estado_tramite);
            $this->assertSame(0, RegistroAuditoria::query()->count());
        }
    }

    public function test_responder_returns_not_found_for_missing_or_foreign_observations(): void
    {
        $context = $this->createReadyConvention('EnCorreccion');
        $otherContext = $this->createReadyConvention('EnCorreccion');
        $foreignObservation = $this->createObservation($otherContext);
        $this->authenticate($context['manager']);
        $base = "/api/convenios/{$context['convenio']->id_instrumento}";

        $this->postJson(
            "{$base}/observaciones/999999/responder",
            ['respuesta' => 'Se aplicaron los cambios solicitados.']
        )->assertNotFound();

        $this->postJson(
            "{$base}/observaciones/{$foreignObservation->id_observacion}/responder",
            ['respuesta' => 'Se aplicaron los cambios solicitados.']
        )->assertNotFound();

        $this->assertSame('Pendiente', $foreignObservation->fresh()->estado_tramite);
        $this->assertSame(0, RegistroAuditoria::query()->count());
    }

    public function test_responder_rejects_an_observation_that_is_already_resolved(): void
    {
        $context = $this->createReadyConvention('EnCorreccion');
        $observacion = $this->createObservation($context, 'Resuelta');
        $this->authenticate($context['manager']);

        $this->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}"
                ."/observaciones/{$observacion->id_observacion}/responder",
            ['respuesta' => 'Otra respuesta para la observación.']
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Solo se pueden responder observaciones pendientes.'
            );

        $this->assertSame('Respuesta previa de Gestión.', $observacion->fresh()->respuesta);
        $this->assertSame(0, RegistroAuditoria::query()->count());
    }

    public function test_respuesta_is_required_and_must_contain_at_least_five_characters(): void
    {
        $context = $this->createReadyConvention('EnCorreccion');
        $observacion = $this->createObservation($context);
        $this->authenticate($context['manager']);
        $url = "/api/convenios/{$context['convenio']->id_instrumento}"
            ."/observaciones/{$observacion->id_observacion}/responder";

        $this->postJson($url, [])->assertUnprocessable()
            ->assertJsonValidationErrors(['respuesta']);
        $this->postJson($url, ['respuesta' => 'abcd'])->assertUnprocessable()
            ->assertJsonValidationErrors(['respuesta']);

        $this->assertSame('Pendiente', $observacion->fresh()->estado_tramite);
        $this->assertSame(0, RegistroAuditoria::query()->count());
    }

    public function test_audit_failure_rolls_back_the_observation_response(): void
    {
        $context = $this->createReadyConvention('EnCorreccion');
        $observacion = $this->createObservation($context);
        $this->authenticate($context['manager']);
        DB::unprepared(
            'CREATE TRIGGER fail_observation_audit BEFORE INSERT ON registro_auditoria '
            ."WHEN NEW.accion = 'RESPUESTA_OBSERVACION' "
            ."BEGIN SELECT RAISE(FAIL, 'audit write failed'); END"
        );

        $this->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}"
                ."/observaciones/{$observacion->id_observacion}/responder",
            ['respuesta' => 'Se aplicaron los cambios solicitados.']
        )->assertStatus(500);

        $this->assertSame('Pendiente', $observacion->fresh()->estado_tramite);
        $this->assertNull($observacion->fresh()->respuesta);
        $this->assertNull($observacion->fresh()->fecha_resolucion);
        $this->assertSame(0, RegistroAuditoria::query()->count());
    }

    public function test_enviar_revision_is_blocked_by_pending_observations(): void
    {
        $context = $this->createReadyConvention('EnCorreccion');
        $this->createObservation($context, 'Pendiente');
        $this->authenticate($context['manager']);

        $this->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision"
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El convenio todavía tiene observaciones pendientes de respuesta.'
            );

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'EnCorreccion',
        ]);
        $this->assertSame(0, RegistroAuditoria::query()->count());
    }

    public function test_resolved_observations_do_not_block_resubmission_or_get_deleted(): void
    {
        $context = $this->createReadyConvention('EnCorreccion');
        $resolvedObservation = $this->createObservation($context, 'Resuelta');
        $this->authenticate($context['manager']);

        $this->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision"
        )->assertOk();

        $this->assertDatabaseHas('observacion', [
            'id_observacion' => $resolvedObservation->id_observacion,
            'estado_tramite' => 'Resuelta',
            'respuesta' => 'Respuesta previa de Gestión.',
        ]);
        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Revisión',
        ]);
    }

    private function createReadyConvention(string $state = 'EnCorreccion'): array
    {
        $institution = $this->createInstitution();
        $unit = $this->createUnit($institution);
        $manager = $this->createUser('Gestión', $unit->id_unidad_academica);
        $reviewer = $this->createUser('Revisión', $unit->id_unidad_academica);

        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio para observaciones',
            'estado' => $state,
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);
        $secondInstitution = $this->createInstitution();
        $convenio->institucionesFirmantes()->attach([
            $institution->id_institucion,
            $secondInstitution->id_institucion,
        ]);

        $documento = Documento::query()->create([
            'tipo' => 'Anexo',
            'titulo' => 'Documento de prueba',
            'id_instrumento' => $instrumento->id_instrumento,
        ]);
        Version::query()->create([
            'numero' => 1,
            'nombre_archivo' => 'convenio.pdf',
            'tamanio_bytes' => 10,
            'hash_sha256' => str_repeat('b', 64),
            'ruta_interna' => 'documentos/convenio.pdf',
            'fecha_carga' => now(),
            'id_documento' => $documento->id_documento,
            'id_usuario' => $manager->id_usuario,
        ]);

        return [
            'instrumento' => $instrumento,
            'convenio' => $convenio,
            'manager' => $manager,
            'reviewer' => $reviewer,
        ];
    }

    private function createObservation(
        array $context,
        string $state = 'Pendiente',
        string $date = '2026-11-13 10:00:00'
    ): Observacion {
        return Observacion::query()->create([
            'contenido' => 'Actualizar la cláusula de vigencia.',
            'fecha' => $date,
            'estado_tramite' => $state,
            'resolucion' => $state === 'Resuelta' ? 'Aceptada' : null,
            'respuesta' => $state === 'Resuelta'
                ? 'Respuesta previa de Gestión.'
                : null,
            'fecha_resolucion' => $state === 'Resuelta'
                ? '2026-11-14 09:00:00'
                : null,
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['reviewer']->id_usuario,
        ]);
    }

    private function createInstitution(): InstitucionFirmante
    {
        return InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number++,
        ]);
    }

    private function createUnit(InstitucionFirmante $institution): UnidadAcademica
    {
        return UnidadAcademica::query()->create([
            'sigla' => 'OBS-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(string $roleName, int $unitId): Usuario
    {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'observaciones-'.$this->number++.'@example.com',
            'nombre' => 'Usuario de observaciones',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
        ]);
    }

    private function authenticate(Usuario $usuario): void
    {
        Auth::forgetGuards();
        $this->withToken($usuario->createToken('test')->plainTextToken);
    }
}
