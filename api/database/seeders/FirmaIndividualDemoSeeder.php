<?php

namespace Database\Seeders;

use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Persona;
use App\Models\Representante;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class FirmaIndividualDemoSeeder extends Seeder
{
    private const EXPEDIENTES = ['DEMO-004', 'DEMO-015'];

    private const DNI = '99000001';

    private const NOMBRE = 'Firma Individual';

    private const APELLIDO = 'Demo';

    private const EMAIL = 'firma@unlpam.edu.ar';

    private const PASSWORD = 'password123';

    private const ROL = 'Consulta';

    private const SIGLA_UNIDAD = 'FI';

    private const INSTITUCION_PROPIA = 'Universidad Nacional de La Pampa';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('FirmaIndividualDemoSeeder no se ejecutó en producción.');

            return;
        }

        $resultado = DB::transaction(function (): array {
            $instrumentos = [];

            foreach (self::EXPEDIENTES as $expediente) {
                $coincidencias = Instrumento::query()
                    ->where('expediente', $expediente)
                    ->lockForUpdate()
                    ->get();

                if ($coincidencias->count() !== 1) {
                    throw new RuntimeException(
                        "Se esperaba un único instrumento con expediente {$expediente}; "
                        .'no se modificaron datos.'
                    );
                }

                $instrumento = $coincidencias->first();

                if ($instrumento->estado !== 'Aprobado') {
                    throw new RuntimeException(
                        "El expediente {$expediente} debe estar exactamente en estado Aprobado "
                        ."(estado actual: {$instrumento->estado}); no se modificó su estado."
                    );
                }

                if (! $instrumento->convenio()->exists()) {
                    throw new RuntimeException(
                        "El instrumento {$expediente} no tiene registro relacionado en convenio."
                    );
                }

                $instrumentos[$expediente] = $instrumento;
            }

            $instituciones = InstitucionFirmante::query()
                ->where('razon_social', self::INSTITUCION_PROPIA)
                ->where('es_propia', true)
                ->lockForUpdate()
                ->get();

            if ($instituciones->count() !== 1) {
                throw new RuntimeException(
                    'No se encontró exactamente una institución propia llamada '
                    .self::INSTITUCION_PROPIA.'.'
                );
            }

            $institucion = $instituciones->first();

            if (! $institucion->activo) {
                throw new RuntimeException(
                    'La institución propia está inactiva; no se prepararon datos.'
                );
            }

            foreach ($instrumentos as $expediente => $instrumento) {
                $participa = DB::table('convenio_institucion')
                    ->where('id_instrumento', $instrumento->id_instrumento)
                    ->where('id_institucion', $institucion->id_institucion)
                    ->exists();

                if (! $participa) {
                    throw new RuntimeException(
                        "La institución propia no participa en {$expediente}; "
                        .'no se modificaron datos.'
                    );
                }
            }

            $roles = Rol::query()->where('nombre', self::ROL)->get();

            if ($roles->count() !== 1) {
                throw new RuntimeException("No se encontró exactamente un rol «".self::ROL.'».');
            }

            $unidades = UnidadAcademica::query()
                ->where('sigla', self::SIGLA_UNIDAD)
                ->get();

            if ($unidades->count() !== 1 || ! $unidades->first()->activo) {
                throw new RuntimeException(
                    'No se encontró una unidad académica FI única y activa.'
                );
            }

            $rol = $roles->first();
            $unidad = $unidades->first();
            $persona = Persona::query()->where('dni', self::DNI)->lockForUpdate()->first();
            $usuario = Usuario::query()->where('mail', self::EMAIL)->lockForUpdate()->first();

            if ($persona && ($persona->nombre !== self::NOMBRE || $persona->apellido !== self::APELLIDO)) {
                throw new RuntimeException(
                    'El DNI reservado para la demostración ya pertenece a datos distintos; '
                    .'no se modificaron.'
                );
            }

            if ($usuario && (
                ! $persona
                || (int) $usuario->id_persona !== (int) $persona->id_persona
                || $usuario->nombre !== 'Usuario Demo Firma Individual'
                || ! $usuario->activo
                || (int) $usuario->id_rol !== (int) $rol->id_rol
                || (int) $usuario->id_unidad !== (int) $unidad->id_unidad_academica
                || ! Hash::check(self::PASSWORD, $usuario->password)
            )) {
                throw new RuntimeException(
                    'El email reservado para la demostración ya pertenece a una cuenta '
                    .'incompatible o con credenciales distintas; no se modificó.'
                );
            }

            if ($persona) {
                $usuarioVinculado = Usuario::query()
                    ->where('id_persona', $persona->id_persona)
                    ->when($usuario, fn ($query) => $query->where('id_usuario', '!=', $usuario->id_usuario))
                    ->exists();

                if ($usuarioVinculado) {
                    throw new RuntimeException(
                        'La persona reservada ya está vinculada a otra cuenta de usuario.'
                    );
                }
            }

            $representante = $persona
                ? Representante::query()->where('id_persona', $persona->id_persona)->lockForUpdate()->first()
                : null;

            if ($representante && (
                (int) $representante->id_institucion !== (int) $institucion->id_institucion
                || ! $representante->activo
            )) {
                throw new RuntimeException(
                    'La persona reservada ya tiene un representante inactivo o asociado '
                    .'a otra institución; no se modificó.'
                );
            }

            if ($persona) {
                foreach ($instrumentos as $instrumento) {
                    $asignacion = DB::table('firma')
                        ->where('id_instrumento', $instrumento->id_instrumento)
                        ->where('id_persona', $persona->id_persona)
                        ->lockForUpdate()
                        ->first();

                    if ($asignacion && (
                        $asignacion->fecha_hora_firma !== null
                        || $asignacion->latitud !== null
                        || $asignacion->longitud !== null
                        || $asignacion->trazo_ruta !== null
                        || (bool) $asignacion->biometria_confirmada
                    )) {
                        throw new RuntimeException(
                            "La asignación existente para {$instrumento->expediente} ya tiene "
                            .'evidencia de firma; no se alteró.'
                        );
                    }
                }
            }

            $persona ??= Persona::query()->create([
                'nombre' => self::NOMBRE,
                'apellido' => self::APELLIDO,
                'dni' => self::DNI,
                'cuil' => null,
            ]);

            if (! $usuario) {
                $usuario = Usuario::query()->create([
                    'mail' => self::EMAIL,
                    'nombre' => 'Usuario Demo Firma Individual',
                    'password' => Hash::make(self::PASSWORD),
                    'activo' => true,
                    'id_rol' => $rol->id_rol,
                    'id_unidad' => $unidad->id_unidad_academica,
                    'alcance_global' => true,
                    'id_persona' => $persona->id_persona,
                ]);
            } elseif (! $usuario->alcance_global) {
                $usuario->alcance_global = true;
                $usuario->save();
            }

            if (! $representante) {
                $representante = new Representante();
                $representante->id_persona = $persona->id_persona;
                $representante->id_institucion = $institucion->id_institucion;
                $representante->activo = true;
                $representante->save();
            }

            $creadas = [];

            foreach ($instrumentos as $expediente => $instrumento) {
                $existe = DB::table('firma')
                    ->where('id_instrumento', $instrumento->id_instrumento)
                    ->where('id_persona', $persona->id_persona)
                    ->exists();

                if (! $existe) {
                    DB::table('firma')->insert([
                        'id_instrumento' => $instrumento->id_instrumento,
                        'id_persona' => $persona->id_persona,
                        'fecha_hora_firma' => null,
                        'latitud' => null,
                        'longitud' => null,
                        'trazo_ruta' => null,
                        'biometria_confirmada' => false,
                    ]);
                    $creadas[] = $expediente;
                }
            }

            return [
                'persona_id' => $persona->id_persona,
                'usuario_id' => $usuario->id_usuario,
                'representante_creado' => $representante->wasRecentlyCreated,
                'firmas_creadas' => $creadas,
                'firmas_reutilizadas' => array_values(array_diff(self::EXPEDIENTES, $creadas)),
                'instrumentos' => array_map(
                    fn (Instrumento $instrumento) => [
                        'expediente' => $instrumento->expediente,
                        'id_instrumento' => $instrumento->id_instrumento,
                    ],
                    array_values($instrumentos)
                ),
            ];
        });

        $this->command?->info('Datos de demostración de firma individual preparados.');
        $this->command?->line('Cuenta: '.self::EMAIL);
        $this->command?->line('Persona: '.self::NOMBRE.' '.self::APELLIDO.' (DNI reservado de demo).');
        $this->command?->line('Firmas creadas: '.(implode(', ', $resultado['firmas_creadas']) ?: 'ninguna; ya existían'));
        $this->command?->line('Firmas pendientes reutilizadas: '.(implode(', ', $resultado['firmas_reutilizadas']) ?: 'ninguna'));
        $this->command?->line('No se crearon documentos ni versiones PDF.');
    }
}
