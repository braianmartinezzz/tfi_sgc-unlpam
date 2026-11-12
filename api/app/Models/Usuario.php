<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Usuario extends Model
{
    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'mail',
        'nombre',
        'password',
        'activo',
        'id_rol',
        'id_unidad',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function unidadAcademica(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'id_unidad', 'id_unidad_academica');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(Version::class, 'id_usuario', 'id_usuario');
    }

    public function historialesEstado(): HasMany
    {
        return $this->hasMany(HistorialEstado::class, 'id_usuario', 'id_usuario');
    }
}
