<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogoPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_and_consulta_cannot_write_any_catalog(): void
    {
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-LECTURA',
            'nombre' => 'Unidad para lectores',
            'id_institucion' => $institutionId,
        ]);
        $personId = DB::table('persona')->insertGetId([
            'nombre' => 'Representante',
            'apellido' => 'Existente',
            'dni' => '100000001',
        ]);
        DB::table('representante')->insert([
            'id_persona' => $personId,
            'id_institucion' => $institutionId,
        ]);

        foreach (['Revisión', 'Consulta'] as $roleName) {
            $roleId = DB::table('rol')->insertGetId(['nombre' => $roleName]);
            $roleMail = $roleName === 'Consulta' ? 'consulta' : 'revision';
            $reader = Usuario::query()->create([
                'mail' => $roleMail.'-catalogos@example.com',
                'nombre' => $roleName,
                'password' => Hash::make('password-segura'),
                'activo' => true,
                'id_rol' => $roleId,
                'id_unidad' => $unitId,
            ]);
            Sanctum::actingAs($reader);

            $this->postJson('/api/instituciones-firmantes', [
                'razon_social' => 'Nueva institución '.$roleName,
            ])->assertForbidden();
            $this->putJson('/api/instituciones-firmantes/'.$institutionId, [
                'domicilio_legal' => 'Domicilio no autorizado',
            ])->assertForbidden();
            $this->patchJson('/api/instituciones-firmantes/'.$institutionId.'/estado', [
                'activo' => false,
            ])->assertForbidden();

            $this->postJson('/api/unidades-academicas', [
                'sigla' => 'UA-'.($roleName === 'Consulta' ? 'CONSULTA' : 'REVISION'),
                'nombre' => 'Nueva unidad '.$roleName,
                'id_institucion' => $institutionId,
            ])->assertForbidden();
            $this->putJson('/api/unidades-academicas/'.$unitId, [
                'nombre' => 'Unidad no autorizada',
            ])->assertForbidden();
            $this->patchJson('/api/unidades-academicas/'.$unitId.'/estado', [
                'activo' => false,
            ])->assertForbidden();

            $this->postJson('/api/representantes', [
                'nombre' => 'Nuevo',
                'apellido' => 'Representante',
                'dni' => $roleName === 'Consulta' ? '100000003' : '100000002',
                'id_institucion' => $institutionId,
            ])->assertForbidden();
            $this->putJson('/api/representantes/'.$personId, [
                'cargo' => 'Cargo no autorizado',
            ])->assertForbidden();
            $this->patchJson('/api/representantes/'.$personId.'/estado', [
                'activo' => false,
            ])->assertForbidden();
        }
    }
}
