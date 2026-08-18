<?php

use App\Enums\HTTP;
use App\Enums\Permisos;
use App\Http\Controllers\Api\AdminManagementController;
use App\Http\Controllers\Api\ApiBaseController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CasoController;
use App\Http\Controllers\Api\DefensaController;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\EvaluadorController;
use App\Http\Controllers\Api\ExamenController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\PreguntaController;
use App\Http\Controllers\Api\PseudonimoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\SeccionController;
use App\Http\Controllers\Api\TablaCotejoController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\SimpleHealthCheckController;

// Pruebas para debug en entorno dev

if (app()->environment('local')) {
    Route::get('/debug/log', function () {
        Log::debug('Debug desde /debug/log', ['ip' => request()->ip()]);

        return ApiBaseController::sendResponse(HTTP::OK, [], 'Logs de prueba');
    });

    Route::get('/debug/error', function () {
        throw new RuntimeException('Excepción de prueba');
    });
}

// Chequeo de salud de la aplicación

Route::get('/health', SimpleHealthCheckController::class);

// Rutas públicas

Route::post('/login', [AuthController::class, 'login'])
    ->middleware(['throttle:login']);

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware(['throttle:password']);

Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Rutas que requieren un usuario autenticado
Route::middleware('auth:sanctum')->group(function () {
    // Obtener los datos del usuario autenticado
    Route::get('/usuarios/yo', [UserController::class, 'me']);
    // Cerrar sesión
    Route::post('/logout', [AuthController::class, 'logout']);
    // Cerrar sesión
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    Route::prefix('examenes')
        ->group(function () {
            // Listar todos los examenes (GET /examenes)
            Route::get('/', [ExamenController::class, 'index'])
                ->middleware('permission:'.Permisos::EXAMEN_INDEX->value);

            // Ver detalle de un examen (GET /examenes/{id})
            Route::get('{id}', [ExamenController::class, 'show'])
                ->middleware('permission:'.Permisos::EXAMEN_SHOW->value);

            // Ver detalle de un examen por su código (GET /examenes/codigo/{codigo})
            Route::get('/codigo/{codigo}', [ExamenController::class, 'showByCode'])
                ->middleware('permission:'.Permisos::EXAMEN_SHOW_BY_CODE->value);

            // Crear examen (POST /examenes)
            Route::post('/', [ExamenController::class, 'store'])
                ->middleware('permission:'.Permisos::EXAMEN_STORE->value);

            // Actualizar un examen (PUT /examenes/{id})
            Route::put('{id}', [ExamenController::class, 'update'])
                ->middleware('permission:'.Permisos::EXAMEN_UPDATE->value);

            // Eliminar un examen (DELETE /examenes/{id})
            Route::delete('{id}', [ExamenController::class, 'destroy'])
                ->middleware('permission:'.Permisos::EXAMEN_DESTROY->value);

            // Reestablecer un examen (PUT /examenes/reestablecer/{id})
            Route::put('reestablecer/{id}', [ExamenController::class, 'reestablecer'])
                ->middleware('permission:'.Permisos::EXAMEN_REESTABLISH->value);

            // Reestablecer un examen (PUT /examenes/{id})
            Route::put('publicar/{id}', [ExamenController::class, 'publicar'])
                ->middleware('permission:'.Permisos::EXAMEN_PUBLIC->value);

            // Reestablecer un examen (PUT /examenes/{id})
            Route::put('finalizar/{id}', [ExamenController::class, 'finalizar'])
                ->middleware('permission:'.Permisos::EXAMEN_FINISH->value);

            Route::put('defender/{id}', [ExamenController::class, 'defender'])
                ->middleware('permission:'.Permisos::EXAMEN_DEFENSE->value);
        });

    Route::prefix('administrar-examenes')
        ->group(function () {
            // Retornar el porcentaje de alumnos que finalizo el examen (GET /examenes/porcentaje-finalizacion-alumnos/{id})
            Route::get('porcentaje-finalizacion-alumnos/{id}', [AdminManagementController::class, 'showFinalizados'])
                ->middleware('permission:'.Permisos::ADMINISTRAR_EXAMEN_PORCENTAJE_ALUMNOS_FINALIZADOS->value);

            // Retornar el porcentaje de evaluadores que finalizo la correccion (GET /examenes/porcentaje-finalizacion-evaluadores/{id})
            Route::get('porcentaje-finalizacion-evaluadores/{id}', [AdminManagementController::class, 'showEvaluados'])
                ->middleware('permission:'.Permisos::ADMINISTRAR_EXAMEN_PORCENTAJE_EVALUADORES_FINALIZADOS->value);

            // Retornar el orden de finalizacion de examen de los alumnos (GET /examenes/alumnos-estado-finalizado/{id})
            Route::get('alumnos-estado-finalizado/{id}', [AdminManagementController::class, 'alumnosFinalizados'])
                ->middleware('permission:'.Permisos::ADMINISTRAR_EXAMEN_ALUMNOS_FINALIZADOS->value);

            // Retornar el progreso de los estudiantes en el examen publicado (GET /examenes/examen-progreso-estudiantes/{examenId})
            Route::get('examen-progreso-estudiantes/{examenId}', [AdminManagementController::class, 'monitorEstudiantes'])
                ->middleware('permission:'.Permisos::ADMINISTRAR_EXAMEN_PROGRESO_ESTUDIANTES->value);

            // Retornar el progreso de los estudiantes en el examen publicado (PUT /examenes/alertar-estudiante/{examenId}/{estudianteId})
            Route::put('alertar-estudiante/{examenId}/{estudianteId}', [AdminManagementController::class, 'alertarEstudiante'])
                ->middleware('permission:'.Permisos::ADMINISTRAR_EXAMEN_ALERTAR_ESTUDIANTE->value);

            // Retornar el progreso de los evaluadores en las correcciones (GET /examenes/examen-progreso-evaluadores/{examenId})
            Route::get('examen-progreso-evaluadores/{examenId}', [AdminManagementController::class, 'monitorEvaluadores'])
                ->middleware('permission:'.Permisos::ADMINISTRAR_EXAMEN_PROGRESO_EVALUADORES->value);

            Route::get('/estados-evaluadores/{examenId}', [AdminManagementController::class, 'estadosEvaluadores'])
                ->middleware('permission:'.Permisos::ADMINISTRAR_EXAMEN_ESTADOS_EVALUADORES->value);

            Route::get('estados-estudiantes/{examenId}', [AdminManagementController::class, 'estadosEstudiantes'])
                ->middleware('permission:'.Permisos::ADMINISTRAR_EXAMEN_ESTADOS_ESTUDIANTES->value);
        });

    Route::prefix('usuarios')
        ->group(function () {

            // Listar todos los usuarios (GET /usuarios?{examen}={id}&{rol}={value})
            Route::get('/', [UserController::class, 'index'])
                ->middleware('permission:'.Permisos::USUARIO_INDEX->value);

            // Upsert flexible (PUT /usuarios?examen={id})
            Route::put('', [UserController::class, 'upsert'])
                ->middleware('permission:'.Permisos::USUARIO_UPSERT->value);

            // Ver detalle de un usuario (GET /usuarios/{dni})
            Route::get('{dni}', [UserController::class, 'show'])
                ->middleware('permission:'.Permisos::USUARIO_SHOW->value);

            // Eliminar un usuario (DELETE /usuarios/{dni})
            Route::delete('{dni}', [UserController::class, 'destroy'])
                ->middleware('permission:'.Permisos::USUARIO_DESTROY->value);

            // Importar usuarios (POST /usuarios/importar?{examen}={id}&rol={rol})
            Route::post('importar', [UserController::class, 'import'])
                ->middleware('permission:'.Permisos::USUARIO_IMPORT->value);
        });

    Route::prefix('meta')
        ->group(function () {
            Route::get('roles', [MetaController::class, 'roles'])
                ->middleware('permission:'.Permisos::META_ROLES->value);
        });

    Route::prefix('pseudonimos')
        ->group(function () {
            // Listar todos los pseudonimos para un examen (GET /pseudonimos)
            Route::get('', [PseudonimoController::class, 'index'])
                ->middleware('permission:'.Permisos::PSEUDONIMO_INDEX->value);

            // Crear un pseudonimo (POST /pseudonimos)
            Route::post('/', [PseudonimoController::class, 'store'])
                ->middleware('permission:'.Permisos::PSEUDONIMO_STORE->value);

            // Ver un pseudonimo para un examen (GET /pseudonimos/{id})
            Route::get('/{id}', [PseudonimoController::class, 'show'])
                ->middleware('permission:'.Permisos::PSEUDONIMO_SHOW->value);

            // Actualizar un pseudonimo (PUT /pseudonimos/{id})
            Route::put('/{pseudonimoId}', [PseudonimoController::class, 'update'])
                ->middleware('permission:'.Permisos::PSEUDONIMO_UPDATE->value);

            // Eliminar un pseudonimo (DELETE /pseudonimos/{id})
            Route::delete('/{id}', [PseudonimoController::class, 'destroy'])
                ->middleware('permission:'.Permisos::PSEUDONIMO_DESTROY->value);
        });

    Route::prefix('casos')
        ->group(function () {
            // Listar todos los casos para un examen (GET /casos)
            Route::get('', [CasoController::class, 'index'])
                ->middleware('permission:'.Permisos::CASO_INDEX->value);

            // Actualizar un caso (PUT /casos/{id})
            Route::put('/{id}', [CasoController::class, 'update'])
                ->middleware('permission:'.Permisos::CASO_UPDATE->value);

            // Ver un caso(GET /casos/{id})
            Route::get('/{id}', [CasoController::class, 'show'])
                ->middleware('permission:'.Permisos::CASO_SHOW->value);

            // Crear un caso (POST /casos)
            Route::post('/', [CasoController::class, 'store'])
                ->middleware('permission:'.Permisos::CASO_STORE->value);

            // Eliminar un caso (DELETE /casos/{id})
            Route::delete('/{id}', [CasoController::class, 'destroy'])
                ->middleware('permission:'.Permisos::CASO_DESTROY->value);

            // Importar casos (POST /casos/importar?{examen}={id})
            Route::post('importar', [CasoController::class, 'import'])
                ->middleware('permission:'.Permisos::CASO_IMPORT->value);
        });

    Route::prefix('secciones')
        ->group(function () {
            // Listar todas las secciones para un examen (GET /secciones)
            Route::get('', [SeccionController::class, 'index'])
                ->middleware('permission:'.Permisos::SECCION_INDEX->value);

            // Crear una seccion (POST /secciones)
            Route::post('/', [SeccionController::class, 'store'])
                ->middleware('permission:'.Permisos::SECCION_STORE->value);

            // Ver una seccion(GET /secciones/{id})
            Route::get('/{id}', [SeccionController::class, 'show'])
                ->middleware('permission:'.Permisos::SECCION_SHOW->value);

            // Actualizar una seccion (PUT /secciones/{id})
            Route::put('/{id}', [SeccionController::class, 'update'])
                ->middleware('permission:'.Permisos::SECCION_UPDATE->value);

            // Eliminar una seccion (DELETE /secciones/{id})
            Route::delete('/{id}', [SeccionController::class, 'destroy'])
                ->middleware('permission:'.Permisos::SECCION_DESTROY->value);

            // Importar secciones (POST /secciones/importar?{examen}={id})
            Route::post('importar', [SeccionController::class, 'import'])
                ->middleware('permission:'.Permisos::SECCION_IMPORT->value);
        });

    Route::prefix('preguntas')
        ->group(function () {
            // Listar todas las preguntas para un examen (GET /preguntas)
            Route::get('', [PreguntaController::class, 'index'])
                ->middleware('permission:'.Permisos::PREGUNTA_INDEX->value);

            // Crear una pregunta (POST /preguntas)
            Route::post('/', [PreguntaController::class, 'store'])
                ->middleware('permission:'.Permisos::PREGUNTA_STORE->value);

            // Eliminar una pregunta (DELETE /preguntas/{id})
            Route::delete('/{id}', [PreguntaController::class, 'destroy'])
                ->middleware('permission:'.Permisos::PREGUNTA_DESTROY->value);

            // Actualizar una pregunta (PUT /preguntas/{id})
            Route::put('/{id}', [PreguntaController::class, 'update'])
                ->middleware('permission:'.Permisos::PREGUNTA_UPDATE->value);

            // Ver una pregunta y sus detalles (GET /preguntas/{id})
            Route::get('/{id}', [PreguntaController::class, 'show'])
                ->middleware('permission:'.Permisos::PREGUNTA_SHOW->value);

            // Importar preguntas (POST /preguntas/importar?{examen}={id})
            Route::post('importar', [PreguntaController::class, 'import'])
                ->middleware('permission:'.Permisos::PREGUNTA_IMPORT->value);
        });

    Route::prefix('tablas-cotejo')
        ->group(function () {
            // Ver las tablas de cotejo de un caso (GET /tablas-cotejo/{id})
            Route::get('/{id}', [TablaCotejoController::class, 'show'])
                ->middleware('permission:'.Permisos::TABLA_COTEJO_SHOW->value);

            // Crear tablas cotejo para un caso (POST /tablas-cotejo)
            Route::post('/', [TablaCotejoController::class, 'store'])
                ->middleware('permission:'.Permisos::TABLA_COTEJO_STORE->value);

            // Actualizar tablas cotejo para un caso (PUT /tablas-cotejo)
            Route::put('/{id}', [TablaCotejoController::class, 'update'])
                ->middleware('permission:'.Permisos::TABLA_COTEJO_UPDATE->value);

            // Eliminar las tablas cotejo de un caso (DELETE /tablas-cotejo/{id})
            Route::delete('/{id}', [TablaCotejoController::class, 'destroy'])
                ->middleware('permission:'.Permisos::TABLA_COTEJO_DESTROY->value);

            // Listar todas los casos con tablas cotejos asociadas, dado un examen (GET /tablas-cotejo/{id})
            Route::get('/', [TablaCotejoController::class, 'index'])
                ->middleware('permission:'.Permisos::TABLA_COTEJO_INDEX->value);

            // Recupera los puntajes de una tabla de cotejo para su caso, sección y preguntas asociadas
            Route::get('/puntajes/{id}', [TablaCotejoController::class, 'puntajes'])
                ->middleware(['permission:'.Permisos::TABLA_COTEJO_PUNTAJES->value]);

            // Importar tablas de cotejo (POST /tablas-cotejo/importar?{examen}={id})
            Route::post('importar', [TablaCotejoController::class, 'import'])
                ->middleware('permission:'.Permisos::TABLA_COTEJO_IMPORT->value);
        });

    Route::prefix('archivos')
        ->group(function () {
            // Listar todos los archivos de una entidad específica (GET /archivos?{entidad}={id})
            Route::get('', [FileController::class, 'index'])
                ->middleware('permission:'.Permisos::FILE_INDEX->value);

            // Cargar un archivo para una entidad específica (POST /archivos?{entidad}={id})
            Route::post('', [FileController::class, 'store'])
                ->middleware('permission:'.Permisos::FILE_STORE->value);

            // Ver un archivo específico (GET /archivos/{media})
            Route::get('/{media}', [FileController::class, 'show'])
                ->middleware('permission:'.Permisos::FILE_SHOW->value);

            // Eliminar un archivo específico (DELETE /archivos/{media})
            Route::delete('/{media}', [FileController::class, 'destroy'])
                ->middleware('permission:'.Permisos::FILE_DESTROY->value);
        });

    Route::prefix('reporte-examen')
        ->group(function () {
            Route::get('pdf/estructura/{id}', [ReporteController::class, 'pdfEstructura'])
                ->middleware('permission:'.Permisos::REPORTE_PDF_ESTRUCTURA->value);

            Route::get('pdf/correcciones/{id}', [ReporteController::class, 'pdfCorrecciones'])
                ->middleware('permission:'.Permisos::REPORTE_PDF_CORRECCIONES->value);

            Route::get('excel/notas/{id}', [ReporteController::class, 'excelNotas'])
                ->middleware('permission:'.Permisos::REPORTE_XLSX_NOTAS->value);

            Route::get('excel/correcciones-detalladas/{id}', [ReporteController::class, 'excelCorreccionesDetalladas'])
                ->middleware('permission:'.Permisos::REPORTE_XLSX_CORRECCIONES_DETALLADAS->value);

            Route::get('pdf/respuestas/{id}', [ReporteController::class, 'pdfRespuestas'])
                ->middleware('permission:'.Permisos::REPORTE_PDF_RESPUESTAS->value);
        });

    Route::prefix('estudiantes')->group(function () {
        Route::put('iniciar-examen/{id}', [EstudianteController::class, 'iniciar'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_EXAMEN_INICIAR->value);

        Route::put('finalizar-examen/{id}', [EstudianteController::class, 'finalizar'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_EXAMEN_FINALIZAR->value);

        Route::put('desactivar-alerta-examen/{id}', [EstudianteController::class, 'desactivarAlerta'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_EXAMEN_ALERTAR->value);

        Route::get('pseudonimo-examen/{id}', [EstudianteController::class, 'pseudonimo'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_EXAMEN_PSEUDONIMO->value);

        Route::get('estado-examen/{id}', [EstudianteController::class, 'estado'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_EXAMEN_ESTADO->value);

        Route::put('actualizar-puntero-examen/{id}', [EstudianteController::class, 'actualizarPuntero'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_EXAMEN_ACTUALIZAR_PUNTERO->value);

        Route::get('navegar-examen/{id}', [EstudianteController::class, 'navegar'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_EXAMEN_NAVEGAR->value);

        Route::put('responder-pregunta/{preguntaId}', [EstudianteController::class, 'responder'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_PREGUNTA_RESPONDER->value);

        Route::get('respuesta-pregunta/{preguntaId}', [EstudianteController::class, 'respuesta'])
            ->middleware('permission:'.Permisos::ESTUDIANTE_PREGUNTA_RESPUESTA->value);
    });

    Route::prefix('evaluador')->group(function () {

        Route::get('respuestas-estudiantes', [EvaluadorController::class, 'respuestasEstudiantes'])
            ->middleware('permission:'.Permisos::EVALUADOR_RESPUESTAS_ESTUDIANTES->value);

        Route::get('correcciones', [EvaluadorController::class, 'correcciones'])
            ->middleware('permission:'.Permisos::EVALUADOR_CORRECCIONES_INDEX->value);

        Route::put('cargar-correcciones-pregunta', [EvaluadorController::class, 'cargarCorreccionesPregunta'])
            ->middleware('permission:'.Permisos::EVALUADOR_CARGAR_NOTA_PREGUNTA->value);

        Route::get('estado-correccion-examen', [EvaluadorController::class, 'estadoCorreccionExamen'])
            ->middleware('permission:'.Permisos::EVALUADOR_ESTADO_EXAMEN->value);

        Route::put('finalizar-correccion-examen', [EvaluadorController::class, 'finalizarCorreccionExamen'])
            ->middleware('permission:'.Permisos::EVALUADOR_FINALIZAR_CORRECCION_EXAMEN->value);
    });

    Route::prefix('defensas')->group(function () {
        Route::get('', [DefensaController::class, 'index'])
            ->middleware('permission:'.Permisos::DEFENSAS_INDEX->value);

        Route::put('', [DefensaController::class, 'upsert'])
            ->middleware('permission:'.Permisos::DEFENSAS_UPSERT->value);

        Route::put('/marcar-estudiante-defendido', [DefensaController::class, 'marcarEstudianteDefendido'])
            ->middleware('permission:'.Permisos::DEFENSAS_ACTUALIZAR_ESTADO_ESTUDIANTE->value);
    });

});
