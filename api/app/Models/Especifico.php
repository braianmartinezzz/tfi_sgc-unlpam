<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Especifico extends Model
{
    protected $table = 'especifico';

    protected $primaryKey = 'id_instrumento';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id_instrumento',
    ];

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class, 'id_instrumento', 'id_instrumento');
    }

    public function marcos(): HasMany
    {
        return $this->hasMany(Marco::class, 'id_convenio_especifico', 'id_instrumento');
    }
}
