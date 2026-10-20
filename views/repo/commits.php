<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'code', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <h2 class="page-subtitle"><?= icon('history', 16) ?> 提交历史 <code class="mono"><?= e($ref) ?></code></h2>
  <?php partial('ref-select', ['branches' => $branches, 'tags' => $tags, 'defaultRef' => $defaultRef, 'repo' => $repo, 'current' => $ref, 'switchBase' => repo_url($repo, '/commits/')]) ?>
</div>

<div class="commit-list card">
  <?php foreach ($commits as $commit): ?>
  <div class="commit-item">
    <?= avatar(['username' => (string)$commit['author_name']], 22) ?>
    <div class="commit-main">
      <a class="commit-subject" href="<?= repo_url($repo, '/commit/' . $commit['hash']) ?>"><?= e((string)$commit['subject']) ?></a>
      <div class="commit-meta text-muted text-small">
        <strong><?= e((string)$commit['author_name']) ?></strong> 提交于 <?= time_ago((string)$commit['date']) ?>
      </div>
    </div>
    <a class="commit-sha mono" href="<?= repo_url($repo, '/commit/' . $commit['hash']) ?>" title="<?= e((string)$commit['hash']) ?>">
      <?= e((string)$commit['short']) ?>
    </a>
  </div>
  <?php endforeach; ?>
  <?php if ($commits === []): ?>
    <div class="dash-empty">该分支下暂无提交</div>
  <?php endif; ?>
</div>

<?= paginate($total, $page, $per, repo_url($repo, '/commits/' . rawurlencode((string)$ref))) ?>
