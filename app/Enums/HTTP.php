<?php

namespace App\Enums;

use App\Traits\EnumsTrait;
use Illuminate\Http\Response;

/**
 * Enum para códigos HTTP y mensajes asociados.
 */
enum HTTP: int
{
    use EnumsTrait;

    case OK = Response::HTTP_OK;
    case CREATED = Response::HTTP_CREATED;
    case ACCEPTED = Response::HTTP_ACCEPTED;
    case NO_CONTENT = Response::HTTP_NO_CONTENT;
    case BAD_REQUEST = Response::HTTP_BAD_REQUEST;
    case UNAUTHORIZED = Response::HTTP_UNAUTHORIZED;
    case FORBIDDEN = Response::HTTP_FORBIDDEN;
    case NOT_FOUND = Response::HTTP_NOT_FOUND;
    case METHOD_NOT_ALLOWED = Response::HTTP_METHOD_NOT_ALLOWED;
    case CONFLICT = Response::HTTP_CONFLICT;
    case TOO_MANY_REQUESTS = Response::HTTP_TOO_MANY_REQUESTS;
    case UNPROCESSABLE_ENTITY = Response::HTTP_UNPROCESSABLE_ENTITY;
    case INTERNAL_SERVER_ERROR = Response::HTTP_INTERNAL_SERVER_ERROR;
    case SERVICE_UNAVAILABLE = Response::HTTP_SERVICE_UNAVAILABLE;

    public function message(): string
    {
        if (in_array($this->value, HTTP::values())) {
            return __("http-statuses.{$this->value}");
        }

        return __('http-statuses.unknownError');
    }
}
