<?php
/**
 * Gitweb 统一入口（前端控制器）
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;

$uri = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

// 静态资源（php -S 内置服务器需要；nginx/Apache 下由 Web 服务器直接处理）
if (preg_match('#^/assets/(.+)$#', $uri, $m) === 1) {
    $file = GW_APP_ROOT . '/public/assets/' . $m[1];
    if (is_file($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css' => 'text/css',
            'js' => 'text/javascript',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'woff2' => 'font/woff2',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=3600');
        readfile($file);
        return;
    }
}

if ($uri === '/favicon.svg') {
    $file = GW_APP_ROOT . '/public/favicon.svg';
    if (is_file($file)) {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        return;
    }
}

if ($uri === '/favicon.ico') {
    header('Location: ' . asset('/favicon.svg'));
    return;
}

$router = new Router();
require GW_APP_ROOT . '/app/routes.php';
$router->dispatch();
