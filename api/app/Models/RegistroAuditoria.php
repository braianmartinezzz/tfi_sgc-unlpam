<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroAuditoria extends Model
{
    protected $table = 'registro_auditoria';

    protected $primaryKey = 'id_registro';

    public $timestamps = false;

    protected $fillable = [
        'accion',
        'fecha_hora',
        'detalle',
        'id_instrumento',
        'id_usuario',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
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
