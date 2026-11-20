<?php
declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;
use Throwable;

class Router
{
    /** @var array<int, array{method: string, pattern: string, regex: string, handler: mixed, middlewares: array<mixed>}> */
    private array $routes = [];
    private string $groupPrefix = '';
    private array $groupMiddlewares = [];

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     * @param array<mixed> $middlewares
     */
    public function addRoute(string $method, string $path, mixed $handler, array $middlewares = []): self
    {
        $fullPath = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        if ($fullPath !== '/' && str_ends_with($fullPath, '/')) {
            $fullPath = rtrim($fullPath, '/');
        }

        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $regex = preg_replace('/:([a-zA-Z0-9_]+)/', '(?P<$1>[^/]+)', (string)$regex);
        $regex = ($fullPath === '/') ? '#^/?$#' : '#^' . $regex . '/?$#';

        $allMiddlewares = array_merge($this->groupMiddlewares, $middlewares);

        $this->routes[] = [
            'method'      => strtoupper($method),
            'pattern'     => $fullPath,
            'regex'       => (string)$regex,
            'handler'     => $handler,
            'middlewares' => $allMiddlewares,
        ];

        return $this;
    }

    public function get(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function patch(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function put(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function any(string $path, mixed $handler, array $middlewares = []): self
    {
        return $this->addRoute('ANY', $path, $handler, $middlewares);
    }

    /**
     * @param callable(Router): void $callback
     * @param array<mixed> $middlewares
     */
    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->groupPrefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddlewares = array_merge($previousMiddlewares, $middlewares);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    public function dispatch(Request $request): Response
    {
        $requestMethod = $request->getMethod();
        $rawPath = $request->getPath();
        $requestPath = rtrim($rawPath, '/');
        if ($requestPath === '') {
            $requestPath = '/';
        }
        $methodMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $requestPath, $matches)) {
                continue;
            }

            if ($route['method'] !== 'ANY' && $route['method'] !== $requestMethod) {
                $methodMatched = true;
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = urldecode($value);
                }
            }
            $request->setParams($params);

            return $this->runPipeline($request, $route['middlewares'], $route['handler']);
        }

        if ($methodMatched) {
            return Response::json([
                'success' => false,
                'error'   => "405 Method Not Allowed: {$requestMethod} is not supported for {$requestPath}",
            ], 405);
        }

        if ($request->isAjax() || str_starts_with($requestPath, '/api/')) {
            return Response::json([
                'success' => false,
                'error'   => "404 Not Found: endpoint '{$requestPath}' does not exist.",
            ], 404);
        }

        return Response::html("
            <!DOCTYPE html>
            <html lang=\"en\">
            <head><meta charset=\"UTF-8\"><title>404 Not Found - MKR College ERP</title></head>
            <body style=\"font-family: sans-serif; text-align: center; padding: 80px 20px;\">
                <h1 style=\"color: #800000; font-size: 48px; margin-bottom: 8px;\">404</h1>
                <p style=\"color: #475569; font-size: 18px;\">The requested page '<strong>" . htmlspecialchars($requestPath, ENT_QUOTES, 'UTF-8') . "</strong>' was not found.</p>
                <a href=\"/\" style=\"display: inline-block; margin-top: 20px; background: #800000; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 6px;\">Return to Home</a>
            </body>
            </html>
        ", 404);
    }

    /**
     * @param array<mixed> $middlewares
     */
    private function runPipeline(Request $request, array $middlewares, mixed $handler): Response
    {
        $pipeline = array_reduce(
            array_reverse($middlewares),
            function (Closure $next, mixed $middleware) {
                return function (Request $req) use ($middleware, $next): Response {
                    if (is_callable($middleware)) {
                        $res = $middleware($req, $next);
                        return $this->ensureResponse($res);
                    }
                    if (is_string($middleware) && class_exists($middleware)) {
                        $instance = new $middleware();
                        if (method_exists($instance, 'handle')) {
                            $res = $instance->handle($req, $next);
                            return $this->ensureResponse($res);
                        }
                    }
                    return $next($req);
                };
            },
            function (Request $req) use ($handler): Response {
                return $this->executeHandler($req, $handler);
            }
        );

        return $pipeline($request);
    }

    private function executeHandler(Request $request, mixed $handler): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request);
            return $this->ensureResponse($result);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (is_string($class) && class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $method)) {
                    $result = $controller->$method($request);
                    return $this->ensureResponse($result);
                }
                throw new RuntimeException("Method '{$method}' not found in controller '{$class}'");
            }
        }

        throw new RuntimeException("Invalid route handler provided");
    }

    private function ensureResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        if (is_string($result)) {
            return Response::html($result);
        }
        return Response::html('', 204);
    }

    /**
     * @return array<int, array{method: string, pattern: string}>
     */
    public function getRoutes(): array
    {
        return array_map(function ($r) {
            return ['method' => $r['method'], 'pattern' => $r['pattern']];
        }, $this->routes);
    }
}
