<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Roles globales (Spatie)
        $rolesGlobales = $this->getRoleNames()->values()->all();

        // Roles por examen => { examen_id: [ 'administrador', 'evaluador', ... ] }
        $rolesExamenes = $this->rolesPorExamen()
            ->get()
            ->groupBy(fn ($role) => (int) $role->pivot->examen_id)
            ->map(fn ($roles) => $roles->pluck('name')->values()->all())
            ->toArray();

        return [
            'id' => $this->id,
            'usuario' => $this->usuario,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'legajo' => $this->legajo,
            'dni' => $this->dni,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $rolesGlobales,
            'roles_examenes' => (object) $rolesExamenes,
            'pseudonimo' => $this->when(isset($this->pseudonimo), $this->pseudonimo),
            'must_change_password' => Hash::check((string) $this->dni, (string) $this->password),
        ];
    }
}
