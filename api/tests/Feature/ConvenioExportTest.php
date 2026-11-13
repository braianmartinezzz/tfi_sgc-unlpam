<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConvenioExportTest extends TestCase
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
        $this->getJson('/api/convenios/exportar')->assertStatus(401);
    }

    public function test_devuelve_csv_con_bom_y_encabezados(): void
    {
        $this->authenticate();

        $response = $this->get('/api/convenios/exportar');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $contenido = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('ID;Título;"Tipo de convenio"', $contenido);
    }

    public function test_valida_filtros(): void
    {
        $this->authenticate();

        $this->getJson('/api/convenios/exportar?id_unidad_academica=99999')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Los datos enviados no son válidos.');
    }
}