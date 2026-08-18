<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EstadoExamenEvaluador extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'estados_examenes_evaluadores';

    protected $fillable = [
        'finalizado',
        'correcciones_asignadas',
        'correcciones_realizadas',
        'examen_id',
        'evaluador_id',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function evaluador()
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }
}
