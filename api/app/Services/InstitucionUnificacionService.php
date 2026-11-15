<?php

namespace App\Services;

use App\Models\InstitucionFirmante;
use Illuminate\Support\Facades\DB;

class InstitucionUnificacionService
{
    private const CAMPOS_COMPLETABLES = ['cuit', 'domicilio_legal', 'tipo_institucion'];

    public function __construct(private AuditoriaService $auditoria)
    {
    }

    /**
     * Unifica instituciones firmantes duplicadas en una principal.
     *
     * Todo lo que apuntaba a una duplicada (unidades académicas, representantes y convenios)
     * pasa a apuntar a la principal, y después se elimina la duplicada. Si un convenio ya estaba
     * vinculado a la principal, no se duplica el vínculo. Los datos vacíos de la principal
     * (cuit, domicilio legal, tipo) se completan con los de las duplicadas; nunca se pisan.
     * Todo ocurre en una transacción: si algo falla, no se cambia nada.
     *
     * @param  array<int, int>  $idsDuplicadas
     */
    public function unificar(int $idPrincipal, array $idsDuplicadas, int $actorId): array
    {
        return DB::transaction(function () use ($idPrincipal, $idsDuplicadas, $actorId) {
            $principal = InstitucionFirmante::query()
                ->lockForUpdate()
                ->findOrFail($idPrincipal);

            $duplicadas = InstitucionFirmante::query()
                ->whereIn('id_institucion', $idsDuplicadas)
                ->orderBy('id_institucion')
                ->lockForUpdate()
                ->get();

            $resumen = [
                'unidades_academicas' => 0,
                'representantes' => 0,
                'convenios_reasignados' => 0,
                'convenios_ya_vinculados' => 0,
            ];
            $datosCompletados = [];

            foreach ($duplicadas as $duplicada) {
                $idDuplicada = $duplicada->id_institucion;

                $resumen['unidades_academicas'] += DB::table('unidad_academica')
                    ->where('id_institucion', $idDuplicada)
                    ->update(['id_institucion' => $idPrincipal]);

                $resumen['representantes'] += DB::table('representante')
                    ->where('id_institucion', $idDuplicada)
                    ->update(['id_institucion' => $idPrincipal]);

                $this->unificarConvenios($idPrincipal, $idDuplicada, $resumen);

                foreach (self::CAMPOS_COMPLETABLES as $campo) {
                    if (blank($principal->{$campo}) && filled($duplicada->{$campo})) {
                        $principal->{$campo} = $duplicada->{$campo};
                        $datosCompletados[] = $campo;
                    }
                }

                $duplicada->delete();
            }

            $principal->save();

            $this->auditoria->registrar(
                'Unificación de instituciones',
                $actorId,
                null,
                json_encode([
                    'principal' => [
                        'id' => $principal->id_institucion,
                        'razon_social' => $principal->razon_social,
                    ],
                    'unificadas' => $duplicadas->map(fn (InstitucionFirmante $institucion) => [
                        'id' => $institucion->id_institucion,
                        'razon_social' => $institucion->razon_social,
                    ])->values()->all(),
                    'resumen' => $resumen,
                ], JSON_UNESCAPED_UNICODE)
            );

            return [
                'principal' => $principal->fresh(),
                'eliminadas' => $duplicadas->pluck('id_institucion')->values()->all(),
                'resumen' => $resumen,
                'datos_completados' => array_values(array_unique($datosCompletados)),
            ];
        });
    }

    private function unificarConvenios(int $idPrincipal, int $idDuplicada, array &$resumen): void
    {
        $yaEnPrincipal = DB::table('convenio_institucion')
            ->where('id_institucion', $idPrincipal)
            ->pluck('id_instrumento');

        $repetidos = DB::table('convenio_institucion')
            ->where('id_institucion', $idDuplicada)
            ->whereIn('id_instrumento', $yaEnPrincipal)
            ->delete();

        $reasignados = DB::table('convenio_institucion')
            ->where('id_institucion', $idDuplicada)
            ->update(['id_institucion' => $idPrincipal]);

        $resumen['convenios_ya_vinculados'] += $repetidos;
        $resumen['convenios_reasignados'] += $reasignados;
    }
}
