<div class="error-page">
  <div class="error-code"><?= (int)($code ?? 500) ?></div>
  <h1 class="error-title">服务器内部错误</h1>
  <p class="text-muted"><?= e($message ?? '服务器开小差了，请稍后重试。') ?></p>
  <a class="btn btn-primary" href="<?= url('/') ?>">返回首页</a>
</div>
