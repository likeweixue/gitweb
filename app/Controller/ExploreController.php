<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Model\Repository;
use App\Model\User;

/**
 * 探索页（公开仓库 / 用户搜索）
 */
final class ExploreController extends Controller
{
    public function index(): void
    {
        $tab = $this->req->get('tab') === 'users' ? 'users' : 'repos';
        $q = trim((string)$this->req->get('q', ''));
        $page = max(1, (int)$this->req->get('page', 1));
        $sort = (string)$this->req->get('sort', 'updated');
        if (!in_array($sort, ['updated', 'created', 'stars'], true)) {
            $sort = 'updated';
        }

        $data = ['title' => '探索', 'tab' => $tab, 'q' => $q, 'sort' => $sort, 'page' => $page];

        if ($tab === 'users') {
            $data['users'] = User::search($q, 50);
        } else {
            $feed = Repository::publicFeed($page, 20, $q, $sort);
            $data['repos'] = $feed['items'];
            $data['total'] = $feed['total'];
        }

        $this->view('explore/index', $data);
    }
}
