<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEstado extends Model
{
    protected $table = 'historial_estado';

    protected $primaryKey = 'id_historial';

    public $timestamps = false;

    protected $fillable = [
        'estado_anterior',
        'estado_nuevo',
        'fecha_hora',
        'motivo',
        'id_instrumento',
        'id_usuario',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class, 'id_instrumento', 'id_instrumento');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
