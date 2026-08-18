<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaDefensa extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'notas_defensas';

    protected $fillable = [
        'nota',
        'estudiante_id',
        'caso_id',
        'seccion_id',
    ];

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
    }
}
