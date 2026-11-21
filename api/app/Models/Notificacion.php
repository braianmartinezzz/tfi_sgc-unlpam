<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificacion';

    protected $primaryKey = 'id_notificacion';

    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'mensaje',
        'fecha_relevante',
        'fecha_generacion',
        'leida',
        'id_instrumento',
        'id_usuario',
    ];

    protected $casts = [
        'fecha_relevante' => 'date',
        'fecha_generacion' => 'datetime',
        'leida' => 'boolean',
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
