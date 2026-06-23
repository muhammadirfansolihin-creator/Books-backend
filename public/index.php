<?php
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->add(new App\Middleware\JsonBodyParser());
$app->addErrorMiddleware(true, true, true);
$app->add(new App\Middleware\SecurityHeaders());
$app->add(new App\Middleware\Cors());
(require __DIR__ . '/../src/routes.php')($app);
$app->run();