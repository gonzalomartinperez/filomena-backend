<?php

namespace App\Enums;

use App\Traits\EnumsTrait;

/**
 * Colecciones para guardar información en almacenamiento público
 */
enum Colecciones: string
{
    use EnumsTrait;

    case ARCHIVOS = 'archivos';
    case REPORTES = 'reportes';
}
