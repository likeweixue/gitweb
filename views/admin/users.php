<div class="page-head">
  <h1 class="page-title">用户管理</h1>
  <form class="searchbox" action="<?= url('/admin/users') ?>" method="get">
    <?= icon('search', 14) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="搜索用户名 / 邮箱">
  </form>
</div>

<div class="card">
  <table class="table admin-table">
    <thead>
      <tr><th>ID</th><th>用户名</th><th>邮箱</th><th>角色</th><th>状态</th><th>注册时间</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td class="text-muted"><?= (int)$u['id'] ?></td>
        <td><a href="<?= url('/' . rawurlencode((string)$u['username'])) ?>"><?= e((string)$u['username']) ?></a></td>
        <td class="text-muted text-small"><?= e((string)$u['email']) ?></td>
        <td>
          <?php if ($u['role'] === 'admin'): ?><span class="badge badge-admin">管理员</span><?php else: ?><span class="badge">用户</span><?php endif; ?>
        </td>
        <td>
          <?php if ($u['status'] === 'banned'): ?><span class="badge badge-closed">已封禁</span><?php else: ?><span class="badge badge-open">正常</span><?php endif; ?>
        </td>
        <td class="text-muted text-small"><?= fmt_date((string)$u['created_at']) ?></td>
        <td class="text-right admin-actions">
          <?php if ($u['role'] !== 'admin'): ?>
          <form method="post" action="<?= url('/admin/users/' . (int)$u['id'] . '/promote') ?>" class="inline-form">
            <?= csrf_field() ?><button type="submit" class="btn btn-sm">设为管理员</button>
          </form>
          <?php endif; ?>
          <?php if ((int)$u['id'] !== (int)(current_user()['id'] ?? 0)): ?>
            <?php if ($u['status'] === 'banned'): ?>
            <form method="post" action="<?= url('/admin/users/' . (int)$u['id'] . '/unban') ?>" class="inline-form">
              <?= csrf_field() ?><button type="submit" class="btn btn-sm">解封</button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= url('/admin/users/' . (int)$u['id'] . '/ban') ?>" class="inline-form"
                  data-confirm="确定封禁该用户？其会话将立即失效。">
              <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-danger">封禁</button>
            </form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($u['role'] === 'admin' && (int)$u['id'] !== (int)(current_user()['id'] ?? 0)): ?>
          <form method="post" action="<?= url('/admin/users/' . (int)$u['id'] . '/demote') ?>" class="inline-form">
            <?= csrf_field() ?><button type="submit" class="btn btn-sm">取消管理员</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= paginate($total, $page, $per, url('/admin/users?q=' . rawurlencode($q))) ?>
