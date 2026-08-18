<?php

namespace App\Http\Controllers\Api;

use App\Enums\HTTP;
use App\Http\Requests\AuthRequests\ChangePasswordRequest;
use App\Http\Requests\AuthRequests\ForgotPasswordRequest;
use App\Http\Requests\AuthRequests\LoginRequest;
use App\Http\Requests\AuthRequests\ResetPasswordRequest;
use App\Jobs\SendResetPasswordLinkJob;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends ApiBaseController
{
    /**
     * Inicia sesión y genera un token de acceso.
     *
     * @param  LoginRequest  $request  Solicita las credenciales de dni y contraseña.
     * @return JsonResponse Devuelve el token en caso de éxito o 401 en caso de credenciales inválidas.
     */
    public function login(LoginRequest $request)
    {
        $credentials = [
            'dni' => $request->input('dni'),
            'password' => $request->input('password'),
        ];

        if (! Auth::attempt($credentials)) {
            return $this->sendResponse(HTTP::UNAUTHORIZED, [], 'Las credenciales de usuario son incorrectas.');
        }

        $token = $request->user()->createToken('auth_token')->plainTextToken;

        return $this->sendResponse(HTTP::OK, ['token' => $token], 'Inicio de sesión exitoso');
    }

    /**
     * Cierra sesión eliminando el token de acceso actual.
     *
     * @param  Request  $request  El usuario autenticado y su token actual.
     * @return JsonResponse Retorna 200 OK al eliminar el token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse(HTTP::OK, [], 'Cierre de sesión exitoso');
    }

    /**
     * Envía el enlace de restablecimiento de contraseña al correo proporcionado.
     *
     * @param  ForgotPasswordRequest  $request  Solicita el campo email del usuario.
     * @return JsonResponse Retorna 200 OK si el enlace fue enviado o 400 en caso de error.
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        ['email' => $email] = $request->safe()->only('email');
        $email = mb_strtolower(trim($email));

        SendResetPasswordLinkJob::dispatch($email);

        return $this->sendResponse(HTTP::OK, [], 'Se registró su solicitud de restablecimiento de contraseña. Por favor, revise su email para continuar.');
    }

    /**
     * Restablece la contraseña usando el token recibido y elimina los tokens de acceso existentes.
     *
     * @param  ResetPasswordRequest  $request  Solicita email, password, password_confirmation y token.
     * @return JsonResponse Retorna 200 OK si la contraseña se restableció correctamente o 400 en caso de error.
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $data = $request->safe()->only(['email', 'password', 'password_confirmation', 'token']);
        $data['email'] = mb_strtolower(trim((string) $data['email']));

        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            event(new PasswordReset($user));
        });

        return match ($status) {
            Password::PASSWORD_RESET => $this->sendResponse(HTTP::OK, [], 'Restablecimiento de contraseña exitoso.'),
            Password::INVALID_TOKEN => $this->sendResponse(HTTP::BAD_REQUEST, [], 'El enlace de restablecimiento no es válido o expiró.'),
            Password::INVALID_USER => $this->sendResponse(HTTP::NOT_FOUND, [], 'No encontramos un usuario con ese email.'),
            default => $this->sendResponse(HTTP::BAD_REQUEST, [], 'Ocurrió un problema al intentar restablecer su contraseña. Intente nuevamente.'),
        };
    }

    /**
     * Cambia la contraseña del usuario autenticado.
     *
     * @param  ChangePasswordRequest  $request  password (actual), new_password y password_confirmation.
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        // Verificar la contraseña actual
        if (! Hash::check($request->input('password'), $user->password)) {
            return $this->sendResponse(HTTP::UNAUTHORIZED, [], 'La contraseña actual es incorrecta.');
        }

        // Actualizar contraseña
        $user->forceFill([
            'password' => Hash::make($request->input('new_password')),
        ])->save();

        // Rotar tokens por seguridad y emitir uno nuevo
        $newToken = null;
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
            $newToken = $user->createToken('auth_token')->plainTextToken;
        }

        return $this->sendResponse(
            HTTP::OK,
            ['token' => $newToken],
            'Contraseña actualizada correctamente.'
        );
    }
}
