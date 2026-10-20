<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 控制器基类
 */
abstract class Controller
{
    protected Request $req;

    public function __construct()
    {
        $this->req = new Request();
    }

    protected function view(string $template, array $data = [], ?string $layout = 'main'): void
    {
        echo View::render($template, $data, $layout);
        // 渲染后清除一次性输入/错误
        Session::forget('_errors');
        Session::forget('_old');
    }

    protected function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function redirect(string $to, int $code = 302): void
    {
        http_response_code($code);
        header('Location: ' . $to);
    }

    protected function back(): void
    {
        $this->redirect((string)($_SERVER['HTTP_REFERER'] ?? url('/')));
    }

    protected function requireLogin(): array
    {
        $user = Auth::user();
        if ($user === null) {
            Session::flash('info', '请先登录');
            Session::set('_intended', (string)($_SERVER['REQUEST_URI'] ?? '/'));
            $this->redirect(url('/login'));
            exit;
        }
        if ($user['status'] === 'banned') {
            Auth::logout();
            Session::flash('error', '账号已被封禁');
            $this->redirect(url('/login'));
            exit;
        }
        return $user;
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireLogin();
        if ($user['role'] !== 'admin') {
            abort(403, '需要管理员权限');
        }
        return $user;
    }

    /** @return array<string, string> 验证失败时自动回退并终止 */
    protected function validate(array $data, array $rules): array
    {
        $errors = Validator::make($data, $rules);
        if ($errors !== []) {
            Session::set('_errors', $errors);
            Session::set('_old', $data);
            $this->back();
            exit;
        }
        return $data;
    }

    protected function notFound(string $message = '页面不存在'): void
    {
        abort(404, $message);
    }
}
