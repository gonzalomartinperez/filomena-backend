<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pseudonimo extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'pseudonimos';

    protected $fillable = [
        'nombre',
    ];

    protected $casts = [
        'en_uso' => 'boolean',
    ];

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'examen_estudiante_pseudonimo',
            'pseudonimo_id',
            'estudiante_id'
        )->withPivot('examen_id');
    }
}
