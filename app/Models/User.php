<?php

namespace App\Models;

use App\Traits\ActivityLogsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use YMigVal\LaravelModelCache\HasCachedQueries;
use YMigVal\LaravelModelCache\ModelRelationships;

class User extends Authenticatable
{
    use ActivityLogsTrait, HasApiTokens, HasCachedQueries, HasFactory, HasRoles, ModelRelationships, Notifiable, SoftDeletes;

    protected $fillable = [
        'usuario',
        'password',
        'nombre',
        'apellido',
        'legajo',
        'dni',
        'email',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function examenes(): BelongsToMany
    {
        return $this->belongsToMany(
            Examen::class,
            'examen_user_roles',
            'user_id',
            'examen_id'
        );
    }

    public function respuestasEstudiante(): HasMany
    {
        return $this->hasMany(RespuestaEstudiante::class, 'estudiante_id');
    }

    public function notasItems(): HasMany
    {
        return $this->hasMany(NotaItem::class, 'estudiante_id');
    }

    public function notasDefensas(): HasMany
    {
        return $this->hasMany(NotaDefensa::class, 'estudiante_id');
    }

    public function pseudonimos(): BelongsToMany
    {
        return $this->belongsToMany(
            Pseudonimo::class,
            'examen_estudiante_pseudonimo',
            'estudiante_id',
            'pseudonimo_id'
        )->withPivot('examen_id');
    }

    public function rolesPorExamen(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'examen_user_roles',
            'user_id',
            'role_id'
        )->withPivot('examen_id');
    }

    public function ensureGlobalRolesFor(array $roles): void
    {
        $roles = array_values(array_unique(array_filter($roles)));
        if (! $roles) {
            return;
        }

        $current = $this->getRoleNames()->values()->all();
        $missing = array_values(array_diff($roles, $current));
        if ($missing) {
            $this->assignRole($missing);
        }
    }

    public function syncRolesPorExamen(int $examenId, array $roles): void
    {
        $roles = array_values(array_unique(array_filter($roles)));
        $this->rolesPorExamen()->wherePivot('examen_id', $examenId)->detach();
        if (! $roles) {
            return;
        }

        $ids = Role::query()->whereIn('name', $roles)->pluck('id')->all();

        $attach = [];
        foreach ($ids as $rid) {
            $attach[$rid] = ['examen_id' => $examenId];
        }
        $this->rolesPorExamen()->attach($attach);
    }
}
