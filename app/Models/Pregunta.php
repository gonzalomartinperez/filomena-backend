<?php

namespace App\Models;

use App\Enums\Colecciones;
use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Pregunta extends BaseCacheableModel implements HasMedia
{
    use ActivityLogsTrait, HasFactory, InteractsWithMedia;

    protected $table = 'preguntas';

    protected $fillable = [
        'consigna',
        'descripcion',
        'orden_relativo',
        'examen_id',
        'caso_id',
    ];

    protected const FILES_COLLECTION = 'archivos';

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }

    public function secciones()
    {
        return $this->hasManyThrough(
            Seccion::class,
            Item::class,
            'pregunta_id',
            'id',
            'id',
            'seccion_id'
        )
            ->distinct()
            ->orderBy('orden_relativo');
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function respuestasEstudiantes()
    {
        return $this->hasMany(RespuestaEstudiante::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(Colecciones::ARCHIVOS->value)
            ->useDisk('public');
    }
}
