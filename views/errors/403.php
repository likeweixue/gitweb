<div class="error-page">
  <div class="error-code"><?= (int)($code ?? 403) ?></div>
  <h1 class="error-title"><?= e($message ?? '没有权限') ?></h1>
  <p class="text-muted">你无权访问该资源，可能需要登录或联系仓库属主。</p>
  <a class="btn btn-primary" href="<?= url('/') ?>">返回首页</a>
</div>
