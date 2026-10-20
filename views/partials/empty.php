<?php
/** 空状态
 *  @var string $icon, $message, $actionHtml
 */
?>
<div class="empty-state">
  <div class="empty-icon"><?= icon($icon ?? 'info', 30) ?></div>
  <p class="empty-text"><?= e($message ?? '暂无内容') ?></p>
  <?php if (!empty($actionHtml)): ?>
    <div class="empty-action"><?= $actionHtml ?></div>
  <?php endif; ?>
</div>
