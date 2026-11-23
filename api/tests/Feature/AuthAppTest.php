<?php

namespace Tests\Feature;

use App\Mail\CodigoMfaMail;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthAppTest extends TestCase
{
    use RefreshDatabase;

    private const PUSH_TOKEN = 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]';

    public function test_login_de_la_app_valida_la_contrasena_y_pide_mfa_sin_entregar_token(): void
    {
        $usuario = $this->createUsuario();

        $this->postJson('/api/app/login', [
            'mail' => $usuario->mail,
            'password' => 'password-segura',
        ])
            ->assertOk()
            ->assertJsonPath('requiere_mfa', true)
            ->assertJsonPath('canales', ['mail', 'sms', 'push'])
            ->assertJsonStructure(['mfa_token'])
            ->assertJsonMissingPath('access_token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_de_la_app_rechaza_credenciales_invalidas_y_usuarios_inactivos(): void
    {
        $activo = $this->createUsuario();
        $inactivo = $this->createUsuario(false, 'inactivo@example.com');

        $this->postJson('/api/app/login', ['mail' => $activo->mail, 'password' => 'incorrecta'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciales inválidas.');

        $this->postJson('/api/app/login', ['mail' => $inactivo->mail, 'password' => 'password-segura'])
            ->assertUnauthorized();

        $this->postJson('/api/app/login', ['mail' => 'no-es-un-correo', 'password' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mail']);
    }

    public function test_codigo_por_mail_abre_una_sesion_que_vence_a_los_10_minutos_de_inactividad(): void
    {
        Mail::fake();
        $this->travelTo(now()->startOfMinute());
        $usuario = $this->createUsuario();
        $mfaToken = $this->iniciarLogin($usuario);

        $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'mail'])
            ->assertOk()
            ->assertJsonPath('canal', 'mail')
            ->assertJsonPath('destino', 'u***@example.com')
            ->assertJsonPath('expira_en_segundos', 300)
            ->assertJsonMissingPath('codigo_simulado');

        $codigo = null;
        Mail::assertSent(CodigoMfaMail::class, function (CodigoMfaMail $mail) use ($usuario, &$codigo) {
            $codigo = $mail->codigo;

            return $mail->hasTo($usuario->mail);
        });

        $respuesta = $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $codigo])
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesión exitoso.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('sesion_expira_en_minutos', 10)
            ->assertJsonPath('user.mail', $usuario->mail)
            ->assertJsonPath('user.rol.nombre', 'Administración')
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'app-movil',
            'expires_at' => now()->addMinutes(10)->toDateTimeString(),
        ]);

        $this->withToken($respuesta->json('access_token'))->getJson('/api/me')->assertOk();
    }

    public function test_la_sesion_de_la_app_se_renueva_con_el_uso_y_vence_sin_actividad(): void
    {
        $this->travelTo(now()->startOfMinute());
        $token = $this->crearSesionApp($this->createUsuario());

        $this->travel(9)->minutes();
        $this->withToken($token)->getJson('/api/me')->assertOk();

        // Se renovó 9 minutos atrás: 9 minutos más desde ese pedido siguen siendo válidos.
        $this->travel(9)->minutes();
        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->travel(11)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_el_token_de_la_web_no_vence_por_inactividad(): void
    {
        $usuario = $this->createUsuario();

        $token = $this->postJson('/api/login', [
            'mail' => $usuario->mail,
            'password' => 'password-segura',
        ])->assertOk()->json('access_token');

        $this->travel(60)->minutes();

        $this->withToken($token)->getJson('/api/me')->assertOk();
    }

    public function test_sms_simulado_deja_el_codigo_en_el_log_y_solo_lo_expone_si_esta_configurado(): void
    {
        Log::spy();
        $usuario = $this->createUsuario();

        $mfaToken = $this->iniciarLogin($usuario);
        $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'sms'])
            ->assertOk()
            ->assertJsonPath('canal', 'sms')
            ->assertJsonMissingPath('codigo_simulado');

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $mensaje) => str_contains($mensaje, '[SMS simulado]'))
            ->once();

        config(['sgc.mfa.exponer_codigo_simulado' => true]);

        $respuesta = $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'sms'])
            ->assertOk()
            ->assertJsonStructure(['codigo_simulado']);

        $this->postJson('/api/app/mfa/verificar', [
            'mfa_token' => $mfaToken,
            'codigo' => $respuesta->json('codigo_simulado'),
        ])->assertOk()->assertJsonStructure(['access_token']);
    }

    public function test_codigo_por_push_usa_el_servicio_de_expo_y_nunca_se_expone(): void
    {
        config(['sgc.mfa.exponer_codigo_simulado' => true]);
        Http::fake(['exp.host/*' => Http::response(['data' => ['status' => 'ok', 'id' => 'ticket-1']])]);
        $mfaToken = $this->iniciarLogin($this->createUsuario());

        $this->postJson('/api/app/mfa/enviar', [
            'mfa_token' => $mfaToken,
            'canal' => 'push',
            'push_token' => self::PUSH_TOKEN,
        ])
            ->assertOk()
            ->assertJsonPath('canal', 'push')
            ->assertJsonMissingPath('codigo_simulado');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://exp.host/--/api/v2/push/send'
            && $request['to'] === self::PUSH_TOKEN
            && preg_match('/\b\d{6}\b/', $request['body']) === 1);
    }

    public function test_push_falla_con_mensaje_claro_sin_token_o_si_expo_rechaza_el_envio(): void
    {
        $mfaToken = $this->iniciarLogin($this->createUsuario());

        $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'push'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este dispositivo no tiene habilitadas las notificaciones push. Elegí otro canal.');

        Http::fake(['exp.host/*' => Http::response(['data' => ['status' => 'error', 'message' => 'DeviceNotRegistered']])]);

        $this->postJson('/api/app/mfa/enviar', [
            'mfa_token' => $mfaToken,
            'canal' => 'push',
            'push_token' => self::PUSH_TOKEN,
        ])->assertStatus(502);

        // Los envíos fallidos no consumen el cupo ni dejan un código válido.
        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Primero pedí el código por mail, SMS o push.');
    }

    public function test_el_codigo_incorrecto_descuenta_intentos_y_se_bloquea_al_quinto(): void
    {
        config(['sgc.mfa.exponer_codigo_simulado' => true]);
        $mfaToken = $this->iniciarLogin($this->createUsuario());
        $codigo = $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'sms'])
            ->json('codigo_simulado');
        $incorrecto = $codigo === '000000' ? '111111' : '000000';

        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $incorrecto])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Código incorrecto. Te quedan 4 intentos.');

        foreach (range(1, 3) as $_) {
            $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $incorrecto])
                ->assertUnauthorized();
        }

        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $incorrecto])
            ->assertStatus(429);

        // El desafío se destruyó: ni siquiera el código correcto sirve.
        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $codigo])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'La verificación venció. Volvé a iniciar sesión.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_el_codigo_vence_a_los_5_minutos_y_un_codigo_nuevo_invalida_al_anterior(): void
    {
        config(['sgc.mfa.exponer_codigo_simulado' => true]);
        $mfaToken = $this->iniciarLogin($this->createUsuario());
        $primero = $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'sms'])
            ->json('codigo_simulado');

        $segundo = $primero;
        while ($segundo === $primero) {
            $segundo = $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'sms'])
                ->json('codigo_simulado');
        }

        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $primero])
            ->assertUnauthorized();

        $this->travel(6)->minutes();

        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $segundo])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'El código venció. Pedí uno nuevo.');
    }

    public function test_el_desafio_es_de_un_solo_uso_y_limita_los_reenvios(): void
    {
        config(['sgc.mfa.exponer_codigo_simulado' => true]);
        $mfaToken = $this->iniciarLogin($this->createUsuario());
        $codigo = $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'sms'])
            ->json('codigo_simulado');

        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $codigo])->assertOk();
        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => $codigo])
            ->assertUnauthorized();

        $otro = $this->iniciarLogin($this->createUsuario(true, 'otro@example.com'));

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $otro, 'canal' => 'sms'])->assertOk();
        }

        $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $otro, 'canal' => 'sms'])->assertStatus(429);
    }

    public function test_el_mfa_valida_los_datos_de_entrada(): void
    {
        $mfaToken = $this->iniciarLogin($this->createUsuario());

        $this->postJson('/api/app/mfa/enviar', ['mfa_token' => $mfaToken, 'canal' => 'paloma'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['canal']);

        $this->postJson('/api/app/mfa/verificar', ['mfa_token' => $mfaToken, 'codigo' => '12ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);

        $this->postJson('/api/app/mfa/enviar', ['mfa_token' => 'inventado', 'canal' => 'mail'])
            ->assertUnauthorized();
    }

    public function test_activar_la_biometria_pide_la_contrasena_y_requiere_sesion(): void
    {
        $usuario = $this->createUsuario();
        $sesion = $this->crearSesionApp($usuario);

        $this->postJson('/api/app/biometria', ['password' => 'password-segura'])->assertUnauthorized();

        $this->withToken($sesion)->postJson('/api/app/biometria', ['password' => 'incorrecta'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'La contraseña no es correcta.');

        $this->withToken($sesion)->postJson('/api/app/biometria', ['password' => 'password-segura'])
            ->assertCreated()
            ->assertJsonStructure(['token_dispositivo'])
            ->assertJsonPath('dias_validez', 90);

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'app-biometrico']);
    }

    public function test_el_ingreso_con_biometria_abre_una_sesion_sin_pedir_mfa(): void
    {
        $usuario = $this->createUsuario();
        $dispositivo = $this->activarBiometria($usuario);

        $respuesta = $this->postJson('/api/app/login/biometrico', [], ['Authorization' => "Bearer {$dispositivo}"])
            ->assertOk()
            ->assertJsonPath('sesion_expira_en_minutos', 10)
            ->assertJsonPath('user.mail', $usuario->mail);

        $this->withToken($respuesta->json('access_token'))->getJson('/api/me')->assertOk();
    }

    public function test_el_token_del_dispositivo_solo_sirve_para_ingresar_no_para_usar_la_api(): void
    {
        $dispositivo = $this->activarBiometria($this->createUsuario());
        $this->app['auth']->forgetGuards(); // en tests el guard recuerda al usuario del pedido anterior

        $this->withToken($dispositivo)->getJson('/api/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Este token solo sirve para ingresar con biometría.');

        $this->withToken($dispositivo)->getJson('/api/convenios')->assertForbidden();
    }

    public function test_el_ingreso_con_biometria_rechaza_tokens_ajenos_vencidos_o_revocados(): void
    {
        $usuario = $this->createUsuario();
        $dispositivo = $this->activarBiometria($usuario);
        $sesion = $this->crearSesionApp($usuario);

        // Sin token, token inventado o un token de sesión común: no sirven.
        $this->postJson('/api/app/login/biometrico')->assertUnauthorized();
        $this->postJson('/api/app/login/biometrico', [], ['Authorization' => 'Bearer 1|inventado'])
            ->assertUnauthorized();
        $this->postJson('/api/app/login/biometrico', [], ['Authorization' => "Bearer {$sesion}"])
            ->assertUnauthorized();

        // Revocado desde Ajustes.
        $this->withToken($sesion)
            ->deleteJson('/api/app/biometria', ['token_dispositivo' => $dispositivo])
            ->assertOk();
        $this->postJson('/api/app/login/biometrico', [], ['Authorization' => "Bearer {$dispositivo}"])
            ->assertUnauthorized();

        // Vencido (90 días).
        $otro = $this->activarBiometria($usuario);
        $this->travel(91)->days();
        $this->postJson('/api/app/login/biometrico', [], ['Authorization' => "Bearer {$otro}"])
            ->assertUnauthorized();
    }

    public function test_el_ingreso_con_biometria_rechaza_a_los_usuarios_dados_de_baja(): void
    {
        $usuario = $this->createUsuario();
        $dispositivo = $this->activarBiometria($usuario);

        $usuario->update(['activo' => false]);

        $this->postJson('/api/app/login/biometrico', [], ['Authorization' => "Bearer {$dispositivo}"])
            ->assertUnauthorized();
    }

    public function test_desactivar_sin_indicar_dispositivo_revoca_todos_y_uno_inexistente_no_borra_nada(): void
    {
        $usuario = $this->createUsuario();
        $sesion = $this->crearSesionApp($usuario);
        $this->activarBiometria($usuario);
        $this->activarBiometria($usuario);

        $dispositivos = fn () => DB::table('personal_access_tokens')->where('name', 'app-biometrico')->count();
        $this->assertSame(2, $dispositivos());

        $this->withToken($sesion)->deleteJson('/api/app/biometria', ['token_dispositivo' => '9|no-existe'])
            ->assertOk();
        $this->assertSame(2, $dispositivos());

        $this->withToken($sesion)->deleteJson('/api/app/biometria')->assertOk();
        $this->assertSame(0, $dispositivos());
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'app-movil']);
    }

    public function test_cerrar_sesion_revoca_la_sesion_pero_conserva_la_biometria_del_dispositivo(): void
    {
        $usuario = $this->createUsuario();
        $dispositivo = $this->activarBiometria($usuario);
        $sesion = $this->crearSesionApp($usuario);

        $this->withToken($sesion)->postJson('/api/logout')->assertOk();

        $this->postJson('/api/app/login/biometrico', [], ['Authorization' => "Bearer {$dispositivo}"])
            ->assertOk();
    }

    private function iniciarLogin(Usuario $usuario): string
    {
        return $this->postJson('/api/app/login', [
            'mail' => $usuario->mail,
            'password' => 'password-segura',
        ])->assertOk()->json('mfa_token');
    }

    private function crearSesionApp(Usuario $usuario): string
    {
        return $usuario->createToken('app-movil', ['*'], now()->addMinutes(10))->plainTextToken;
    }

    private function activarBiometria(Usuario $usuario): string
    {
        return $this->withToken($this->crearSesionApp($usuario))
            ->postJson('/api/app/biometria', ['password' => 'password-segura'])
            ->assertCreated()
            ->json('token_dispositivo');
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
