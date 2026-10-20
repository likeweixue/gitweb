<?php
/** 仓库卡片
 *  @var array $repo
 */
?>
<div class="repo-card">
  <div class="repo-card-head">
    <?= icon('repo', 15, 'repo-card-icon') ?>
    <a href="<?= repo_url($repo) ?>" class="repo-card-name">
      <?= e($repo['owner_name']) ?>/<strong><?= e($repo['name']) ?></strong>
    </a>
    <span class="badge <?= (int)$repo['is_private'] === 1 ? 'badge-private' : 'badge-public' ?>">
      <?= (int)$repo['is_private'] === 1 ? '私有' : '公开' ?>
    </span>
  </div>
  <?php if ((string)$repo['description'] !== ''): ?>
    <p class="repo-card-desc"><?= e(str_limit((string)$repo['description'], 120)) ?></p>
  <?php endif; ?>
  <div class="repo-card-meta">
    <span title="Star"><?= icon('star', 13) ?> <?= (int)$repo['stars_count'] ?></span>
    <span title="Fork"><?= icon('fork', 13) ?> <?= (int)$repo['forks_count'] ?></span>
    <span title="更新时间"><?= icon('clock', 13) ?> <?= time_ago(!empty($repo['pushed_at']) ? (string)$repo['pushed_at'] : (string)$repo['created_at']) ?></span>
  </div>
</div>
