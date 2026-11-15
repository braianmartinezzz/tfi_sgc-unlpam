<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UmbralesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/configuracion/umbrales')->assertUnauthorized();
        $this->putJson('/api/configuracion/umbrales', ['dias_preaviso_defecto' => 10])->assertUnauthorized();
    }

    public function test_returns_default_values_when_nothing_was_configured(): void
    {
        $this->authenticate();

        $this->getJson('/api/configuracion/umbrales')
            ->assertOk()
            ->assertJsonPath('data.dias_preaviso_defecto', 30)
            ->assertJsonPath('data.dias_ventana_vencimientos', 90);
    }

    public function test_updates_both_umbrales_persists_them_and_registers_the_change(): void
    {
        $admin = $this->authenticate();

        $this->putJson('/api/configuracion/umbrales', [
            'dias_preaviso_defecto' => 45,
            'dias_ventana_vencimientos' => 120,
        ])
            ->assertOk()
            ->assertJsonPath('data.dias_preaviso_defecto', 45)
            ->assertJsonPath('data.dias_ventana_vencimientos', 120);

        $this->getJson('/api/configuracion/umbrales')
            ->assertOk()
            ->assertJsonPath('data.dias_preaviso_defecto', 45)
            ->assertJsonPath('data.dias_ventana_vencimientos', 120);

        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'Configuración de umbrales',
            'id_usuario' => $admin->id_usuario,
        ]);
    }

    public function test_updates_only_the_umbral_that_was_sent(): void
    {
        $this->authenticate();

        $this->putJson('/api/configuracion/umbrales', ['dias_ventana_vencimientos' => 150])
            ->assertOk()
            ->assertJsonPath('data.dias_preaviso_defecto', 30)
            ->assertJsonPath('data.dias_ventana_vencimientos', 150);
    }

    public function test_rejects_invalid_values_and_keeps_the_previous_configuration(): void
    {
        $this->authenticate();

        $this->putJson('/api/configuracion/umbrales', [])->assertStatus(422);

        $this->putJson('/api/configuracion/umbrales', ['dias_preaviso_defecto' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dias_preaviso_defecto']);

        $this->putJson('/api/configuracion/umbrales', ['dias_ventana_vencimientos' => 400])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dias_ventana_vencimientos']);

        // El preaviso por defecto (100) no puede superar la ventana vigente (90).
        $this->putJson('/api/configuracion/umbrales', ['dias_preaviso_defecto' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dias_preaviso_defecto']);

        $this->getJson('/api/configuracion/umbrales')
            ->assertOk()
            ->assertJsonPath('data.dias_preaviso_defecto', 30)
            ->assertJsonPath('data.dias_ventana_vencimientos', 90);
    }

    public function test_vencimientos_uses_the_configured_window_when_dias_is_not_sent(): void
    {
        $this->authenticate();

        $this->putJson('/api/configuracion/umbrales', ['dias_ventana_vencimientos' => 40])->assertOk();

        $this->getJson('/api/convenios/vencimientos')
            ->assertOk()
            ->assertJsonPath('meta.dias', 40);

        $this->getJson('/api/convenios/vencimientos?dias=15')
            ->assertOk()
            ->assertJsonPath('meta.dias', 15);
    }

    private function authenticate(): Usuario
    {
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Administración']);
        $institucionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unidadId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-CFG',
            'nombre' => 'Unidad de configuración',
            'id_institucion' => $institucionId,
        ]);

        $admin = Usuario::query()->create([
            'mail' => 'admin-config@example.com',
            'nombre' => 'Administrador de configuración',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unidadId,
        ]);

        $this->withToken($admin->createToken('config-test')->plainTextToken);

        return $admin;
    }
}
