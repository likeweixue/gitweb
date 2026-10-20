<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Model\Setting;
use App\Model\User;

/**
 * 认证（登录 / 注册 / 退出）
 */
final class AuthController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            $this->redirect(url('/'));
            return;
        }
        $this->view('auth/login', ['title' => '登录'], 'auth');
    }

    public function login(): void
    {
        Csrf::verify();
        $data = [
            'username' => trim((string)$this->req->post('username', '')),
            'password' => (string)$this->req->post('password', ''),
            'remember' => (string)$this->req->post('remember', ''),
        ];
        $errors = Validator::make($data, [
            'username' => 'required',
            'password' => 'required',
        ]);
        if ($errors !== []) {
            Session::set('_errors', $errors);
            Session::set('_old', $data);
            $this->redirect(url('/login'));
            return;
        }

        $ip = $this->req->ip();
        if (Auth::tooManyAttempts($ip)) {
            Session::flash('error', '登录失败次数过多，请 ' . (int)config('security.login_lock_minutes', 15) . ' 分钟后再试');
            $this->redirect(url('/login'));
            return;
        }

        $success = Auth::attempt($data['username'], $data['password'], $data['remember'] === '1');
        Auth::recordAttempt($ip, $data['username'], $success);

        if (!$success) {
            Session::set('_errors', ['username' => '用户名或密码错误（或账号已被封禁）']);
            Session::set('_old', $data);
            $this->redirect(url('/login'));
            return;
        }

        $intended = Session::get('_intended');
        Session::forget('_intended');
        Session::flash('success', '欢迎回来，' . Auth::user()['display_name'] . '！');
        $this->redirect(is_string($intended) && $intended !== '' ? $intended : url('/'));
    }

    public function registerForm(): void
    {
        if (Auth::check()) {
            $this->redirect(url('/'));
            return;
        }
        if ((string)Setting::get('allow_register', '1') !== '1') {
            Session::flash('error', '站点已关闭注册');
            $this->redirect(url('/login'));
            return;
        }
        $this->view('auth/register', ['title' => '注册'], 'auth');
    }

    public function register(): void
    {
        Csrf::verify();
        if ((string)Setting::get('allow_register', '1') !== '1') {
            Session::flash('error', '站点已关闭注册');
            $this->redirect(url('/login'));
            return;
        }

        $data = [
            'username'              => trim((string)$this->req->post('username', '')),
            'email'                 => trim((string)$this->req->post('email', '')),
            'password'              => (string)$this->req->post('password', ''),
            'password_confirmation' => (string)$this->req->post('password_confirmation', ''),
        ];
        $errors = Validator::make($data, [
            'username' => 'required|username|unique:users,username',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        if ($errors === [] && in_array(strtolower($data['username']), array_map('strtolower', User::reservedUsernames()), true)) {
            $errors['username'] = '该用户名为系统保留';
        }

        if ($errors !== []) {
            Session::set('_errors', $errors);
            Session::set('_old', $data);
            $this->redirect(url('/register'));
            return;
        }

        $id = User::create($data['username'], $data['email'], $data['password']);
        Auth::login(User::find($id) ?? []);
        Session::flash('success', '注册成功，欢迎加入 ' . site_name() . '！');
        $this->redirect(url('/'));
    }

    public function logout(): void
    {
        Csrf::verify();
        Auth::logout();
        Session::flash('success', '已退出登录');
        $this->redirect(url('/'));
    }
}
