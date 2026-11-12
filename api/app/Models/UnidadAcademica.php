<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnidadAcademica extends Model
{
    protected $table = 'unidad_academica';

    protected $primaryKey = 'id_unidad_academica';

    public $timestamps = false;

    protected $fillable = [
        'sigla',
        'nombre',
        'id_institucion',
    ];

    public function institucionFirmante(): BelongsTo
    {
        return $this->belongsTo(InstitucionFirmante::class, 'id_institucion', 'id_institucion');
    }

    public function convenios(): HasMany
    {
        return $this->hasMany(Convenio::class, 'id_unidad_academica', 'id_unidad_academica');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_unidad', 'id_unidad_academica');
    }
}
