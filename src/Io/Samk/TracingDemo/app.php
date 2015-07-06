<?php

namespace Io\Samk\TracingDemo;

use Io\Samk\Logging\RequestProcessor;
use Io\Samk\Logging\TracingEventListener;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Silex\Application;
use Silex\Provider\MonologServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$topDir = realpath(__DIR__ . '/../../../../');
require_once $topDir . '/vendor/autoload.php';

$app = new Application();
/**
 * Register the App Logger
 */
$app->register(
    new MonologServiceProvider(),
    array(
        'monolog.logfile' => $topDir . '/logs/development.log',
        'monolog.level' => Logger::INFO,
        'monolog.name' => 'FileExporterApp'
    )
);
//##############################//
//##### START LOG JUGGLING #####//
// see Io/Samk/Logging          //
/**
 * replace the Handler with one that has our Trace formatter
 * ?? got to be a better way to do this ??
 */
$app['monolog'] = $app->share($app->extend('monolog', function ($monolog) use ($topDir) {
    $handler = new StreamHandler($topDir . '/logs/development.log', Logger::INFO);
    $handler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% #TRACE#%extra%\n"));
    /** @var Logger $monolog */
    $monolog->popHandler();
    $monolog->pushHandler($handler);

    return $monolog;
}));
$app['monolog.listener'] = $app->share(function () use ($app) {
    return new TracingEventListener($app['logger']);
});
$app['logger']->pushProcessor(new RequestProcessor($app));
//###### END LOG JUGGLING ######//
//##############################//

function errorResponse($message, $statusCode)
{
    return new Response(
        json_encode(
            [
                "code" => $statusCode,
                "message" => $message
            ]
        ),
        400,
        ['Content-Type' => 'application/json']
    );
}

function resourceResponse($payload, $statusCode = 200)
{
    $response = new Response();
    $response->headers->set('Content-Type', 'application/json');
    $response->setContent(json_encode($payload));
    $response->setStatusCode($statusCode);

    return $response;
}

$app->get(
    '/',
    function () use ($app) {
        return 'Hello This Demo Companion App';
    }
);

$app->post(
    '/payloads',
    function (Request $request) use ($app) {
        $app['monolog']->addInfo('Received Work: ' . $request->getContent());

        return resourceResponse("", 204);
    }
);

return $app;
