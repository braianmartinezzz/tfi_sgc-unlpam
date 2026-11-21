<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Marco;
use App\Models\Instrumento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VencimientoReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/convenios/vencimientos')->assertUnauthorized();
    }

    public function test_lists_only_convenios_expiring_within_window_ordered_by_date(): void
    {
        $ctx = $this->authenticate();

        $this->createConvenio('Vence en 40 días', $ctx, now()->addDays(40), 10);
        $this->createConvenio('Vence en 5 días', $ctx, now()->addDays(5), 10);
        $this->createConvenio('Vence en 200 días', $ctx, now()->addDays(200), 10);
        $this->createConvenio('Ya venció', $ctx, now()->subDays(3), 10);
        $this->createConvenio('Sin vencimiento', $ctx, null, null);

        $response = $this->getJson('/api/convenios/vencimientos?dias=90');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.titulo', 'Vence en 5 días')
            ->assertJsonPath('data.0.dias_restantes', 5)
            ->assertJsonPath('data.1.titulo', 'Vence en 40 días')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_flags_convenios_inside_preaviso_window(): void
    {
        $ctx = $this->authenticate();

        $this->createConvenio('En preaviso', $ctx, now()->addDays(10), 30);
        $this->createConvenio('Fuera de preaviso', $ctx, now()->addDays(60), 30);

        $this->getJson('/api/convenios/vencimientos?dias=90')
            ->assertOk()
            ->assertJsonPath('data.0.en_preaviso', true)
            ->assertJsonPath('data.1.en_preaviso', false);
    }

    public function test_rejects_invalid_dias(): void
    {
        $this->authenticate();

        $this->getJson('/api/convenios/vencimientos?dias=0')->assertStatus(422);
    }

    private function createConvenio(string $titulo, array $ctx, $vencimiento, ?int $preaviso): void
    {
        $instrumento = Instrumento::query()->create([
            'titulo' => $titulo,
            'estado' => 'Firmado',
            'vencimiento' => $vencimiento?->toDateString(),
            'dias_preaviso' => $preaviso,
        ]);

        Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $ctx['unidad'],
        ]);

        Marco::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
        ]);
    }

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
}