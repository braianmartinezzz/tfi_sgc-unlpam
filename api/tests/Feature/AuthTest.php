<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_and_me_returns_user_relations(): void
    {
        $usuario = $this->createUsuario();

        $loginResponse = $this->postJson('/api/login', [
            'mail' => $usuario->mail,
            'password' => 'password-segura',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesión exitoso.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.mail', $usuario->mail)
            ->assertJsonPath('user.rol.nombre', 'Administración')
            ->assertJsonPath('user.unidad_academica.nombre', 'Unidad de prueba')
            ->assertJsonMissingPath('user.password');

        $token = $loginResponse->json('access_token');

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.mail', $usuario->mail)
            ->assertJsonPath('user.rol.nombre', 'Administración')
            ->assertJsonPath('user.unidad_academica.nombre', 'Unidad de prueba')
            ->assertJsonMissingPath('user.password');
    }

    public function test_login_rejects_invalid_or_inactive_credentials(): void
    {
        $activo = $this->createUsuario();
        $inactivo = $this->createUsuario(false, 'inactivo@example.com');

        $this->postJson('/api/login', [
            'mail' => $activo->mail,
            'password' => 'incorrecta',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciales inválidas.');

        $this->postJson('/api/login', [
            'mail' => $inactivo->mail,
            'password' => 'password-segura',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciales inválidas.');

        $this->postJson('/api/login', [
            'mail' => 'no-es-un-correo',
            'password' => 'password-segura',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Los datos enviados no son válidos.')
            ->assertJsonValidationErrors(['mail']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_revokes_only_the_current_token_and_protected_routes_require_authentication(): void
    {
        $usuario = $this->createUsuario();

        $this->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->postJson('/api/convenios')->assertUnauthorized();

        $currentToken = $usuario->createToken('current-token');
        $otherToken = $usuario->createToken('other-token');

        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
    }

    private function createUsuario(bool $activo = true, string $mail = 'usuario@example.com'): Usuario
    {
        $rolId = DB::table('rol')->where('nombre', 'Administración')->value('id_rol');
        $rolId ??= DB::table('rol')->insertGetId(['nombre' => 'Administración']);

        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA',
            'nombre' => 'Unidad de prueba',
            'id_institucion' => $institutionId,
        ]);

        return Usuario::query()->create([
            'mail' => $mail,
            'nombre' => 'Usuario de prueba',
            'password' => Hash::make('password-segura'),
            'activo' => $activo,
            'id_rol' => $rolId,
            'id_unidad' => $unitId,
        ]);
    }
}
