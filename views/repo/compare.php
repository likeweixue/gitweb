<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'code', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <h2 class="page-subtitle"><?= icon('git-compare', 16) ?> 对比分支 / 标签</h2>
</div>

<form class="card compare-form" method="get" action="<?= repo_url($repo, '/compare') ?>">
  <div class="compare-row">
    <div class="form-group">
      <label class="form-label" for="base">base（基准）</label>
      <select class="form-control" id="base" name="base">
        <?php foreach ($refs as $refName): ?>
        <option value="<?= e($refName) ?>" <?= $refName === $base ? 'selected' : '' ?>><?= e($refName) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <span class="compare-arrow"><?= icon('arrow-left', 18) ?></span>
    <div class="form-group">
      <label class="form-label" for="head">head（目标）</label>
      <select class="form-control" id="head" name="head">
        <?php foreach ($refs as $refName): ?>
        <option value="<?= e($refName) ?>" <?= $refName === $head ? 'selected' : '' ?>><?= e($refName) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">对比</button>
  </div>
</form>

<?php if ($error !== ''): ?>
  <div class="flash flash-error" data-flash><span class="flash-icon"><?= icon('alert', 16) ?></span><span class="flash-text"><?= e($error) ?></span></div>
<?php elseif (!empty($submitted)): ?>
  <div class="card compare-result">
    <h3 class="compare-title">
      <code class="mono"><?= e($base) ?></code> ... <code class="mono"><?= e($head) ?></code>
      <span class="text-muted text-small"><?= count($commits) ?> 个提交</span>
    </h3>
    <div class="compare-actions">
      <?php if ($commits !== []): ?>
        <a class="btn btn-primary" href="<?= repo_url($repo, '/pulls/new?base=' . rawurlencode($base) . '&head=' . rawurlencode($head)) ?>">
          <?= icon('pull-request', 15) ?> 发起 Pull request
        </a>
      <?php endif; ?>
      <a class="btn" href="<?= repo_url($repo, '/pulls') ?>"><?= icon('pull-request', 15) ?> 查看所有 PR</a>
    </div>
    <?php if ($commits !== []): ?>
    <div class="commit-list">
      <?php foreach ($commits as $commit): ?>
      <div class="commit-item">
        <?= avatar(['username' => (string)$commit['author_name']], 20) ?>
        <div class="commit-main">
          <a class="commit-subject" href="<?= repo_url($repo, '/commit/' . $commit['hash']) ?>"><?= e((string)$commit['subject']) ?></a>
          <div class="commit-meta text-muted text-small"><strong><?= e((string)$commit['author_name']) ?></strong> 提交于 <?= time_ago((string)$commit['date']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?= partial('diff', ['diff' => $diff]) ?>
  </div>
<?php endif; ?>
