<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Version extends Model
{
    protected $table = 'version';

    protected $primaryKey = 'id_version';

    public $timestamps = false;

    protected $fillable = [
        'numero',
        'nombre_archivo',
        'tamanio_bytes',
        'hash_sha256',
        'ruta_interna',
        'fecha_carga',
        'id_documento',
        'id_usuario',
    ];

    protected $casts = [
        'numero' => 'integer',
        'tamanio_bytes' => 'integer',
        'fecha_carga' => 'datetime',
    ];

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento', 'id_documento');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
