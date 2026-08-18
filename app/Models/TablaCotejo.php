<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TablaCotejo extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'tablas_cotejo';

    protected $fillable = [
        'porcentaje',
        'examen_id',
        'caso_id',
        'seccion_id',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function seccion()
    {
        return $this->belongsTo(Seccion::class);
    }

    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class)->orderBy('id');
    }
}
