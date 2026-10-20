<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Model\AccessToken;
use App\Model\Repository;
use App\Model\SshKey;
use App\Model\User;
use InvalidArgumentException;

/**
 * 用户主页与个人设置
 */
final class UserController extends Controller
{
    // ------------------------------------------------------------ 个人主页

    public function profile(string $username): void
    {
        $user = User::findByUsername($username);
        if ($user === null) {
            $this->notFound('用户不存在');
        }
        $viewer = Auth::user();
        $tab = $this->req->get('tab') === 'stars' ? 'stars' : 'repos';
        $page = max(1, (int)$this->req->get('page', 1));

        if ($tab === 'stars') {
            $items = Repository::starredBy((int)$user['id'], $page, 20);
            $total = Repository::starredCount((int)$user['id']);
        } else {
            $feed = Repository::forUser((int)$user['id'], $viewer === null ? null : (int)$viewer['id'], $page, 20);
            $items = ['items' => $feed['items']];
            $total = $feed['total'];
        }

        $this->view('user/profile', [
            'title'      => $user['display_name'] !== '' ? $user['display_name'] : $user['username'],
            'user'       => $user,
            'tab'        => $tab,
            'repos'      => $items['items'],
            'total'      => $total,
            'page'       => $page,
            'isSelf'     => $viewer !== null && (int)$viewer['id'] === (int)$user['id'],
            'repoCount'  => Repository::forUser((int)$user['id'], null, 1, 1)['total'],
        ]);
    }

    // ------------------------------------------------------------ 设置：基本资料

    public function settingsGeneral(): void
    {
        $user = $this->requireLogin();
        $this->view('user/settings/general', ['title' => '个人设置', 'user' => $user]);
    }

    public function updateProfile(): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $data = [
            'display_name' => trim((string)$this->req->post('display_name', '')),
            'bio'          => (string)$this->req->post('bio', ''),
            'location'     => trim((string)$this->req->post('location', '')),
            'website'      => trim((string)$this->req->post('website', '')),
        ];
        $errors = Validator::make($data, [
            'display_name' => 'max:80',
            'bio'          => 'max:500',
            'location'     => 'max:80',
            'website'      => 'url',
        ]);
        if ($errors !== []) {
            Session::set('_errors', $errors);
            $this->redirect(url('/settings'));
            return;
        }
        User::updateProfile((int)$user['id'], $data);
        Session::flash('success', '资料已更新');
        $this->redirect(url('/settings'));
    }

    public function updatePassword(): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $data = [
            'current_password'      => (string)$this->req->post('current_password', ''),
            'new_password'          => (string)$this->req->post('new_password', ''),
            'new_password_confirmation' => (string)$this->req->post('new_password_confirmation', ''),
        ];
        $errors = Validator::make($data, [
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed',
        ]);
        if ($errors === [] && !password_verify($data['current_password'], (string)$user['password_hash'])) {
            $errors['current_password'] = '当前密码错误';
        }
        if ($errors !== []) {
            Session::set('_errors', $errors);
            $this->redirect(url('/settings'));
            return;
        }
        User::updatePassword((int)$user['id'], $data['new_password']);
        Session::flash('success', '密码已修改');
        $this->redirect(url('/settings'));
    }

    // ------------------------------------------------------------ 设置：SSH 密钥

    public function sshKeys(): void
    {
        $user = $this->requireLogin();
        $this->view('user/settings/ssh', [
            'title' => 'SSH 公钥',
            'user'  => $user,
            'keys'  => SshKey::allForUser((int)$user['id']),
        ]);
    }

    public function addSshKey(): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $title = trim((string)$this->req->post('title', ''));
        $key = trim((string)$this->req->post('public_key', ''));
        try {
            SshKey::create((int)$user['id'], $title, $key);
            Session::flash('success', 'SSH 公钥已添加');
        } catch (InvalidArgumentException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect(url('/settings/ssh'));
    }

    public function deleteSshKey(): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        SshKey::delete((int)$this->req->post('id', 0), (int)$user['id']);
        Session::flash('success', 'SSH 公钥已删除');
        $this->redirect(url('/settings/ssh'));
    }

    // ------------------------------------------------------------ 设置：访问令牌

    public function tokens(): void
    {
        $user = $this->requireLogin();
        $newToken = Session::get('_new_token');
        Session::forget('_new_token');
        $this->view('user/settings/tokens', [
            'title'    => '访问令牌',
            'user'     => $user,
            'tokens'   => AccessToken::allForUser((int)$user['id']),
            'newToken' => is_string($newToken) ? $newToken : null,
        ]);
    }

    public function createToken(): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        $name = trim((string)$this->req->post('name', ''));
        if ($name === '') {
            $name = '未命名令牌';
        }
        $result = AccessToken::create((int)$user['id'], $name);
        Session::set('_new_token', $result['token']);
        Session::flash('success', '令牌已创建（仅显示一次，请妥善保存）');
        $this->redirect(url('/settings/tokens'));
    }

    public function deleteToken(): void
    {
        $user = $this->requireLogin();
        Csrf::verify();
        AccessToken::delete((int)$this->req->post('id', 0), (int)$user['id']);
        Session::flash('success', '令牌已吊销');
        $this->redirect(url('/settings/tokens'));
    }
}
