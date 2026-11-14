<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Representante extends Model
{
    protected $table = 'representante';

    protected $primaryKey = 'id_persona';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_persona',
        'cargo',
        'id_institucion',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function institucionFirmante(): BelongsTo
    {
        return $this->belongsTo(
            InstitucionFirmante::class,
            'id_institucion',
            'id_institucion'
        );
    }

    public function pasantiasComoTutorLaboral(): HasMany
    {
        return $this->hasMany(Pasantia::class, 'id_tutor_laboral', 'id_persona');
    }
}
