<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Caso extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'casos';

    protected $fillable = [
        'titulo',
        'orden_relativo',
        'examen_id',
        'evaluador_id',
    ];

    public function evaluador()
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function secciones()
    {
        return $this->hasManyThrough(
            Seccion::class,
            TablaCotejo::class,
            'caso_id',
            'id',
            'id',
            'seccion_id'
        )
            ->distinct()
            ->orderBy('orden_relativo');
    }

    public function preguntas()
    {
        return $this->hasMany(Pregunta::class)->orderBy('orden_relativo');
    }

    public function items()
    {
        return $this->hasMany(Item::class)->orderBy('id');
    }

    public function tablasCotejo()
    {
        return $this->hasMany(TablaCotejo::class);
    }
}
