<div class="page-head">
  <h1 class="page-title">仓库管理</h1>
  <form class="searchbox" action="<?= url('/admin/repositories') ?>" method="get">
    <?= icon('search', 14) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="搜索仓库名 / 属主">
  </form>
</div>

<div class="card">
  <table class="table admin-table">
    <thead>
      <tr><th>ID</th><th>仓库</th><th>属主</th><th>可见性</th><th>Star</th><th>体积</th><th>最后推送</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($repos as $repo): ?>
      <tr>
        <td class="text-muted"><?= (int)$repo['id'] ?></td>
        <td><a href="<?= repo_url($repo) ?>"><?= e((string)$repo['name']) ?></a></td>
        <td class="text-muted"><?= e((string)$repo['owner_name']) ?></td>
        <td>
          <?php if ((int)$repo['is_private'] === 1): ?><span class="badge badge-private">私有</span><?php else: ?><span class="badge badge-public">公开</span><?php endif; ?>
        </td>
        <td class="text-muted"><?= (int)$repo['stars_count'] ?></td>
        <td class="text-muted text-small"><?= human_size((int)$repo['size_bytes']) ?></td>
        <td class="text-muted text-small"><?= time_ago(!empty($repo['pushed_at']) ? (string)$repo['pushed_at'] : null) ?></td>
        <td class="text-right admin-actions">
          <form method="post" action="<?= url('/admin/repositories/' . (int)$repo['id'] . '/visibility') ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm">切换可见性</button>
          </form>
          <form method="post" action="<?= url('/admin/repositories/' . (int)$repo['id'] . '/delete') ?>" class="inline-form"
                data-confirm="将永久删除该仓库及全部代码，确定？">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-danger">删除</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($repos === []): ?>
        <tr><td colspan="8" class="dash-empty">没有仓库</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?= paginate($total, $page, $per, url('/admin/repositories?q=' . rawurlencode($q))) ?>
