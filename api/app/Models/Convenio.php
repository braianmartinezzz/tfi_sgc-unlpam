<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Convenio extends Model
{
    protected $table = 'convenio';

    protected $primaryKey = 'id_instrumento';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_instrumento',
        'tipo',
        'alcance',
        'obligaciones',
        'condiciones_recision',
        'marco_legal',
        'condicion_renovacion',
        'confidencialidad',
        'id_unidad_academica',
    ];

    protected $casts = [
        'confidencialidad' => 'boolean',
    ];

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(Instrumento::class, 'id_instrumento', 'id_instrumento');
    }

    public function unidadAcademica(): BelongsTo
    {
        return $this->belongsTo(UnidadAcademica::class, 'id_unidad_academica', 'id_unidad_academica');
    }

    public function especifico(): HasOne
    {
        return $this->hasOne(Especifico::class, 'id_instrumento', 'id_instrumento');
    }

    public function marco(): HasOne
    {
        return $this->hasOne(Marco::class, 'id_instrumento', 'id_instrumento');
    }

    public function historialEstados(): HasMany
    {
        return $this->hasMany(HistorialEstado::class, 'id_instrumento', 'id_instrumento');
    }

    public function institucionesFirmantes(): BelongsToMany
    {
        return $this->belongsToMany(
            InstitucionFirmante::class,
            'convenio_institucion',
            'id_instrumento',
            'id_institucion',
            'id_instrumento',
            'id_institucion'
        );
    }

    public function personasFirmantes(): BelongsToMany
    {
        return $this->belongsToMany(
            Persona::class,
            'firma',
            'id_instrumento',
            'id_persona',
            'id_instrumento',
            'id_persona'
        )->withPivot('fecha_hora_firma', 'latitud', 'longitud');
    }
}
