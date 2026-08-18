<?php

namespace App\Http\Controllers\Api;

use App\Enums\Colecciones;
use App\Enums\HTTP;
use App\Http\Requests\FileRequests\StoreFileRequest;
use App\Http\Resources\FileResource;
use App\Models\Examen;
use App\Models\Pregunta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Controlador genérico para gestionar los adjuntos (Spatie Media-Library)
 * de distintas entidades (Examen, Pregunta, …).
 *
 * Se guía por un único parámetro de query-string que indica la entidad
 * propietaria del archivo.  Por ejemplo:
 *   • GET  /archivos?examen=3
 *   • POST /archivos?pregunta=42
 */
class FileController extends ApiBaseController
{
    /**
     * Mapa «nombre del parámetro de query → clase del modelo».
     *
     * @var array<string, class-string<Model>>
     */
    private const ENTITY_MAP = [
        'examen' => Examen::class,
        'pregunta' => Pregunta::class,
    ];

    /**
     * Resuelve la entidad propietaria a partir de los parámetros recibidos.
     *
     * @return Model|null|false
     *                          Model  → entidad encontrada
     *                          null   → parámetro válido pero no existe registro
     *                          false  → cero o más de un parámetro ► 400 Bad Request
     */
    private function resolveOwner(Request $request): Model|null|false
    {
        $params = array_intersect(array_keys($request->query()), array_keys(self::ENTITY_MAP));

        if (count($params) !== 1) {
            return false;
        }

        $param = reset($params);
        $class = self::ENTITY_MAP[$param];

        return $class::find($request->query($param));
    }

    public function index(Request $request)
    {
        $owner = $this->resolveOwner($request);

        if ($owner === false) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'Parámetros incorrectos');
        }
        if (! $owner) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Entidad no encontrada');
        }

        $files = $owner->getMedia(Colecciones::ARCHIVOS->value);

        return $this->sendResponse(HTTP::OK, FileResource::collection($files), 'Archivos recuperados exitosamente');
    }

    public function store(StoreFileRequest $request)
    {
        $owner = $this->resolveOwner($request);

        if ($owner === false) {
            return $this->sendResponse(HTTP::BAD_REQUEST, [], 'Parámetros incorrectos');
        }
        if (! $owner) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Entidad no encontrada');
        }

        $media = $owner->addMediaFromRequest('archivo')->toMediaCollection(Colecciones::ARCHIVOS->value);

        return $this->sendResponse(HTTP::CREATED, new FileResource($media), 'Archivo guardado exitosamente');
    }

    public function show(Media $media)
    {
        return $this->sendResponse(HTTP::OK, new FileResource($media), 'Archivo recuperado exitosamente');
    }

    public function destroy(Media $media)
    {
        $media->delete();

        return $this->sendResponse(HTTP::OK, [], 'Archivo eliminado exitosamente');
    }
}
