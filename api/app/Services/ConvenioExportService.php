<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConvenioExportService
{
    public const ENCABEZADOS = [
        'ID', 'Título', 'Tipo de convenio', 'Marco/Específico', 'Estado',
        'Unidad académica', 'Fecha de firma', 'Inicio', 'Vencimiento',
        'Días de preaviso', 'Expediente', 'Resolución',
    ];

    public function consulta(array $filtros): Builder
    {
        $esMarco = DB::raw(
            "CASE WHEN EXISTS (SELECT 1 FROM marco m WHERE m.id_instrumento = c.id_instrumento)
                  THEN 'Marco' ELSE 'Específico' END"
        );

        return DB::table('convenio as c')
            ->join('instrumento as i', 'i.id_instrumento', '=', 'c.id_instrumento')
            ->leftJoin('unidad_academica as u', 'u.id_unidad_academica', '=', 'c.id_unidad_academica')
            ->select([
                'i.id_instrumento', 'i.titulo', 'c.tipo',
                DB::raw("{$esMarco->getValue(DB::connection()->getQueryGrammar())} as subtipo"),
                'i.estado', 'u.sigla', 'i.fecha_firma', 'i.inicio', 'i.vencimiento',
                'i.dias_preaviso', 'i.expediente', 'i.resolucion',
            ])
            ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->where('i.estado', $v))
            ->when($filtros['tipo'] ?? null, fn ($q, $v) => $q->where('c.tipo', $v))
            ->when($filtros['id_unidad_academica'] ?? null, fn ($q, $v) => $q->where('c.id_unidad_academica', $v))
            ->when(array_key_exists('es_marco', $filtros) && $filtros['es_marco'] !== null, function ($q) use ($filtros) {
                $filtros['es_marco']
                    ? $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('marco as m')->whereColumn('m.id_instrumento', 'c.id_instrumento'))
                    : $q->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('marco as m')->whereColumn('m.id_instrumento', 'c.id_instrumento'));
            })
            ->when($filtros['buscar'] ?? null, function ($q, $v) {
                $q->where(fn ($w) => $w
                    ->where('i.titulo', 'like', "%{$v}%")
                    ->orWhere('i.objeto', 'like', "%{$v}%")
                    ->orWhere('i.expediente', 'like', "%{$v}%"));
            })
            ->orderBy('i.id_instrumento');
    }
}