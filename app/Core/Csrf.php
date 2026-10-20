<?php
declare(strict_types=1);

namespace App\Core;

/**
 * CSRF 跨站请求伪造防护
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(self::token()) . '">';
    }

    public static function check(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['_csrf_token']) && hash_equals((string)$_SESSION['_csrf_token'], $token);
    }

    /** 校验失败即终止请求（Web 表单 POST 使用；git 协议路由不调用本方法） */
    public static function verify(): void
    {
        if (!self::check(isset($_POST['_csrf_token']) ? (string)$_POST['_csrf_token'] : null)) {
            http_response_code(419);
            header('Content-Type: text/plain; charset=utf-8');
            exit('页面已过期，请返回重试（CSRF 校验失败）。');
        }
    }
}
