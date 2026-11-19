<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

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
        'alcance_global',
        'id_persona',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'alcance_global' => 'boolean',
    ];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function unidadAcademica(): BelongsTo
    {
        return $this->belongsTo(
            UnidadAcademica::class,
            'id_unidad',
            'id_unidad_academica'
        );
    }

    /** Persona firmante vinculada a este usuario (Rector/a, Decano/a, representantes). */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(Version::class, 'id_usuario', 'id_usuario');
    }

    public function historialesEstado(): HasMany
    {
        return $this->hasMany(
            HistorialEstado::class,
            'id_usuario',
            'id_usuario'
        );
    }
}
