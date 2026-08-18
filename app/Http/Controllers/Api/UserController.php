<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Enums\Roles;
use App\Helpers\UserImportHelper;
use App\Http\Requests\UserRequests\ImportUsersRequest;
use App\Http\Requests\UserRequests\UpsertUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Examen;
use App\Models\User;
use App\Services\PseudonimoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends ApiBaseController
{
    public function __construct(
        private UserImportHelper $importHelper,
        private PseudonimoService $pseudonimoService
    ) {}

    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return $this->sendResponse(HTTP::UNAUTHORIZED, [], 'No autenticado');
        }

        $user->load(['roles', 'rolesPorExamen']);

        return $this->sendResponse(
            HTTP::OK,
            new UserResource($user),
            'Usuario recuperado exitosamente'
        );
    }

    public function index(Request $request)
    {
        $examenId = (int) $request->query('examen');
        $rol = $request->query('rol'); // ESTUDIANTE | EVALUADOR | ADMINISTRADOR

        // 1) Con examen: usar relaciones del modelo Examen
        if ($examenId) {
            /** @var Examen $examen */
            $examen = Examen::findOrFail($examenId);

            $relationMap = [
                Roles::ESTUDIANTE->value => 'estudiantes',
                Roles::EVALUADOR->value => 'evaluadores',
                Roles::ADMINISTRADOR->value => 'administradores',
            ];

            $builder = ($rol && isset($relationMap[$rol]))
                ? $examen->{$relationMap[$rol]}()
                : $examen->users();

            // Eager-load de roles globales + roles por examen (acotados al examen actual)
            $usuarios = $builder
                ->with([
                    'roles',
                    'rolesPorExamen' => fn ($q) => $q->where('examen_id', $examen->id),
                ])
                ->distinct()
                ->get();

            // --- Pseudónimo SOLO para estudiantes del examen ---
            if ($usuarios->isNotEmpty()) {
                $estudianteIds = ($rol === Roles::ESTUDIANTE->value)
                    ? $usuarios->pluck('id')->all()
                    : $examen->estudiantes()->pluck('users.id')->all();

                $esEstudiante = array_fill_keys($estudianteIds, true);

                foreach ($usuarios as $u) {
                    if (isset($esEstudiante[$u->id])) {
                        $u->pseudonimo = $this->pseudonimoService->pseudonimoParaExamen($u, $examen);
                    }
                }
            }

            return $this->sendResponse(
                HTTP::OK,
                UserResource::collection($usuarios),
                'Usuarios recuperados exitosamente'
            );
        }

        // 2) Sin examen pero con rol global: filtra por rol y carga relaciones
        if ($rol) {
            $usuarios = User::query()
                ->with(['roles', 'rolesPorExamen'])
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->distinct()
                ->get();

            return $this->sendResponse(
                HTTP::OK,
                UserResource::collection($usuarios),
                'Usuarios recuperados exitosamente'
            );
        }

        // 3) Sin examen ni rol: todos los usuarios, ordenados, con relaciones
        $usuarios = User::query()
            ->with(['roles', 'rolesPorExamen'])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->distinct()
            ->get();

        return $this->sendResponse(
            HTTP::OK,
            UserResource::collection($usuarios),
            'Usuarios recuperados exitosamente'
        );
    }

    /**
     * Flujo único de crear/actualizar lógica original:
     * - Si existe soft-deleted por DNI: restaurar + actualizar (sin tocar password salvo que lo envíen)
     * - Si no existe: crear (password = enviado o dni)
     * - Roles globales + roles por examen (?examen) + pseudónimo si estudiante
     */
    public function upsert(UpsertUserRequest $request)
    {
        $data = $request->validated();
        $dni = $data['dni'];
        $examenId = (int) $data['examen'];

        /** @var Examen $examen */
        $examen = Examen::query()->select('id')->find($examenId);
        if (! $examen) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
        }

        $user = User::withTrashed()->where('dni', $dni)->first();
        $wasCreated = false;
        $wasRestored = false;

        DB::beginTransaction();
        try {
            if ($user) {
                if ($user->trashed()) {
                    $user->restore();
                    $wasRestored = true;
                }

                // Actualizar datos (no forzamos password si no viene)
                $user->usuario = $data['usuario'];
                $user->nombre = $data['nombre'];
                $user->apellido = $data['apellido'];
                $user->legajo = $data['legajo'];
                $user->dni = $data['dni'];
                $user->email = $data['email'];
                if (! empty($data['password'])) {
                    $user->password = Hash::make($data['password']);
                }
                $user->save();
            } else {
                // Crear nuevo (password explícito o dni por defecto)
                $user = new User;
                $user->usuario = $data['usuario'];
                $user->nombre = $data['nombre'];
                $user->apellido = $data['apellido'];
                $user->legajo = $data['legajo'];
                $user->dni = $data['dni'];
                $user->email = $data['email'];
                $user->password = Hash::make($data['password'] ?? $data['dni']);
                $user->save();
                $wasCreated = true;
            }

            // 1) Roles globales
            if (! empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            // 2) Roles por EXAMEN
            $rolesExamen = array_values(array_filter((array) data_get($data, "roles_examenes.$examenId", [])));

            // Asignar también globales faltantes (mantiene tu enfoque actual)
            $user->ensureGlobalRolesFor($rolesExamen);

            // Sincronizar roles por examen (usamos la fachada del Examen, ver sección 3)
            $user->syncRolesPorExamen($examen->id, $rolesExamen);

            // Pseudónimo si el usuario es Estudiante en ese examen
            if (in_array(Roles::ESTUDIANTE->value, $rolesExamen, true)) {
                $this->pseudonimoService->asignarPseudonimo($user, $examen);
            }

            DB::commit();

            $status = $wasCreated ? HTTP::CREATED : HTTP::OK;
            $message = $wasCreated
                ? 'Usuario creado exitosamente'
                : ($wasRestored ? 'Usuario creado exitosamente' : 'Usuario actualizado exitosamente');

            return $this->sendResponse($status, new UserResource($user), $message);

        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], $e->getMessage());
        }
    }

    public function show(string $dni)
    {
        $user = User::query()
            ->where('dni', $dni)
            ->with(['roles', 'rolesPorExamen'])
            ->first();

        if (! $user) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Usuario no encontrado');
        }

        return $this->sendResponse(
            HTTP::OK,
            new UserResource($user),
            'Usuario recuperado exitosamente'
        );
    }

    public function destroy(Request $request, $dni)
    {
        $examenId = $request->query('examen');

        $user = User::withTrashed()->where('dni', $dni)->first();
        if (! $user) {
            return $this->sendResponse(HTTP::NOT_FOUND, [], 'Usuario no encontrado');
        }

        if ($user->id === $request->user()?->id) {
            return $this->sendResponse(HTTP::FORBIDDEN, [], 'No es posible auto eliminarse como usuario.');
        }

        if ($examenId) {
            $examen = Examen::find($examenId);

            if (! $examen) {
                return $this->sendResponse(HTTP::NOT_FOUND, [], 'Examen no encontrado');
            }

            if ($user->hasRole(Roles::ESTUDIANTE->value)) {
                $this->pseudonimoService->liberarPseudonimo($user, $examen);
            }

            $examen->users()->detach($user->id);
        }

        return $this->sendResponse(HTTP::OK, [], 'Usuario eliminado exitosamente');
    }

    public function import(ImportUsersRequest $request)
    {
        $archivo = $request->file('archivo');
        $rol = (string) $request->query('rol');
        $examenId = $request->query('examen');

        try {
            // 1) Parseo + normalización (sin side-effects)
            $rows = $this->importHelper->parse($archivo);

            // 2) Examen (si aplica)
            $examen = null;
            if ($examenId) {
                $examen = Examen::findOrFail((int) $examenId);
            }

            // 3) Transacción: todo o nada
            $resultado = DB::transaction(function () use ($rows, $rol, $examen) {
                return $this->importHelper->persist($rows, $rol, $examen);
            });

            return $this->sendResponse(HTTP::OK, $resultado, 'Importación completada');

        } catch (\Throwable $e) {
            return $this->sendResponse(HTTP::UNPROCESSABLE_ENTITY, [], $e->getMessage());
        }
    }
}
