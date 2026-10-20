<?php
/** 仓库页头（标题 / 可见性 / Star / Fork / 标签页）
 *  @var array $repo, $permission, $tab, $isStarred, $forkedFrom
 */
$perm = $permission ?? null;
$tabKey = $tab ?? 'code';
$forked = $forkedFrom ?? null;
$tabs = [
    'code'     => ['label' => '代码', 'url' => repo_url($repo), 'icon' => 'code'],
    'issues'   => ['label' => 'Issues', 'url' => repo_url($repo, '/issues'), 'icon' => 'issue', 'count' => (int)($repo['issues_count'] ?? 0)],
    'pulls'    => ['label' => 'Pull requests', 'url' => repo_url($repo, '/pulls'), 'icon' => 'pull-request', 'count' => \App\Model\PullRequest::openCount((int)$repo['id'])],
    'releases' => ['label' => 'Releases', 'url' => repo_url($repo, '/releases'), 'icon' => 'package'],
];
if (in_array($perm, ['owner', 'admin'], true)) {
    $tabs['settings'] = ['label' => '设置', 'url' => repo_url($repo, '/settings'), 'icon' => 'settings'];
}
?>
<div class="repo-head">
  <div class="repo-head-top">
    <div class="repo-title">
      <?= icon((int)$repo['is_private'] === 1 ? 'lock' : 'globe', 15, 'repo-visibility') ?>
      <a href="<?= url('/' . rawurlencode((string)$repo['owner_name'])) ?>"><?= e($repo['owner_name']) ?></a>
      <span class="repo-slash">/</span>
      <a href="<?= repo_url($repo) ?>" class="repo-name"><?= e($repo['name']) ?></a>
      <span class="badge <?= (int)$repo['is_private'] === 1 ? 'badge-private' : 'badge-public' ?>">
        <?= (int)$repo['is_private'] === 1 ? '私有' : '公开' ?>
      </span>
      <?php if ($forked !== null): ?>
        <span class="badge badge-fork">Fork</span>
        <span class="text-muted text-small">forked from <a href="<?= repo_url($forked) ?>"><?= e($forked['owner_name']) ?>/<?= e($forked['name']) ?></a></span>
      <?php endif; ?>
    </div>
    <div class="repo-actions">
      <form action="<?= repo_url($repo, '/star') ?>" method="post">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm <?= !empty($isStarred) ? 'btn-starred' : '' ?>">
          <?= icon('star', 14) ?> <?= !empty($isStarred) ? '已 Star' : 'Star' ?>
          <span class="counter"><?= (int)$repo['stars_count'] ?></span>
        </button>
      </form>
      <form action="<?= repo_url($repo, '/fork') ?>" method="post">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm" title="Fork 到我的账号">
          <?= icon('fork', 14) ?> Fork <span class="counter"><?= (int)$repo['forks_count'] ?></span>
        </button>
      </form>
    </div>
  </div>
  <?php if ((string)$repo['description'] !== ''): ?>
    <p class="repo-desc"><?= e($repo['description']) ?></p>
  <?php endif; ?>
  <nav class="repo-tabs">
    <?php foreach ($tabs as $key => $t): ?>
    <a href="<?= $t['url'] ?>" class="repo-tab <?= $tabKey === $key ? 'active' : '' ?>">
      <?= icon($t['icon'], 15) ?> <?= e($t['label']) ?>
      <?php if (isset($t['count']) && $t['count'] > 0): ?><span class="counter"><?= $t['count'] ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>
</div>
