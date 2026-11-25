<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushToken extends Model
{
    protected $table = 'push_token';

    protected $primaryKey = 'id_push_token';

    protected $fillable = [
        'id_usuario',
        'token',
        'dispositivo',
        'ultimo_uso',
    ];

    protected $casts = [
        'ultimo_uso' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
