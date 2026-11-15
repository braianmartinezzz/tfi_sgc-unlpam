<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docente extends Model
{
    protected $table = 'docente';

    protected $primaryKey = 'id_persona';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_persona',
    ];

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'id_persona', 'id_persona');
    }

    public function pasantiasComoGuia(): HasMany
    {
        return $this->hasMany(Pasantia::class, 'id_docente_guia', 'id_persona');
    }
}
