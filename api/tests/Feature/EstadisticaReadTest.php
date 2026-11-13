<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EstadisticaReadTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): array
    {
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Consulta']);
        $institucionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unidadId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-VENC-01',
            'nombre' => 'Unidad de vencimientos',
            'id_institucion' => $institucionId,
        ]);
        $usuario = Usuario::query()->create([
            'mail' => 'vencimientos@example.com',
            'nombre' => 'Usuario de vencimientos',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unidadId,
        ]);

        $this->withToken($usuario->createToken('venc-test')->plainTextToken);

        return ['unidad' => $unidadId];
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/estadisticas')->assertStatus(401);
    }

    public function test_devuelve_estructura_del_panel(): void
    {
        $this->authenticate();

        $this->getJson('/api/estadisticas')
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => [
                'totales' => ['convenios', 'marco', 'especificos'],
                'por_estado', 'por_tipo', 'por_unidad_academica',
                'vencimientos' => ['dias', 'vencidos', 'proximos', 'en_preaviso'],
            ]]);
    }

    public function test_valida_parametro_dias(): void
    {
        $this->authenticate();

        $this->getJson('/api/estadisticas?dias=999')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Los datos enviados no son válidos.');
    }
}