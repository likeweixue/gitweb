<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'code', 'isStarred' => $isStarred]) ?>

<div class="commit-detail card">
  <div class="commit-detail-head">
    <h2 class="commit-detail-title"><?= e(explode("\n", (string)$meta['body'])[0]) ?></h2>
    <div class="commit-detail-meta">
      <div class="commit-author">
        <?= avatar(['username' => (string)$meta['author_name']], 32) ?>
        <div>
          <strong><?= e((string)$meta['author_name']) ?></strong>
          <span class="text-muted text-small">提交于 <?= fmt_date((string)$meta['author_date']) ?>（<?= time_ago((string)$meta['author_date']) ?>）</span>
          <?php if ((string)$meta['committer_name'] !== (string)$meta['author_name']): ?>
          <div class="text-muted text-small">由 <?= e((string)$meta['committer_name']) ?> 提交</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="commit-refs">
        <span class="text-muted text-small">提交</span>
        <code class="mono commit-sha-lg"><?= e((string)$meta['hash']) ?></code>
        <?php if (count($meta['parents']) > 1): ?>
        <span class="badge">合并提交（<?= count($meta['parents']) ?> 个父提交）</span>
        <?php endif; ?>
      </div>
    </div>
    <?php if (count($meta['parents']) === 1): ?>
    <div class="commit-parents text-small">
      <span class="text-muted">父提交：</span>
      <a class="mono" href="<?= repo_url($repo, '/commit/' . $meta['parents'][0]) ?>"><?= e(substr((string)$meta['parents'][0], 0, 7)) ?></a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (trim((string)$meta['body']) !== ''): ?>
<div class="card commit-body-card">
  <div class="markdown-body"><?= markdown((string)$meta['body']) ?></div>
</div>
<?php endif; ?>

<?= partial('diff', ['diff' => $diff]) ?>
