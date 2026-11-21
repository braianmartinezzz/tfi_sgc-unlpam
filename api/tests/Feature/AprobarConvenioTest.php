<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Observacion;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AprobarConvenioTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/convenios/1/aprobar')->assertUnauthorized();
    }

    public function test_approves_a_convenio_in_revision_and_registers_history_and_audit(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")
            ->assertOk()
            ->assertJsonPath('message', 'Convenio aprobado correctamente.')
            ->assertJsonPath('data.instrumento.estado', 'Aprobado');

        $this->assertSame('Aprobado', $ctx['convenio']->instrumento->fresh()->estado);

        $this->assertDatabaseHas('historial_estado', [
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'estado_anterior' => 'Revisión',
            'estado_nuevo' => 'Aprobado',
            'id_usuario' => $ctx['reviewer']->id_usuario,
        ]);

        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'APROBACION_DE_CONVENIO',
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'id_usuario' => $ctx['reviewer']->id_usuario,
        ]);
    }

    public function test_notifies_active_gestion_users_of_the_unit_to_send_the_convenio_to_the_firmantes(): void
    {
        $ctx = $this->createContext();
        $firmante = InstitucionFirmante::query()->create(['razon_social' => 'Empresa Firmante SA']);
        $ctx['convenio']->institucionesFirmantes()->attach($firmante->id_institucion);
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")->assertOk();

        $mensaje = 'El convenio #'.$ctx['convenio']->id_instrumento
            .' fue aprobado y está listo para ser enviado a los firmantes.'
            .' Firmantes: Empresa Firmante SA.'
            .' Una vez firmado, cargue el documento firmado y registre la firma.';

        foreach ([$ctx['manager'], $ctx['secondManager']] as $gestor) {
            $this->assertDatabaseHas('notificacion', [
                'tipo' => 'CONVENIO_APROBADO',
                'id_usuario' => $gestor->id_usuario,
                'id_instrumento' => $ctx['convenio']->id_instrumento,
                'mensaje' => $mensaje,
            ]);
        }

        $this->assertDatabaseMissing('notificacion', ['id_usuario' => $ctx['inactiveManager']->id_usuario]);
        $this->assertDatabaseMissing('notificacion', ['id_usuario' => $ctx['otherUnitManager']->id_usuario]);
        $this->assertDatabaseCount('notificacion', 2);
    }

    public function test_resolved_observaciones_do_not_block_the_approval(): void
    {
        $ctx = $this->createContext();
        $this->createObservacion($ctx, 'Resuelta');
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")->assertOk();
    }

    public function test_rejects_a_convenio_with_pending_observaciones(): void
    {
        $ctx = $this->createContext();
        $this->createObservacion($ctx, 'Pendiente');
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")
            ->assertStatus(409)
            ->assertJsonPath('message', 'El convenio tiene observaciones pendientes.');

        $this->assertSame('Revisión', $ctx['convenio']->instrumento->fresh()->estado);
        $this->assertDatabaseCount('historial_estado', 0);
        $this->assertDatabaseCount('notificacion', 0);
    }

    public function test_requires_a_document_with_a_pdf_version(): void
    {
        $sinDocumentos = $this->createContext('Revisión', true, null);
        $this->authenticate($sinDocumentos['reviewer']);

        $this->postJson("/api/convenios/{$sinDocumentos['convenio']->id_instrumento}/aprobar")
            ->assertStatus(409)
            ->assertJsonPath('message', 'El convenio debe tener al menos un documento con una versión PDF.');
        $this->assertSame('Revisión', $sinDocumentos['convenio']->instrumento->fresh()->estado);

        $soloWord = $this->createContext('Revisión', true, 'documentos/1/convenio.docx');
        $this->authenticate($soloWord['reviewer']);

        $this->postJson("/api/convenios/{$soloWord['convenio']->id_instrumento}/aprobar")
            ->assertStatus(409)
            ->assertJsonPath('message', 'El convenio debe tener al menos un documento con una versión PDF.');
        $this->assertSame('Revisión', $soloWord['convenio']->instrumento->fresh()->estado);
    }

    public function test_accepts_a_pdf_extension_in_uppercase(): void
    {
        $ctx = $this->createContext('Revisión', true, 'documentos/1/CONVENIO.PDF');
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")->assertOk();
    }

    public function test_only_convenios_in_revision_can_be_approved(): void
    {
        foreach (['Borrador', 'EnCorreccion', 'Aprobado', 'Firmado', 'Rechazado', 'Archivado'] as $estado) {
            $ctx = $this->createContext($estado);
            $this->authenticate($ctx['reviewer']);

            $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")
                ->assertStatus(409)
                ->assertJsonPath('message', 'El convenio no se encuentra en estado Revisión.');

            $this->assertSame($estado, $ctx['convenio']->instrumento->fresh()->estado);
        }
    }

    public function test_fails_without_changes_when_there_are_no_gestion_users_to_notify(): void
    {
        $ctx = $this->createContext('Revisión', false);
        $this->authenticate($ctx['reviewer']);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")
            ->assertStatus(409);

        $this->assertSame('Revisión', $ctx['convenio']->instrumento->fresh()->estado);
        $this->assertDatabaseCount('historial_estado', 0);
        $this->assertDatabaseCount('notificacion', 0);
    }

    public function test_only_revision_and_administracion_can_approve(): void
    {
        $ctx = $this->createContext();
        $consulta = $this->createUser('Consulta', $ctx['unit']->id_unidad_academica);
        $admin = $this->createUser('Administración', $this->createUnit()->id_unidad_academica);

        foreach ([$ctx['manager'], $consulta] as $usuario) {
            $this->authenticate($usuario);

            $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")
                ->assertForbidden();
        }

        $this->assertSame('Revisión', $ctx['convenio']->instrumento->fresh()->estado);

        $this->authenticate($admin);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")->assertOk();
    }

    public function test_revision_is_limited_to_its_unit_unless_it_has_global_scope(): void
    {
        $ctx = $this->createContext();
        $otraUnidad = $this->createUnit();
        $revisorAjeno = $this->createUser('Revisión', $otraUnidad->id_unidad_academica);
        $revisorGlobal = $this->createUser('Revisión', $otraUnidad->id_unidad_academica, true);

        $this->authenticate($revisorAjeno);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")->assertForbidden();
        $this->assertSame('Revisión', $ctx['convenio']->instrumento->fresh()->estado);

        $this->authenticate($revisorGlobal);

        $this->postJson("/api/convenios/{$ctx['convenio']->id_instrumento}/aprobar")->assertOk();
    }

    public function test_returns_not_found_for_a_missing_convenio(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);

        $this->postJson('/api/convenios/999999/aprobar')
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    private function createContext(
        string $estado = 'Revisión',
        bool $withManagers = true,
        ?string $pdfPath = 'documentos/1/convenio.pdf'
    ): array {
        $unit = $this->createUnit();
        $otherUnit = $this->createUnit();

        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio '.$this->number++,
            'estado' => $estado,
        ]);

        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);

        $context = [
            'unit' => $unit,
            'convenio' => $convenio,
            'reviewer' => $this->createUser('Revisión', $unit->id_unidad_academica),
            'otherUnitManager' => $this->createUser('Gestión', $otherUnit->id_unidad_academica),
        ];

        if ($withManagers) {
            $context['manager'] = $this->createUser('Gestión', $unit->id_unidad_academica);
            $context['secondManager'] = $this->createUser('Gestión', $unit->id_unidad_academica);
            $context['inactiveManager'] = $this->createUser('Gestión', $unit->id_unidad_academica, false, false);
        }

        if ($pdfPath !== null) {
            $documento = Documento::query()->create([
                'tipo' => 'Convenio',
                'titulo' => 'Documento del convenio',
                'id_instrumento' => $instrumento->id_instrumento,
            ]);

            Version::query()->create([
                'numero' => 1,
                'nombre_archivo' => basename($pdfPath),
                'tamanio_bytes' => 1024,
                'hash_sha256' => str_repeat('a', 64),
                'ruta_interna' => $pdfPath,
                'fecha_carga' => now(),
                'id_documento' => $documento->id_documento,
                'id_usuario' => $context['reviewer']->id_usuario,
            ]);
        }

        return $context;
    }

    private function createObservacion(array $ctx, string $estado): Observacion
    {
        $resuelta = $estado === 'Resuelta';

        return Observacion::query()->create([
            'contenido' => 'Actualizar la cláusula de vigencia.',
            'fecha' => now(),
            'estado_tramite' => $estado,
            'resolucion' => $resuelta ? 'Aceptada' : null,
            'respuesta' => $resuelta ? 'Corregido.' : null,
            'fecha_resolucion' => $resuelta ? now() : null,
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'id_usuario' => $ctx['reviewer']->id_usuario,
        ]);
    }

    private function createUnit(): UnidadAcademica
    {
        $institution = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number++,
        ]);

        return UnidadAcademica::query()->create([
            'sigla' => 'APR-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(
        string $roleName,
        int $unitId,
        bool $alcanceGlobal = false,
        bool $activo = true
    ): Usuario {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'aprobar-'.$this->number++.'@example.com',
            'nombre' => 'Usuario '.$roleName.' '.$this->number,
            'password' => Hash::make('password-segura'),
            'activo' => $activo,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
            'alcance_global' => $alcanceGlobal,
        ]);
    }

    private function authenticate(Usuario $usuario): void
    {
        Auth::forgetGuards();
        $this->withToken($usuario->createToken('test')->plainTextToken);
    }
}
