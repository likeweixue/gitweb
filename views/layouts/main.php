<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? site_name()) ?></title>
<link rel="icon" href="<?= asset('/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/highlight.css') ?>">
<link rel="stylesheet" href="<?= asset('/css/splash.css') ?>">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <button class="nav-toggle" id="navToggle" type="button" aria-label="打开菜单"><?= icon('menu', 20) ?></button>
    <a class="brand" href="<?= url('/') ?>">
      <svg class="brand-mark" width="26" height="26" viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="7" fill="#ff7500"/>
        <g fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round">
          <line x1="11" y1="10" x2="11" y2="22"/>
          <path d="M11 15.5a5.5 5.5 0 0 1 5.5-5.5"/>
        </g>
        <circle cx="11" cy="8" r="2.4" fill="#ffffff"/>
        <circle cx="20.5" cy="10" r="2.4" fill="#ffffff"/>
        <circle cx="11" cy="24" r="2.4" fill="#ffffff"/>
      </svg>
      <span class="brand-name">Gitweb</span>
    </a>
    <nav class="topnav" id="topnav">
      <a href="<?= url('/explore') ?>" class="<?= active_class('/explore') ?>">探索</a>
      <?php if (is_logged_in()): ?>
        <a href="<?= url('/new') ?>" class="<?= active_class('/new') ?>">新建仓库</a>
      <?php endif; ?>
    </nav>
    <form class="searchbox" action="<?= url('/explore') ?>" method="get" role="search">
      <?= icon('search', 14) ?>
      <input type="search" name="q" value="<?= e($q ?? '') ?>" placeholder="搜索仓库 / 用户">
    </form>
    <div class="topbar-right">
      <?php if (is_logged_in()): $me = current_user(); ?>
      <div class="dropdown" data-dropdown>
        <button class="avatar-btn" type="button" aria-haspopup="true" aria-label="用户菜单">
          <?= avatar($me, 26) ?><?= icon('chevron-down', 13) ?>
        </button>
        <div class="dropdown-menu dropdown-menu-right">
          <div class="dropdown-header"><?= e($me['display_name'] !== '' ? $me['display_name'] : $me['username']) ?> <small>@<?= e($me['username']) ?></small></div>
          <a href="<?= url('/' . rawurlencode((string)$me['username'])) ?>"><?= icon('user', 15) ?> 个人主页</a>
          <a href="<?= url('/settings') ?>"><?= icon('settings', 15) ?> 设置</a>
          <?php if (is_admin()): ?>
            <a href="<?= url('/admin') ?>"><?= icon('shield', 15) ?> 管理后台</a>
          <?php endif; ?>
          <div class="dropdown-divider"></div>
          <form action="<?= url('/logout') ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="dropdown-item dropdown-item-danger"><?= icon('logout', 15) ?> 退出登录</button>
          </form>
        </div>
      </div>
      <?php else: ?>
      <a class="btn btn-sm" href="<?= url('/login') ?>">登录</a>
      <a class="btn btn-primary btn-sm" href="<?= url('/register') ?>">注册</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="container">
  <?= partial('flash') ?>
  <?= $content ?>
</main>

<footer class="footer">
  <div class="footer-inner">
    <span>Powered by <strong>Gitweb</strong> <?= defined('GW_VERSION') ? GW_VERSION : '' ?></span>
    <span class="footer-sep">·</span>
    <span>Built by WorkBuddy × likeweixue</span>
    <span class="footer-sep">·</span>
    <span class="mono-foot">POWERED BY <b>WORKBUDDY</b></span>
    <span class="footer-sep">·</span>
    <a href="<?= url('/explore') ?>">探索</a>
  </div>
</footer>

<?= partial('splash') ?>

<script src="<?= asset('/js/app.js') ?>" defer></script>
<script src="<?= asset('/js/splash.js') ?>" defer></script>
</body>
</html>
