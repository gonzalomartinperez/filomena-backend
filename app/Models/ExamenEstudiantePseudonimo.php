<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExamenEstudiantePseudonimo extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'examen_estudiante_pseudonimo';

    protected $fillable = [
        'examen_id',
        'pseudonimo_id',
        'estudiante_id',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function pseudonimo()
    {
        return $this->belongsTo(Pseudonimo::class);
    }

    public function estudiante()
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }
}
