<?php
/** @var array $flashes */
$flashes = \App\Core\Session::takeFlashes();
?>
<?php foreach ($flashes as $flash): ?>
<div class="flash flash-<?= e($flash['type']) ?>" data-flash>
  <span class="flash-icon">
    <?= icon($flash['type'] === 'error' ? 'alert' : ($flash['type'] === 'success' ? 'check' : 'info'), 16) ?>
  </span>
  <span class="flash-text"><?= e($flash['message']) ?></span>
  <button class="flash-close" type="button" data-flash-close aria-label="关闭"><?= icon('x', 13) ?></button>
</div>
<?php endforeach; ?>
