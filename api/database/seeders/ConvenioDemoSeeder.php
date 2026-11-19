<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\HistorialEstado;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Carga convenios ficticios para demostración.
 *
 * Ejecutar con: php artisan db:seed --class=ConvenioDemoSeeder
 */
class ConvenioDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('ConvenioDemoSeeder no se ejecutó en producción.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        $unidadUno = UnidadAcademica::query()
            ->where('sigla', 'FCH')
            ->firstOrFail();
        $unidadDos = UnidadAcademica::query()
            ->where('sigla', 'FI')
            ->firstOrFail();
        $rolRevision = Rol::query()->where('nombre', 'Revisión')->firstOrFail();
        $rolConsulta = Rol::query()->where('nombre', 'Consulta')->firstOrFail();

        $usuarios = [
            'revision.global@unlpam.edu.ar' => [
                'nombre' => 'Usuario de Revisión Global Demo',
                'password' => 'revision123',
                'id_rol' => $rolRevision->id_rol,
                'id_unidad' => $unidadUno->id_unidad_academica,
                'alcance_global' => true,
            ],
            'revision.unidad@unlpam.edu.ar' => [
                'nombre' => 'Usuario de Revisión por Unidad Demo',
                'password' => 'revision123',
                'id_rol' => $rolRevision->id_rol,
                'id_unidad' => $unidadUno->id_unidad_academica,
                'alcance_global' => false,
            ],
            'consulta.global@unlpam.edu.ar' => [
                'nombre' => 'Usuario de Consulta Global Demo',
                'password' => 'consulta123',
                'id_rol' => $rolConsulta->id_rol,
                'id_unidad' => $unidadDos->id_unidad_academica,
                'alcance_global' => true,
            ],
            'consulta.unidad@unlpam.edu.ar' => [
                'nombre' => 'Usuario de Consulta por Unidad Demo',
                'password' => 'consulta123',
                'id_rol' => $rolConsulta->id_rol,
                'id_unidad' => $unidadDos->id_unidad_academica,
                'alcance_global' => false,
            ],
        ];

        foreach ($usuarios as $mail => $datos) {
            Usuario::query()->updateOrCreate(
                ['mail' => $mail],
                [
                    'nombre' => $datos['nombre'],
                    'password' => Hash::make($datos['password']),
                    'activo' => true,
                    'id_rol' => $datos['id_rol'],
                    'id_unidad' => $datos['id_unidad'],
                    'alcance_global' => $datos['alcance_global'],
                ]
            );
        }

        $institucionesExternas = [
            InstitucionFirmante::query()->firstOrCreate(
                ['razon_social' => 'Empresa Demo 1 S.A.'],
                ['cuit' => '30-00000001-1', 'tipo_institucion' => 'Empresa']
            ),
            InstitucionFirmante::query()->firstOrCreate(
                ['razon_social' => 'Fundación Ejemplo 2'],
                ['cuit' => '30-00000002-2', 'tipo_institucion' => 'Fundación']
            ),
            InstitucionFirmante::query()->firstOrCreate(
                ['razon_social' => 'Cooperativa Demo 3'],
                ['cuit' => '30-00000003-3', 'tipo_institucion' => 'Cooperativa']
            ),
            InstitucionFirmante::query()->firstOrCreate(
                ['razon_social' => 'Asociación Ficticia 4'],
                ['cuit' => '30-00000004-4', 'tipo_institucion' => 'Asociación']
            ),
            InstitucionFirmante::query()->firstOrCreate(
                ['razon_social' => 'Instituto de Ensayo Demo 5'],
                ['cuit' => '30-00000005-5', 'tipo_institucion' => 'Instituto']
            ),
        ];

        $revisionGlobal = Usuario::query()
            ->where('mail', 'revision.global@unlpam.edu.ar')
            ->firstOrFail();
        $gestion = Usuario::query()
            ->where('mail', 'gestion@unlpam.edu.ar')
            ->firstOrFail();
        $administracion = Usuario::query()
            ->where('mail', 'admin@unlpam.edu.ar')
            ->firstOrFail();

        $casos = [
            ['FCH', 'marco', null, 'Firmado', 400, false],
            ['FCH', 'marco', null, 'Firmado', 25, true],
            ['FCH', 'especifico', 1, 'Firmado', 300, false],
            ['FCH', 'especifico', 1, 'Aprobado', 365, false],
            ['FCH', 'especifico', 2, 'Revisión', null, false],
            ['FCH', 'marco', null, 'Borrador', null, false],
            ['FCH', 'especifico', 1, 'Borrador', null, false],
            ['FCH', 'especifico', 2, 'EnCorreccion', null, false],
            ['FCH', 'marco', null, 'Rescindido', 90, false],
            ['FCH', 'especifico', 2, 'Archivado', -60, false],
            ['FCH', 'marco', null, 'Rechazado', null, false],
            ['FCH', 'especifico', 1, 'Firmado', null, false],
            ['FI', 'marco', null, 'Firmado', 200, false],
            ['FI', 'especifico', 13, 'Firmado', 10, true],
            ['FI', 'marco', null, 'Aprobado', 365, false],
            ['FI', 'especifico', 13, 'Revisión', null, false],
            ['FI', 'marco', null, 'Borrador', null, false],
            ['FI', 'especifico', 13, 'Rescindido', 120, false],
            ['FI', 'marco', null, 'Archivado', -30, false],
            ['FI', 'especifico', 13, 'EnCorreccion', null, false],
        ];

        $marcosPorNumero = [];
        $hoy = now();

        foreach ($casos as $indice => [$siglaUnidad, $clase, $marcoNumero, $estado, $diasVencimiento, $confidencialidad]) {
            $numero = $indice + 1;
            $numeroExpediente = sprintf('%03d', $numero);
            $unidad = $siglaUnidad === 'FCH' ? $unidadUno : $unidadDos;
            $institucionExterna = $institucionesExternas[$indice % count($institucionesExternas)];
            $fechaVencimiento = $diasVencimiento === null
                ? null
                : $hoy->copy()->addDays($diasVencimiento)->toDateString();
            $fechaFirma = in_array($estado, ['Firmado', 'Rescindido', 'Archivado'], true)
                ? $hoy->copy()->subDays(90 + (($indice % 5) * 20))->toDateString()
                : null;
            $resolucion = in_array($estado, ['Aprobado', 'Firmado', 'Rescindido', 'Archivado'], true)
                ? "RES-DEMO-{$numeroExpediente}/2026"
                : null;
            $titulo = $clase === 'marco'
                ? "Convenio Marco de Colaboración con {$institucionExterna->razon_social} ({$numeroExpediente})"
                : "Convenio Específico de Colaboración {$numeroExpediente} con {$institucionExterna->razon_social}";

            $instrumento = Instrumento::query()->updateOrCreate(
                ['expediente' => "DEMO-{$numeroExpediente}"],
                [
                    'titulo' => $titulo,
                    'objeto' => "Desarrollar actividades de colaboración académica y técnica ({$numeroExpediente}).",
                    'estado' => $estado,
                    'fecha_firma' => $fechaFirma,
                    'inicio' => $fechaFirma,
                    'vencimiento' => $fechaVencimiento,
                    'dias_preaviso' => $numero % 2 === 0 ? 60 : 30,
                    'resolucion' => $resolucion,
                ]
            );

            $convenio = Convenio::query()->updateOrCreate(
                ['id_instrumento' => $instrumento->id_instrumento],
                [
                    'tipo' => 'Colaboración',
                    'alcance' => "Colaboración entre la unidad y {$institucionExterna->razon_social}.",
                    'obligaciones' => 'Las partes designarán referentes y compartirán los avances acordados.',
                    'condiciones_recision' => 'Cualquiera de las partes podrá solicitar la rescisión con aviso previo.',
                    'marco_legal' => 'Conforme a la normativa institucional vigente.',
                    'condicion_renovacion' => 'La renovación requiere acuerdo escrito de ambas partes.',
                    'confidencialidad' => $confidencialidad,
                    'id_unidad_academica' => $unidad->id_unidad_academica,
                ]
            );

            if ($clase === 'marco') {
                $marco = Marco::query()->firstOrCreate([
                    'id_instrumento' => $instrumento->id_instrumento,
                ]);
                $marcosPorNumero[$numero] = $marco;
            } else {
                $marco = $marcosPorNumero[$marcoNumero] ?? Instrumento::query()
                    ->where('expediente', sprintf('DEMO-%03d', $marcoNumero))
                    ->firstOrFail()
                    ->convenio
                    ->marco;

                Especifico::query()->updateOrCreate(
                    ['id_instrumento' => $instrumento->id_instrumento],
                    ['id_convenio_marco' => $marco->id_instrumento]
                );
            }

            $convenio->institucionesFirmantes()->syncWithoutDetaching([
                $unidad->id_institucion,
                $institucionExterna->id_institucion,
            ]);

            $this->recrearHistorial(
                $instrumento->id_instrumento,
                $estado,
                $siglaUnidad === 'FCH' ? $gestion : $administracion,
                $revisionGlobal,
                $administracion,
                $hoy
            );
        }
    }

    private function recrearHistorial(
        int $idInstrumento,
        string $estadoFinal,
        Usuario $usuarioCreador,
        Usuario $revisionGlobal,
        Usuario $administracion,
        $hoy
    ): void {
        $eventos = [
            ['Borrador', $usuarioCreador, 'Creación del convenio de demostración.'],
        ];

        if ($estadoFinal !== 'Borrador') {
            $eventos[] = ['Revisión', $revisionGlobal, 'Enviado a revisión.'];
        }

        if (in_array($estadoFinal, ['EnCorreccion', 'Rechazado'], true)) {
            $motivo = $estadoFinal === 'EnCorreccion'
                ? 'Observaciones pendientes.'
                : 'No cumple requisitos.';
            $eventos[] = [$estadoFinal, $revisionGlobal, $motivo];
        } elseif (! in_array($estadoFinal, ['Borrador', 'Revisión'], true)) {
            $eventos[] = ['Aprobado', $revisionGlobal, 'Requisitos aprobados.'];

            if ($estadoFinal !== 'Aprobado') {
                $eventos[] = ['Firmado', $administracion, 'Convenio firmado.'];
            }

            if (in_array($estadoFinal, ['Rescindido', 'Archivado'], true)) {
                $motivo = $estadoFinal === 'Rescindido'
                    ? 'Rescisión anticipada.'
                    : 'Vencimiento del plazo.';
                $eventos[] = [$estadoFinal, $administracion, $motivo];
            }
        }

        DB::transaction(function () use ($idInstrumento, $eventos, $hoy): void {
            HistorialEstado::query()
                ->where('id_instrumento', $idInstrumento)
                ->delete();

            $estadoAnterior = null;

            foreach ($eventos as $indice => [$estadoNuevo, $usuario, $motivo]) {
                HistorialEstado::query()->create([
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo' => $estadoNuevo,
                    'fecha_hora' => $hoy->copy()->subDays(90 - ($indice * 15)),
                    'motivo' => $motivo,
                    'id_instrumento' => $idInstrumento,
                    'id_usuario' => $usuario->id_usuario,
                ]);

                $estadoAnterior = $estadoNuevo;
            }
        });
    }
}
