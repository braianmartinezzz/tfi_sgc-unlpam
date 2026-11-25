<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\HistorialEstado;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirmadorTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Los PDF y los trazos de prueba no deben tocar el almacenamiento real.
        Storage::fake('private');
    }

    // ---------------------------------------------------------------- firmantes

    public function test_gestion_defines_the_firmantes_of_a_convenio(): void
    {
        $ctx = $this->escenario(['estado' => 'Revisión', 'firmantes' => false]);
        $this->authenticate($ctx['gestion']);

        $this->putJson("/api/convenios/{$ctx['convenio']->id_instrumento}/firmantes", [
            'ids_persona' => [$ctx['personaA'], $ctx['personaB']],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.firmado', false);

        $this->assertDatabaseCount('firma', 2);
        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'DEFINIR_FIRMANTES',
            'id_instrumento' => $ctx['convenio']->id_instrumento,
        ]);
    }

    public function test_requires_at_least_two_distinct_existing_personas(): void
    {
        $ctx = $this->escenario(['estado' => 'Revisión', 'firmantes' => false]);
        $this->authenticate($ctx['gestion']);
        $url = "/api/convenios/{$ctx['convenio']->id_instrumento}/firmantes";

        $this->putJson($url, ['ids_persona' => [$ctx['personaA']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids_persona']);

        $this->putJson($url, ['ids_persona' => [$ctx['personaA'], $ctx['personaA']]])
            ->assertStatus(422);

        $this->putJson($url, ['ids_persona' => [$ctx['personaA'], 99999]])
            ->assertStatus(422);

        $this->assertDatabaseCount('firma', 0);
    }

    public function test_pending_firmantes_can_be_replaced_but_not_one_who_already_signed(): void
    {
        $ctx = $this->escenario();
        $tercera = $this->createPersona();
        $cuarta = $this->createPersona();
        DB::table('firma')
            ->where('id_instrumento', $ctx['convenio']->id_instrumento)
            ->where('id_persona', $ctx['personaA'])
            ->update(['fecha_hora_firma' => now()]);

        $this->authenticate($ctx['gestion']);
        $url = "/api/convenios/{$ctx['convenio']->id_instrumento}/firmantes";

        // Se reemplaza a B (pendiente) por una tercera persona.
        $this->putJson($url, ['ids_persona' => [$ctx['personaA'], $tercera]])->assertOk();
        $this->assertDatabaseMissing('firma', ['id_persona' => $ctx['personaB']]);
        $this->assertDatabaseHas('firma', ['id_persona' => $tercera]);

        // A ya firmó: no se la puede quitar.
        $this->putJson($url, ['ids_persona' => [$tercera, $cuarta]])->assertStatus(409);
        $this->assertDatabaseHas('firma', ['id_persona' => $ctx['personaA']]);
    }

    public function test_firmantes_cannot_change_once_the_convenio_is_signed_or_closed(): void
    {
        $ctx = $this->escenario(['estado' => 'Firmado']);
        $this->authenticate($ctx['gestion']);

        $this->putJson("/api/convenios/{$ctx['convenio']->id_instrumento}/firmantes", [
            'ids_persona' => [$ctx['personaA'], $ctx['personaB']],
        ])->assertStatus(409);
    }

    public function test_only_gestion_of_the_unit_or_administracion_can_define_firmantes(): void
    {
        $ctx = $this->escenario(['estado' => 'Revisión', 'firmantes' => false]);
        $otraUnidad = $this->createUnit();
        $gestionAjena = $this->createUser('Gestión', $otraUnidad->id_unidad_academica);
        $consulta = $this->createUser('Consulta', $ctx['unit']->id_unidad_academica);
        $body = ['ids_persona' => [$ctx['personaA'], $ctx['personaB']]];
        $url = "/api/convenios/{$ctx['convenio']->id_instrumento}/firmantes";

        foreach ([$gestionAjena, $consulta] as $usuario) {
            $this->authenticate($usuario);
            $this->putJson($url, $body)->assertForbidden();
        }

        $this->assertDatabaseCount('firma', 0);

        $this->authenticate($ctx['rector']);
        $this->putJson($url, $body)->assertOk();
    }

    // ---------------------------------------------------------------- pendientes

    public function test_lists_pending_signatures_of_the_user_oldest_first(): void
    {
        $ctx = $this->escenario(['horas' => 5]);
        $viejo = $this->convenioConFirmantes($ctx, 'Aprobado viejo', 'Aprobado', 100);
        $firmado = $this->convenioConFirmantes($ctx, 'Ya firmado por mí', 'Aprobado', 50);
        $enRevision = $this->convenioConFirmantes($ctx, 'Todavía en revisión', 'Revisión', null);
        DB::table('firma')
            ->where('id_instrumento', $firmado->id_instrumento)
            ->where('id_persona', $ctx['personaB'])
            ->update(['fecha_hora_firma' => now()]);

        $this->authenticate($ctx['decano']);

        $response = $this->getJson('/api/firmas/pendientes')->assertOk();

        $titulos = collect($response->json('data'))->pluck('titulo')->all();
        $this->assertSame(['Aprobado viejo', 'Convenio a firmar'], $titulos);
        $this->assertNotContains('Todavía en revisión', $titulos);

        $response
            ->assertJsonPath('data.0.demorado', true)
            ->assertJsonPath('data.1.demorado', false)
            ->assertJsonCount(2, 'data.0.firmantes')
            ->assertJsonPath('data.1.version_registro', 1)
            ->assertJsonPath('data.1.documento.nombre_archivo', 'convenio.pdf');

        $this->assertNotNull($viejo);
        $this->assertNotNull($enRevision);
    }

    public function test_user_without_a_linked_persona_has_no_pending_signatures(): void
    {
        $ctx = $this->escenario();
        $this->authenticate($ctx['gestion']);

        $this->getJson('/api/firmas/pendientes')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_pending_signatures_are_limited_to_ten(): void
    {
        $ctx = $this->escenario();

        for ($i = 1; $i <= 11; $i++) {
            $this->convenioConFirmantes($ctx, 'Extra '.$i, 'Aprobado', $i);
        }

        $this->authenticate($ctx['decano']);

        $this->getJson('/api/firmas/pendientes')->assertOk()->assertJsonCount(10, 'data');
    }

    // ---------------------------------------------------------------- documento

    public function test_a_firmante_can_download_the_pdf_to_sign(): void
    {
        $ctx = $this->escenario(['horas' => 3]);
        $this->authenticate($ctx['decano']);

        $response = $this->get("/api/firmas/{$ctx['convenio']->id_instrumento}/documento");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-demo', $response->streamedContent());
    }

    public function test_only_assigned_firmantes_can_download_the_pdf(): void
    {
        $ctx = $this->escenario(['horas' => 3]);
        $sinPersona = $ctx['gestion'];
        $ajena = $this->createUser('Gestión', $ctx['unit']->id_unidad_academica, $this->createPersona());

        foreach ([$sinPersona, $ajena] as $usuario) {
            $this->authenticate($usuario);
            $this->getJson("/api/firmas/{$ctx['convenio']->id_instrumento}/documento")->assertForbidden();
        }

        $sinPdf = $this->escenario(['pdf' => false]);
        $this->authenticate($sinPdf['decano']);
        $this->getJson("/api/firmas/{$sinPdf['convenio']->id_instrumento}/documento")->assertNotFound();
    }

    // ---------------------------------------------------------------- firmar

    public function test_first_signature_is_recorded_and_the_convenio_stays_approved(): void
    {
        $ctx = $this->escenario();
        $this->authenticate($ctx['decano']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/firmar", $this->datosDeFirma())
            ->assertOk()
            ->assertJsonPath('message', 'Firma registrada correctamente.')
            ->assertJsonPath('data.estado', 'Aprobado')
            ->assertJsonPath('data.firmas_pendientes', 1)
            ->assertJsonPath('data.firma.id_persona', $ctx['personaB']);

        $fila = DB::table('firma')
            ->where('id_instrumento', $ctx['convenio']->id_instrumento)
            ->where('id_persona', $ctx['personaB'])
            ->first();

        $this->assertNotNull($fila->fecha_hora_firma);
        $this->assertEqualsWithDelta(-36.6167, (float) $fila->latitud, 0.0001);
        $this->assertEqualsWithDelta(-64.2833, (float) $fila->longitud, 0.0001);
        $this->assertTrue((bool) $fila->biometria_confirmada);
        Storage::disk('private')->assertExists($fila->trazo_ruta);

        $this->assertSame('Aprobado', $ctx['convenio']->instrumento->fresh()->estado);
        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'FIRMA_REGISTRADA',
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'id_usuario' => $ctx['decano']->id_usuario,
        ]);

        // Se avisa a Gestión activa de la unidad (el decano y el personal), no al inactivo.
        foreach ([$ctx['decano'], $ctx['gestion']] as $destinatario) {
            $this->assertDatabaseHas('notificacion', [
                'tipo' => 'FIRMA_REGISTRADA',
                'id_usuario' => $destinatario->id_usuario,
                'id_instrumento' => $ctx['convenio']->id_instrumento,
            ]);
        }
        $this->assertDatabaseMissing('notificacion', ['id_usuario' => $ctx['inactivo']->id_usuario]);
    }

    public function test_the_last_signature_moves_the_convenio_to_firmado_automatically(): void
    {
        $ctx = $this->escenario();

        $this->authenticate($ctx['decano']);
        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/firmar", $this->datosDeFirma())
            ->assertOk();

        $this->authenticate($ctx['rector']);
        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/firmar", $this->datosDeFirma())
            ->assertOk()
            ->assertJsonPath('data.estado', 'Firmado')
            ->assertJsonPath('data.firmas_pendientes', 0);

        $instrumento = $ctx['convenio']->instrumento->fresh();
        $this->assertSame('Firmado', $instrumento->estado);
        $this->assertSame(now()->toDateString(), $instrumento->fecha_firma->toDateString());

        $this->assertDatabaseHas('historial_estado', [
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'estado_anterior' => 'Aprobado',
            'estado_nuevo' => 'Firmado',
            'id_usuario' => $ctx['rector']->id_usuario,
        ]);
        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'CONVENIO_FIRMADO',
            'id_instrumento' => $ctx['convenio']->id_instrumento,
        ]);
        $this->assertDatabaseHas('notificacion', [
            'tipo' => 'CONVENIO_FIRMADO',
            'id_usuario' => $ctx['gestion']->id_usuario,
        ]);
    }

    public function test_signing_requires_being_an_assigned_firmante_with_a_linked_persona(): void
    {
        $ctx = $this->escenario();
        $ajena = $this->createUser('Gestión', $ctx['unit']->id_unidad_academica, $this->createPersona());
        $url = "/api/convenios/{$ctx['convenio']->id_instrumento}/firmar";

        foreach ([$ctx['gestion'], $ajena] as $usuario) {
            $this->authenticate($usuario);
            $this->postJson($url, $this->datosDeFirma())->assertForbidden();
        }

        $this->assertSame(0, DB::table('firma')->whereNotNull('fecha_hora_firma')->count());
        $this->assertSame([], Storage::disk('private')->allFiles('firmas'));
    }

    public function test_cannot_sign_twice_or_when_not_approved_or_with_fewer_than_two_firmantes(): void
    {
        $ctx = $this->escenario();
        $this->authenticate($ctx['decano']);
        $url = "/api/convenios/{$ctx['convenio']->id_instrumento}/firmar";
        $this->postJson($url, $this->datosDeFirma())->assertOk();
        $this->postJson($url, $this->datosDeFirma())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ya firmaste este convenio.');

        $enRevision = $this->escenario(['estado' => 'Revisión']);
        $this->authenticate($enRevision['decano']);
        $this->postJson("/api/convenios/{$enRevision['convenio']->id_instrumento}/firmar", $this->datosDeFirma())
            ->assertStatus(409)
            ->assertJsonPath('message', 'El convenio debe estar en estado Aprobado para poder firmarse.');

        $unSoloFirmante = $this->escenario();
        DB::table('firma')
            ->where('id_instrumento', $unSoloFirmante['convenio']->id_instrumento)
            ->where('id_persona', $unSoloFirmante['personaA'])
            ->delete();
        $this->authenticate($unSoloFirmante['decano']);
        $this->postJson("/api/convenios/{$unSoloFirmante['convenio']->id_instrumento}/firmar", $this->datosDeFirma())
            ->assertStatus(409);
    }

    public function test_validates_the_signature_payload_and_stores_nothing_when_invalid(): void
    {
        $ctx = $this->escenario();
        $this->authenticate($ctx['decano']);
        $url = "/api/convenios/{$ctx['convenio']->id_instrumento}/firmar";

        $casos = [
            'sin latitud' => [['latitud' => null], 'latitud'],
            'latitud fuera de rango' => [['latitud' => 91], 'latitud'],
            'longitud fuera de rango' => [['longitud' => -181], 'longitud'],
            'sin biometría' => [['biometria_confirmada' => false], 'biometria_confirmada'],
            'trazo con prefijo inválido' => [['trazo' => 'hola'], 'trazo'],
            'trazo que no es PNG' => [['trazo' => 'data:image/png;base64,'.base64_encode('no soy un png')], 'trazo'],
            'trazo no decodificable' => [['trazo' => 'data:image/png;base64,@@@@'], 'trazo'],
        ];

        foreach ($casos as $nombre => [$cambios, $campo]) {
            $this->postJson($url, array_merge($this->datosDeFirma(), $cambios))
                ->assertStatus(422)
                ->assertJsonValidationErrors($campo);
        }

        $this->assertSame(0, DB::table('firma')->whereNotNull('fecha_hora_firma')->count());
        $this->assertSame([], Storage::disk('private')->allFiles('firmas'));
    }

    public function test_returns_not_found_when_signing_a_missing_convenio(): void
    {
        $ctx = $this->escenario();
        $this->authenticate($ctx['decano']);

        $this->postJson('/api/convenios/999999/firmar', $this->datosDeFirma())->assertNotFound();
    }

    public function test_signing_with_a_stale_version_is_rejected(): void
    {
        $ctx = $this->escenario();
        $this->authenticate($ctx['decano']);

        // La app cargó la versión 1; entre tanto, el convenio pasó a la versión 2.
        Instrumento::query()
            ->whereKey($ctx['convenio']->id_instrumento)
            ->update(['version_registro' => 2]);

        $this->postJson(
            "/api/convenios/{$ctx['convenio']->id_instrumento}/firmar",
            array_merge($this->datosDeFirma(), ['version_registro' => 1])
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El convenio cambió desde que lo cargaste. Volvé a cargarlo antes de firmar.'
            );

        // La firma no se registró.
        $fila = DB::table('firma')
            ->where('id_instrumento', $ctx['convenio']->id_instrumento)
            ->where('id_persona', $ctx['personaB'])
            ->first();
        $this->assertNull($fila->fecha_hora_firma);

        // Con la versión vigente la firma pasa.
        $this->postJson(
            "/api/convenios/{$ctx['convenio']->id_instrumento}/firmar",
            array_merge($this->datosDeFirma(), ['version_registro' => 2])
        )->assertOk();
    }

    // ---------------------------------------------------------------- constancia y trazo

    public function test_firmantes_and_gestion_can_see_the_signature_record(): void
    {
        $ctx = $this->escenario();
        $this->authenticate($ctx['decano']);
        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/firmar", $this->datosDeFirma())->assertOk();

        $outsider = $this->createUser('Consulta', $this->createUnit()->id_unidad_academica);
        $gestionAjena = $this->createUser('Gestión', $this->createUnit()->id_unidad_academica);
        $url = "/api/convenios/{$ctx['convenio']->id_instrumento}/firmas";

        foreach ([$ctx['decano'], $ctx['rector'], $ctx['gestion']] as $usuario) {
            $this->authenticate($usuario);

            $this->getJson($url)
                ->assertOk()
                ->assertJsonPath('data.firmas_requeridas', 2)
                ->assertJsonPath('data.firmas_registradas', 1)
                ->assertJsonCount(2, 'data.firmantes');
        }

        foreach ([$outsider, $gestionAjena] as $usuario) {
            $this->authenticate($usuario);
            $this->getJson($url)->assertForbidden();
        }

        $this->authenticate($ctx['gestion']);
        $firmaB = collect($this->getJson($url)->json('data.firmantes'))
            ->firstWhere('id_persona', $ctx['personaB']);

        $this->assertTrue($firmaB['firmado']);
        $this->assertTrue($firmaB['tiene_trazo']);
        $this->assertTrue($firmaB['biometria_confirmada']);
        $this->assertEqualsWithDelta(-36.6167, $firmaB['latitud'], 0.0001);
    }

    public function test_the_trazo_of_a_signature_can_be_downloaded_once_it_exists(): void
    {
        $ctx = $this->escenario();
        $this->authenticate($ctx['decano']);
        $datos = $this->datosDeFirma();
        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/firmar", $datos)->assertOk();

        $this->authenticate($ctx['gestion']);
        $id = $ctx['convenio']->id_instrumento;

        $response = $this->get("/api/convenios/{$id}/firmas/{$ctx['personaB']}/trazo");
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $response->streamedContent());

        // La otra firma todavía no se registró.
        $this->getJson("/api/convenios/{$id}/firmas/{$ctx['personaA']}/trazo")->assertNotFound();

        $gestionAjena = $this->createUser('Gestión', $this->createUnit()->id_unidad_academica);
        $this->authenticate($gestionAjena);
        $this->getJson("/api/convenios/{$id}/firmas/{$ctx['personaB']}/trazo")->assertForbidden();
    }

    // ---------------------------------------------------------------- helpers

    private function datosDeFirma(): array
    {
        return [
            'trazo' => 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n".str_repeat('x', 64)),
            'latitud' => -36.6167,
            'longitud' => -64.2833,
            'biometria_confirmada' => true,
        ];
    }

    /**
     * Convenio de la unidad A, con dos firmantes pendientes:
     *  - Rector/a (Administración global) y Decano/a (Gestión de la unidad), cada uno vinculado a una persona.
     *  - Más personal de Gestión de la unidad (sin persona) y un usuario de Gestión inactivo.
     */
    private function escenario(array $opciones = []): array
    {
        $estado = $opciones['estado'] ?? 'Aprobado';
        $conFirmantes = $opciones['firmantes'] ?? true;
        $conPdf = $opciones['pdf'] ?? true;
        $horasAprobado = $opciones['horas'] ?? 10;

        $unit = $this->createUnit();
        $personaA = $this->createPersona();
        $personaB = $this->createPersona();

        $ctx = [
            'unit' => $unit,
            'personaA' => $personaA,
            'personaB' => $personaB,
            'rector' => $this->createUser('Administración', $this->createUnit()->id_unidad_academica, $personaA, true),
            'decano' => $this->createUser('Gestión', $unit->id_unidad_academica, $personaB),
            'gestion' => $this->createUser('Gestión', $unit->id_unidad_academica),
            'inactivo' => $this->createUser('Gestión', $unit->id_unidad_academica, null, false, false),
        ];

        $ctx['convenio'] = $this->crearConvenio($unit, 'Convenio a firmar', $estado, $ctx['gestion'], $conPdf);

        if ($conFirmantes) {
            DB::table('firma')->insert([
                ['id_instrumento' => $ctx['convenio']->id_instrumento, 'id_persona' => $personaA, 'fecha_hora_firma' => null],
                ['id_instrumento' => $ctx['convenio']->id_instrumento, 'id_persona' => $personaB, 'fecha_hora_firma' => null],
            ]);
        }

        if ($horasAprobado !== null && $estado === 'Aprobado') {
            $this->registrarAprobacion($ctx['convenio'], $horasAprobado, $ctx['gestion']);
        }

        return $ctx;
    }

    /** Crea otro convenio de la misma unidad con las mismas dos personas como firmantes. */
    private function convenioConFirmantes(array $ctx, string $titulo, string $estado, ?int $horasAprobado): Convenio
    {
        $convenio = $this->crearConvenio($ctx['unit'], $titulo, $estado, $ctx['gestion'], false);

        DB::table('firma')->insert([
            ['id_instrumento' => $convenio->id_instrumento, 'id_persona' => $ctx['personaA'], 'fecha_hora_firma' => null],
            ['id_instrumento' => $convenio->id_instrumento, 'id_persona' => $ctx['personaB'], 'fecha_hora_firma' => null],
        ]);

        if ($horasAprobado !== null) {
            $this->registrarAprobacion($convenio, $horasAprobado, $ctx['gestion']);
        }

        return $convenio;
    }

    private function crearConvenio(
        UnidadAcademica $unit,
        string $titulo,
        string $estado,
        Usuario $usuario,
        bool $conPdf = true
    ): Convenio {
        $instrumento = Instrumento::query()->create(['titulo' => $titulo, 'estado' => $estado]);

        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);

        if ($conPdf) {
            $this->crearPdf($convenio, $usuario);
        }

        return $convenio;
    }

    private function crearPdf(Convenio $convenio, Usuario $usuario): void
    {
        $documento = Documento::query()->create([
            'tipo' => 'Convenio',
            'titulo' => 'Documento del convenio',
            'id_instrumento' => $convenio->id_instrumento,
        ]);

        $ruta = 'documentos/'.$convenio->id_instrumento.'/convenio.pdf';

        Version::query()->create([
            'numero' => 1,
            'nombre_archivo' => 'convenio.pdf',
            'tamanio_bytes' => 9,
            'hash_sha256' => str_repeat('a', 64),
            'ruta_interna' => $ruta,
            'fecha_carga' => now(),
            'id_documento' => $documento->id_documento,
            'id_usuario' => $usuario->id_usuario,
        ]);

        Storage::disk('private')->put($ruta, '%PDF-demo');
    }

    private function registrarAprobacion(Convenio $convenio, int $horas, Usuario $usuario): void
    {
        HistorialEstado::query()->create([
            'estado_anterior' => 'Revisión',
            'estado_nuevo' => 'Aprobado',
            'fecha_hora' => now()->subHours($horas),
            'motivo' => null,
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    private function createPersona(): int
    {
        $this->number++;

        return DB::table('persona')->insertGetId([
            'nombre' => 'Persona '.$this->number,
            'apellido' => 'Firmante',
            'dni' => (string) (40000000 + $this->number),
        ]);
    }

    private function createUnit(): UnidadAcademica
    {
        $institution = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number++,
        ]);

        return UnidadAcademica::query()->create([
            'sigla' => 'FIR-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(
        string $roleName,
        int $unitId,
        ?int $personaId = null,
        bool $alcanceGlobal = false,
        bool $activo = true
    ): Usuario {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'firmador-'.$this->number++.'@example.com',
            'nombre' => 'Usuario '.$roleName.' '.$this->number,
            'password' => Hash::make('password-segura'),
            'activo' => $activo,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
            'alcance_global' => $alcanceGlobal,
            'id_persona' => $personaId,
        ]);
    }

    private function authenticate(Usuario $usuario): void
    {
        Auth::forgetGuards();
        $this->withToken($usuario->createToken('test')->plainTextToken);
    }
}
