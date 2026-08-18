<?php

namespace App\Http\Requests\UserRequests;

use App\Enums\Roles;
use App\Http\Requests\ApiBaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpsertUserRequest extends ApiBaseRequest
{
    public function rules(): array
    {
        // Soporta tanto crear como actualizar.
        // Si viene {dni} en la ruta o en el body, ignoramos ese DNI en las reglas de unique.
        $dni = $this->route('dni') ?: $this->input('dni');

        $unique = function ($column) use ($dni) {
            $rule = Rule::unique('users', $column)->whereNull('deleted_at');
            if ($dni) {
                // Ignora el registro que ya tenga este DNI (update por DNI)
                $rule = $rule->ignore($dni, 'dni');
            }

            return $rule;
        };

        return [
            'usuario' => ['required', 'string', $unique('usuario')],
            'password' => ['nullable', 'string', 'min:6'],
            'nombre' => ['required', 'string'],
            'apellido' => ['required', 'string'],
            'legajo' => ['required', 'string', $unique('legajo')],
            'dni' => ['required', 'string', $unique('dni')],
            'email' => ['required', 'email', $unique('email')],

            // Roles globales (Spatie)
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', new Enum(Roles::class), Rule::in(Roles::values())],

            // Roles por examen
            'roles_examenes' => ['nullable', 'array'],
            'roles_examenes.*' => ['array'],
            'roles_examenes.*.*' => ['string', new Enum(Roles::class), Rule::in(Roles::values())],

            // Requerido por tu nueva regla de negocio
            'examen' => ['required', 'integer', 'exists:examenes,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $examenId = (int) $this->input('examen'); // viene desde validationData()
            // Aseguramos que se envíe roles_examenes[examen] no vacío
            $path = "roles_examenes.$examenId";
            $rolesExamen = $this->input($path);
            if (! is_array($rolesExamen) || count($rolesExamen) === 0) {
                $validator->errors()->add($path, "Debe enviar roles_examenes[$examenId] con al menos un rol.");
            }
        });
    }

    public function validationData(): array
    {
        // Exponemos ?examen como parte de los datos validados
        return array_merge($this->all(), [
            'examen' => $this->query('examen'),
        ]);
    }
}
