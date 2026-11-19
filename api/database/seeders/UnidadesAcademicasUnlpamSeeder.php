<?php

namespace Database\Seeders;

use App\Models\InstitucionFirmante;
use App\Models\UnidadAcademica;
use Illuminate\Database\Seeder;

class UnidadesAcademicasUnlpamSeeder extends Seeder
{
    public function run(): void
    {
        $institucion = InstitucionFirmante::query()
            ->where('es_propia', true)
            ->firstOrFail();

        $unidadesAcademicas = [
            'FA' => 'Facultad de Agronomía',
            'FCS' => 'Facultad de Ciencias de la Salud',
            'FCEyJ' => 'Facultad de Ciencias Económicas y Jurídicas',
            'FCEyN' => 'Facultad de Ciencias Exactas y Naturales',
            'FCH' => 'Facultad de Ciencias Humanas',
            'FCV' => 'Facultad de Ciencias Veterinarias',
            'FI' => 'Facultad de Ingeniería',
        ];

        foreach ($unidadesAcademicas as $sigla => $nombre) {
            $unidad = UnidadAcademica::query()->updateOrCreate(
                ['sigla' => $sigla],
                [
                    'nombre' => $nombre,
                    'id_institucion' => $institucion->id_institucion,
                ]
            );

            $unidad->activo = true;
            $unidad->save();
        }
    }
}
