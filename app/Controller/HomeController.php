<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Model\Repository;

/**
 * 首页 / 仪表盘
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        if (Auth::check()) {
            $user = Auth::user();
            $repos = Repository::forUser((int)$user['id'], (int)$user['id'], 1, 12);
            $starred = Repository::starredBy((int)$user['id'], 1, 6);
            $this->view('home/dashboard', [
                'title'   => '仪表盘',
                'user'    => $user,
                'repos'   => $repos['items'],
                'starred' => $starred,
            ]);
            return;
        }
        $this->view('home/landing', ['title' => site_name() . ' - 属于你自己的代码托管平台']);
    }
}
