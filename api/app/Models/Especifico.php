<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Especifico extends Model
{
    protected $table = 'especifico';

    protected $primaryKey = 'id_instrumento';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_instrumento',
        'id_convenio_marco',
    ];

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(
            Convenio::class,
            'id_instrumento',
            'id_instrumento'
        );
    }

    public function marco(): BelongsTo
    {
        return $this->belongsTo(
            Marco::class,
            'id_convenio_marco',
            'id_instrumento'
        );
    }
}