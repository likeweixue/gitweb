<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '登录') ?> · <?= e(site_name()) ?></title>
<link rel="icon" href="<?= asset('/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('/css/app.css') ?>">
</head>
<body class="auth-body">
<main class="auth-wrap">
  <a class="brand auth-brand" href="<?= url('/') ?>">
    <svg class="brand-mark" width="30" height="30" viewBox="0 0 32 32" aria-hidden="true">
      <rect width="32" height="32" rx="7" fill="#0969da"/>
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
  <div class="card auth-card">
    <?= partial('flash') ?>
    <?= $content ?>
  </div>
  <p class="auth-foot">Powered by Gitweb · Built by WorkBuddy × likeweixue</p>
</main>
</body>
</html>
