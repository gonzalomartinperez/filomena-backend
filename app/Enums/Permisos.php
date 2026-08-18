<?php

namespace App\Enums;

use App\Traits\EnumsTrait;

/**
 * Enum de permisos disponibles en el sistema.
 */
enum Permisos: string
{
    use EnumsTrait;

    case EXAMEN_INDEX = 'examen_index';
    case EXAMEN_SHOW = 'examen_show';
    case EXAMEN_SHOW_BY_CODE = 'examen_show_by_code';
    case EXAMEN_STORE = 'examen_store';
    case EXAMEN_UPDATE = 'examen_update';
    case EXAMEN_DESTROY = 'examen_destroy';
    case EXAMEN_REESTABLISH = 'examen_reestablish';
    case EXAMEN_PUBLIC = 'examen_public';
    case EXAMEN_FINISH = 'examen_finish';
    case EXAMEN_DEFENSE = 'examen_defense';

    case ADMINISTRAR_EXAMEN_PORCENTAJE_ALUMNOS_FINALIZADOS = 'administrar_examen_porcentaje_alumnos_finalizados';
    case ADMINISTRAR_EXAMEN_PORCENTAJE_EVALUADORES_FINALIZADOS = 'administrar_examen_porcentaje_evaluadores_finalizados';
    case ADMINISTRAR_EXAMEN_ALUMNOS_FINALIZADOS = 'administrar_examen_alumnos_finalizados';
    case ADMINISTRAR_EXAMEN_PROGRESO_ESTUDIANTES = 'administrar_examen_progreso_estudiante';
    case ADMINISTRAR_EXAMEN_ALERTAR_ESTUDIANTE = 'administrar_examen_alertar_estudiante';
    case ADMINISTRAR_EXAMEN_PROGRESO_EVALUADORES = 'administrar_examen_progreso_evaluador';
    case ADMINISTRAR_EXAMEN_ESTADOS_ESTUDIANTES = 'estados_estudiantes_examen';
    case ADMINISTRAR_EXAMEN_ESTADOS_EVALUADORES = 'estados_evaluadores_examen';

    case USUARIO_INDEX = 'usuario_index';
    case USUARIO_SHOW = 'usuario_show';
    case USUARIO_UPSERT = 'usuario_upsert';
    case USUARIO_DESTROY = 'usuario_destroy';
    case USUARIO_IMPORT = 'usuario_import';

    case PSEUDONIMO_INDEX = 'pseudonimo_index';
    case PSEUDONIMO_SHOW = 'pseudonimo_show';
    case PSEUDONIMO_STORE = 'pseudonimo_store';
    case PSEUDONIMO_UPDATE = 'pseudonimo_update';
    case PSEUDONIMO_DESTROY = 'pseudonimo_destroy';

    case CASO_INDEX = 'caso_index';
    case CASO_UPDATE = 'caso_update';
    case CASO_SHOW = 'caso_show';
    case CASO_STORE = 'caso_store';
    case CASO_DESTROY = 'caso_destroy';
    case CASO_IMPORT = 'caso_import';

    case SECCION_INDEX = 'seccion_index';
    case SECCION_STORE = 'seccion_store';
    case SECCION_SHOW = 'seccion_show';
    case SECCION_UPDATE = 'seccion_update';
    case SECCION_DESTROY = 'seccion_destroy';
    case SECCION_IMPORT = 'seccion_import';

    case PREGUNTA_INDEX = 'pregunta_index';
    case PREGUNTA_STORE = 'pregunta_store';
    case PREGUNTA_DESTROY = 'pregunta_destroy';
    case PREGUNTA_UPDATE = 'pregunta_update';
    case PREGUNTA_SHOW = 'pregunta_show';
    case PREGUNTA_IMPORT = 'pregunta_import';

    case TABLA_COTEJO_INDEX = 'tabla_cotejo_index';
    case TABLA_COTEJO_SHOW = 'tabla_cotejo_show';
    case TABLA_COTEJO_STORE = 'tabla_cotejo_store';
    case TABLA_COTEJO_UPDATE = 'tabla_cotejo_update';
    case TABLA_COTEJO_DESTROY = 'tabla_cotejo_destroy';
    case TABLA_COTEJO_PUNTAJES = 'tabla_cotejo_puntajes';
    case TABLA_COTEJO_IMPORT = 'tabla_cotejo_import';

    case META_ROLES = 'meta_roles';

    case FILE_INDEX = 'file_index';
    case FILE_STORE = 'file_store';
    case FILE_SHOW = 'file_show';
    case FILE_DESTROY = 'file_destroy';

    case REPORTE_PDF_ESTRUCTURA = 'reporte_pdf_estructura';
    case REPORTE_PDF_CORRECCIONES = 'reporte_pdf_correcciones';
    case REPORTE_XLSX_NOTAS = 'reporte_xlsx_notas';
    case REPORTE_XLSX_CORRECCIONES_DETALLADAS = 'reporte_xlsx_correcciones_detalladas';
    case REPORTE_PDF_RESPUESTAS = 'reporte_pdf_respuestas';

    case ESTUDIANTE_EXAMEN_INICIAR = 'estudiante_examen_iniciar';
    case ESTUDIANTE_EXAMEN_FINALIZAR = 'estudiante_examen_finalizar';
    case ESTUDIANTE_EXAMEN_DEFENDER = 'estudiante_examen_defender';
    case ESTUDIANTE_EXAMEN_ALERTAR = 'estudiante_examen_alertar';
    case ESTUDIANTE_EXAMEN_PSEUDONIMO = 'estudiante_examen_pseudonimo';
    case ESTUDIANTE_EXAMEN_ESTADO = 'estudiante_examen_estado';
    case ESTUDIANTE_EXAMEN_ACTUALIZAR_PUNTERO = 'estudiante_examen_actualizar_puntero';
    case ESTUDIANTE_EXAMEN_NAVEGAR = 'estudiante_examen_navegar';
    case ESTUDIANTE_PREGUNTA_RESPONDER = 'estudiante_pregunta_responder';
    case ESTUDIANTE_PREGUNTA_RESPUESTA = 'estudiante_pregunta_respuesta';

    case EVALUADOR_RESPUESTAS_ESTUDIANTES = 'evaluador_respuestas_estudiantes';
    case EVALUADOR_CORRECCIONES_INDEX = 'evaluador_correccion_index';
    case EVALUADOR_CARGAR_NOTA_PREGUNTA = 'evaluador_cargar_nota_pregunta';
    case EVALUADOR_ESTADO_EXAMEN = 'evaluador_estado_examen';
    case EVALUADOR_FINALIZAR_CORRECCION_EXAMEN = 'evaluador_finalizar_correccion_examen';

    case DEFENSAS_INDEX = 'defensas_index';
    case DEFENSAS_UPSERT = 'defensas_upsert';
    case DEFENSAS_ACTUALIZAR_ESTADO_ESTUDIANTE = 'defensas_actualizar_estado_estudiante';
}
