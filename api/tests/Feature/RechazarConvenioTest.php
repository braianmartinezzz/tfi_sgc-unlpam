<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RechazarConvenioTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/convenios/1/rechazar', ['motivo' => 'Motivo válido'])
            ->assertUnauthorized();
    }

    public function test_rejects_a_convenio_in_revision_and_registers_history_and_audit(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar", [
            'motivo' => 'No cumple con los requisitos legales.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Convenio rechazado correctamente.')
            ->assertJsonPath('data.instrumento.estado', 'Rechazado');

        $this->assertSame('Rechazado', $ctx['convenio']->instrumento->fresh()->estado);

        $this->assertDatabaseHas('historial_estado', [
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'estado_anterior' => 'Revisión',
            'estado_nuevo' => 'Rechazado',
            'motivo' => 'No cumple con los requisitos legales.',
            'id_usuario' => $ctx['reviewer']->id_usuario,
        ]);

        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'RECHAZO_DE_CONVENIO',
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'id_usuario' => $ctx['reviewer']->id_usuario,
        ]);
    }

    public function test_notifies_only_active_gestion_users_of_the_convenio_unit(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar", [
            'motivo' => 'No cumple con los requisitos legales.',
        ])->assertOk();

        foreach ([$ctx['manager'], $ctx['secondManager']] as $gestor) {
            $this->assertDatabaseHas('notificacion', [
                'tipo' => 'CONVENIO_RECHAZADO',
                'id_usuario' => $gestor->id_usuario,
                'id_instrumento' => $ctx['convenio']->id_instrumento,
                'mensaje' => 'El convenio #'.$ctx['convenio']->id_instrumento
                    .' fue rechazado. Motivo: No cumple con los requisitos legales.',
            ]);
        }

        $this->assertDatabaseMissing('notificacion', ['id_usuario' => $ctx['inactiveManager']->id_usuario]);
        $this->assertDatabaseMissing('notificacion', ['id_usuario' => $ctx['otherUnitManager']->id_usuario]);
        $this->assertDatabaseMissing('notificacion', ['id_usuario' => $ctx['reviewer']->id_usuario]);
        $this->assertDatabaseCount('notificacion', 2);
    }

    public function test_requires_a_motivo(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);
        $url = "/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar";

        $this->postJson($url)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->postJson($url, ['motivo' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->assertSame('Revisión', $ctx['convenio']->instrumento->fresh()->estado);
    }

    public function test_only_convenios_in_revision_can_be_rejected(): void
    {
        foreach (['Borrador', 'EnCorreccion', 'Aprobado', 'Firmado', 'Rechazado', 'Archivado'] as $estado) {
            $ctx = $this->createContext($estado);
            $this->authenticate($ctx['reviewer']);

            $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar", [
                'motivo' => 'Motivo válido',
            ])
                ->assertStatus(409)
                ->assertJsonPath('message', 'El convenio no se encuentra en estado Revisión.');

            $this->assertSame($estado, $ctx['convenio']->instrumento->fresh()->estado);
        }
    }

    public function test_rejection_is_final(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);
        $id = $ctx['convenio']->id_instrumento;

        $this->postJson("/api/convenios/{$id}/rechazar", ['motivo' => 'Motivo válido'])->assertOk();

        $this->postJson("/api/convenios/{$id}/devolver", ['motivo' => 'Motivo válido'])->assertStatus(409);
        $this->postJson("/api/convenios/{$id}/rechazar", ['motivo' => 'Motivo válido'])->assertStatus(409);

        $this->assertSame('Rechazado', $ctx['convenio']->instrumento->fresh()->estado);
    }

    public function test_fails_without_changes_when_there_are_no_gestion_users_to_notify(): void
    {
        $ctx = $this->createContext('Revisión', false);
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar", [
            'motivo' => 'Motivo válido',
        ])->assertStatus(409);

        $this->assertSame('Revisión', $ctx['convenio']->instrumento->fresh()->estado);
        $this->assertDatabaseCount('historial_estado', 0);
        $this->assertDatabaseCount('notificacion', 0);
    }

    public function test_only_revision_and_administracion_can_reject(): void
    {
        $ctx = $this->createContext();
        $consulta = $this->createUser('Consulta', $ctx['unit']->id_unidad_academica);
        $admin = $this->createUser('Administración', $this->createUnit()->id_unidad_academica);

        foreach ([$ctx['manager'], $consulta] as $usuario) {
            $this->authenticate($usuario);

            $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar", [
                'motivo' => 'Motivo válido',
            ])->assertForbidden();
        }

        $this->assertSame('Revisión', $ctx['convenio']->instrumento->fresh()->estado);

        $this->authenticate($admin);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar", [
            'motivo' => 'Motivo válido',
        ])->assertOk();
    }

    public function test_revision_is_limited_to_its_unit_unless_it_has_global_scope(): void
    {
        $ctx = $this->createContext();
        $otraUnidad = $this->createUnit();
        $revisorAjeno = $this->createUser('Revisión', $otraUnidad->id_unidad_academica);
        $revisorGlobal = $this->createUser('Revisión', $otraUnidad->id_unidad_academica, true);

        $this->authenticate($revisorAjeno);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar", [
            'motivo' => 'Motivo válido',
        ])->assertForbidden();
        $this->assertSame('Revisión', $ctx['convenio']->instrumento->fresh()->estado);

        $this->authenticate($revisorGlobal);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/rechazar", [
            'motivo' => 'Motivo válido',
        ])->assertOk();
    }

    public function test_returns_not_found_for_a_missing_convenio(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);

        $this->postJson('/api/convenios/999999/rechazar', ['motivo' => 'Motivo válido'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    private function createContext(string $estado = 'Revisión', bool $withManagers = true): array
    {
        $unit = $this->createUnit();
        $otherUnit = $this->createUnit();

        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio '.$this->number++,
            'estado' => $estado,
        ]);

        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);

        $context = [
            'unit' => $unit,
            'convenio' => $convenio,
            'reviewer' => $this->createUser('Revisión', $unit->id_unidad_academica),
            'otherUnitManager' => $this->createUser('Gestión', $otherUnit->id_unidad_academica),
        ];

        if ($withManagers) {
            $context['manager'] = $this->createUser('Gestión', $unit->id_unidad_academica);
            $context['secondManager'] = $this->createUser('Gestión', $unit->id_unidad_academica);
            $context['inactiveManager'] = $this->createUser('Gestión', $unit->id_unidad_academica, false, false);
        }

        return $context;
    }

    private function createUnit(): UnidadAcademica
    {
        $institution = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number++,
        ]);

        return UnidadAcademica::query()->create([
            'sigla' => 'RCH-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(
        string $roleName,
        int $unitId,
        bool $alcanceGlobal = false,
        bool $activo = true
    ): Usuario {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'rechazar-'.$this->number++.'@example.com',
            'nombre' => 'Usuario '.$roleName.' '.$this->number,
            'password' => Hash::make('password-segura'),
            'activo' => $activo,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
            'alcance_global' => $alcanceGlobal,
        ]);
    }

    private function authenticate(Usuario $usuario): void
    {
        Auth::forgetGuards();
        $this->withToken($usuario->createToken('test')->plainTextToken);
    }
}
