<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'estudiante_id' => $this->estudiante_id,
            'item_id' => $this->item_id,
            'aprobado' => (bool) $this->aprobado,
        ];
    }
}
