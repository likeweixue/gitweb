<?php
/**
 * Gitweb 应用引导文件
 * 所有请求的统一入口（public/index.php 引入本文件）
 */
declare(strict_types=1);

use App\Core\Config;
use App\Core\Logger;
use App\Core\Session;

define('GW_VERSION', 'beta1');
define('GW_APP_ROOT', dirname(__DIR__));
define('GW_STORAGE', GW_APP_ROOT . '/storage');
define('GW_START', microtime(true));

// ---------- 自动加载（须先于任何 App\ 类使用） ----------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = GW_APP_ROOT . '/app/' . $rel . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ---------- 配置 ----------
$configFile = GW_APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("缺少配置文件。请执行：cp config.example.php config.php 并修改数据库连接。\n");
}
$config = require $configFile;
Config::init($config);

// ---------- 基础环境 ----------
date_default_timezone_set((string)Config::get('app.timezone', 'Asia/Shanghai'));
mb_internal_encoding('UTF-8');

$debug = (bool)Config::get('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

// ---------- 目录保障 ----------
foreach ([GW_STORAGE . '/logs', GW_STORAGE . '/repositories', GW_STORAGE . '/cache'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

// git 可执行文件路径生效（CLI 与 Web 均可用）
App\Lib\Git::configure();

// ---------- 错误 / 异常处理 ----------
set_error_handler(static function (int $no, string $msg, string $file = '', int $line = 0): bool {
    if ((error_reporting() & $no) === 0) {
        return false;
    }
    Logger::error("PHP 错误[$no] $msg @ $file:$line");
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    Logger::error('未捕获异常: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (PHP_SAPI !== 'cli') {
        try {
            echo App\Core\View::render('errors/500', [
                'message' => Config::get('app.debug') ? $e->getMessage() : '服务器内部错误，请稍后重试。',
            ], null);
            return;
        } catch (Throwable) {
            // 渲染失败则走纯文本
        }
    }
    echo '服务器内部错误';
});

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e !== null && ($e['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        Logger::error('致命错误: ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
    }
});

// ---------- 会话（CLI 模式不启动） ----------
if (PHP_SAPI !== 'cli') {
    Session::start((array)Config::get('session', []));
}

// ---------- 全局工具函数 ----------
require GW_APP_ROOT . '/app/Core/Helpers.php';
