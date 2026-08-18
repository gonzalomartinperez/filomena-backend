<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotaItem extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'notas_items';

    protected $fillable = [
        'aprobado',
        'item_id',
        'estudiante_id',
    ];

    public function estudiante()
    {
        return $this->belongsTo(User::class, 'estudiante_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
