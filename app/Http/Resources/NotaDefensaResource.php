<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotaDefensaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'estudiante_id' => $this->estudiante_id,
            'caso_id' => $this->caso_id,
            'seccion_id' => $this->seccion_id,
            'nota' => $this->nota,
            'max_nota' => $this->max_nota,
        ];
    }
}
