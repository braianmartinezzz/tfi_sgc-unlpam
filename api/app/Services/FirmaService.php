<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Notificacion;
use App\Models\Persona;
use App\Models\Usuario;
use App\Models\Version;
use Carbon\Carbon;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Firma de convenios desde la app: cada firmante (persona vinculada a un usuario) firma con su
 * trazo manuscrito, confirmación biométrica y ubicación. Es un procedimiento administrativo
 * interno: no equivale a una firma digital PKI.
 */
class FirmaService
{
    public const PENDIENTES_MAX = 10;

    public const MAX_FIRMANTES = 20;

    public const HORAS_DEMORADA = 72;

    private const TRAZO_PREFIJO = 'data:image/png;base64,';

    private const TRAZO_MAX_BYTES = 1048576;


    public function __construct(
        private ConvenioService $convenios,
        private AuditoriaService $auditoria
    ) {
    }

    /**
     * Approved agreements awaiting this user's individual signature, newest first.
     */
    public function pendientes(Usuario $usuario): array
    {
        if ($usuario->id_persona === null) {
            return [];
        }

        $ids = DB::table('firma as f')
            ->join('instrumento as i', 'i.id_instrumento', '=', 'f.id_instrumento')
            ->where('f.id_persona', $usuario->id_persona)
            ->whereNull('f.fecha_hora_firma')
            ->where('i.estado', 'Aprobado')
            ->pluck('f.id_instrumento');

        if ($ids->isEmpty()) {
            return [];
        }

        $aprobadoEl = HistorialEstado::query()
            ->where('estado_nuevo', 'Aprobado')
            ->whereIn('id_instrumento', $ids)
            ->selectRaw('id_instrumento, MAX(fecha_hora) as aprobado_el')
            ->groupBy('id_instrumento')
            ->pluck('aprobado_el', 'id_instrumento');

        return Instrumento::query()
            ->whereIn('id_instrumento', $ids)
            ->with('convenio.unidadAcademica:id_unidad_academica,sigla,nombre')
            ->get()
            ->map(fn (Instrumento $instrumento) => [
                'instrumento' => $instrumento,
                'aprobado_el' => $aprobadoEl->has($instrumento->id_instrumento)
                    ? Carbon::parse($aprobadoEl[$instrumento->id_instrumento])
                    : null,
            ])
            ->sort(function ($a, $b) {
                $fechaA = $a['aprobado_el']?->timestamp ?? PHP_INT_MIN;
                $fechaB = $b['aprobado_el']?->timestamp ?? PHP_INT_MIN;

                return ($fechaB <=> $fechaA)
                    ?: ($b['instrumento']->id_instrumento <=> $a['instrumento']->id_instrumento);
            })
            ->take(self::PENDIENTES_MAX)
            ->map(function (array $fila) {
                /** @var Instrumento $instrumento */
                $instrumento = $fila['instrumento'];
                $horas = $fila['aprobado_el'] ? (int) $fila['aprobado_el']->diffInHours(now()) : null;
                $version = $this->versionParaFirmar($instrumento->id_instrumento);

                return [
                    'id_instrumento' => $instrumento->id_instrumento,
                    'titulo' => $instrumento->titulo,
                    'tipo' => $instrumento->convenio?->tipo,
                    'unidad_academica' => $instrumento->convenio?->unidadAcademica?->sigla,
                    'aprobado_el' => $fila['aprobado_el']?->toIso8601String(),
                    'horas_pendiente' => $horas,
                    'demorado' => $horas !== null && $horas >= self::HORAS_DEMORADA,
                    'documento' => $version === null ? null : [
                        'id_documento' => $version->id_documento,
                        'id_version' => $version->id_version,
                        'nombre_archivo' => $version->nombre_archivo,
                    ],
                    'firmantes' => $this->firmantes($instrumento->id_instrumento),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Estado de la firma de cada firmante del convenio.
     */
    public function firmantes(int $idInstrumento): array
    {
        return DB::table('firma as f')
            ->join('persona as p', 'p.id_persona', '=', 'f.id_persona')
            ->where('f.id_instrumento', $idInstrumento)
            ->orderByDesc('p.id_persona')
            ->get([
                'p.id_persona',
                'p.nombre',
                'p.apellido',
                'f.fecha_hora_firma',
                'f.latitud',
                'f.longitud',
                'f.biometria_confirmada',
                'f.trazo_ruta',
            ])
            ->map(fn ($fila) => [
                'id_persona' => (int) $fila->id_persona,
                'nombre' => $fila->nombre,
                'apellido' => $fila->apellido,
                'firmado' => $fila->fecha_hora_firma !== null,
                'fecha_hora_firma' => $fila->fecha_hora_firma,
                'latitud' => $fila->latitud !== null ? (float) $fila->latitud : null,
                'longitud' => $fila->longitud !== null ? (float) $fila->longitud : null,
                'biometria_informada_por_app' => (bool) $fila->biometria_confirmada,
                'tiene_trazo' => $fila->trazo_ruta !== null,
            ])
            ->all();
    }

    /**
     * Constancia de firmas de un convenio (firmantes, estado y metadatos de cada firma).
     */
    public function constancia(string $id, Usuario $usuario): array
    {
        $convenio = Convenio::query()->with('instrumento')->findOrFail($id);

        $this->autorizarConsulta($convenio, $usuario);

        $firmantes = $this->firmantes($convenio->id_instrumento);

        return [
            'id_instrumento' => $convenio->id_instrumento,
            'titulo' => $convenio->instrumento?->titulo,
            'estado' => $convenio->instrumento?->estado,
            'firmas_requeridas' => count($firmantes),
            'firmas_registradas' => count(array_filter($firmantes, fn (array $firmante) => $firmante['firmado'])),
            'firmantes' => $firmantes,
        ];
    }

    /**
     * Defines who may record individual app signature evidence for this agreement.
     */
    public function definirFirmantes(string $id, array $idsPersona, Usuario $actor): array
    {
        return DB::transaction(function () use ($id, $idsPersona, $actor) {
            $convenio = Convenio::query()->lockForUpdate()->findOrFail($id);

            if (! Gate::forUser($actor)->allows('enviar-convenio-a-revision', $convenio)) {
                throw new AuthorizationException('No tiene permisos para definir los firmantes de este convenio.');
            }

            $instrumento = $convenio->instrumento()->lockForUpdate()->firstOrFail();

            if (! in_array($instrumento->estado, ['Borrador', 'EnCorreccion'], true)) {
                throw new DomainException(
                    'La nomina solo puede modificarse en Borrador o EnCorreccion antes de la primera aprobacion.'
                );
            }

            if (HistorialEstado::query()
                ->where('id_instrumento', $convenio->id_instrumento)
                ->where('estado_nuevo', 'Aprobado')
                ->exists()) {
                throw new DomainException('The roster is frozen from the first approval.');
            }

            $nuevos = array_values(array_unique(array_map('intval', $idsPersona)));
            $this->validarFirmantesParticipantes($convenio, $nuevos);

            $actuales = DB::table('firma')
                ->where('id_instrumento', $convenio->id_instrumento)
                ->get()
                ->keyBy('id_persona');

            $quitados = $actuales->keys()->map(fn ($clave) => (int) $clave)->diff($nuevos)->values();

            foreach ($quitados as $idPersona) {
                if ($actuales[$idPersona]->fecha_hora_firma !== null) {
                    throw new DomainException('No se puede quitar a un firmante que ya firmó.');
                }
            }

            if ($quitados->isNotEmpty()) {
                DB::table('firma')
                    ->where('id_instrumento', $convenio->id_instrumento)
                    ->whereIn('id_persona', $quitados->all())
                    ->delete();
            }

            $agregados = array_diff($nuevos, $actuales->keys()->map(fn ($clave) => (int) $clave)->all());

            foreach ($agregados as $idPersona) {
                DB::table('firma')->insert([
                    'id_instrumento' => $convenio->id_instrumento,
                    'id_persona' => $idPersona,
                    'fecha_hora_firma' => null,
                ]);
            }

            $this->auditoria->registrar(
                'DEFINIR_FIRMANTES',
                $actor->id_usuario,
                $convenio->id_instrumento,
                'Firmantes del convenio #'.$convenio->id_instrumento.' definidos: personas '
                    .implode(', ', $nuevos).'.'
            );

            return $this->firmantes($convenio->id_instrumento);
        });
    }

    /**
     * Registers individual app evidence without completing the administrative signing workflow.
     *
     * @param  array{trazo: string, latitud: float|int|string, longitud: float|int|string}  $datos
     */
    public function firmar(string $id, array $datos, Usuario $usuario): array
    {
        $bytesTrazo = $this->decodificarTrazo($datos['trazo']);
        $rutaTrazo = null;

        try {
            return DB::transaction(function () use ($id, $datos, $usuario, $bytesTrazo, &$rutaTrazo) {
                if ($usuario->id_persona === null) {
                    throw new AuthorizationException('Tu usuario no esta vinculado a una persona firmante.');
                }

                $convenio = Convenio::query()->lockForUpdate()->findOrFail($id);
                $instrumento = $convenio->instrumento()->lockForUpdate()->firstOrFail();

                $fila = DB::table('firma')
                    ->where('id_instrumento', $convenio->id_instrumento)
                    ->where('id_persona', $usuario->id_persona)
                    ->lockForUpdate()
                    ->first();

                if ($fila === null) {
                    throw new AuthorizationException('No figuras como firmante habilitado de este convenio.');
                }

                if ($instrumento->estado !== 'Aprobado') {
                    throw new DomainException('El convenio debe estar en estado Aprobado para registrar la firma individual.');
                }

                if ($fila->fecha_hora_firma !== null) {
                    throw new DomainException('Ya registraste tu firma individual para este convenio.');
                }

                $ahora = now();
                $rutaTrazo = 'firmas/'.$convenio->id_instrumento.'/'.$usuario->id_persona
                    .'-'.$ahora->format('YmdHis').'.png';

                Storage::disk('private')->put($rutaTrazo, $bytesTrazo);

                DB::table('firma')
                    ->where('id_instrumento', $convenio->id_instrumento)
                    ->where('id_persona', $usuario->id_persona)
                    ->update([
                        'fecha_hora_firma' => $ahora,
                        'latitud' => $datos['latitud'],
                        'longitud' => $datos['longitud'],
                        'trazo_ruta' => $rutaTrazo,
                        // This stores the app-reported result; the server does not verify biometrics.
                        'biometria_confirmada' => (bool) $datos['biometria_confirmada'],
                    ]);

                $persona = Persona::query()->findOrFail($usuario->id_persona);
                $nombre = trim($persona->nombre.' '.$persona->apellido);
                $pendientes = DB::table('firma')
                    ->where('id_instrumento', $convenio->id_instrumento)
                    ->whereNull('fecha_hora_firma')
                    ->count();

                $this->auditoria->registrar(
                    'FIRMA_REGISTRADA',
                    $usuario->id_usuario,
                    $convenio->id_instrumento,
                    $nombre.' registro evidencia individual para el convenio #'.$convenio->id_instrumento
                );

                $this->notificarGestion(
                    $convenio,
                    $ahora,
                    'FIRMA_REGISTRADA',
                    $nombre.' registro evidencia de firma individual para el convenio #'
                        .$convenio->id_instrumento.'. Quedan '.$pendientes.' firmas individuales pendientes.'
                );

                return [
                    'id_instrumento' => $convenio->id_instrumento,
                    // Individual evidence never closes the administrative signing workflow.
                    'estado' => $instrumento->estado,
                    'firmas_pendientes' => $pendientes,
                    'firma' => [
                        'id_persona' => $persona->id_persona,
                        'nombre' => $persona->nombre,
                        'apellido' => $persona->apellido,
                        'fecha_hora_firma' => $ahora->toIso8601String(),
                        'latitud' => (float) $datos['latitud'],
                        'longitud' => (float) $datos['longitud'],
                        'biometria_informada_por_app' => (bool) $datos['biometria_confirmada'],
                    ],
                ];
            });
        } catch (Throwable $exception) {
            if ($rutaTrazo !== null) {
                Storage::disk('private')->delete($rutaTrazo);
            }

            throw $exception;
        }
    }

    public function documentoParaFirmante(string $id, Usuario $usuario): Version
    {
        $convenio = Convenio::query()->findOrFail($id);
        $instrumento = $convenio->instrumento()->firstOrFail();

        if (! $this->esFirmante($convenio->id_instrumento, $usuario)) {
            throw new AuthorizationException('No figurás como firmante de este convenio.');
        }

        if ($instrumento->estado !== 'Aprobado') {
            throw new DomainException('El documento solo esta disponible mientras el convenio esta Aprobado.');
        }

        return $this->versionParaFirmar($convenio->id_instrumento)
            ?? throw new ModelNotFoundException('El convenio no tiene un documento PDF.');
    }

    /**
     * Trazo guardado de una firma: [ruta interna, nombre de descarga].
     *
     * @return array{0: string, 1: string}
     */
    public function trazoDe(string $id, string $idPersona, Usuario $usuario): array
    {
        $convenio = Convenio::query()->findOrFail($id);

        $this->autorizarConsulta($convenio, $usuario);

        $ruta = DB::table('firma')
            ->where('id_instrumento', $convenio->id_instrumento)
            ->where('id_persona', $idPersona)
            ->value('trazo_ruta');

        if ($ruta === null) {
            throw new ModelNotFoundException('La firma todavía no fue registrada.');
        }

        return [$ruta, 'trazo-'.$convenio->id_instrumento.'-'.$idPersona.'.png'];
    }

    /**
     * Only active representatives of institutions linked to the agreement can be assigned.
     * University authorities are included when their institution participates.
     * An empty list is valid because app signatures are optional evidence.
     *
     * @param  list<int>  $idsPersona
     */
    private function validarFirmantesParticipantes(Convenio $convenio, array $idsPersona): void
    {
        if ($idsPersona === []) {
            return;
        }

        $institucionesIds = DB::table('convenio_institucion')
            ->where('id_instrumento', $convenio->id_instrumento)
            ->pluck('id_institucion');

        $personasValidas = DB::table('representante')
            ->whereIn('id_institucion', $institucionesIds)
            ->where('activo', true)
            ->whereIn('id_persona', $idsPersona)
            ->pluck('id_persona')
            ->map(fn ($id) => (int) $id)
            ->all();

        $noAutorizadas = array_values(array_diff($idsPersona, $personasValidas));

        if ($noAutorizadas !== []) {
            throw new DomainException(
                'Solo se pueden asignar representantes activos de instituciones participantes. '
                    .'Personas no autorizadas: '.implode(', ', $noAutorizadas).'.'
            );
        }
    }

    private function esFirmante(int $idInstrumento, Usuario $usuario): bool
    {
        return $usuario->id_persona !== null
            && DB::table('firma')
                ->where('id_instrumento', $idInstrumento)
                ->where('id_persona', $usuario->id_persona)
                ->exists();
    }

    /**
     * Pueden consultar las firmas: los propios firmantes, Gestión de la unidad, Administración
     * y los roles de Revisión y Consulta dentro de lo que ya pueden ver del convenio.
     */
    private function autorizarConsulta(Convenio $convenio, Usuario $usuario): void
    {
        $rol = $usuario->rol?->nombre;

        if ($rol === 'Consulta' || $rol === "Revisi\u{00F3}n") {
            $this->convenios->assertCanViewWithinVisibilityScope($convenio, $usuario);

            return;
        }

        if ($this->esFirmante($convenio->id_instrumento, $usuario)) {
            return;
        }

        if (Gate::forUser($usuario)->allows('enviar-convenio-a-revision', $convenio)) {
            return;
        }

        throw new AuthorizationException('No tiene permisos para consultar las firmas de este convenio.');
    }

    private function versionParaFirmar(int $idInstrumento): ?Version
    {
        return Version::query()
            ->whereIn(
                'id_documento',
                Documento::query()
                    ->where('id_instrumento', $idInstrumento)
                    ->where('es_documento_firmado', false)
                    ->select('id_documento')
            )
            ->whereRaw('LOWER(ruta_interna) LIKE ?', ['%.pdf'])
            ->orderByDesc('fecha_carga')
            ->orderByDesc('id_version')
            ->first();
    }

    private function decodificarTrazo(string $dataUrl): string
    {
        $bytes = str_starts_with($dataUrl, self::TRAZO_PREFIJO)
            ? base64_decode(substr($dataUrl, strlen(self::TRAZO_PREFIJO)), true)
            : false;

        if ($bytes === false || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            throw ValidationException::withMessages([
                'trazo' => ['El trazo de la firma debe ser una imagen PNG válida.'],
            ]);
        }

        if (strlen($bytes) > self::TRAZO_MAX_BYTES) {
            throw ValidationException::withMessages([
                'trazo' => ['El trazo de la firma no puede superar 1 MB.'],
            ]);
        }

        $imagen = @getimagesizefromstring($bytes);

        if ($imagen === false || ($imagen['mime'] ?? null) !== 'image/png') {
            throw ValidationException::withMessages([
                'trazo' => ['El trazo enviado no contiene una imagen PNG valida.'],
            ]);
        }

        return $bytes;
    }

    private function notificarGestion(Convenio $convenio, Carbon $fecha, string $tipo, string $mensaje): void
    {
        $destinatarios = Usuario::query()
            ->where('activo', true)
            ->where('id_unidad', $convenio->id_unidad_academica)
            ->whereHas('rol', fn ($query) => $query->where('nombre', 'Gestión'))
            ->get(['id_usuario']);

        foreach ($destinatarios as $destinatario) {
            Notificacion::query()->create([
                'tipo' => $tipo,
                'mensaje' => $mensaje,
                'fecha_generacion' => $fecha,
                'id_instrumento' => $convenio->id_instrumento,
                'id_usuario' => $destinatario->id_usuario,
            ]);
        }
    }
}
