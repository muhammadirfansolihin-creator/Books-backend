<?php
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$app->add(new App\Middleware\JsonBodyParser());
$app->addRoutingMiddleware();
$app->add(new App\Middleware\Cors());
$app->add(new App\Middleware\SecurityHeaders());

(require __DIR__ . '/../src/routes.php')($app);
$app->run();