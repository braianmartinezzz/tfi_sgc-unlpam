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
