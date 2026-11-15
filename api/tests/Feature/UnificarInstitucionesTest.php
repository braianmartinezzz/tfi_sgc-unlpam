<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnificarInstitucionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/instituciones-firmantes/unificar', [
            'id_principal' => 1,
            'ids_duplicadas' => [2],
        ])->assertUnauthorized();
    }

    public function test_unifies_duplicated_instituciones_moving_everything_to_the_principal(): void
    {
        $ctx = $this->authenticate();
        $datos = $this->crearEscenarioDuplicado($ctx['unidad']);

        $this->postJson('/api/instituciones-firmantes/unificar', [
            'id_principal' => $datos['principal'],
            'ids_duplicadas' => [$datos['dup1'], $datos['dup2']],
        ])
            ->assertOk()
            ->assertJsonPath('data.principal.id_institucion', $datos['principal'])
            ->assertJsonPath('data.eliminadas', [$datos['dup1'], $datos['dup2']])
            ->assertJsonPath('data.resumen.unidades_academicas', 2)
            ->assertJsonPath('data.resumen.representantes', 1)
            ->assertJsonPath('data.resumen.convenios_reasignados', 2)
            ->assertJsonPath('data.resumen.convenios_ya_vinculados', 1)
            ->assertJsonPath('data.datos_completados', ['cuit', 'domicilio_legal']);

        // Las duplicadas ya no existen.
        $this->assertDatabaseMissing('institucion_firmante', ['id_institucion' => $datos['dup1']]);
        $this->assertDatabaseMissing('institucion_firmante', ['id_institucion' => $datos['dup2']]);

        // Unidades y representante pasaron a la principal.
        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $datos['unidadA'],
            'id_institucion' => $datos['principal'],
        ]);
        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $datos['unidadB'],
            'id_institucion' => $datos['principal'],
        ]);
        $this->assertDatabaseHas('representante', [
            'id_persona' => $datos['persona'],
            'id_institucion' => $datos['principal'],
        ]);

        // Convenios: ninguno queda con una duplicada y el repetido no duplica el vínculo.
        $this->assertDatabaseMissing('convenio_institucion', ['id_institucion' => $datos['dup1']]);
        $this->assertDatabaseMissing('convenio_institucion', ['id_institucion' => $datos['dup2']]);

        foreach (['c1', 'c2', 'c3'] as $clave) {
            $this->assertSame(
                1,
                DB::table('convenio_institucion')
                    ->where('id_instrumento', $datos[$clave])
                    ->where('id_institucion', $datos['principal'])
                    ->count()
            );
        }
    }

    public function test_completes_empty_data_of_the_principal_without_overwriting_existing_data(): void
    {
        $ctx = $this->authenticate();
        $datos = $this->crearEscenarioDuplicado($ctx['unidad']);

        $this->postJson('/api/instituciones-firmantes/unificar', [
            'id_principal' => $datos['principal'],
            'ids_duplicadas' => [$datos['dup1']],
        ])->assertOk();

        $this->assertDatabaseHas('institucion_firmante', [
            'id_institucion' => $datos['principal'],
            'razon_social' => 'Universidad Nacional X',
            'cuit' => '30-11111111-1',
            'domicilio_legal' => 'Calle Falsa 123',
            'tipo_institucion' => 'Universidad',
        ]);
    }

    public function test_registers_the_unification_in_the_audit_log(): void
    {
        $ctx = $this->authenticate();
        $datos = $this->crearEscenarioDuplicado($ctx['unidad']);

        $this->postJson('/api/instituciones-firmantes/unificar', [
            'id_principal' => $datos['principal'],
            'ids_duplicadas' => [$datos['dup1']],
        ])->assertOk();

        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'Unificación de instituciones',
            'id_usuario' => $ctx['admin']->id_usuario,
        ]);

        $this->getJson('/api/auditoria?'.http_build_query(['buscar' => 'Univ. Nac. X']))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_rejects_invalid_requests(): void
    {
        $ctx = $this->authenticate();
        $datos = $this->crearEscenarioDuplicado($ctx['unidad']);

        $this->postJson('/api/instituciones-firmantes/unificar', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_principal', 'ids_duplicadas']);

        $this->postJson('/api/instituciones-firmantes/unificar', [
            'id_principal' => $datos['principal'],
            'ids_duplicadas' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['ids_duplicadas']);

        // La principal no puede estar entre las duplicadas.
        $this->postJson('/api/instituciones-firmantes/unificar', [
            'id_principal' => $datos['principal'],
            'ids_duplicadas' => [$datos['principal']],
        ])->assertStatus(422)->assertJsonValidationErrors(['ids_duplicadas.0']);

        // Ids repetidos o inexistentes.
        $this->postJson('/api/instituciones-firmantes/unificar', [
            'id_principal' => $datos['principal'],
            'ids_duplicadas' => [$datos['dup1'], $datos['dup1']],
        ])->assertStatus(422)->assertJsonValidationErrors(['ids_duplicadas.0']);

        $this->postJson('/api/instituciones-firmantes/unificar', [
            'id_principal' => $datos['principal'],
            'ids_duplicadas' => [99999],
        ])->assertStatus(422)->assertJsonValidationErrors(['ids_duplicadas.0']);

        // Nada se tocó.
        $this->assertDatabaseHas('institucion_firmante', ['id_institucion' => $datos['dup1']]);
        $this->assertDatabaseHas('institucion_firmante', ['id_institucion' => $datos['dup2']]);
    }

    /**
     * Principal (con tipo cargado, sin cuit ni domicilio) y dos duplicadas:
     *  - dup1: tiene cuit, domicilio y un tipo distinto; una unidad (A), un representante y los convenios c1 y c2.
     *  - dup2: una unidad (B) y el convenio c3.
     * La principal ya está vinculada al convenio c2 (el que comparte con dup1).
     */
    private function crearEscenarioDuplicado(int $unidadBase): array
    {
        $principal = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Universidad Nacional X',
            'tipo_institucion' => 'Universidad',
        ]);
        $dup1 = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Univ. Nac. X',
            'cuit' => '30-11111111-1',
            'domicilio_legal' => 'Calle Falsa 123',
            'tipo_institucion' => 'Otro tipo',
        ]);
        $dup2 = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'U.N. X',
        ]);

        $unidadA = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-A',
            'nombre' => 'Unidad A',
            'id_institucion' => $dup1,
        ]);
        $unidadB = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-B',
            'nombre' => 'Unidad B',
            'id_institucion' => $dup2,
        ]);

        $persona = DB::table('persona')->insertGetId([
            'nombre' => 'Rita',
            'apellido' => 'Rectora',
            'dni' => '20123456',
        ]);
        DB::table('representante')->insert([
            'id_persona' => $persona,
            'cargo' => 'Rectora',
            'id_institucion' => $dup1,
        ]);

        $c1 = $this->crearConvenio('Convenio 1', $unidadBase);
        $c2 = $this->crearConvenio('Convenio 2', $unidadBase);
        $c3 = $this->crearConvenio('Convenio 3', $unidadBase);

        DB::table('convenio_institucion')->insert([
            ['id_instrumento' => $c1, 'id_institucion' => $dup1],
            ['id_instrumento' => $c2, 'id_institucion' => $dup1],
            ['id_instrumento' => $c2, 'id_institucion' => $principal],
            ['id_instrumento' => $c3, 'id_institucion' => $dup2],
        ]);

        return compact('principal', 'dup1', 'dup2', 'unidadA', 'unidadB', 'persona', 'c1', 'c2', 'c3');
    }

    private function crearConvenio(string $titulo, int $unidadId): int
    {
        $instrumento = Instrumento::query()->create([
            'titulo' => $titulo,
            'estado' => 'Borrador',
        ]);

        Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unidadId,
        ]);

        Marco::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
        ]);

        return $instrumento->id_instrumento;
    }

    private function authenticate(): array
    {
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Administración']);
        $institucionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución base',
        ]);
        $unidadId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-BASE',
            'nombre' => 'Unidad base',
            'id_institucion' => $institucionId,
        ]);

        $admin = Usuario::query()->create([
            'mail' => 'admin-unificar@example.com',
            'nombre' => 'Administrador de unificación',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unidadId,
        ]);

        $this->withToken($admin->createToken('unificar-test')->plainTextToken);

        return ['admin' => $admin, 'unidad' => $unidadId];
    }
}
