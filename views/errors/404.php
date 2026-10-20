<div class="error-page">
  <div class="error-code"><?= (int)($code ?? 404) ?></div>
  <h1 class="error-title"><?= e($message ?? '页面不存在') ?></h1>
  <p class="text-muted">你访问的页面不存在或已被移除。</p>
  <a class="btn btn-primary" href="<?= url('/') ?>">返回首页</a>
</div>
