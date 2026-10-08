<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
const APP_VERSION = '1';

require __DIR__ . '/helpers/Lang.php';

spl_autoload_register(static function (string $class): void {
    foreach (['core', 'controllers', 'services', 'repositories', 'middleware', 'validators', 'helpers'] as $dir) {
        $file = BASE_PATH . "/app/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

Env::load(BASE_PATH . '/.env');

$debug = Env::bool('APP_DEBUG', false) && Env::get('APP_ENV') !== 'production';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Baghdad');

Session::start();
