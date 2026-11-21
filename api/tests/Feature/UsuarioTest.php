<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/usuarios')->assertUnauthorized();
        $this->getJson('/api/usuarios/1')->assertUnauthorized();
        $this->postJson('/api/usuarios', [])->assertUnauthorized();
        $this->putJson('/api/usuarios/1', [])->assertUnauthorized();
        $this->deleteJson('/api/usuarios/1')->assertUnauthorized();
    }

    public function test_only_administration_can_manage_users(): void
    {
        $ctx = $this->authenticate();
        $objetivo = $this->crearUsuario($ctx['rol'], $ctx['unidad'], [
            'mail' => 'objetivo@example.com',
        ]);
        $payload = [
            'mail' => 'nuevo@example.com',
            'nombre' => 'Usuario nuevo',
            'password' => 'clave-segura-1',
            'id_rol' => $ctx['rol'],
            'id_unidad' => $ctx['unidad'],
        ];

        foreach (['Gestión', 'Revisión', 'Consulta'] as $nombreRol) {
            $rolId = DB::table('rol')->insertGetId(['nombre' => $nombreRol]);
            $usuario = $this->crearUsuario($rolId, $ctx['unidad'], [
                'mail' => strtolower(str_replace('ó', 'o', $nombreRol)).'@example.com',
            ]);

            $this->withToken($usuario->createToken('usuarios-denegados')->plainTextToken)
                ->getJson('/api/usuarios')
                ->assertForbidden();

            $this->getJson('/api/usuarios/'.$objetivo->id_usuario)
                ->assertForbidden();

            $this->postJson('/api/usuarios', $payload)
                ->assertForbidden();

            $this->putJson('/api/usuarios/'.$objetivo->id_usuario, [
                'nombre' => 'Nombre modificado',
            ])->assertForbidden();

            $this->deleteJson('/api/usuarios/'.$objetivo->id_usuario)
                ->assertForbidden();
        }

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $objetivo->id_usuario,
            'nombre' => $objetivo->nombre,
            'activo' => true,
        ]);
        $this->assertDatabaseMissing('usuario', ['mail' => 'nuevo@example.com']);
    }

    public function test_lists_paginated_and_applies_filters(): void
    {
        $ctx = $this->authenticate();
        $rolConsulta = DB::table('rol')->insertGetId(['nombre' => 'Consulta']);

        $this->crearUsuario($rolConsulta, $ctx['unidad'], ['nombre' => 'Ana Pérez', 'mail' => 'ana@example.com']);
        $this->crearUsuario($rolConsulta, $ctx['unidad'], [
            'nombre' => 'Beto Gómez',
            'mail' => 'beto@example.com',
            'activo' => false,
        ]);

        $this->getJson('/api/usuarios')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonMissingPath('data.0.password');

        $this->getJson('/api/usuarios?id_rol='.$rolConsulta)
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/usuarios?activo=false')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.nombre', 'Beto Gómez');

        $this->getJson('/api/usuarios?buscar=ana')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.mail', 'ana@example.com');
    }

    public function test_shows_one_usuario_or_returns_404(): void
    {
        $ctx = $this->authenticate();

        $this->getJson('/api/usuarios/'.$ctx['admin']->id_usuario)
            ->assertOk()
            ->assertJsonPath('data.mail', $ctx['admin']->mail)
            ->assertJsonPath('data.rol.nombre', 'Administración')
            ->assertJsonMissingPath('data.password');

        $this->getJson('/api/usuarios/99999')->assertNotFound();
    }

    public function test_creates_a_usuario_with_hashed_password(): void
    {
        $ctx = $this->authenticate();

        $this->postJson('/api/usuarios', [
            'mail' => 'nuevo@example.com',
            'nombre' => 'Usuario nuevo',
            'password' => 'clave-segura-1',
            'id_rol' => $ctx['rol'],
            'id_unidad' => $ctx['unidad'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.mail', 'nuevo@example.com')
            ->assertJsonPath('data.activo', true)
            ->assertJsonMissingPath('data.password');

        $usuario = Usuario::query()->where('mail', 'nuevo@example.com')->first();

        $this->assertNotSame('clave-segura-1', $usuario->password);
        $this->assertTrue(Hash::check('clave-segura-1', $usuario->password));

        $this->postJson('/api/login', [
            'mail' => 'nuevo@example.com',
            'password' => 'clave-segura-1',
        ])->assertOk();
    }

    public function test_rejects_invalid_data_on_create(): void
    {
        $ctx = $this->authenticate();

        $this->postJson('/api/usuarios', [
            'mail' => $ctx['admin']->mail,
            'nombre' => 'Repetido',
            'password' => 'corta',
            'id_rol' => 99999,
            'id_unidad' => $ctx['unidad'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mail', 'password', 'id_rol']);
    }

    public function test_updates_without_touching_password_when_omitted(): void
    {
        $ctx = $this->authenticate();
        $usuario = $this->crearUsuario($ctx['rol'], $ctx['unidad'], ['mail' => 'editar@example.com']);
        $hashAnterior = $usuario->password;

        $this->putJson('/api/usuarios/'.$usuario->id_usuario, [
            'mail' => 'editar@example.com',
            'nombre' => 'Nombre actualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre actualizado');

        $this->assertSame($hashAnterior, $usuario->fresh()->password);
    }

    public function test_updates_password_when_provided(): void
    {
        $ctx = $this->authenticate();
        $usuario = $this->crearUsuario($ctx['rol'], $ctx['unidad'], ['mail' => 'clave@example.com']);

        $this->putJson('/api/usuarios/'.$usuario->id_usuario, [
            'password' => 'otra-clave-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('otra-clave-123', $usuario->fresh()->password));
    }

    public function test_destroy_deactivates_the_usuario_and_revokes_tokens(): void
    {
        $ctx = $this->authenticate();
        $otro = $this->crearUsuario($ctx['rol'], $ctx['unidad'], ['mail' => 'baja@example.com']);
        $otro->createToken('token-de-prueba');

        $this->deleteJson('/api/usuarios/'.$otro->id_usuario)
            ->assertOk()
            ->assertJsonPath('message', 'Usuario desactivado correctamente.');

        $this->assertDatabaseHas('usuario', ['id_usuario' => $otro->id_usuario, 'activo' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $otro->id_usuario,
            'tokenable_type' => Usuario::class,
        ]);

        $this->postJson('/api/login', [
            'mail' => 'baja@example.com',
            'password' => 'password-segura',
        ])->assertUnauthorized();
    }

    public function test_can_reactivate_a_usuario(): void
    {
        $ctx = $this->authenticate();
        $inactivo = $this->crearUsuario($ctx['rol'], $ctx['unidad'], [
            'mail' => 'inactivo@example.com',
            'activo' => false,
        ]);

        $this->putJson('/api/usuarios/'.$inactivo->id_usuario, ['activo' => true])
            ->assertOk()
            ->assertJsonPath('data.activo', true);

        $this->postJson('/api/login', [
            'mail' => 'inactivo@example.com',
            'password' => 'password-segura',
        ])->assertOk();
    }

    public function test_cannot_create_reactivate_or_move_a_usuario_to_an_inactive_unit(): void
    {
        $ctx = $this->authenticate();
        $inactiva = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-INACTIVA',
            'nombre' => 'Unidad inactiva',
            'id_institucion' => DB::table('unidad_academica')
                ->where('id_unidad_academica', $ctx['unidad'])
                ->value('id_institucion'),
            'activo' => false,
        ]);

        $this->postJson('/api/usuarios', [
            'mail' => 'usuario-unidad-inactiva@example.com',
            'nombre' => 'Usuario en unidad inactiva',
            'password' => 'clave-segura-1',
            'id_rol' => $ctx['rol'],
            'id_unidad' => $inactiva,
        ])->assertStatus(409);

        $inactivo = $this->crearUsuario($ctx['rol'], $ctx['unidad'], [
            'mail' => 'reactivar-unidad-inactiva@example.com',
            'activo' => false,
        ]);
        $this->putJson('/api/usuarios/'.$inactivo->id_usuario, [
            'activo' => true,
            'id_unidad' => $inactiva,
        ])->assertStatus(409);

        $activo = $this->crearUsuario($ctx['rol'], $ctx['unidad'], [
            'mail' => 'mover-unidad-inactiva@example.com',
        ]);
        $this->putJson('/api/usuarios/'.$activo->id_usuario, [
            'id_unidad' => $inactiva,
        ])->assertStatus(409);

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $inactivo->id_usuario,
            'activo' => false,
            'id_unidad' => $ctx['unidad'],
        ]);
        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $activo->id_usuario,
            'id_unidad' => $ctx['unidad'],
        ]);
    }

    public function test_admin_cannot_deactivate_or_change_the_role_of_their_own_account(): void
    {
        $ctx = $this->authenticate();
        $otroRol = DB::table('rol')->insertGetId(['nombre' => 'Consulta']);
        $propioId = $ctx['admin']->id_usuario;

        $this->deleteJson('/api/usuarios/'.$propioId)->assertStatus(409);

        $this->putJson('/api/usuarios/'.$propioId, ['activo' => false])->assertStatus(409);

        $this->putJson('/api/usuarios/'.$propioId, ['id_rol' => $otroRol])->assertStatus(409);

        $this->putJson('/api/usuarios/'.$propioId, ['nombre' => 'Nombre propio'])->assertOk();

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $propioId,
            'activo' => true,
            'id_rol' => $ctx['rol'],
        ]);
    }

    public function test_returns_404_when_updating_or_deleting_an_unknown_usuario(): void
    {
        $this->authenticate();

        $this->putJson('/api/usuarios/99999', ['nombre' => 'Nadie'])->assertNotFound();
        $this->deleteJson('/api/usuarios/99999')->assertNotFound();
    }

    public function test_creates_a_usuario_with_alcance_global_disabled_by_default(): void
    {
        $ctx = $this->authenticate();

        $this->postJson('/api/usuarios', [
            'mail' => 'sin-alcance@example.com',
            'nombre' => 'Usuario sin alcance global',
            'password' => 'clave-segura-1',
            'id_rol' => $ctx['rol'],
            'id_unidad' => $ctx['unidad'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.alcance_global', false);

        $this->assertDatabaseHas('usuario', [
            'mail' => 'sin-alcance@example.com',
            'alcance_global' => false,
        ]);
    }

    public function test_creates_a_usuario_with_alcance_global_enabled(): void
    {
        $ctx = $this->authenticate();

        $this->postJson('/api/usuarios', [
            'mail' => 'con-alcance@example.com',
            'nombre' => 'Usuario con alcance global',
            'password' => 'clave-segura-1',
            'id_rol' => $ctx['rol'],
            'id_unidad' => $ctx['unidad'],
            'alcance_global' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.alcance_global', true);

        $this->assertDatabaseHas('usuario', [
            'mail' => 'con-alcance@example.com',
            'alcance_global' => true,
        ]);
    }

    public function test_updates_alcance_global_of_an_existing_usuario(): void
    {
        $ctx = $this->authenticate();
        $usuario = $this->crearUsuario($ctx['rol'], $ctx['unidad'], ['mail' => 'alcance@example.com']);

        $this->putJson('/api/usuarios/'.$usuario->id_usuario, ['alcance_global' => true])
            ->assertOk()
            ->assertJsonPath('data.alcance_global', true);

        $this->assertTrue($usuario->fresh()->alcance_global);

        $this->putJson('/api/usuarios/'.$usuario->id_usuario, ['alcance_global' => false])
            ->assertOk()
            ->assertJsonPath('data.alcance_global', false);

        $this->assertFalse($usuario->fresh()->alcance_global);
    }

    public function test_rejects_a_non_boolean_alcance_global(): void
    {
        $ctx = $this->authenticate();

        $this->putJson('/api/usuarios/'.$ctx['admin']->id_usuario, ['alcance_global' => 'quizás'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alcance_global']);
    }

    public function test_links_a_usuario_with_a_persona_and_rejects_reusing_the_persona(): void
    {
        $ctx = $this->authenticate();
        $personaId = DB::table('persona')->insertGetId([
            'nombre' => 'Rita',
            'apellido' => 'Rectora',
            'dni' => '20123456',
        ]);

        $this->postJson('/api/usuarios', [
            'mail' => 'rectora@example.com',
            'nombre' => 'Rita Rectora',
            'password' => 'clave-segura-1',
            'id_rol' => $ctx['rol'],
            'id_unidad' => $ctx['unidad'],
            'id_persona' => $personaId,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id_persona', $personaId)
            ->assertJsonPath('data.persona.apellido', 'Rectora');

        $this->postJson('/api/usuarios', [
            'mail' => 'otra@example.com',
            'nombre' => 'Otra persona',
            'password' => 'clave-segura-1',
            'id_rol' => $ctx['rol'],
            'id_unidad' => $ctx['unidad'],
            'id_persona' => $personaId,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_persona']);

        $this->postJson('/api/usuarios', [
            'mail' => 'inexistente@example.com',
            'nombre' => 'Persona inexistente',
            'password' => 'clave-segura-1',
            'id_rol' => $ctx['rol'],
            'id_unidad' => $ctx['unidad'],
            'id_persona' => 99999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_persona']);
    }

    public function test_updates_and_clears_the_persona_of_an_existing_usuario(): void
    {
        $ctx = $this->authenticate();
        $usuario = $this->crearUsuario($ctx['rol'], $ctx['unidad'], ['mail' => 'vinculo@example.com']);
        $personaId = DB::table('persona')->insertGetId([
            'nombre' => 'Dario',
            'apellido' => 'Decano',
            'dni' => '20654321',
        ]);

        $this->putJson('/api/usuarios/'.$usuario->id_usuario, ['id_persona' => $personaId])
            ->assertOk()
            ->assertJsonPath('data.id_persona', $personaId);

        $this->putJson('/api/usuarios/'.$usuario->id_usuario, ['id_persona' => null])
            ->assertOk()
            ->assertJsonPath('data.id_persona', null);
    }

    private function crearUsuario(int $rolId, int $unidadId, array $atributos = []): Usuario
    {
        static $contador = 0;
        $contador++;

        return Usuario::query()->create(array_merge([
            'mail' => "usuario{$contador}@example.com",
            'nombre' => "Usuario {$contador}",
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unidadId,
        ], $atributos));
    }

    private function authenticate(): array
    {
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Administración']);
        $institucionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unidadId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-USR',
            'nombre' => 'Unidad de prueba',
            'id_institucion' => $institucionId,
        ]);

        $admin = $this->crearUsuario($rolId, $unidadId, [
            'mail' => 'admin-usuarios@example.com',
            'nombre' => 'Administrador de pruebas',
        ]);

        $this->withToken($admin->createToken('usuarios-test')->plainTextToken);

        return ['admin' => $admin, 'rol' => $rolId, 'unidad' => $unidadId];
    }
}
