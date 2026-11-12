<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\Instrumento;
use App\Models\Marco;
use Illuminate\Support\Facades\DB;

class ConvenioService
{
    public function store(array $data): Instrumento
    {
        return DB::transaction(function () use ($data) {
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

            return $instrumento->load([
                'convenio',
                'convenio.marco',
                'convenio.especifico',
                'convenio.institucionesFirmantes',
            ]);
        });
    }
}
