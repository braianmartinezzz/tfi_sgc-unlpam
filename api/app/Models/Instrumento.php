<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Instrumento extends Model
{
    protected $table = 'instrumento';

    protected $primaryKey = 'id_instrumento';

    public $timestamps = false;

    protected $fillable = [
        'titulo',
        'objeto',
        'estado',
        'fecha_firma',
        'inicio',
        'vencimiento',
        'dias_preaviso',
        'expediente',
        'resolucion',
        'notas',
        'version_registro',
    ];

    protected $casts = [
        'fecha_firma' => 'date',
        'inicio' => 'date',
        'vencimiento' => 'date',
        'dias_preaviso' => 'integer',
        'version_registro' => 'integer',
    ];

    public function convenio(): HasOne
    {
        return $this->hasOne(Convenio::class, 'id_instrumento', 'id_instrumento');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'id_instrumento', 'id_instrumento');
    }
}
