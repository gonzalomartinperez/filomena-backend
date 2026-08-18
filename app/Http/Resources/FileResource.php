<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'file_name' => $this->file_name,
            'mime' => $this->mime_type,
            'size' => $this->humanReadableSize,
            'url' => $this->getUrl(),
        ];
    }
}
