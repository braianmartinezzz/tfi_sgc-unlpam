<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observacion extends Model
{
    protected $table = 'observacion';

    protected $primaryKey = 'id_observacion';

    public $timestamps = false;

    protected $fillable = [
        'contenido',
        'fecha',
        'estado_tramite',
        'resolucion',
        'respuesta',
        'fecha_resolucion',
        'id_instrumento',
        'id_usuario',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'fecha_resolucion' => 'datetime',
    ];

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(
            Convenio::class,
            'id_instrumento',
            'id_instrumento'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
