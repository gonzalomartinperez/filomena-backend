<?php

namespace App\Enums;

enum Reportes: string
{
    case PDF_ESTRUCTURA = 'pdf_estructura';
    case PDF_CORRECCIONES = 'pdf_correcciones';
    case XLSX_NOTAS = 'xlsx_notas';
    case XLSX_CORRECCIONES_DETALLADAS = 'xslx_correcciones_detalladas';
    case PDF_RESPUESTAS = 'pdf_respuestas';

    public function defaultFilePrefix(): string
    {
        return match ($this) {
            self::PDF_ESTRUCTURA => 'estructura-examen',
            self::PDF_CORRECCIONES => 'correcciones-examen',
            self::XLSX_NOTAS => 'notas-examen',
            self::XLSX_CORRECCIONES_DETALLADAS => 'correcciones-detalladas',
            self::PDF_RESPUESTAS => 'respuestas-examen',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::PDF_ESTRUCTURA, self::PDF_CORRECCIONES, self::PDF_RESPUESTAS => 'pdf',
            self::XLSX_NOTAS, self::XLSX_CORRECCIONES_DETALLADAS => 'xlsx',
        };
    }

    public function isPdf(): bool
    {
        return \in_array($this, [self::PDF_ESTRUCTURA, self::PDF_CORRECCIONES, self::PDF_RESPUESTAS], true);
    }
}
