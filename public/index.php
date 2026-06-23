<?php
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->add(new App\Middleware\SecurityHeaders());
$app->add(new App\Middleware\JsonBodyParser());
$app->add(new App\Middleware\Cors());
$app->addErrorMiddleware(true, true, true);

$errorMiddleware->setDefaultErrorHandler(function (
    ServerRequestInterface $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    // 1. Generate Slim's standard error response payload
    $response = $app->getResponseFactory()->createResponse();
    
    $payload = [
        'error' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ];
    
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    
    // 2. Inject DevTools suggestion: Append hard-coded CORS headers so the browser allows the error message through
    $origin = $request->getHeaderLine('Origin');
    $allowOrigin = !empty($origin) ? $origin : '*';

    return $response
        ->withStatus(500)
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Total-Count')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

(require __DIR__ . '/../src/routes.php')($app);
$app->run();