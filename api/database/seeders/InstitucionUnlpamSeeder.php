<?php

namespace Database\Seeders;

use App\Models\InstitucionFirmante;
use Illuminate\Database\Seeder;

class InstitucionUnlpamSeeder extends Seeder
{
    public function run(): void
    {
        $institucion = InstitucionFirmante::query()->firstOrCreate(
            ['razon_social' => 'Universidad Nacional de La Pampa'],
            [
                'domicilio_legal' => 'Coronel Gil 353, Santa Rosa, La Pampa',
                'tipo_institucion' => null,
            ]
        );

        $institucion->domicilio_legal = 'Coronel Gil 353, Santa Rosa, La Pampa';
        $institucion->activo = true;
        $institucion->es_propia = true;
        $institucion->save();

        InstitucionFirmante::query()
            ->where('es_propia', true)
            ->where('id_institucion', '!=', $institucion->id_institucion)
            ->update(['es_propia' => false]);
    }
}
