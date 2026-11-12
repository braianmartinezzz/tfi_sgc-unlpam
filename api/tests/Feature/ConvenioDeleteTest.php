<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Usuario;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConvenioDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_a_draft_marco_and_preserves_audit_and_related_entities(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio($context, true);
        $referencedConvenio = $this->createConvenio($context, true);
        $personaId = DB::table('persona')->insertGetId([
            'nombre' => 'Firma',
            'apellido' => 'Prueba',
            'dni' => '12345678',
        ]);

        $convenio->personasFirmantes()->attach($personaId);
        HistorialEstado::query()->create([
            'estado_anterior' => null,
            'estado_nuevo' => 'Borrador',
            'motivo' => 'Creación del convenio',
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
        $auditId = DB::table('registro_auditoria')->insertGetId([
            'accion' => 'Creación',
            'detalle' => 'Registro que debe conservarse',
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
        $observationId = DB::table('observacion')->insertGetId([
            'contenido' => 'Observación asociada',
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
        $notificationId = DB::table('notificacion')->insertGetId([
            'tipo' => 'Aviso',
            'mensaje' => 'Notificación que debe conservarse',
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
        $documentId = DB::table('documento')->insertGetId([
            'tipo' => 'Convenio',
            'titulo' => 'Documento asociado',
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $versionId = DB::table('version')->insertGetId([
            'numero' => 1,
            'nombre_archivo' => 'convenio.pdf',
            'tamanio_bytes' => 100,
            'hash_sha256' => str_repeat('a', 64),
            'ruta_interna' => 'convenios/convenio.pdf',
            'id_documento' => $documentId,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
        DB::table('adenda')->insert([
            'id_instrumento' => $convenio->id_instrumento,
            'id_instrumento_modificado' => $referencedConvenio->id_instrumento,
        ]);
        DB::table('sustitucion')->insert([
            'id_instrumento' => $convenio->id_instrumento,
            'fecha' => '2026-01-01',
            'id_instrumento_sustituido' => $referencedConvenio->id_instrumento,
        ]);
        DB::table('renovacion')->insert([
            'id_instrumento' => $convenio->id_instrumento,
            'fecha' => '2026-01-01',
            'id_instrumento_renovado' => $referencedConvenio->id_instrumento,
        ]);

        $this->deleteJson('/api/convenios/'.$convenio->id_instrumento)
            ->assertOk()
            ->assertJsonPath('message', 'Convenio eliminado correctamente.');

        $this->assertDatabaseMissing('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseMissing('convenio', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseMissing('marco', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseMissing('historial_estado', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseMissing('observacion', [
            'id_observacion' => $observationId,
        ]);
        $this->assertDatabaseMissing('convenio_institucion', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseMissing('firma', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseMissing('documento', [
            'id_documento' => $documentId,
        ]);
        $this->assertDatabaseMissing('version', [
            'id_version' => $versionId,
        ]);
        $this->assertDatabaseMissing('adenda', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseMissing('sustitucion', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseMissing('renovacion', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseHas('registro_auditoria', [
            'id_registro' => $auditId,
            'id_instrumento' => null,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
        $this->assertDatabaseHas('notificacion', [
            'id_notificacion' => $notificationId,
            'id_instrumento' => null,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
        $this->assertDatabaseHas('persona', [
            'id_persona' => $personaId,
        ]);
        $this->assertDatabaseHas('institucion_firmante', [
            'id_institucion' => $context['institucion'],
        ]);
        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
    }

    public function test_it_deletes_a_draft_especifico_and_its_pasantia_without_deleting_people(): void
    {
        $context = $this->authenticate();
        $marco = $this->createConvenio($context, true);
        $especifico = $this->createConvenio(
            $context,
            false,
            $marco->id_instrumento
        );
        $studentId = $this->createPerson('Estudiante');
        $teacherId = $this->createPerson('Docente');
        $representativeId = $this->createPerson('Representante');

        DB::table('estudiante')->insert([
            'id_persona' => $studentId,
            'carrera' => 'Ingeniería',
        ]);
        DB::table('docente')->insert([
            'id_persona' => $teacherId,
        ]);
        DB::table('representante')->insert([
            'id_persona' => $representativeId,
            'id_institucion' => $context['institucion'],
        ]);
        $pasantiaId = DB::table('pasantia')->insertGetId([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
            'carga_horaria' => 20,
            'asignacion_estimulo' => 1000,
            'id_convenio_especifico' => $especifico->id_instrumento,
            'id_estudiante' => $studentId,
            'id_docente_guia' => $teacherId,
            'id_tutor_laboral' => $representativeId,
        ]);

        $this->deleteJson('/api/convenios/'.$especifico->id_instrumento)
            ->assertOk();

        $this->assertDatabaseMissing('pasantia', [
            'id_pasantia' => $pasantiaId,
        ]);
        $this->assertDatabaseMissing('especifico', [
            'id_instrumento' => $especifico->id_instrumento,
        ]);
        $this->assertDatabaseHas('marco', [
            'id_instrumento' => $marco->id_instrumento,
        ]);
        foreach ([$studentId, $teacherId, $representativeId] as $personId) {
            $this->assertDatabaseHas('persona', [
                'id_persona' => $personId,
            ]);
        }
        $this->assertDatabaseHas('estudiante', [
            'id_persona' => $studentId,
        ]);
        $this->assertDatabaseHas('docente', [
            'id_persona' => $teacherId,
        ]);
        $this->assertDatabaseHas('representante', [
            'id_persona' => $representativeId,
        ]);
    }

    public function test_it_returns_404_for_a_missing_convenio(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/convenios/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    public function test_it_rejects_deleting_a_convenio_that_is_not_a_draft(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio($context, true, null, 'Firmado');

        $this->deleteJson('/api/convenios/'.$convenio->id_instrumento)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Solo se pueden eliminar convenios en estado Borrador.'
            );

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'estado' => 'Firmado',
        ]);
    }

    public function test_it_rejects_deleting_a_marco_with_linked_especificos_before_changes(): void
    {
        $context = $this->authenticate();
        $marco = $this->createConvenio($context, true);
        $especifico = $this->createConvenio(
            $context,
            false,
            $marco->id_instrumento
        );
        $auditId = DB::table('registro_auditoria')->insertGetId([
            'accion' => 'Referencia',
            'id_instrumento' => $marco->id_instrumento,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);

        $this->deleteJson('/api/convenios/'.$marco->id_instrumento)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se puede eliminar un Convenio Marco que posee Convenios Específicos vinculados.'
            );

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $marco->id_instrumento,
        ]);
        $this->assertDatabaseHas('especifico', [
            'id_instrumento' => $especifico->id_instrumento,
            'id_convenio_marco' => $marco->id_instrumento,
        ]);
        $this->assertDatabaseHas('registro_auditoria', [
            'id_registro' => $auditId,
            'id_instrumento' => $marco->id_instrumento,
        ]);
    }

    public function test_it_rejects_deleting_a_convenio_referenced_by_another_instrument(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio($context, true);
        $adendaInstrumento = Instrumento::query()->create([
            'titulo' => 'Adenda de prueba',
            'estado' => 'Borrador',
        ]);
        DB::table('adenda')->insert([
            'id_instrumento' => $adendaInstrumento->id_instrumento,
            'id_instrumento_modificado' => $convenio->id_instrumento,
        ]);

        $this->deleteJson('/api/convenios/'.$convenio->id_instrumento)
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se puede eliminar el convenio porque está referenciado por otros instrumentos.'
            );
    }

    public function test_it_rolls_back_all_deletion_changes_when_a_database_error_occurs(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio($context, true);
        $auditId = DB::table('registro_auditoria')->insertGetId([
            'accion' => 'Rollback',
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);

        DB::listen(function (QueryExecuted $query) {
            if (preg_match('/^\s*delete\s+from\s+[`"]?instrumento\b/i', $query->sql) === 1) {
                throw new \RuntimeException('Error simulado al eliminar el instrumento.');
            }
        });

        $this->deleteJson('/api/convenios/'.$convenio->id_instrumento)
            ->assertInternalServerError()
            ->assertJsonPath(
                'message',
                'Ocurrió un error inesperado al eliminar el convenio.'
            );

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseHas('convenio', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseHas('marco', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseHas('convenio_institucion', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseHas('registro_auditoria', [
            'id_registro' => $auditId,
            'id_instrumento' => $convenio->id_instrumento,
        ]);
    }

    public function test_delete_requires_authentication(): void
    {
        $this->deleteJson('/api/convenios/1')->assertUnauthorized();
    }

    private function createConvenio(
        array $context,
        bool $esMarco,
        ?int $idConvenioMarco = null,
        string $estado = 'Borrador'
    ): Convenio {
        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio para eliminación',
            'estado' => $estado,
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $context['unidad'],
        ]);

        if ($esMarco) {
            Marco::query()->create([
                'id_instrumento' => $instrumento->id_instrumento,
            ]);
        } else {
            Especifico::query()->create([
                'id_instrumento' => $instrumento->id_instrumento,
                'id_convenio_marco' => $idConvenioMarco,
            ]);
        }

        $convenio->institucionesFirmantes()->attach($context['institucion']);

        return $convenio;
    }

    private function createPerson(string $name): int
    {
        return DB::table('persona')->insertGetId([
            'nombre' => $name,
            'apellido' => 'Prueba',
            'dni' => (string) random_int(10000000, 99999999),
        ]);
    }

    private function authenticate(): array
    {
        $rolId = DB::table('rol')->insertGetId([
            'nombre' => 'Eliminación',
        ]);
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de eliminación',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-DEL-'.random_int(1000, 9999),
            'nombre' => 'Unidad de eliminación',
            'id_institucion' => $institutionId,
        ]);
        $usuario = Usuario::query()->create([
            'mail' => 'delete-'.random_int(1000, 9999).'@example.com',
            'nombre' => 'Usuario de eliminación',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unitId,
        ]);

        $this->withToken($usuario->createToken('delete-test')->plainTextToken);

        return [
            'institucion' => $institutionId,
            'unidad' => $unitId,
            'usuario' => $usuario,
        ];
    }
}
