<?php

namespace App\Enums;

use App\Traits\EnumsTrait;

/**
 * Enum de roles disponibles en el sistema y sus permisos asociados.
 */
enum Roles: string
{
    use EnumsTrait;

    case ADMINISTRADOR = 'administrador';
    case EVALUADOR = 'evaluador';
    case ESTUDIANTE = 'estudiante';

    /**
     * Devuelve el conjunto de permisos asignados a cada rol.
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMINISTRADOR => [
                Permisos::META_ROLES->value,

                Permisos::USUARIO_INDEX->value,
                Permisos::USUARIO_SHOW->value,
                Permisos::USUARIO_UPSERT->value,
                Permisos::USUARIO_DESTROY->value,
                Permisos::USUARIO_IMPORT->value,

                Permisos::EXAMEN_INDEX->value,
                Permisos::EXAMEN_SHOW->value,
                Permisos::EXAMEN_SHOW_BY_CODE->value,
                Permisos::EXAMEN_STORE->value,
                Permisos::EXAMEN_UPDATE->value,
                Permisos::EXAMEN_DESTROY->value,
                Permisos::EXAMEN_REESTABLISH->value,
                Permisos::EXAMEN_PUBLIC->value,
                Permisos::EXAMEN_FINISH->value,
                Permisos::EXAMEN_DEFENSE->value,

                Permisos::ADMINISTRAR_EXAMEN_PORCENTAJE_ALUMNOS_FINALIZADOS->value,
                Permisos::ADMINISTRAR_EXAMEN_PORCENTAJE_EVALUADORES_FINALIZADOS->value,
                Permisos::ADMINISTRAR_EXAMEN_ALUMNOS_FINALIZADOS->value,
                Permisos::ADMINISTRAR_EXAMEN_PROGRESO_ESTUDIANTES->value,
                Permisos::ADMINISTRAR_EXAMEN_ALERTAR_ESTUDIANTE->value,
                Permisos::ADMINISTRAR_EXAMEN_PROGRESO_EVALUADORES->value,
                Permisos::ADMINISTRAR_EXAMEN_ESTADOS_ESTUDIANTES->value,
                Permisos::ADMINISTRAR_EXAMEN_ESTADOS_EVALUADORES->value,

                Permisos::PSEUDONIMO_INDEX->value,
                Permisos::PSEUDONIMO_SHOW->value,
                Permisos::PSEUDONIMO_STORE->value,
                Permisos::PSEUDONIMO_UPDATE->value,
                Permisos::PSEUDONIMO_DESTROY->value,

                Permisos::CASO_INDEX->value,
                Permisos::CASO_UPDATE->value,
                Permisos::CASO_SHOW->value,
                Permisos::CASO_STORE->value,
                Permisos::CASO_DESTROY->value,
                Permisos::CASO_IMPORT->value,

                Permisos::SECCION_INDEX->value,
                Permisos::SECCION_STORE->value,
                Permisos::SECCION_SHOW->value,
                Permisos::SECCION_UPDATE->value,
                Permisos::SECCION_DESTROY->value,
                Permisos::SECCION_IMPORT->value,

                Permisos::PREGUNTA_INDEX->value,
                Permisos::PREGUNTA_STORE->value,
                Permisos::PREGUNTA_DESTROY->value,
                Permisos::PREGUNTA_UPDATE->value,
                Permisos::PREGUNTA_SHOW->value,
                Permisos::PREGUNTA_IMPORT->value,

                Permisos::TABLA_COTEJO_INDEX->value,
                Permisos::TABLA_COTEJO_SHOW->value,
                Permisos::TABLA_COTEJO_STORE->value,
                Permisos::TABLA_COTEJO_UPDATE->value,
                Permisos::TABLA_COTEJO_DESTROY->value,
                Permisos::TABLA_COTEJO_PUNTAJES->value,
                Permisos::TABLA_COTEJO_IMPORT->value,

                Permisos::FILE_INDEX->value,
                Permisos::FILE_STORE->value,
                Permisos::FILE_SHOW->value,
                Permisos::FILE_DESTROY->value,

                Permisos::REPORTE_PDF_ESTRUCTURA->value,
                Permisos::REPORTE_PDF_CORRECCIONES->value,
                Permisos::REPORTE_XLSX_NOTAS->value,
                Permisos::REPORTE_XLSX_CORRECCIONES_DETALLADAS->value,

                Permisos::DEFENSAS_INDEX->value,
                Permisos::DEFENSAS_UPSERT->value,
                Permisos::DEFENSAS_ACTUALIZAR_ESTADO_ESTUDIANTE->value,
                Permisos::EVALUADOR_RESPUESTAS_ESTUDIANTES->value,
                Permisos::EVALUADOR_CORRECCIONES_INDEX->value,
            ],
            self::EVALUADOR => [
                Permisos::EXAMEN_SHOW_BY_CODE->value,

                Permisos::USUARIO_INDEX->value,
                Permisos::USUARIO_SHOW->value,

                Permisos::PSEUDONIMO_INDEX->value,
                Permisos::PSEUDONIMO_SHOW->value,

                Permisos::SECCION_INDEX->value,
                Permisos::SECCION_SHOW->value,

                Permisos::CASO_INDEX->value,
                Permisos::CASO_SHOW->value,

                Permisos::PREGUNTA_INDEX->value,
                Permisos::PREGUNTA_SHOW->value,

                Permisos::TABLA_COTEJO_INDEX->value,
                Permisos::TABLA_COTEJO_SHOW->value,

                Permisos::FILE_INDEX->value,

                Permisos::EVALUADOR_RESPUESTAS_ESTUDIANTES->value,
                Permisos::EVALUADOR_CORRECCIONES_INDEX->value,
                Permisos::EVALUADOR_CARGAR_NOTA_PREGUNTA->value,
                Permisos::EVALUADOR_ESTADO_EXAMEN->value,
                Permisos::EVALUADOR_FINALIZAR_CORRECCION_EXAMEN->value,

                Permisos::REPORTE_PDF_CORRECCIONES->value,
            ],
            self::ESTUDIANTE => [
                Permisos::EXAMEN_SHOW_BY_CODE->value,
                Permisos::CASO_INDEX->value,
                Permisos::CASO_SHOW->value,
                Permisos::PREGUNTA_INDEX->value,
                Permisos::PREGUNTA_SHOW->value,
                Permisos::FILE_INDEX->value,
                Permisos::FILE_SHOW->value,

                Permisos::ESTUDIANTE_EXAMEN_INICIAR->value,
                Permisos::ESTUDIANTE_EXAMEN_FINALIZAR->value,
                Permisos::ESTUDIANTE_EXAMEN_DEFENDER->value,
                Permisos::ESTUDIANTE_EXAMEN_ALERTAR->value,
                Permisos::ESTUDIANTE_EXAMEN_PSEUDONIMO->value,
                Permisos::ESTUDIANTE_EXAMEN_ESTADO->value,
                Permisos::ESTUDIANTE_EXAMEN_ACTUALIZAR_PUNTERO->value,
                Permisos::ESTUDIANTE_EXAMEN_NAVEGAR->value,

                Permisos::ESTUDIANTE_PREGUNTA_RESPONDER->value,
                Permisos::ESTUDIANTE_PREGUNTA_RESPUESTA->value,

                Permisos::REPORTE_PDF_RESPUESTAS->value,
            ],
        };
    }
}
