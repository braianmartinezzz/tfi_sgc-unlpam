<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Version;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Usuarios de demostración para quienes intervienen en la firma de convenios.
 *
 * El sistema tiene cuatro roles (Administración, Gestión, Revisión y Consulta);
 * "Rector/a", "Decano/a" y "Representante legal" no son roles nuevos, sino
 * perfiles que se resuelven con esos roles y con el alcance de cada usuario:
 *
 *  - Rector/a      -> Administración con alcance global: registra la firma de
 *                     cualquier convenio aprobado (marcos institucionales, adendas).
 *  - Decano/a      -> Gestión de su unidad académica: registra la firma de los
 *                     convenios aprobados de su Facultad (regla del Gate
 *                     'enviar-convenio-a-revision').
 *  - Representante -> Consulta sin alcance global: solo lee los convenios de su
 *                     unidad que ya están firmados, rescindidos o archivados. No
 *                     firma: la firma es un registro administrativo que hace
 *                     personal interno (el TP excluye la firma digital).
 *
 * El Rector/a y los Decanos además se vinculan a una persona firmante (usuario.id_persona):
 * así el firmador de la app los reconoce como firmantes de los convenios que se les asignen.
 *
 * Requiere haber corrido antes DatabaseSeeder (usa sus roles y unidades).
 * Se puede repetir sin duplicar usuarios.
 *
 *   php artisan db:seed --class=AutoridadesFirmantesSeeder
 */
class AutoridadesFirmantesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('AutoridadesFirmantesSeeder no se ejecutó en producción.');

            return;
        }

        $unidadUno = UnidadAcademica::query()->where('sigla', 'UA-TEST-01')->firstOrFail();
        $unidadDos = UnidadAcademica::query()->where('sigla', 'UA-TEST-02')->firstOrFail();

        $rolAdministracion = Rol::query()->where('nombre', 'Administración')->firstOrFail();
        $rolGestion = Rol::query()->where('nombre', 'Gestión')->firstOrFail();
        $rolConsulta = Rol::query()->where('nombre', 'Consulta')->firstOrFail();

        $usuarios = [
            'rector@unlpam.edu.ar' => [
                'nombre' => 'Rector/a Demo',
                'password' => 'rector123',
                'id_rol' => $rolAdministracion->id_rol,
                'id_unidad' => $unidadUno->id_unidad_academica,
                'alcance_global' => true,
                'persona' => ['dni' => '10000001', 'nombre' => 'Rector/a', 'apellido' => 'Demo'],
            ],
            'decano.ua1@unlpam.edu.ar' => [
                'nombre' => 'Decano/a Demo (Unidad 1)',
                'password' => 'decano123',
                'id_rol' => $rolGestion->id_rol,
                'id_unidad' => $unidadUno->id_unidad_academica,
                'alcance_global' => false,
                'persona' => ['dni' => '10000002', 'nombre' => 'Decano/a Unidad 1', 'apellido' => 'Demo'],
            ],
            'decano.ua2@unlpam.edu.ar' => [
                'nombre' => 'Decano/a Demo (Unidad 2)',
                'password' => 'decano123',
                'id_rol' => $rolGestion->id_rol,
                'id_unidad' => $unidadDos->id_unidad_academica,
                'alcance_global' => false,
                'persona' => ['dni' => '10000003', 'nombre' => 'Decano/a Unidad 2', 'apellido' => 'Demo'],
            ],
            'representante.legal@empresademo.com.ar' => [
                'nombre' => 'Representante Legal Autorizado Demo',
                'password' => 'representante123',
                'id_rol' => $rolConsulta->id_rol,
                'id_unidad' => $unidadUno->id_unidad_academica,
                'alcance_global' => false,
            ],
        ];

        foreach ($usuarios as $mail => $datos) {
            $idPersona = isset($datos['persona'])
                ? Persona::query()->updateOrCreate(
                    ['dni' => $datos['persona']['dni']],
                    ['nombre' => $datos['persona']['nombre'], 'apellido' => $datos['persona']['apellido']]
                )->id_persona
                : null;

            Usuario::query()->updateOrCreate(
                ['mail' => $mail],
                [
                    'nombre' => $datos['nombre'],
                    'password' => Hash::make($datos['password']),
                    'activo' => true,
                    'id_rol' => $datos['id_rol'],
                    'id_unidad' => $datos['id_unidad'],
                    'alcance_global' => $datos['alcance_global'],
                    'id_persona' => $idPersona,
                ]
            );
        }

        $this->crearConveniosDemo($unidadUno, $unidadDos);
    }

    private function crearConveniosDemo(UnidadAcademica $unidadUno, UnidadAcademica $unidadDos): void
    {
        $convenios = [
            ['titulo' => 'Convenio Marco pendiente de firma - Unidad 1', 'unidad' => $unidadUno, 'expediente' => 'FIRMADOR-DEMO-001', 'firmantes' => ['rector@unlpam.edu.ar', 'decano.ua1@unlpam.edu.ar']],
            ['titulo' => 'Convenio Específico pendiente de firma - Unidad 2', 'unidad' => $unidadDos, 'expediente' => 'FIRMADOR-DEMO-002', 'firmantes' => ['rector@unlpam.edu.ar', 'decano.ua2@unlpam.edu.ar']],
        ];

        foreach ($convenios as $indice => $datos) {
            $instrumento = Instrumento::query()->updateOrCreate(
                ['expediente' => $datos['expediente']],
                [
                    'titulo' => $datos['titulo'],
                    'objeto' => 'Demostración del flujo de firma de convenios desde la aplicación móvil.',
                    'estado' => 'Aprobado',
                    'inicio' => now()->subDays(3)->toDateString(),
                    'vencimiento' => now()->addDays(365)->toDateString(),
                    'dias_preaviso' => 30,
                    'resolucion' => 'RES-FIRMADOR-DEMO-'.($indice + 1),
                ]
            );

            $convenio = Convenio::query()->updateOrCreate(
                ['id_instrumento' => $instrumento->id_instrumento],
                [
                    'tipo' => 'Colaboración',
                    'alcance' => 'Demostración del firmador de convenios.',
                    'obligaciones' => 'Las partes deberán registrar las firmas correspondientes.',
                    'confidencialidad' => false,
                    'id_unidad_academica' => $datos['unidad']->id_unidad_academica,
                ]
            );

            DB::table('firma')
                ->where('id_instrumento', $instrumento->id_instrumento)
                ->delete();

            foreach ($datos['firmantes'] as $email) {
                $usuario = Usuario::query()->where('mail', $email)->firstOrFail();
                if ($usuario->id_persona === null) {
                    throw new \RuntimeException("El usuario {$email} no tiene una persona vinculada.");
                }

                DB::table('firma')->insert([
                    'id_instrumento' => $instrumento->id_instrumento,
                    'id_persona' => $usuario->id_persona,
                    'fecha_hora_firma' => null,
                ]);
            }

            HistorialEstado::query()
                ->where('id_instrumento', $instrumento->id_instrumento)
                ->delete();

            $actor = Usuario::query()->where('mail', 'gestion@unlpam.edu.ar')->firstOrFail();
            $revision = Usuario::query()->where('mail', 'revision.global@unlpam.edu.ar')->first();

            HistorialEstado::query()->create([
                'estado_anterior' => 'Borrador',
                'estado_nuevo' => 'Revisión',
                'fecha_hora' => now()->subDays(2),
                'motivo' => 'Convenio creado para demostrar el firmador.',
                'id_instrumento' => $instrumento->id_instrumento,
                'id_usuario' => $actor->id_usuario,
            ]);
            HistorialEstado::query()->create([
                'estado_anterior' => 'Revisión',
                'estado_nuevo' => 'Aprobado',
                'fecha_hora' => now()->subHours($indice === 0 ? 100 : 5),
                'motivo' => 'Aprobación de demostración.',
                'id_instrumento' => $instrumento->id_instrumento,
                'id_usuario' => $revision?->id_usuario ?? $actor->id_usuario,
            ]);

            $this->crearPdfDemo($instrumento, $actor);
            $convenio->institucionesFirmantes()->syncWithoutDetaching([
                $datos['unidad']->id_institucion,
            ]);
        }

        $this->command?->info('Se crearon/actualizaron convenios aprobados asignados al firmador.');
    }

    private function crearPdfDemo(Instrumento $instrumento, Usuario $usuario): void
    {
        $documento = Documento::query()->updateOrCreate(
            ['id_instrumento' => $instrumento->id_instrumento, 'tipo' => 'Convenio'],
            [
                'titulo' => 'Documento para firma - '.$instrumento->titulo,
                'es_documento_firmado' => false,
            ]
        );

        $ruta = 'documentos/'.$instrumento->id_instrumento.'/firmador-demo.pdf';
        $texto = $instrumento->titulo."\n\nDocumento de demostracion del firmador.\nEste convenio esta aprobado y tiene firmas pendientes.";

        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
            ."4 0 obj\n<< /Length ".strlen($texto)." >>\nstream\nBT /F1 14 Tf 72 720 Td (".str_replace(['(', ')'], ['\\(', '\\)'], $texto).") Tj ET\nendstream\nendobj\n"
            ."5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
            ."xref\n0 6\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R /Size 6 >>\nstartxref\n0\n%%EOF";

        Storage::disk('private')->put($ruta, $pdf);

        Version::query()->updateOrCreate(
            ['id_documento' => $documento->id_documento, 'numero' => 1],
            [
                'nombre_archivo' => 'firmador-demo.pdf',
                'tamanio_bytes' => strlen($pdf),
                'hash_sha256' => hash('sha256', $pdf),
                'ruta_interna' => $ruta,
                'fecha_carga' => now(),
                'id_usuario' => $usuario->id_usuario,
            ]
        );
    }
}
