<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Renovacion extends Model
{
    protected $table = 'renovacion';

    protected $primaryKey = 'id_instrumento';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_instrumento',
        'fecha',
        'automatica',
        'id_instrumento_renovado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'automatica' => 'boolean',
    ];

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(
            Convenio::class,
            'id_instrumento',
            'id_instrumento'
        );
    }

    public function convenioRenovado(): BelongsTo
    {
        return $this->belongsTo(
            Convenio::class,
            'id_instrumento_renovado',
            'id_instrumento'
        );
    }
}
