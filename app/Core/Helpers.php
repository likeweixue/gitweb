<?php
declare(strict_types=1);

/**
 * 全局工具函数
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Lib\Highlighter;
use App\Lib\Markdown;
use App\Model\Setting;

/** HTML 转义 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 读取配置 */
function config(string $key, mixed $default = null): mixed
{
    return App\Core\Config::get($key, $default);
}

/** 生成站点 URL */
function url(string $path = ''): string
{
    $base = rtrim((string)config('app.url', ''), '/');
    $basePath = (string)config('app.base_path', '');
    return $base . $basePath . '/' . ltrim($path, '/');
}

/** 生成静态资源 URL */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** 仓库 URL */
function repo_url(array $repo, string $path = ''): string
{
    return url('/' . rawurlencode((string)$repo['owner_name']) . '/' . rawurlencode((string)$repo['name']) . $path);
}

/** clone 地址（HTTPS / SSH） */
function clone_urls(array $repo): array
{
    $base = rtrim((string)config('app.url', ''), '/');
    $https = $base . '/' . rawurlencode((string)$repo['owner_name']) . '/' . rawurlencode((string)$repo['name']) . '.git';
    $ssh = (string)config('git.ssh_user', 'git') . '@' . (string)config('git.ssh_host')
        . ':' . (string)$repo['owner_name'] . '/' . (string)$repo['name'] . '.git';
    return ['https' => $https, 'ssh' => $ssh];
}

/** 重定向 */
function redirect(string $to, int $code = 302): void
{
    http_response_code($code);
    header('Location: ' . $to);
}

/** 返回上一个页面 */
function back(): void
{
    redirect((string)($_SERVER['HTTP_REFERER'] ?? url('/')));
}

/** 终止请求并渲染错误页 */
function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    $titles = [400 => '请求无效', 403 => '没有权限', 404 => '页面不存在', 419 => '页面已过期', 500 => '服务器错误'];
    $view = match ($code) {
        403     => 'errors/403',
        500     => 'errors/500',
        default => 'errors/404',
    };
    try {
        echo View::render($view, [
            'code'    => $code,
            'message' => $message !== '' ? $message : ($titles[$code] ?? '错误'),
        ], 'main');
    } catch (\Throwable) {
        header('Content-Type: text/plain; charset=utf-8');
        echo ($titles[$code] ?? '错误') . ($message !== '' ? '：' . $message : '');
    }
    exit;
}

/** 旧输入回填 */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::get('_old', []);
    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

/** 验证错误列表 */
function errors(): array
{
    $errors = Session::get('_errors', []);
    return is_array($errors) ? $errors : [];
}

/** 单字段错误 */
function error_for(string $key): ?string
{
    $errors = errors();
    return isset($errors[$key]) ? (string)$errors[$key] : null;
}

/** CSRF 隐藏域 */
function csrf_field(): string
{
    return Csrf::field();
}

/** 渲染片段视图（直接输出；调用写 <?php partial('name', $data) ?> 或 <?= partial(...) ?> 均可） */
function partial(string $name, array $data = []): void
{
    echo View::render('partials/' . $name, $data, null);
}

/** 线性图标 */
function icon(string $name, int $size = 16, string $cls = ''): string
{
    static $icons = null;
    if ($icons === null) {
        $icons = require GW_APP_ROOT . '/app/Core/icons.php';
    }
    $path = $icons[$name] ?? $icons['file'] ?? '';
    return sprintf(
        '<svg class="icon %s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
        $cls,
        $size,
        $size,
        $path
    );
}

/**  identicon 头像（零图片资源，根据用户名生成） */
function avatar(array $user, int $size = 24): string
{
    $seed = (string)($user['username'] ?? 'user');
    $hash = md5($seed);
    $hue = ((int)hexdec(substr($hash, 0, 4)) % 360);
    $fg = sprintf('hsl(%d, 62%%, 42%%)', $hue);
    $bg = sprintf('hsl(%d, 75%%, 91%%)', $hue);
    $cells = '';
    for ($i = 0; $i < 15; $i++) {
        $x = $i % 5;
        $y = intdiv($i, 5);
        if ($x >= 3) {
            continue;
        }
        if ((int)hexdec($hash[$i]) % 2 === 1) {
            $cells .= sprintf('<rect x="%d" y="%d" width="1" height="1"/>', $x, $y);
            if ($x < 2) {
                $cells .= sprintf('<rect x="%d" y="%d" width="1" height="1"/>', 4 - $x, $y);
            }
        }
    }
    return sprintf(
        '<svg class="avatar" width="%d" height="%d" viewBox="0 0 5 5" aria-hidden="true"><rect width="5" height="5" rx="1.1" fill="%s"/><g fill="%s">%s</g></svg>',
        $size,
        $size,
        $bg,
        $fg,
        $cells
    );
}

/** 相对时间 */
function time_ago(?string $datetime): string
{
    if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }
    $diff = max(0, time() - $ts);
    return match (true) {
        $diff < 60       => '刚刚',
        $diff < 3600     => intdiv($diff, 60) . ' 分钟前',
        $diff < 86400    => intdiv($diff, 3600) . ' 小时前',
        $diff < 2592000  => intdiv($diff, 86400) . ' 天前',
        $diff < 31536000 => intdiv($diff, 2592000) . ' 个月前',
        default          => intdiv($diff, 31536000) . ' 年前',
    };
}

/** 日期格式化 */
function fmt_date(?string $datetime, string $format = 'Y-m-d H:i'): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '—' : date($format, $ts);
}

/** 人类可读体积 */
function human_size(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)min(floor(log((float)$bytes, 1024)), count($units) - 1);
    return round((float)$bytes / 1024 ** $i, $i >= 2 ? 2 : 1) . ' ' . $units[$i];
}

/** 截断文本 */
function str_limit(?string $text, int $limit = 80): string
{
    $text = (string)$text;
    return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text;
}

/** 分页 HTML */
function paginate(int $total, int $page, int $perPage, string $baseUrl): string
{
    $pages = max(1, (int)ceil($total / $perPage));
    if ($pages <= 1) {
        return '';
    }
    $page = max(1, min($page, $pages));
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $link = static fn (int $p): string => e($baseUrl . $sep . 'page=' . $p);

    $html = '<nav class="pagination" aria-label="分页">';
    $html .= '<a class="page-item' . ($page <= 1 ? ' page-disabled' : '') . '" href="' . $link(max(1, $page - 1)) . '">上一页</a>';
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    if ($start > 1) {
        $html .= '<a class="page-item" href="' . $link(1) . '">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-item page-disabled">…</span>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<a class="page-item' . ($i === $page ? ' page-active' : '') . '" href="' . $link($i) . '">' . $i . '</a>';
    }
    if ($end < $pages) {
        if ($end < $pages - 1) {
            $html .= '<span class="page-item page-disabled">…</span>';
        }
        $html .= '<a class="page-item" href="' . $link($pages) . '">' . $pages . '</a>';
    }
    $html .= '<a class="page-item' . ($page >= $pages ? ' page-disabled' : '') . '" href="' . $link(min($pages, $page + 1)) . '">下一页</a>';
    $html .= '</nav>';
    return $html;
}

/** 当前登录用户 */
function current_user(): ?array
{
    return Auth::user();
}

function is_logged_in(): bool
{
    return Auth::check();
}

function is_admin(): bool
{
    return Auth::isAdmin();
}

/** 站点设置 */
function site_name(): string
{
    return (string)Setting::get('site_name', 'Gitweb');
}

function site_description(): string
{
    return (string)Setting::get('site_description', '属于你自己的代码托管平台');
}

function site_setting(string $key, mixed $default = null): mixed
{
    return Setting::get($key, $default);
}

/** 导航高亮 */
function active_class(string $prefix): string
{
    $uri = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return str_starts_with($uri, $prefix) ? 'active' : '';
}

/** Markdown 渲染 */
function markdown(?string $text): string
{
    return Markdown::render((string)$text);
}

/** 语法高亮 */
function highlight(string $code, string $lang): string
{
    return Highlighter::highlight($code, $lang);
}

/** 按文件扩展名推断高亮语言 */
function lang_from_path(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return match ($ext) {
        'php', 'phtml'         => 'php',
        'js', 'mjs', 'jsx'     => 'javascript',
        'ts', 'tsx'            => 'javascript',
        'css', 'scss', 'less'  => 'css',
        'html', 'htm', 'xml', 'svg', 'vue' => 'html',
        'json'                => 'json',
        'py'                  => 'python',
        'sh', 'bash', 'zsh'    => 'bash',
        'sql'                 => 'sql',
        'yml', 'yaml'          => 'yaml',
        'md', 'markdown'       => 'markdown',
        'go'                  => 'go',
        'rb'                  => 'ruby',
        'java'                => 'java',
        default               => '',
    };
}

/** 判断文本是否可能是二进制 */
function is_binary_string(string $content): bool
{
    $sample = substr($content, 0, 8000);
    return str_contains($sample, "\0");
}
