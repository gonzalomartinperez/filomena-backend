<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RespuestaEstudiante extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'respuestas_estudiantes';

    protected $fillable = [
        'respuesta',
        'pregunta_id',
        'estudiante_id',
    ];

    public function estudiante()
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function pregunta()
    {
        return $this->belongsTo(Pregunta::class);
    }
}
