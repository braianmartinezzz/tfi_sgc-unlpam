<?php

namespace Database\Seeders;

use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\UnidadAcademica;
use Illuminate\Database\Seeder;

/**
 * Convenios de ejemplo para probar listado, búsqueda y filtros.
 *
 * Requiere haber corrido antes DatabaseSeeder (usa sus unidades académicas).
 * Se puede ejecutar varias veces: no duplica los convenios (busca por título).
 *
 *   php artisan db:seed --class=ConveniosSeeder
 */
class ConveniosSeeder extends Seeder
{
    public function run(): void
    {
        $ua1 = UnidadAcademica::where('sigla', 'UA-TEST-01')->firstOrFail();
        $ua2 = UnidadAcademica::where('sigla', 'UA-TEST-02')->firstOrFail();

        $firmanteA = InstitucionFirmante::firstOrCreate([
            'razon_social' => 'Institución de prueba para convenios',
        ]);
        $firmanteB = InstitucionFirmante::firstOrCreate([
            'razon_social' => 'Municipalidad de General Pico',
        ]);
        $firmanteC = InstitucionFirmante::firstOrCreate([
            'razon_social' => 'Cooperativa Eléctrica de Pico',
        ]);

        // Convenios marco (uno por unidad) a los que se asocian los específicos.
        $marco1 = $this->crear($ua1, [$firmanteA, $firmanteB], 'Convenio marco de cooperación con el municipio', 'Cooperación', 'Firmado', 'marco', null, 400, 700);
        $marco2 = $this->crear($ua2, [$firmanteC], 'Convenio marco de colaboración científica', 'Colaboración', 'Firmado', 'marco', null, 200, 500);

        // UA-TEST-01 (la ve el usuario de Gestión de prueba).
        $this->crear($ua1, [$firmanteB], 'Pasantías de estudiantes en el área de sistemas', 'Pasantía', 'Borrador', 'especifico', $marco1);
        $this->crear($ua1, [$firmanteB], 'Capacitación en herramientas digitales para docentes', 'Capacitación', 'EnCorreccion', 'especifico', $marco1);
        $this->crear($ua1, [$firmanteA], 'Prácticas profesionales supervisadas', 'Pasantía', 'Revisión', 'especifico', $marco1);
        $this->crear($ua1, [$firmanteB], 'Desarrollo de un sistema de gestión municipal', 'Colaboración', 'Aprobado', 'especifico', $marco1);
        $this->crear($ua1, [$firmanteA, $firmanteB], 'Intercambio de material bibliográfico', 'Cooperación', 'Firmado', 'especifico', $marco1, 120, 25);
        $this->crear($ua1, [$firmanteB], 'Programa de extensión universitaria rural', 'Extensión', 'Rescindido', 'especifico', $marco1, 300, 60);

        // UA-TEST-02 (solo la ve Administración, o un rol con alcance global).
        $this->crear($ua2, [$firmanteC], 'Estudio de eficiencia energética en edificios', 'Colaboración', 'Borrador', 'especifico', $marco2);
        $this->crear($ua2, [$firmanteC], 'Pasantías en la cooperativa eléctrica', 'Pasantía', 'Firmado', 'especifico', $marco2, 90, 10);
        $this->crear($ua2, [$firmanteC], 'Proyecto de investigación sobre energías renovables', 'Investigación', 'Rechazado', 'especifico', $marco2);
        $this->crear($ua2, [$firmanteC], 'Convenio de asistencia técnica (vencido)', 'Asistencia técnica', 'Archivado', 'especifico', $marco2, 900, -30);
    }

    /**
     * @param  array<int, InstitucionFirmante>  $firmantes
     * @param  'marco'|'especifico'  $subtipo
     * @param  int|null  $diasDesdeFirma  hace cuántos días se firmó (solo estados firmados)
     * @param  int|null  $diasHastaVencimiento  días hasta el vencimiento (negativo = ya venció)
     */
    private function crear(
        UnidadAcademica $unidad,
        array $firmantes,
        string $titulo,
        string $tipo,
        string $estado,
        string $subtipo,
        ?Convenio $marco = null,
        ?int $diasDesdeFirma = null,
        ?int $diasHastaVencimiento = null,
    ): Convenio {
        $firmado = in_array($estado, ['Firmado', 'Rescindido', 'Archivado'], true);

        $instrumento = Instrumento::firstOrCreate(
            ['titulo' => $titulo],
            [
                'objeto' => "Objeto del convenio: {$titulo}.",
                'estado' => $estado,
                'fecha_firma' => $firmado && $diasDesdeFirma !== null ? now()->subDays($diasDesdeFirma)->toDateString() : null,
                'inicio' => $firmado && $diasDesdeFirma !== null ? now()->subDays($diasDesdeFirma)->toDateString() : null,
                'vencimiento' => $diasHastaVencimiento !== null ? now()->addDays($diasHastaVencimiento)->toDateString() : null,
                'dias_preaviso' => 45,
            ]
        );

        $convenio = Convenio::firstOrCreate(
            ['id_instrumento' => $instrumento->id_instrumento],
            [
                'tipo' => $tipo,
                'id_unidad_academica' => $unidad->id_unidad_academica,
                'confidencialidad' => false,
            ]
        );

        $convenio->institucionesFirmantes()->syncWithoutDetaching(
            collect($firmantes)->pluck('id_institucion')->all()
        );

        if ($subtipo === 'marco') {
            Marco::firstOrCreate(['id_instrumento' => $convenio->id_instrumento]);
        } else {
            Especifico::firstOrCreate(
                ['id_instrumento' => $convenio->id_instrumento],
                ['id_convenio_marco' => $marco?->id_instrumento]
            );
        }

        return $convenio;
    }
}
