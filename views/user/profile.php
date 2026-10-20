<div class="profile">
  <div class="profile-card card">
    <div class="profile-head">
      <?= avatar($user, 72) ?>
      <div class="profile-info">
        <h1 class="profile-name"><?= e($user['display_name'] !== '' ? $user['display_name'] : $user['username']) ?></h1>
        <p class="profile-login text-muted">@<?= e($user['username']) ?></p>
        <?php if ((string)$user['bio'] !== ''): ?><p class="profile-bio"><?= e($user['bio']) ?></p><?php endif; ?>
        <p class="profile-meta text-muted text-small">
          <?php if ((string)$user['location'] !== ''): ?><span><?= icon('globe', 13) ?> <?= e($user['location']) ?></span><?php endif; ?>
          <?php if ((string)$user['website'] !== ''): ?><span><a href="<?= e($user['website']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('link', 13) ?> <?= e($user['website']) ?></a></span><?php endif; ?>
          <span><?= icon('clock', 13) ?> 加入于 <?= fmt_date((string)$user['created_at'], 'Y-m-d') ?></span>
        </p>
      </div>
      <?php if (!empty($isSelf)): ?>
      <a class="btn btn-sm" href="<?= url('/settings') ?>"><?= icon('edit', 14) ?> 编辑资料</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="tabs profile-tabs">
    <a href="<?= url('/' . rawurlencode((string)$user['username'])) ?>" class="tab <?= $tab === 'repos' ? 'active' : '' ?>">仓库 <span class="counter"><?= (int)$repoCount ?></span></a>
    <a href="<?= url('/' . rawurlencode((string)$user['username']) . '?tab=stars') ?>" class="tab <?= $tab === 'stars' ? 'active' : '' ?>">Star</a>
  </div>

  <?php if ($repos === []): ?>
    <?php partial('empty', ['icon' => 'repo', 'message' => $tab === 'stars' ? '还没有 Star 任何仓库' : '还没有仓库']) ?>
  <?php else: ?>
    <div class="repo-grid">
      <?php foreach ($repos as $repo): ?>
        <?php partial('repo-card', ['repo' => $repo]) ?>
      <?php endforeach; ?>
    </div>
    <?= paginate($total, $page, 20, url('/' . rawurlencode((string)$user['username']) . ($tab === 'stars' ? '?tab=stars' : ''))) ?>
  <?php endif; ?>
</div>
