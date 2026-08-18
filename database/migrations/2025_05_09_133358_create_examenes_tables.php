<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. EXÁMENES
        |--------------------------------------------------------------------------
        */

        Schema::create('examenes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('fecha')->nullable();
            $table->string('codigo')->unique();
            $table->boolean('publicado')->default(false);
            $table->boolean('finalizado')->default(false);
            $table->boolean('defendido')->default(false);
            $table->boolean('descargable')->default(false);
            $table->boolean('retrocedible')->default(false);
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | 2. SECCIONES
        |--------------------------------------------------------------------------
        */

        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedInteger('orden_relativo');
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['examen_id', 'orden_relativo'], 'idx_examen_orden');
        });

        /*
        |--------------------------------------------------------------------------
        | 3. CASOS
        |--------------------------------------------------------------------------
        */

        Schema::create('casos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->unsignedInteger('orden_relativo');
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->foreignId('evaluador_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['examen_id', 'orden_relativo'], 'idx_examen_orden');
        });

        /*
        |--------------------------------------------------------------------------
        | 4. PREGUNTAS
        |--------------------------------------------------------------------------
        */

        Schema::create('preguntas', function (Blueprint $table) {
            $table->id();
            $table->string('consigna')->nullable();
            $table->text('descripcion');
            $table->unsignedInteger('orden_relativo');
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['caso_id', 'orden_relativo'], 'idx_caso_orden');
        });

        /*
        |--------------------------------------------------------------------------
        | 5. TABLAS DE COTEJO
        |--------------------------------------------------------------------------
        */

        Schema::create('tablas_cotejo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('seccion_id')->constrained('secciones')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['examen_id', 'caso_id', 'seccion_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | 6. ÍTEMS
        |--------------------------------------------------------------------------
        */

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->text('descripcion');
            $table->decimal('valor')->default(0);
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->foreignId('tabla_cotejo_id')->nullable()->constrained('tablas_cotejo')->cascadeOnDelete();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('seccion_id')->constrained('secciones')->cascadeOnDelete();
            $table->foreignId('pregunta_id')->constrained('preguntas')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['seccion_id', 'tabla_cotejo_id'], 'idx_seccion_tabla');
            $table->index(['seccion_id', 'examen_id'], 'idx_seccion_examen');
            $table->index(['seccion_id', 'caso_id'], 'idx_seccion_caso');
            $table->index(['seccion_id', 'pregunta_id'], 'idx_seccion_pregunta');
        });

        /*
        |--------------------------------------------------------------------------
        | 7. PIVOT examen_user_roles (usuarios de un examen)
        |--------------------------------------------------------------------------
        */

        Schema::create('examen_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['examen_id', 'user_id', 'role_id']);
            $table->index(['examen_id', 'role_id']);
            $table->index(['user_id', 'role_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | 8. ESTADOS DE EXAMEN - USUARIOS
        |--------------------------------------------------------------------------
        */

        Schema::create('estados_examenes_estudiantes', function (Blueprint $table) {
            $table->id();
            $table->boolean('inicio')->default(false);
            $table->time('hora_inicio')->nullable();
            $table->boolean('finalizado')->default(false);
            $table->time('hora_finalizacion')->nullable();
            $table->unsignedInteger('posicion_finalizacion')->nullable();
            $table->boolean('alertado')->default(false);
            $table->unsignedTinyInteger('alertas_disponibles')->default(5);
            $table->boolean('defendido')->default(false);
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->foreignId('caso_id')->nullable()->constrained('casos')->nullOnDelete();
            $table->foreignId('pregunta_id')->nullable()->constrained('preguntas')->nullOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['estudiante_id', 'examen_id']);
            $table->unique(['examen_id', 'posicion_finalizacion'], 'examen_id_posicion_finalizacion_unique');
            $table->timestamps();
        });

        Schema::create('estados_examenes_evaluadores', function (Blueprint $table) {
            $table->id();
            $table->boolean('finalizado')->default(false);
            $table->unsignedInteger('correcciones_asignadas')->default(0);
            $table->unsignedInteger('correcciones_realizadas')->default(0);
            $table->foreignId('evaluador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->unique(['evaluador_id', 'examen_id']);
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | 9. RESPUESTAS DE ESTUDIANTES (preguntas)
        |--------------------------------------------------------------------------
        */

        Schema::create('respuestas_estudiantes', function (Blueprint $table) {
            $table->id();
            $table->text('respuesta')->nullable();
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pregunta_id')->constrained('preguntas')->cascadeOnDelete();
            $table->unique(['estudiante_id', 'pregunta_id']);
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | 10. NOTAS
        |--------------------------------------------------------------------------
        */

        Schema::create('notas_items', function (Blueprint $table) {
            $table->id();
            $table->boolean('aprobado')->default(false);
            $table->foreignId('estudiante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['estudiante_id', 'item_id']);
        });

        Schema::create('notas_defensas', function (Blueprint $table) {
            $table->id();
            $table->decimal('nota')->default(0);
            $table->foreignId('estudiante_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('caso_id')->constrained('casos')->cascadeOnDelete();
            $table->foreignId('seccion_id')->constrained('secciones')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['estudiante_id', 'caso_id', 'seccion_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | 11. PSEUDÓNIMOS
        |--------------------------------------------------------------------------
        */

        Schema::create('pseudonimos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('examen_estudiante_pseudonimo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examen_id')->constrained('examenes')->cascadeOnDelete();
            $table->foreignId('estudiante_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pseudonimo_id')->constrained('pseudonimos')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['examen_id', 'estudiante_id']);
            $table->unique(['examen_id', 'pseudonimo_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examen_estudiante_pseudonimo');
        Schema::dropIfExists('pseudonimos');
        Schema::dropIfExists('notas_defensas');
        Schema::dropIfExists('notas_items');
        Schema::dropIfExists('respuestas_estudiantes');
        Schema::dropIfExists('estados_examenes_evaluadores');
        Schema::dropIfExists('estados_examenes_estudiantes');
        Schema::dropIfExists('examen_user_roles');
        Schema::dropIfExists('items');
        Schema::dropIfExists('tablas_cotejo');
        Schema::dropIfExists('preguntas');
        Schema::dropIfExists('casos');
        Schema::dropIfExists('secciones');
        Schema::dropIfExists('examenes');
    }
};
