<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Documento extends Model
{
    protected $table = 'documento';

    protected $primaryKey = 'id_documento';

    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'titulo',
        'id_instrumento',
    ];

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento', 'id_instrumento');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(Version::class, 'id_documento', 'id_documento');
    }

    public function ultimaVersion(): HasOne
    {
        return $this->hasOne(Version::class, 'id_documento', 'id_documento')
            ->latestOfMany('numero');
    }
}
