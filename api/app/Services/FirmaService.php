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
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Firma de convenios desde la app: cada firmante (persona vinculada a un usuario) firma con su
 * trazo manuscrito, confirmación biométrica y ubicación. Es un procedimiento administrativo
 * interno: no equivale a una firma digital PKI.
 */
class FirmaService
{
    public const MIN_FIRMANTES = 2;

    public const PENDIENTES_MAX = 10;

    public const HORAS_DEMORADA = 72;

    private const TRAZO_PREFIJO = 'data:image/png;base64,';

    private const TRAZO_MAX_BYTES = 1048576;

    private const ESTADOS_QUE_ADMITEN_CAMBIAR_FIRMANTES = ['Borrador', 'EnCorreccion', 'Revisión', 'Aprobado'];

    public function __construct(
        private ConvenioService $convenios,
        private AuditoriaService $auditoria
    ) {
    }

    /**
     * Convenios aprobados que el usuario todavía tiene que firmar, del más antiguo al más reciente.
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
            ->with([
                'convenio.unidadAcademica:id_unidad_academica,sigla,nombre',
                'convenio.especifico.marco.convenio.instrumento:id_instrumento,titulo,expediente',
            ])
            ->get()
            ->map(fn (Instrumento $instrumento) => [
                'instrumento' => $instrumento,
                'aprobado_el' => $aprobadoEl->has($instrumento->id_instrumento)
                    ? Carbon::parse($aprobadoEl[$instrumento->id_instrumento])
                    : null,
            ])
            ->sortBy([
                fn ($a, $b) => ($a['aprobado_el']?->timestamp ?? PHP_INT_MAX)
                    <=> ($b['aprobado_el']?->timestamp ?? PHP_INT_MAX),
                fn ($a, $b) => $a['instrumento']->id_instrumento <=> $b['instrumento']->id_instrumento,
            ])
            ->take(self::PENDIENTES_MAX)
            ->map(function (array $fila) {
                /** @var Instrumento $instrumento */
                $instrumento = $fila['instrumento'];
                $horas = $fila['aprobado_el'] ? (int) $fila['aprobado_el']->diffInHours(now()) : null;
                $version = $this->versionParaFirmar($instrumento->id_instrumento);

                $marco = $instrumento->convenio?->especifico?->marco?->convenio?->instrumento;

                return [
                    'id_instrumento' => $instrumento->id_instrumento,
                    'titulo' => $instrumento->titulo,
                    'expediente' => $instrumento->expediente,
                    'objeto' => $instrumento->objeto,
                    'version_registro' => (int) $instrumento->version_registro,
                    'tipo' => $instrumento->convenio?->tipo,
                    'unidad_academica' => $instrumento->convenio?->unidadAcademica?->sigla,
                    'procedencia' => $instrumento->convenio?->unidadAcademica?->nombre,
                    'inicio' => $instrumento->inicio?->toIso8601String(),
                    'vencimiento' => $instrumento->vencimiento?->toIso8601String(),
                    'convenio_padre' => $marco ? [
                        'id_instrumento' => $marco->id_instrumento,
                        'titulo' => $marco->titulo,
                        'expediente' => $marco->expediente,
                    ] : null,
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
                'biometria_confirmada' => (bool) $fila->biometria_confirmada,
                'tiene_trazo' => $fila->trazo_ruta !== null,
            ])
            ->all();
    }

    /**
     * Constancia de firmas de un convenio (firmantes, estado y metadatos de cada firma).
     */
    public function constancia(string $id, Usuario $usuario): array
    {
        $convenio = Convenio::query()->with([
            'instrumento',
            'unidadAcademica:id_unidad_academica,sigla,nombre',
            'especifico.marco.convenio.instrumento:id_instrumento,titulo,expediente',
        ])->findOrFail($id);

        $this->autorizarConsulta($convenio, $usuario);

        $firmantes = $this->firmantes($convenio->id_instrumento);

        $instrumento = $convenio->instrumento;
        $marco = $convenio->especifico?->marco?->convenio?->instrumento;

        return [
            'id_instrumento' => $convenio->id_instrumento,
            'titulo' => $instrumento?->titulo,
            'expediente' => $instrumento?->expediente,
            'objeto' => $instrumento?->objeto,
            'tipo' => $convenio->tipo,
            'procedencia' => $convenio->unidadAcademica?->nombre,
            'unidad_academica' => $convenio->unidadAcademica?->sigla,
            'inicio' => $instrumento?->inicio?->toIso8601String(),
            'vencimiento' => $instrumento?->vencimiento?->toIso8601String(),
            'convenio_padre' => $marco ? [
                'id_instrumento' => $marco->id_instrumento,
                'titulo' => $marco->titulo,
                'expediente' => $marco->expediente,
            ] : null,
            'firmas_requeridas' => count($firmantes),
            'firmas_registradas' => count(array_filter($firmantes, fn (array $firmante) => $firmante['firmado'])),
            'firmantes' => $firmantes,
        ];
    }

    /**
     * Define quiénes tienen que firmar el convenio (al menos dos).
     */
    public function definirFirmantes(string $id, array $idsPersona, Usuario $actor): array
    {
        return DB::transaction(function () use ($id, $idsPersona, $actor) {
            $convenio = Convenio::query()->lockForUpdate()->findOrFail($id);

            if (! Gate::forUser($actor)->allows('enviar-convenio-a-revision', $convenio)) {
                throw new AuthorizationException('No tiene permisos para definir los firmantes de este convenio.');
            }

            $instrumento = $convenio->instrumento()->lockForUpdate()->firstOrFail();

            if (! in_array($instrumento->estado, self::ESTADOS_QUE_ADMITEN_CAMBIAR_FIRMANTES, true)) {
                throw new DomainException(
                    'Los firmantes solo se pueden modificar mientras el convenio no esté firmado ni cerrado.'
                );
            }

            $nuevos = array_values(array_unique(array_map('intval', $idsPersona)));
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
     * Registra la firma del usuario autenticado. Cuando firma la última parte pendiente,
     * el convenio pasa automáticamente a Firmado.
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
                    throw new AuthorizationException('Tu usuario no está vinculado a una persona firmante.');
                }

                $convenio = Convenio::query()->lockForUpdate()->findOrFail($id);
                $instrumento = $convenio->instrumento()->lockForUpdate()->firstOrFail();

                $fila = DB::table('firma')
                    ->where('id_instrumento', $convenio->id_instrumento)
                    ->where('id_persona', $usuario->id_persona)
                    ->lockForUpdate()
                    ->first();

                if ($fila === null) {
                    throw new AuthorizationException('No figurás como firmante de este convenio.');
                }

                if ($instrumento->estado !== 'Aprobado') {
                    throw new DomainException('El convenio debe estar en estado Aprobado para poder firmarse.');
                }

                // Optimistic locking: rechaza una firma sobre una versión vieja del convenio.
                if (
                    array_key_exists('version_registro', $datos)
                    && (int) $datos['version_registro'] !== (int) $instrumento->version_registro
                ) {
                    throw new DomainException(
                        'El convenio cambió desde que lo cargaste. Volvé a cargarlo antes de firmar.'
                    );
                }

                if ($fila->fecha_hora_firma !== null) {
                    throw new DomainException('Ya firmaste este convenio.');
                }

                $totalFirmantes = DB::table('firma')
                    ->where('id_instrumento', $convenio->id_instrumento)
                    ->count();

                if ($totalFirmantes < self::MIN_FIRMANTES) {
                    throw new DomainException(
                        'El convenio necesita al menos '.self::MIN_FIRMANTES.' firmantes registrados para poder firmarse.'
                    );
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
                        'biometria_confirmada' => true,
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
                    $nombre.' firmó el convenio #'.$convenio->id_instrumento
                        .' (lat '.$datos['latitud'].', lng '.$datos['longitud'].').'
                );

                $estado = 'Aprobado';

                if ($pendientes === 0) {
                    $estado = 'Firmado';

                    $instrumento->fecha_firma = $ahora->toDateString();
                    $instrumento->estado = 'Firmado';
                    $instrumento->save();

                    HistorialEstado::query()->create([
                        'estado_anterior' => 'Aprobado',
                        'estado_nuevo' => 'Firmado',
                        'fecha_hora' => $ahora,
                        'motivo' => 'Firma de todas las partes registrada desde el firmador.',
                        'id_instrumento' => $convenio->id_instrumento,
                        'id_usuario' => $usuario->id_usuario,
                    ]);

                    $this->auditoria->registrar(
                        'CONVENIO_FIRMADO',
                        $usuario->id_usuario,
                        $convenio->id_instrumento,
                        'Convenio #'.$convenio->id_instrumento.' firmado por todas las partes.'
                    );
                }

                $this->notificarGestion(
                    $convenio,
                    $ahora,
                    $estado === 'Firmado' ? 'CONVENIO_FIRMADO' : 'FIRMA_REGISTRADA',
                    $estado === 'Firmado'
                        ? 'El convenio #'.$convenio->id_instrumento.' reunió todas las firmas y pasó a estado Firmado.'
                        : $nombre.' firmó el convenio #'.$convenio->id_instrumento
                            .'. Faltan '.$pendientes.' '.($pendientes === 1 ? 'firma' : 'firmas').'.'
                );

                $this->generarConstanciaPdf($convenio, $usuario);

                return [
                    'id_instrumento' => $convenio->id_instrumento,
                    'estado' => $estado,
                    'firmas_pendientes' => $pendientes,
                    'firma' => [
                        'id_persona' => $persona->id_persona,
                        'nombre' => $persona->nombre,
                        'apellido' => $persona->apellido,
                        'fecha_hora_firma' => $ahora->toIso8601String(),
                        'latitud' => (float) $datos['latitud'],
                        'longitud' => (float) $datos['longitud'],
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

    /**
     * Versión PDF del convenio que el firmante tiene que leer antes de firmar.
     */
    public function documentoParaFirmante(string $id, Usuario $usuario): Version
    {
        $convenio = Convenio::query()->findOrFail($id);

        if (! $this->esFirmante($convenio->id_instrumento, $usuario)) {
            throw new AuthorizationException('No figurás como firmante de este convenio.');
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
        if ($this->esFirmante($convenio->id_instrumento, $usuario)) {
            return;
        }

        if (Gate::forUser($usuario)->allows('enviar-convenio-a-revision', $convenio)) {
            return;
        }

        if (in_array($usuario->rol?->nombre, ['Revisión', 'Consulta'], true)) {
            $this->convenios->assertCanView($convenio, $usuario);

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

    private function generarConstanciaPdf(Convenio $convenio, Usuario $usuario): void
    {
        $constancia = $this->constancia((string) $convenio->id_instrumento, $usuario);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->htmlConstancia($constancia), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $nombreArchivo = 'constancia_firma_convenio_'.$convenio->id_instrumento.'.pdf';
        $rutaArchivo = 'constancias/'.$nombreArchivo;

        Storage::disk('private')->put($rutaArchivo, $dompdf->output());
    }

    public function constanciaPdf(string $id, Usuario $usuario): array
    {
        $convenio = Convenio::query()->with('instrumento')->findOrFail($id);
        $this->autorizarConsulta($convenio, $usuario);

        $constancia = $this->constancia((string) $convenio->id_instrumento, $usuario);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($this->htmlConstancia($constancia), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $ruta = 'constancias/constancia-firma-'.$convenio->id_instrumento.'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('private')->put($ruta, $dompdf->output());

        return ['ruta' => $ruta, 'nombre_archivo' => 'constancia-firma-'.$convenio->id_instrumento.'.pdf'];
    }

    private function htmlConstancia(array $constancia): string
    {
        $firmantes = collect($constancia['firmantes'])->map(function (array $firmante) {
            $estado = $firmante['firmado'] ? 'FIRMADO' : 'PENDIENTE';
            $fecha = $firmante['firmado'] && $firmante['fecha_hora_firma']
                ? htmlspecialchars((string) $firmante['fecha_hora_firma'])
                : '—';
            $ubicacion = $firmante['firmado']
                ? htmlspecialchars(($firmante['latitud'] !== null ? (string) $firmante['latitud'] : '—').', '.($firmante['longitud'] !== null ? (string) $firmante['longitud'] : '—'))
                : '—';

            return '<tr><td>'.htmlspecialchars($firmante['nombre'].' '.$firmante['apellido']).'</td>'
                .'<td>'.$estado.'</td><td>'.$fecha.'</td><td>'.$ubicacion.'</td></tr>';
        })->implode('');

        $padre = $constancia['convenio_padre']
            ? htmlspecialchars($constancia['convenio_padre']['titulo'].' ('.$constancia['convenio_padre']['expediente'].')')
            : '—';

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#171717;margin:36px}'
            .'h1{font-size:20px;color:#b91c1c}.meta td{padding:4px 8px}.firmantes{width:100%;border-collapse:collapse;margin-top:14px}'
            .'.firmantes th,.firmantes td{border:1px solid #a3a3a3;padding:7px;text-align:left}.pie{margin-top:24px;color:#525252;font-size:9px}'
            .'@page{margin:28px}</style></head><body>'
            .' <h1>Constancia de firmas individuales</h1>'
            .' <table class="meta"><tr><td><strong>Convenio</strong></td><td>'.htmlspecialchars((string) ($constancia['titulo'] ?? '—')).'</td></tr>'
            .' <tr><td><strong>Expediente</strong></td><td>'.htmlspecialchars((string) ($constancia['expediente'] ?? '—')).'</td></tr>'
            .' <tr><td><strong>Procedencia</strong></td><td>'.htmlspecialchars((string) ($constancia['procedencia'] ?? '—')).'</td></tr>'
            .' <tr><td><strong>Convenio padre</strong></td><td>'.$padre.'</td></tr>'
            .' <tr><td><strong>Vigencia</strong></td><td>'.htmlspecialchars((string) ($constancia['inicio'] ?? '—')).' al '.htmlspecialchars((string) ($constancia['vencimiento'] ?? '—')).'</td></tr></table>'
            .' <table class="firmantes"><thead><tr><th>Firmante</th><th>Estado</th><th>Fecha y hora</th><th>Latitud, longitud</th></tr></thead><tbody>'.$firmantes.'</tbody></table>'
            .' <p class="pie">Constancia generada el '.htmlspecialchars(now()->toIso8601String()).'. Evidencia administrativa individual; no equivale a firma digital PKI.</p>'
            .' </body></html>';
    }
}
