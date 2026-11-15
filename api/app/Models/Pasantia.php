<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pasantia extends Model
{
    protected $table = 'pasantia';

    protected $primaryKey = 'id_pasantia';

    public $timestamps = false;

    protected $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'carga_horaria',
        'asignacion_estimulo',
        'porcentaje_gasto_admin',
        'lugar',
        'tareas',
        'seguro',
        'id_convenio_especifico',
        'id_estudiante',
        'id_docente_guia',
        'id_tutor_laboral',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'carga_horaria' => 'integer',
        'asignacion_estimulo' => 'decimal:2',
        'porcentaje_gasto_admin' => 'decimal:2',
    ];

    public function convenioEspecifico(): BelongsTo
    {
        return $this->belongsTo(
            Especifico::class,
            'id_convenio_especifico',
            'id_instrumento'
        );
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_persona');
    }

    public function docenteGuia(): BelongsTo
    {
        return $this->belongsTo(
            Docente::class,
            'id_docente_guia',
            'id_persona'
        );
    }

    public function tutorLaboral(): BelongsTo
    {
        return $this->belongsTo(
            Representante::class,
            'id_tutor_laboral',
            'id_persona'
        );
    }
}
