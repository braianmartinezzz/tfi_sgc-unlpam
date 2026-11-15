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

class ArchivarConvenioTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/convenios/1/archivar')->assertUnauthorized();
    }

    public function test_archives_a_rechazado_convenio_and_registers_history_and_audit(): void
    {
        $ctx = $this->createContext('Rechazado');
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar", [
            'motivo' => 'Trámite cerrado por la unidad.',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Convenio archivado correctamente.')
            ->assertJsonPath('data.instrumento.estado', 'Archivado');

        $this->assertSame('Archivado', $ctx['convenio']->instrumento->fresh()->estado);

        $this->assertDatabaseHas('historial_estado', [
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'estado_anterior' => 'Rechazado',
            'estado_nuevo' => 'Archivado',
            'motivo' => 'Trámite cerrado por la unidad.',
            'id_usuario' => $ctx['reviewer']->id_usuario,
        ]);

        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'ARCHIVAR_CONVENIO',
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'id_usuario' => $ctx['reviewer']->id_usuario,
        ]);
    }

    public function test_archives_a_rescindido_convenio_without_motivo(): void
    {
        $ctx = $this->createContext('Rescindido');
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar")
            ->assertOk()
            ->assertJsonPath('data.instrumento.estado', 'Archivado');

        $this->assertDatabaseHas('historial_estado', [
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'estado_anterior' => 'Rescindido',
            'estado_nuevo' => 'Archivado',
            'motivo' => null,
        ]);
    }

    public function test_archives_a_firmado_convenio_whose_vigencia_already_expired(): void
    {
        $ctx = $this->createContext('Firmado', now()->subDays(5)->toDateString());
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar")
            ->assertOk()
            ->assertJsonPath('data.instrumento.estado', 'Archivado');

        $this->assertDatabaseHas('historial_estado', [
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'estado_anterior' => 'Firmado',
            'estado_nuevo' => 'Archivado',
        ]);
    }

    public function test_rejects_a_firmado_convenio_that_is_still_in_force_or_has_no_vencimiento(): void
    {
        $vigente = $this->createContext('Firmado', now()->addDays(30)->toDateString());
        $this->authenticate($vigente['reviewer']);

        $this->postJson("/api/convenios/{$vigente['convenio']->id_instrumento}/archivar")
            ->assertStatus(409);
        $this->assertSame('Firmado', $vigente['convenio']->instrumento->fresh()->estado);

        $sinVencimiento = $this->createContext('Firmado', null);
        $this->authenticate($sinVencimiento['reviewer']);

        $this->postJson("/api/convenios/{$sinVencimiento['convenio']->id_instrumento}/archivar")
            ->assertStatus(409);
        $this->assertSame('Firmado', $sinVencimiento['convenio']->instrumento->fresh()->estado);
    }

    public function test_rejects_convenios_in_states_that_cannot_be_archived(): void
    {
        foreach (['Borrador', 'Revisión', 'EnCorreccion', 'Aprobado'] as $estado) {
            $ctx = $this->createContext($estado);
            $this->authenticate($ctx['reviewer']);

            $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar")
                ->assertStatus(409);

            $this->assertSame($estado, $ctx['convenio']->instrumento->fresh()->estado);
        }

        $archivado = $this->createContext('Archivado');
        $this->authenticate($archivado['reviewer']);

        $this->postJson("/api/convenios/{$archivado['convenio']->id_instrumento}/archivar")
            ->assertStatus(409)
            ->assertJsonPath('message', 'El convenio ya se encuentra archivado.');
    }

    public function test_only_revision_and_administracion_can_archive(): void
    {
        $ctx = $this->createContext('Rechazado');
        $consulta = $this->createUser('Consulta', $ctx['unit']->id_unidad_academica);
        $admin = $this->createUser('Administración', $this->createUnit()->id_unidad_academica);

        foreach ([$ctx['manager'], $consulta] as $usuario) {
            $this->authenticate($usuario);

            $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar")
                ->assertForbidden();
        }

        $this->assertSame('Rechazado', $ctx['convenio']->instrumento->fresh()->estado);

        $this->authenticate($admin);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar")
            ->assertOk();
    }

    public function test_revision_is_limited_to_its_unit_unless_it_has_global_scope(): void
    {
        $ctx = $this->createContext('Rechazado');
        $otraUnidad = $this->createUnit();
        $revisorLocalAjeno = $this->createUser('Revisión', $otraUnidad->id_unidad_academica);
        $revisorGlobal = $this->createUser('Revisión', $otraUnidad->id_unidad_academica, true);

        $this->authenticate($revisorLocalAjeno);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar")
            ->assertForbidden();
        $this->assertSame('Rechazado', $ctx['convenio']->instrumento->fresh()->estado);

        $this->authenticate($revisorGlobal);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar")
            ->assertOk();
    }

    public function test_returns_not_found_and_validates_the_motivo(): void
    {
        $ctx = $this->createContext('Rechazado');
        $this->authenticate($ctx['reviewer']);

        $this->postJson('/api/convenios/999999/archivar')
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/archivar", [
            'motivo' => 'abc',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);

        $this->assertSame('Rechazado', $ctx['convenio']->instrumento->fresh()->estado);
    }

    private function createContext(string $estado, ?string $vencimiento = null): array
    {
        $unit = $this->createUnit();

        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio '.$this->number++,
            'estado' => $estado,
            'vencimiento' => $vencimiento,
        ]);

        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);

        return [
            'unit' => $unit,
            'convenio' => $convenio,
            'manager' => $this->createUser('Gestión', $unit->id_unidad_academica),
            'reviewer' => $this->createUser('Revisión', $unit->id_unidad_academica),
        ];
    }

    private function createUnit(): UnidadAcademica
    {
        $institution = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number++,
        ]);

        return UnidadAcademica::query()->create([
            'sigla' => 'ARC-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(
        string $roleName,
        int $unitId,
        bool $alcanceGlobal = false
    ): Usuario {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'archivar-'.$this->number++.'@example.com',
            'nombre' => 'Usuario '.$roleName.' '.$this->number,
            'password' => Hash::make('password-segura'),
            'activo' => true,
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
