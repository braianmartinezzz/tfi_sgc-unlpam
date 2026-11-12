<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Persona extends Model
{
    protected $table = 'persona';

    protected $primaryKey = 'id_persona';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'cuil',
    ];

    public function conveniosFirmados(): BelongsToMany
    {
        return $this->belongsToMany(
            Convenio::class,
            'firma',
            'id_persona',
            'id_instrumento',
            'id_persona',
            'id_instrumento'
        )->withPivot('fecha_hora_firma', 'latitud', 'longitud');
    }
}
