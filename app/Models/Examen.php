<?php

namespace App\Models;

use App\Enums\Colecciones;
use App\Enums\Roles;
use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Models\Role;

class Examen extends BaseCacheableModel implements HasMedia
{
    use ActivityLogsTrait, HasFactory, InteractsWithMedia;

    protected $table = 'examenes';

    protected $fillable = [
        'nombre',
        'fecha',
        'codigo',
        'publicado',
        'finalizado',
        'defendido',
        'descargable',
        'retrocedible',
        'creado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function estadosExamenesEvaluadores(): HasMany
    {
        return $this->hasMany(EstadoExamenEvaluador::class);
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class, 'examen_id')->orderBy('orden_relativo');
    }

    public function casos(): HasMany
    {
        return $this->hasMany(Caso::class, 'examen_id')->orderBy('orden_relativo');
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class, 'examen_id')->orderBy('orden_relativo');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'examen_id');
    }

    public function estadosExamenesEstudiantes(): HasMany
    {
        return $this->hasMany(EstadoExamenEstudiante::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'examen_user_roles',
            'examen_id',
            'user_id'
        )->withPivot('examen_id')
            ->orderBy('users.nombre')
            ->orderBy('users.apellido')
            ->distinct();
    }

    public function estudiantesAnonimos(): HasMany
    {
        return $this->hasMany(ExamenEstudiantePseudonimo::class, 'examen_id');
    }

    public function administradores(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role_id', Role::query()->where('name', Roles::ADMINISTRADOR->value)->value('id'))
            ->select('users.*')
            ->orderBy('users.nombre')
            ->orderBy('users.apellido')
            ->distinct();
    }

    public function evaluadores(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role_id', Role::query()->where('name', Roles::EVALUADOR->value)->value('id'))
            ->select('users.*')
            ->orderBy('users.nombre')
            ->orderBy('users.apellido')
            ->distinct();
    }

    public function estudiantes(): BelongsToMany
    {
        // Relación directa via la pivot de pseudónimos
        return $this->belongsToMany(
            User::class,
            'examen_estudiante_pseudonimo',
            'examen_id',
            'estudiante_id'
        )
            ->withPivot('pseudonimo_id')
            // Join para poder ordenar por el nombre del pseudónimo
            ->join('pseudonimos', 'pseudonimos.id', '=', 'examen_estudiante_pseudonimo.pseudonimo_id')
            // Seleccionar users.* para no “pisar” columnas con el join
            ->select('users.*', 'examen_estudiante_pseudonimo.pseudonimo_id')
            // Orden numérico: '1','2','10' → 1 < 2 < 10
            ->orderByRaw('CAST(pseudonimos.nombre AS UNSIGNED) ASC')
            // Fallback alfabético por si algún día hay pseudónimos no numéricos
            ->orderBy('pseudonimos.nombre', 'ASC')
            ->distinct();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(Colecciones::ARCHIVOS->value)
            ->useDisk('public');

        $this->addMediaCollection(Colecciones::REPORTES->value)
            ->acceptsMimeTypes([
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
            ])
            ->useDisk('public');
    }
}
