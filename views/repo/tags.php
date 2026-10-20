<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'code', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <h2 class="page-subtitle"><?= icon('tag', 16) ?> 标签 <span class="counter"><?= count($tags) ?></span></h2>
</div>

<div class="card">
  <table class="table">
    <thead>
      <tr><th>标签</th><th>提交</th><th>创建时间</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($tags as $tag): ?>
      <tr>
        <td>
          <a href="<?= repo_url($repo, '/tree/' . rawurlencode((string)$tag['name'])) ?>">
            <?= icon('tag', 14) ?> <strong><?= e($tag['name']) ?></strong>
          </a>
        </td>
        <td class="text-muted text-small mono"><?= e(substr((string)$tag['target'], 0, 7)) ?></td>
        <td class="text-muted text-small"><?= time_ago((string)$tag['date']) ?></td>
        <td class="text-right">
          <a class="btn btn-sm" href="<?= repo_url($repo, '/archive?ref=' . rawurlencode((string)$tag['name']) . '&format=zip') ?>">
            <?= icon('download', 13) ?> zip
          </a>
          <a class="btn btn-sm" href="<?= repo_url($repo, '/archive?ref=' . rawurlencode((string)$tag['name']) . '&format=tar.gz') ?>">
            <?= icon('download', 13) ?> tar.gz
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($tags === []): ?>
        <tr><td colspan="4" class="dash-empty">暂无标签（通过 <code>git tag v1.0.0 && git push --tags</code> 创建）</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
