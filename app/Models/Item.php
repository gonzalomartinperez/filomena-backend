<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends BaseCacheableModel
{
    use ActivityLogsTrait, HasFactory;

    protected $table = 'items';

    protected $fillable = [
        'descripcion',
        'valor',
        'examen_id',
        'seccion_id',
        'tabla_cotejo_id',
        'caso_id',
        'pregunta_id',
    ];

    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }

    public function seccion()
    {
        return $this->belongsTo(Seccion::class);
    }

    public function tablaCotejo()
    {
        return $this->belongsTo(TablaCotejo::class);
    }

    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }

    public function pregunta()
    {
        return $this->belongsTo(Pregunta::class);
    }

    public function notas()
    {
        return $this->hasMany(NotaItem::class);
    }
}
