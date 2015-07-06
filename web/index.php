<?php
/** boilerplate to support build-in PHP server */
$filename = __DIR__ . preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

$app = require __DIR__ . '/../src/Io/Samk/TracingDemo/app.php';

$app['debug'] = true;

$app->run();