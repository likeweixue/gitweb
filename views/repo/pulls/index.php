<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'pulls', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <h2 class="page-subtitle"><?= icon('pull-request', 16) ?> Pull requests</h2>
  <?php if ($permission === 'owner' || $permission === 'admin' || $permission === 'write'): ?>
  <a class="btn btn-primary btn-sm" href="<?= repo_url($repo, '/pulls/new') ?>"><?= icon('pull-request', 14) ?> 新建 Pull request</a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="list-tabs">
    <a href="<?= repo_url($repo, '/pulls?state=open') ?>" class="list-tab <?= $state === 'open' ? 'active' : '' ?>"><?= icon('pull-request', 14) ?> 开放 <span class="counter"><?= (int)$openCount ?></span></a>
    <a href="<?= repo_url($repo, '/pulls?state=closed') ?>" class="list-tab <?= $state === 'closed' ? 'active' : '' ?>"><?= icon('check', 14) ?> 已关闭 <span class="counter"><?= (int)$closedCount ?></span></a>
    <a href="<?= repo_url($repo, '/pulls?state=all') ?>" class="list-tab <?= $state === 'all' ? 'active' : '' ?>">全部</a>
  </div>

  <?php if ($pulls === []): ?>
    <div class="dash-empty">
      <?= icon('pull-request', 28) ?>
      <p><?= $state === 'open' ? '还没有开放的 Pull request' : '没有符合条件的 Pull request' ?></p>
      <?php if ($state === 'open' && in_array($permission, ['owner', 'admin', 'write'], true)): ?>
      <a class="btn btn-primary" href="<?= repo_url($repo, '/pulls/new') ?>">发起第一个 PR</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <ul class="pr-list">
      <?php foreach ($pulls as $pr): ?>
      <li class="pr-item">
        <span class="pr-state-icon">
          <?php if ((string)$pr['state'] === 'open'): ?><?= icon('pull-request', 16, 'icon-open') ?>
          <?php elseif ((string)$pr['state'] === 'merged'): ?><?= icon('git-merge', 16, 'icon-merged') ?>
          <?php else: ?><?= icon('x-circle', 16, 'icon-closed') ?><?php endif; ?>
        </span>
        <div class="pr-main">
          <a class="pr-title" href="<?= repo_url($repo, '/pulls/' . (int)$pr['number']) ?>"><?= e((string)$pr['title']) ?></a>
          <div class="pr-meta text-muted text-small">
            #<?= (int)$pr['number'] ?> 由 <strong><?= e((string)$pr['author_name']) ?></strong> 发起 · <?= time_ago((string)$pr['created_at']) ?>
            <span class="mono"><?= e((string)$pr['head_ref']) ?> → <?= e((string)$pr['base_ref']) ?></span>
          </div>
        </div>
        <span class="badge <?= (string)$pr['state'] === 'open' ? 'badge-open' : ((string)$pr['state'] === 'merged' ? 'badge-merged' : 'badge-closed') ?>">
          <?= (string)$pr['state'] === 'open' ? '开放中' : ((string)$pr['state'] === 'merged' ? '已合并' : '已关闭') ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php if ((int)$total > (int)$per): ?>
<div class="list-pager"><?= paginate((int)$total, (int)$page, (int)$per, repo_url($repo, '/pulls?state=' . e($state))) ?></div>
<?php endif; ?>
