<?php
declare(strict_types=1);

final class AuthController
{
    public static function register(): void
    {
        Response::ok(AuthService::register(Request::body()), 'Registered successfully', 201);
    }

    public static function login(): void
    {
        $b = Request::body();
        $errors = Validator::validate($b, ['login' => 'required|string', 'password' => 'required|string']);
        if ($errors) {
            throw new ApiException('Validation failed', 422, $errors);
        }
        Response::ok(AuthService::login((string) $b['login'], (string) $b['password']), 'Logged in');
    }

    public static function logout(): void
    {
        Auth::logout();
        Response::ok(null, 'Logged out');
    }

    public static function me(): void
    {
        $user = Auth::require();
        Response::ok(UserRepository::publicView($user));
    }

    public static function csrf(): void
    {
        Response::ok(['token' => Csrf::token()]);
    }
}
