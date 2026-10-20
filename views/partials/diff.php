<?php
/** 差异视图
 *  @var array $diff
 */
$statusLabels = ['added' => '新增', 'deleted' => '删除', 'renamed' => '重命名', 'modified' => '修改'];
?>
<div class="diff-box">
  <div class="diff-summary">
    <span><strong><?= count($diff['files']) ?></strong> 个文件</span>
    <span class="diff-stat-add">+<?= (int)$diff['additions'] ?></span>
    <span class="diff-stat-del">−<?= (int)$diff['deletions'] ?></span>
  </div>
  <?php foreach ($diff['files'] as $file): ?>
  <div class="diff-file">
    <div class="diff-file-head">
      <?= icon('file', 14) ?>
      <span class="diff-file-path"><?= e($file['display_path']) ?></span>
      <span class="diff-status diff-status-<?= e($file['status']) ?>"><?= e($statusLabels[$file['status']] ?? '修改') ?></span>
    </div>
    <?php if (!empty($file['binary'])): ?>
      <div class="diff-binary">二进制文件不显示差异</div>
    <?php else: ?>
      <?php foreach ($file['hunks'] as $hunk): ?>
      <div class="diff-hunk">
        <div class="diff-hunk-header"><?= e($hunk['header']) ?></div>
        <table class="diff-table">
          <tbody>
            <?php foreach ($hunk['lines'] as $line): ?>
            <?php
            $cls = $line['type'] === '+' ? 'add' : ($line['type'] === '-' ? 'del' : ($line['type'] === '\\' ? 'meta' : 'ctx'));
            ?>
            <tr class="diff-line diff-line-<?= $cls ?>">
              <td class="diff-sign"><?= $line['type'] === '\\' ? '' : e($line['type']) ?></td>
              <td class="diff-code"><pre><?= e($line['content']) ?></pre></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if ($diff['files'] === []): ?>
    <div class="diff-empty">没有可显示的差异</div>
  <?php endif; ?>
</div>
