<?php
namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Cors implements MiddlewareInterface
{
    private array $allowed;

    public function __construct(){
        $list = (string) ($_ENV['CORS_ALLOWED_ORIGINS'] ?? '');
        $this->allowed = array_filter(array_map('trim', explode(',', $list)));
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // For preflight OPTIONS requests, intercept and return a blank 204 response
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response(204);
            return $this->withCors($request, $response); // Pass both $request and $response
        }
        
        // Pass both $request and the generated $response from downstream handlers
        return $this->withCors($request, $handler->handle($request));
    }

    private function withCors(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface {
        $origin = $req->getHeaderLine('Origin');
        $allow = !empty($origin) ? $origin : '*'; 
        $creds = true;
        
        if ($this->allowed && in_array($origin, $this->allowed, true)) {
            $allow = $origin; 
            $creds = true;
        }
        
        $res = $res
            ->withHeader('Access-Control-Allow-Origin', $allow)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Vary', 'Origin');
            
        if ($creds) {
            $res = $res->withHeader('Access-Control-Allow-Credentials', 'true');
        }
        return $res;
    }
}