<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adenda extends Model
{
    protected $table = 'adenda';

    protected $primaryKey = 'id_instrumento';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_instrumento',
        'id_instrumento_modificado',
    ];

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(
            Instrumento::class,
            'id_instrumento',
            'id_instrumento'
        );
    }

    public function instrumentoModificado(): BelongsTo
    {
        return $this->belongsTo(
            Instrumento::class,
            'id_instrumento_modificado',
            'id_instrumento'
        );
    }
}
