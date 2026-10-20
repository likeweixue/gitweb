<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'code', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <h2 class="page-subtitle"><?= icon('branch', 16) ?> 分支 <span class="counter"><?= count($branches) ?></span></h2>
</div>

<div class="card">
  <table class="table">
    <thead>
      <tr><th>分支</th><th>最新提交</th><th>更新时间</th></tr>
    </thead>
    <tbody>
      <?php foreach ($branches as $branch): ?>
      <tr>
        <td>
          <a href="<?= repo_url($repo, '/tree/' . rawurlencode((string)$branch['name'])) ?>">
            <?= icon('branch', 14) ?> <strong><?= e($branch['name']) ?></strong>
          </a>
          <?php if ((string)$branch['name'] === (string)$defaultRef): ?><span class="badge badge-default">默认</span><?php endif; ?>
        </td>
        <td class="text-muted text-small mono"><?= e(substr((string)$branch['sha'], 0, 7)) ?></td>
        <td class="text-muted text-small"><?= time_ago((string)$branch['date']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if ($branches === []): ?>
        <tr><td colspan="3" class="dash-empty">暂无分支</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
