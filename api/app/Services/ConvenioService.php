<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConvenioService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Convenio::query()->with([
            'instrumento',
            'unidadAcademica',
            'institucionesFirmantes',
            'marco',
            'especifico',
        ]);

        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $query->whereHas('instrumento', function ($instrumentoQuery) use ($filters) {
                $instrumentoQuery->where('estado', $filters['estado']);
            });
        }

        if (isset($filters['tipo']) && $filters['tipo'] !== '') {
            $query->where('tipo', $filters['tipo']);
        }

        if (isset($filters['es_marco']) && $filters['es_marco'] !== '') {
            $esMarco = filter_var(
                $filters['es_marco'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($esMarco !== null) {
                $query->whereHas($esMarco ? 'marco' : 'especifico');
            }
        }

        if (isset($filters['id_unidad_academica']) && $filters['id_unidad_academica'] !== '') {
            $query->where('id_unidad_academica', $filters['id_unidad_academica']);
        }

        if (isset($filters['buscar']) && $filters['buscar'] !== '') {
            $query->whereHas('instrumento', function ($instrumentoQuery) use ($filters) {
                $instrumentoQuery->where('titulo', 'like', '%'.$filters['buscar'].'%');
            });
        }

        return $query->paginate(15)->appends($filters);
    }

    public function findById(string $id): ?Convenio
    {
        return Convenio::query()
            ->with([
                'instrumento',
                'unidadAcademica',
                'institucionesFirmantes',
                'marco',
                'especifico.marco.instrumento',
                'historialEstados',
            ])
            ->find($id);
    }

    public function store(array $data, Usuario $usuario): Instrumento
    {
        return DB::transaction(function () use ($data, $usuario) {
            $instrumento = Instrumento::create([
                'titulo' => $data['titulo'],
                'objeto' => $data['objeto'] ?? null,
                'estado' => 'Borrador',
                'version_registro' => 1,
                'dias_preaviso' => $data['dias_preaviso'] ?? 30,
            ]);

            $convenio = Convenio::create([
                'id_instrumento' => $instrumento->id_instrumento,
                'tipo' => $data['tipo'],
                'id_unidad_academica' => $data['id_unidad_academica'],
            ]);

            if ($data['es_marco']) {
                Marco::create([
                    'id_instrumento' => $instrumento->id_instrumento,
                ]);
            } else {
                Especifico::create([
                    'id_instrumento' => $instrumento->id_instrumento,
                    'id_convenio_marco' => $data['id_convenio_marco'],
                ]);
            }

            $convenio->institucionesFirmantes()->attach($data['instituciones_ids']);

            HistorialEstado::create([
                'estado_anterior' => null,
                'estado_nuevo' => 'Borrador',
                'motivo' => 'Creación del convenio',
                'id_instrumento' => $instrumento->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            return $instrumento->load([
                'convenio',
                'convenio.marco',
                'convenio.especifico',
                'convenio.institucionesFirmantes',
            ]);
        });
    }
}
