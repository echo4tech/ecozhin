<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Content-Type-Options: nosniff');

try {
    $router = new Router();
    require BASE_PATH . '/app/routes.php';

    Csrf::verify();
    $router->dispatch(Request::method(), Request::path());
} catch (ApiException $e) {
    Response::error($e->getMessage(), $e->status, $e->errors);
} catch (Throwable $e) {
    error_log((string) $e);
    Response::error(Env::bool('APP_DEBUG') ? $e->getMessage() : 'Server error', 500);
}
