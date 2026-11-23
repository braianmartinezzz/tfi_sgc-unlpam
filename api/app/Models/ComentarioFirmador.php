<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComentarioFirmador extends Model
{
    protected $table = 'comentario_firmador';

    protected $primaryKey = 'id_comentario';

    public $timestamps = false;

    protected $fillable = [
        'id_instrumento',
        'id_usuario',
        'contenido',
        'ofensivo',
        'fecha',
    ];

    protected $casts = [
        'ofensivo' => 'boolean',
        'fecha' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento', 'id_instrumento');
    }
}
