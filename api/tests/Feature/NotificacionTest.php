<?php

namespace Tests\Feature;

use App\Models\Notificacion;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\InstitucionFirmante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_push_marca_leidas_y_elimina_token(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [['status' => 'ok']]], 200),
        ]);

        $usuario = $this->crearUsuario();
        Sanctum::actingAs($usuario);

        $this->postJson('/api/notificaciones/push', [
            'push_token' => 'ExponentPushToken[notificacion-test]',
            'dispositivo' => 'android-test',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Notificaciones push registradas.');

        $this->assertDatabaseHas('push_token', [
            'id_usuario' => $usuario->id_usuario,
            'token' => 'ExponentPushToken[notificacion-test]',
        ]);

        $primera = Notificacion::query()->create([
            'tipo' => 'PRUEBA',
            'mensaje' => 'Primera',
            'fecha_generacion' => now(),
            'leida' => false,
            'id_usuario' => $usuario->id_usuario,
        ]);

        $segunda = Notificacion::query()->create([
            'tipo' => 'PRUEBA',
            'mensaje' => 'Segunda',
            'fecha_generacion' => now(),
            'leida' => false,
            'id_usuario' => $usuario->id_usuario,
        ]);

        $this->patchJson('/api/notificaciones/'.$primera->id_notificacion.'/leida')
            ->assertOk()
            ->assertJsonPath('data.leida', true);

        $this->patchJson('/api/notificaciones/leidas')
            ->assertOk()
            ->assertJsonPath('cantidad', 1);

        $this->assertDatabaseHas('notificacion', [
            'id_notificacion' => $primera->id_notificacion,
            'leida' => true,
        ]);
        $this->assertDatabaseHas('notificacion', [
            'id_notificacion' => $segunda->id_notificacion,
            'leida' => true,
        ]);

        $this->deleteJson('/api/notificaciones/push', [
            'push_token' => 'ExponentPushToken[notificacion-test]',
        ])
            ->assertOk();

        $this->assertDatabaseMissing('push_token', [
            'token' => 'ExponentPushToken[notificacion-test]',
        ]);
    }

    public function test_push_rechaza_tokens_invalidos_y_notificaciones_foraneas(): void
    {
        $usuario = $this->crearUsuario();
        Sanctum::actingAs($usuario);

        $this->postJson('/api/notificaciones/push', [
            'push_token' => 'token-invalido',
        ])
            ->assertStatus(422);

        $otro = $this->crearUsuario('otro-notificaciones@example.com');
        $notificacion = Notificacion::query()->create([
            'tipo' => 'PRUEBA',
            'mensaje' => 'Ajena',
            'fecha_generacion' => now(),
            'leida' => false,
            'id_usuario' => $otro->id_usuario,
        ]);

        $this->patchJson('/api/notificaciones/'.$notificacion->id_notificacion.'/leida')
            ->assertNotFound();
    }

    private function crearUsuario(string $mail = 'notificaciones@example.com'): Usuario
    {
        $rol = Rol::query()->firstOrCreate(['nombre' => 'Consulta']);

        $institucion = InstitucionFirmante::query()->firstOrCreate(
            ['razon_social' => 'Institución notificaciones'],
            ['tipo_institucion' => 'Universidad', 'activo' => true]
        );

        $unidad = UnidadAcademica::query()->firstOrCreate(
            ['sigla' => 'UA-NOT'],
            ['nombre' => 'Unidad de notificaciones', 'id_institucion' => $institucion->id_institucion, 'activo' => true]
        );

        return Usuario::query()->create([
            'mail' => $mail,
            'nombre' => 'Usuario notificaciones',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rol->id_rol,
            'id_unidad' => $unidad->id_unidad_academica,
        ]);
    }
}
