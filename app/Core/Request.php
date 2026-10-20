<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP 请求封装
 */
final class Request
{
    public function method(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string)$_SERVER[$key] : null;
    }

    public function authorization(): ?string
    {
        return $this->header('Authorization');
    }

    /** 解析 HTTP Basic 认证，返回 [用户名, 密码] 或 null */
    public function basicAuth(): ?array
    {
        $auth = $this->authorization();
        if ($auth === null || !str_starts_with($auth, 'Basic ')) {
            return null;
        }
        $decoded = base64_decode(trim(substr($auth, 6)), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }
        [$user, $pass] = explode(':', $decoded, 2);
        return [$user, $pass];
    }

    public function ip(): string
    {
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    public function queryString(): string
    {
        return (string)($_SERVER['QUERY_STRING'] ?? '');
    }

    public function contentType(): string
    {
        return (string)($_SERVER['CONTENT_TYPE'] ?? '');
    }

    public function contentLength(): string
    {
        return (string)($_SERVER['CONTENT_LENGTH'] ?? '0');
    }

    public function uri(): string
    {
        return (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    }
}
