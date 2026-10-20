<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Model\Repository;
use App\Model\Setting;
use App\Model\User;

/**
 * 管理后台
 */
final class AdminController extends Controller
{
    public function dashboard(): void
    {
        $this->requireAdmin();
        $repos = Repository::all(1, 1000, '');
        $diskUsage = 0;
        foreach ($repos['items'] as $repo) {
            $diskUsage += (int)$repo['size_bytes'];
        }
        $this->view('admin/dashboard', [
            'title'      => '管理后台',
            'userCount'  => User::countAll(),
            'repoCount'  => Repository::countAll(),
            'publicRepoCount' => Repository::countPublic(),
            'diskUsage'  => $diskUsage,
        ]);
    }

    public function users(): void
    {
        $this->requireAdmin();
        $q = trim((string)$this->req->get('q', ''));
        $page = max(1, (int)$this->req->get('page', 1));
        $result = User::paginate($page, 30, $q);
        $this->view('admin/users', [
            'title'  => '用户管理',
            'users'  => $result['items'],
            'total'  => $result['total'],
            'q'      => $q,
            'page'   => $page,
            'per'    => 30,
        ]);
    }

    public function banUser(string $id): void
    {
        $this->requireAdmin();
        Csrf::verify();
        $user = User::find((int)$id);
        if ($user !== null && $user['role'] !== 'admin') {
            User::setStatus((int)$id, 'banned');
            Session::flash('success', '用户已封禁');
        }
        $this->back();
    }

    public function unbanUser(string $id): void
    {
        $this->requireAdmin();
        Csrf::verify();
        User::setStatus((int)$id, 'active');
        Session::flash('success', '用户已解封');
        $this->back();
    }

    public function promoteUser(string $id): void
    {
        $this->requireAdmin();
        Csrf::verify();
        User::setRole((int)$id, 'admin');
        Session::flash('success', '已设为管理员');
        $this->back();
    }

    public function demoteUser(string $id): void
    {
        $admin = $this->requireAdmin();
        Csrf::verify();
        if ((int)$id === (int)$admin['id']) {
            Session::flash('error', '不能取消自己的管理员权限');
            $this->back();
            return;
        }
        User::setRole((int)$id, 'user');
        Session::flash('success', '已取消管理员');
        $this->back();
    }

    public function repositories(): void
    {
        $this->requireAdmin();
        $q = trim((string)$this->req->get('q', ''));
        $page = max(1, (int)$this->req->get('page', 1));
        $result = Repository::all($page, 30, $q);
        $this->view('admin/repositories', [
            'title' => '仓库管理',
            'repos' => $result['items'],
            'total' => $result['total'],
            'q'     => $q,
            'page'  => $page,
            'per'   => 30,
        ]);
    }

    public function toggleVisibility(string $id): void
    {
        $this->requireAdmin();
        Csrf::verify();
        $repo = Repository::find((int)$id);
        if ($repo !== null) {
            Repository::update((int)$id, ['is_private' => (int)$repo['is_private'] === 1 ? 0 : 1]);
            Session::flash('success', '可见性已切换');
        }
        $this->back();
    }

    public function deleteRepository(string $id): void
    {
        $this->requireAdmin();
        Csrf::verify();
        Repository::delete((int)$id);
        Session::flash('success', '仓库已删除');
        $this->back();
    }

    public function settingsForm(): void
    {
        $this->requireAdmin();
        $this->view('admin/settings', [
            'title'   => '站点设置',
            'settings' => Setting::all(),
        ]);
    }

    public function saveSettings(): void
    {
        $this->requireAdmin();
        Csrf::verify();
        $data = [
            'site_name'        => trim((string)$this->req->post('site_name', '')),
            'site_description' => trim((string)$this->req->post('site_description', '')),
            'allow_register'   => (string)$this->req->post('allow_register', '1'),
            'allow_anonymous'  => (string)$this->req->post('allow_anonymous', '1'),
        ];
        $errors = Validator::make($data, [
            'site_name'        => 'required|max:60',
            'site_description' => 'max:200',
            'allow_register'   => 'in:0,1',
            'allow_anonymous'  => 'in:0,1',
        ]);
        if ($errors !== []) {
            Session::set('_errors', $errors);
            $this->redirect(url('/admin/settings'));
            return;
        }
        Setting::set('site_name', $data['site_name']);
        Setting::set('site_description', $data['site_description']);
        Setting::set('allow_register', $data['allow_register']);
        Setting::set('allow_anonymous', $data['allow_anonymous']);
        Session::flash('success', '站点设置已保存');
        $this->redirect(url('/admin/settings'));
    }
}
