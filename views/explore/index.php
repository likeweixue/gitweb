<div class="page-head">
  <h1 class="page-title">探索</h1>
</div>

<form class="explore-search" action="<?= url('/explore') ?>" method="get">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="searchbox searchbox-lg">
    <?= icon('search', 16) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= $tab === 'users' ? '搜索用户' : '搜索仓库名称、描述、作者' ?>">
  </div>
  <button class="btn" type="submit">搜索</button>
</form>

<div class="tabs">
  <a href="<?= url('/explore?tab=repos&q=' . rawurlencode($q)) ?>" class="tab <?= $tab === 'repos' ? 'active' : '' ?>">仓库</a>
  <a href="<?= url('/explore?tab=users&q=' . rawurlencode($q)) ?>" class="tab <?= $tab === 'users' ? 'active' : '' ?>">用户</a>
  <?php if ($tab === 'repos'): ?>
  <div class="tabs-right">
    <a class="tab-sort <?= $sort === 'updated' ? 'active' : '' ?>" href="<?= url('/explore?tab=repos&sort=updated&q=' . rawurlencode($q)) ?>">最近更新</a>
    <a class="tab-sort <?= $sort === 'stars' ? 'active' : '' ?>" href="<?= url('/explore?tab=repos&sort=stars&q=' . rawurlencode($q)) ?>">最多 Star</a>
    <a class="tab-sort <?= $sort === 'created' ? 'active' : '' ?>" href="<?= url('/explore?tab=repos&sort=created&q=' . rawurlencode($q)) ?>">最新创建</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($tab === 'users'): ?>
  <?php if ($users === []): ?>
    <?php partial('empty', ['icon' => 'users', 'message' => $q !== '' ? '没有找到相关用户' : '暂无用户']) ?>
  <?php else: ?>
    <div class="user-list card">
      <?php foreach ($users as $u): ?>
      <a class="user-row" href="<?= url('/' . rawurlencode((string)$u['username'])) ?>">
        <?= avatar($u, 36) ?>
        <span class="user-row-main">
          <span class="user-row-name"><?= e($u['display_name'] !== '' ? $u['display_name'] : $u['username']) ?></span>
          <span class="user-row-login text-muted">@<?= e($u['username']) ?></span>
        </span>
        <?php if (!empty($u['bio'])): ?><span class="user-row-bio text-muted"><?= e(str_limit((string)$u['bio'], 60)) ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php else: ?>
  <?php if ($repos === []): ?>
    <?php partial('empty', ['icon' => 'repo', 'message' => $q !== '' ? '没有找到相关仓库' : '还没有公开仓库']) ?>
  <?php else: ?>
    <div class="repo-grid">
      <?php foreach ($repos as $repo): ?>
        <?php partial('repo-card', ['repo' => $repo]) ?>
      <?php endforeach; ?>
    </div>
    <?= paginate($total, $page, 20, url('/explore?tab=repos&sort=' . $sort . '&q=' . rawurlencode($q))) ?>
  <?php endif; ?>
<?php endif; ?>
