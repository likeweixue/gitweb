<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 正则路由器（支持 {name}、{name:regex}、{name*} 占位符）
 */
final class Router
{
    /** @var array<string, array<int, array{regex: string, handler: mixed}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $pattern, mixed $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, mixed $handler): void
    {
        $this->routes[strtoupper($method)][] = [
            'regex'   => $this->compile($pattern),
            'handler' => $handler,
        ];
    }

    private function compile(string $pattern): string
    {
        // 支持 {name}、{name:regex}（正则可含一层花括号，如 [0-9a-f]{7,64}）、{name*}
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(\*)?(?::((?:[^{}]|\{[^{}]*\})*))?\}/',
            static function (array $m): string {
                $name = $m[1];
                if (isset($m[3]) && $m[3] !== '') {
                    return '(?P<' . $name . '>' . $m[3] . ')';
                }
                return '(?P<' . $name . '>' . (isset($m[2]) && $m[2] === '*' ? '.+' : '[^/]+') . ')';
            },
            $pattern
        );
        return '#^' . $regex . '$#';
    }

    public function dispatch(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $uri = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        $base = (string)Config::get('app.base_path', '');
        if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim(rawurldecode($uri), '/');

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $uri, $matches) === 1) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                $this->invoke($route['handler'], $params);
                return;
            }
        }

        $this->abort(404);
    }

    private function invoke(mixed $handler, array $params): void
    {
        if ($handler instanceof \Closure) {
            $handler($params);
            return;
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $fqcn = 'App\\Controller\\' . $class;
            if (!class_exists($fqcn)) {
                $this->abort(500);
            }
            $controller = new $fqcn();
            $controller->{$method}(...array_values($params));
            return;
        }
        $this->abort(500);
    }

    private function abort(int $code): void
    {
        if (PHP_SAPI !== 'cli') {
            abort($code);
        }
        http_response_code($code);
        echo "HTTP $code\n";
        exit;
    }
}
