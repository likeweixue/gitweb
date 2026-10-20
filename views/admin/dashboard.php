<div class="page-head">
  <h1 class="page-title"><?= icon('shield', 20) ?> 管理后台</h1>
</div>

<div class="stat-grid">
  <div class="card stat-card">
    <div class="stat-icon"><?= icon('users', 20) ?></div>
    <div class="stat-num"><?= (int)$userCount ?></div>
    <div class="stat-label">注册用户</div>
  </div>
  <div class="card stat-card">
    <div class="stat-icon"><?= icon('repo', 20) ?></div>
    <div class="stat-num"><?= (int)$repoCount ?></div>
    <div class="stat-label">仓库总数（公开 <?= (int)$publicRepoCount ?>）</div>
  </div>
  <div class="card stat-card">
    <div class="stat-icon"><?= icon('database', 20) ?></div>
    <div class="stat-num"><?= human_size($diskUsage) ?></div>
    <div class="stat-label">Git 数据占用</div>
  </div>
</div>

<div class="admin-quick">
  <a class="card admin-quick-card" href="<?= url('/admin/users') ?>">
    <?= icon('users', 18) ?> 用户管理
    <span class="text-muted text-small">封禁 / 解封 / 管理员</span>
  </a>
  <a class="card admin-quick-card" href="<?= url('/admin/repositories') ?>">
    <?= icon('repo', 18) ?> 仓库管理
    <span class="text-muted text-small">可见性 / 删除</span>
  </a>
  <a class="card admin-quick-card" href="<?= url('/admin/settings') ?>">
    <?= icon('settings', 18) ?> 站点设置
    <span class="text-muted text-small">名称 / 注册开关</span>
  </a>
</div>
