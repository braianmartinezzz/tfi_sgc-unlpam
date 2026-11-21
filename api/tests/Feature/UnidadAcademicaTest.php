<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnidadAcademicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/unidades-academicas')->assertUnauthorized();
    }

    public function test_lists_ordered_by_name_and_filters_by_institucion(): void
    {
        $ctx = $this->authenticate();
        $otraInstitucion = $this->crearInstitucion('Otra institución');
        $this->crearUnidad('ZZZ', 'Zeta', $ctx['institucion']);
        $this->crearUnidad('AAA', 'Alfa', $ctx['institucion']);
        $this->crearUnidad('OTR', 'Otra', $otraInstitucion);

        $this->getJson('/api/unidades-academicas')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.nombre', 'Alfa')
            ->assertJsonPath('data.3.nombre', 'Zeta');

        $this->getJson('/api/unidades-academicas?id_institucion='.$otraInstitucion)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sigla', 'OTR');
    }

    public function test_shows_one_unidad_or_returns_404(): void
    {
        $ctx = $this->authenticate();

        $this->getJson('/api/unidades-academicas/'.$ctx['unidad'])
            ->assertOk()
            ->assertJsonPath('data.sigla', 'UA-USR');

        $this->getJson('/api/unidades-academicas/99999')->assertNotFound();
    }

    public function test_creates_a_unidad(): void
    {
        $ctx = $this->authenticate();

        $this->postJson('/api/unidades-academicas', [
            'sigla' => 'NUEVA',
            'nombre' => 'Unidad nueva',
            'id_institucion' => $ctx['institucion'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.sigla', 'NUEVA');

        $this->assertDatabaseHas('unidad_academica', ['sigla' => 'NUEVA']);
    }

    public function test_rejects_duplicated_sigla_and_unknown_institucion(): void
    {
        $this->authenticate();

        $this->postJson('/api/unidades-academicas', [
            'sigla' => 'UA-USR',
            'nombre' => 'Repetida',
            'id_institucion' => 99999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sigla', 'id_institucion']);
    }

    public function test_updates_a_unidad_keeping_its_own_sigla(): void
    {
        $ctx = $this->authenticate();

        $this->putJson('/api/unidades-academicas/'.$ctx['unidad'], [
            'sigla' => 'UA-USR',
            'nombre' => 'Nombre actualizado',
            'activo' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre actualizado')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $ctx['unidad'],
            'nombre' => 'Nombre actualizado',
            'activo' => true,
        ]);
    }

    public function test_soft_deletes_a_unidad_and_revokes_tokens_without_reactivating_users(): void
    {
        $ctx = $this->authenticate();
        $otra = $this->crearUnidad('LIB', 'Libre', $ctx['institucion']);
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Gestión']);
        $usuario = Usuario::query()->create([
            'mail' => 'gestion-cascade@example.com',
            'nombre' => 'Usuario de gestión',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $otra,
        ]);
        $usuario->createToken('unidad-token');

        $this->patchJson('/api/unidades-academicas/'.$otra.'/estado', ['activo' => false])
            ->assertOk()
            ->assertJsonPath('data.activo', false)
            ->assertJsonPath('resumen.usuarios_desactivados', 1);

        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $otra,
            'activo' => false,
        ]);
        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $usuario->id_usuario,
            'activo' => false,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $usuario->id_usuario,
            'tokenable_type' => Usuario::class,
        ]);

        $this->patchJson('/api/unidades-academicas/'.$otra.'/estado', ['activo' => true])
            ->assertOk()
            ->assertJsonPath('data.activo', true);
        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $usuario->id_usuario,
            'activo' => false,
        ]);
    }

    public function test_unit_cannot_be_reactivated_or_created_under_an_inactive_institution(): void
    {
        $ctx = $this->authenticate();
        $otraInstitucion = $this->crearInstitucion('Institución a desactivar');
        $unidad = $this->crearUnidad('UA-BAJA', 'Unidad desactivada', $otraInstitucion);

        $this->patchJson('/api/instituciones-firmantes/'.$otraInstitucion.'/estado', [
            'activo' => false,
        ])->assertOk();

        $this->patchJson('/api/unidades-academicas/'.$unidad.'/estado', ['activo' => true])
            ->assertStatus(409);

        $this->postJson('/api/unidades-academicas', [
            'sigla' => 'UA-NUEVA',
            'nombre' => 'Unidad bajo institución inactiva',
            'id_institucion' => $otraInstitucion,
        ])->assertStatus(409);

        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $unidad,
            'activo' => false,
        ]);
        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $ctx['unidad'],
            'activo' => true,
        ]);
    }

    public function test_name_is_unique_within_institution_and_active_filter_is_supported(): void
    {
        $ctx = $this->authenticate();
        $this->crearUnidad('UA-NOMBRE-1', 'Nombre repetido', $ctx['institucion']);
        $otraInstitucion = $this->crearInstitucion('Otra institución');

        $this->postJson('/api/unidades-academicas', [
            'sigla' => 'UA-NOMBRE-2',
            'nombre' => 'Nombre repetido',
            'id_institucion' => $ctx['institucion'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);

        $unidadParaEditar = $this->crearUnidad(
            'UA-NOMBRE-3',
            'Nombre distinto',
            $ctx['institucion']
        );
        $this->putJson('/api/unidades-academicas/'.$unidadParaEditar, [
            'nombre' => 'Nombre repetido',
        ])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);

        $unicaEnOtra = $this->crearUnidad('UA-OTRA', 'Nombre repetido', $otraInstitucion);

        $this->getJson('/api/unidades-academicas?activo=true')
            ->assertOk()
            ->assertJsonFragment(['id_unidad_academica' => $unicaEnOtra]);

        DB::table('unidad_academica')
            ->where('id_unidad_academica', $unicaEnOtra)
            ->update(['activo' => false]);

        $this->getJson('/api/unidades-academicas?activo=false')
            ->assertOk()
            ->assertJsonFragment(['id_unidad_academica' => $unicaEnOtra]);

        $this->deleteJson('/api/unidades-academicas/'.$unicaEnOtra)->assertMethodNotAllowed();
    }

    public function test_management_can_edit_only_its_unit_and_read_only_roles_cannot_write(): void
    {
        $ctx = $this->authenticate();
        $otraUnidad = $this->crearUnidad('UA-AJENA', 'Unidad ajena', $ctx['institucion']);
        $otraInstitucion = $this->crearInstitucion('Institución alternativa');
        $rolGestion = DB::table('rol')->insertGetId(['nombre' => 'Gestión']);
        $gestion = Usuario::query()->create([
            'mail' => 'gestion-unidad@example.com',
            'nombre' => 'Gestión de unidad',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolGestion,
            'id_unidad' => $ctx['unidad'],
        ]);
        Sanctum::actingAs($gestion);

        $this->getJson('/api/unidades-academicas')->assertOk();
        $this->putJson('/api/unidades-academicas/'.$ctx['unidad'], [
            'nombre' => 'Unidad editada por Gestión',
        ])->assertOk();
        $this->putJson('/api/unidades-academicas/'.$otraUnidad, [
            'nombre' => 'Edición no autorizada',
        ])->assertForbidden();
        $this->putJson('/api/unidades-academicas/'.$ctx['unidad'], [
            'id_institucion' => $otraInstitucion,
        ])->assertForbidden();
        $this->postJson('/api/unidades-academicas', [
            'sigla' => 'UA-NO',
            'nombre' => 'No autorizada',
            'id_institucion' => $ctx['institucion'],
        ])->assertForbidden();
        $this->patchJson('/api/unidades-academicas/'.$ctx['unidad'].'/estado', [
            'activo' => false,
        ])->assertForbidden();

        foreach (['Revisión', 'Consulta'] as $rolNombre) {
            $rolId = DB::table('rol')->insertGetId(['nombre' => $rolNombre]);
            $lector = Usuario::query()->create([
                'mail' => strtolower($rolNombre).'-unidad@example.com',
                'nombre' => $rolNombre,
                'password' => Hash::make('password-segura'),
                'activo' => true,
                'id_rol' => $rolId,
                'id_unidad' => $ctx['unidad'],
            ]);
            Sanctum::actingAs($lector);

            $this->getJson('/api/unidades-academicas')->assertOk();
            $this->putJson('/api/unidades-academicas/'.$ctx['unidad'], [
                'nombre' => 'No autorizado',
            ])->assertForbidden();
        }
    }

    private function crearInstitucion(string $razonSocial): int
    {
        return DB::table('institucion_firmante')->insertGetId([
            'razon_social' => $razonSocial,
        ]);
    }

    private function crearUnidad(string $sigla, string $nombre, int $institucionId): int
    {
        return DB::table('unidad_academica')->insertGetId([
            'sigla' => $sigla,
            'nombre' => $nombre,
            'id_institucion' => $institucionId,
        ]);
    }

    private function authenticate(): array
    {
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Administración']);
        $institucionId = $this->crearInstitucion('Institución de prueba');
        $unidadId = $this->crearUnidad('UA-USR', 'Unidad del usuario', $institucionId);

        $usuario = Usuario::query()->create([
            'mail' => 'admin-unidades@example.com',
            'nombre' => 'Admin de unidades',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unidadId,
        ]);

        $this->withToken($usuario->createToken('unidades-test')->plainTextToken);

        return ['institucion' => $institucionId, 'unidad' => $unidadId];
    }
}
