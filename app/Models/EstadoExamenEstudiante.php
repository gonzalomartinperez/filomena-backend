<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EstadoExamenEstudiante extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'estados_examenes_estudiantes';

    protected $fillable = [
        'inicio',
        'hora_inicio',
        'finalizado',
        'hora_finalizacion',
        'posicion_finalizacion',
        'alertado',
        'alertas_disponibles',
        'defendido',
        'estudiante_id',
        'examen_id',
        'caso_id',
        'pregunta_id',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function estudiante()
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
