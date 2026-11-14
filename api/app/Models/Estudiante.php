<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estudiante extends Model
{
    protected $table = 'estudiante';

    protected $primaryKey = 'id_persona';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_persona',
        'carrera',
        'domicilio',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function pasantias(): HasMany
    {
        return $this->hasMany(Pasantia::class, 'id_estudiante', 'id_persona');
    }
}
