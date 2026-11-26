<?php

// PHP 8.6 deprecates spl_object_hash(), which Laravel 11 still calls during boot.
// Hide deprecations before Laravel's error handler is installed.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        require __DIR__.'/../vendor/autoload.php';
        (new Application(dirname(__DIR__)))
            ->useAppPath(dirname(__DIR__).'/app')
            ->handleRequest(Request::capture());
    }
});

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
