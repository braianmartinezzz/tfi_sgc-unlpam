<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitucionFirmante extends Model
{
    protected $table = 'institucion_firmante';

    protected $primaryKey = 'id_institucion';

    public $timestamps = false;

    protected $fillable = [
        'razon_social',
        'cuit',
        'domicilio_legal',
        'tipo_institucion',
    ];

    public function unidadesAcademicas(): HasMany
    {
        return $this->hasMany(UnidadAcademica::class, 'id_institucion', 'id_institucion');
    }

    public function convenios(): BelongsToMany
    {
        return $this->belongsToMany(
            Convenio::class,
            'convenio_institucion',
            'id_institucion',
            'id_instrumento',
            'id_institucion',
            'id_instrumento'
        );
    }
}
