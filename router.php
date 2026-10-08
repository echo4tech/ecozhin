<?php
// Dev router: php -S localhost:8000 router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/api/')) {
    require __DIR__ . '/api/index.php';
    return true;
}
$blocked = ['/app/', '/database/', '/storage/', '/tests/', '/.env'];
foreach ($blocked as $b) {
    if (str_starts_with($path, $b)) {
        http_response_code(404);
        return true;
    }
}
return false;
