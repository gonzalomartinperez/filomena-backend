<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Seccion extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'secciones';

    protected $fillable = [
        'nombre',
        'orden_relativo',
        'examen_id',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function tablasCotejo()
    {
        return $this->hasMany(TablaCotejo::class);
    }

    public function preguntas()
    {
        return $this->hasMany(Pregunta::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function casos()
    {
        return $this->hasMany(Caso::class, 'examen_id', 'examen_id')->orderBy('orden_relativo');
    }
}
