<?php

namespace Tests\Feature;

use App\Models\Especifico;
use App\Models\Instrumento;
use App\Models\Usuario;
use Database\Seeders\ConvenioDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConvenioDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_repeatable_demo_convenios_and_relations(): void
    {
        $this->seed(ConvenioDemoSeeder::class);
        $this->seed(ConvenioDemoSeeder::class);

        $this->assertDatabaseCount('instrumento', 20);
        $this->assertDatabaseCount('convenio', 20);
        $this->assertDatabaseCount('marco', 9);
        $this->assertDatabaseCount('especifico', 11);
        $this->assertDatabaseCount('usuario', 6);
        $this->assertDatabaseCount('institucion_firmante', 6);
        $this->assertDatabaseCount('historial_estado', 66);
        $this->assertSame(20, Instrumento::query()->distinct()->count('titulo'));
        $this->assertSame(2, DB::table('convenio')->where('confidencialidad', true)->count());

        $conteosPorEstado = Instrumento::query()
            ->selectRaw('estado, COUNT(*) as cantidad')
            ->groupBy('estado')
            ->pluck('cantidad', 'estado')
            ->map(fn ($cantidad): int => (int) $cantidad)
            ->all();

        $conteosEsperados = [
            'Borrador' => 3,
            'EnCorreccion' => 2,
            'Revisión' => 2,
            'Aprobado' => 2,
            'Firmado' => 6,
            'Rescindido' => 2,
            'Rechazado' => 1,
            'Archivado' => 2,
        ];
        ksort($conteosEsperados);
        ksort($conteosPorEstado);

        $this->assertSame($conteosEsperados, $conteosPorEstado);

        $this->assertFalse(
            DB::table('convenio_institucion')
                ->select('id_instrumento')
                ->groupBy('id_instrumento')
                ->havingRaw('COUNT(*) < 2')
                ->exists()
        );

        foreach (Especifico::query()->with(['convenio', 'marco.convenio'])->get() as $especifico) {
            $this->assertSame(
                $especifico->convenio->id_unidad_academica,
                $especifico->marco->convenio->id_unidad_academica
            );
        }
    }

    public function test_index_returns_the_expected_total_for_each_demo_role_and_scope(): void
    {
        $this->seed(ConvenioDemoSeeder::class);

        $totalesEsperados = [
            'admin@unlpam.edu.ar' => 20,
            'gestion@unlpam.edu.ar' => 10,
            'revision.global@unlpam.edu.ar' => 11,
            'revision.unidad@unlpam.edu.ar' => 6,
            'consulta.global@unlpam.edu.ar' => 10,
            'consulta.unidad@unlpam.edu.ar' => 4,
        ];

        foreach ($totalesEsperados as $mail => $totalEsperado) {
            Sanctum::actingAs(
                Usuario::query()->where('mail', $mail)->firstOrFail()
            );

            $this->getJson('/api/convenios')
                ->assertOk()
                ->assertJsonPath('total', $totalEsperado);
        }
    }
}
