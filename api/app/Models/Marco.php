<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Marco extends Model
{
    protected $table = 'marco';

    protected $primaryKey = 'id_instrumento';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_instrumento',
        'id_convenio_especifico',
    ];

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class, 'id_instrumento', 'id_instrumento');
    }

    public function convenioEspecifico(): BelongsTo
    {
        return $this->belongsTo(Especifico::class, 'id_convenio_especifico', 'id_instrumento');
    }
}
