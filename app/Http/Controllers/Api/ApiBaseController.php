<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiBaseController extends Controller
{
    /**
     * Formatea y devuelve la respuesta JSON estándar de la API.
     *
     * @param  int  $status  Código de estado HTTP de la respuesta.
     * @param  mixed  $data  Datos que se devolverán en la respuesta, pueden ser resources o arrays.
     * @return JsonResponse Respuesta JSON con estructura: status, message, data.
     */
    public static function sendResponse(HTTP $http, $data, $message = '')
    {
        return response()->json([
            'status' => $http->value,
            'message' => $message != '' ? $message : $http->message(),
            'data' => $data,
        ], $http->value);
    }
}
