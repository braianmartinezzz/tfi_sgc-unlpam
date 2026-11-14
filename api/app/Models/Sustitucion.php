<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sustitucion extends Model
{
    protected $table = 'sustitucion';

    protected $primaryKey = 'id_instrumento';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_instrumento',
        'fecha',
        'motivo',
        'id_instrumento_sustituido',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(
            Convenio::class,
            'id_instrumento',
            'id_instrumento'
        );
    }

    public function convenioSustituido(): BelongsTo
    {
        return $this->belongsTo(
            Convenio::class,
            'id_instrumento_sustituido',
            'id_instrumento'
        );
    }
}
