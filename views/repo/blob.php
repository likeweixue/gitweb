<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'code', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <div class="breadcrumbs">
    <a href="<?= repo_url($repo) ?>"><strong><?= e($repo['name']) ?></strong></a>
    <?php foreach ($breadcrumbs as $crumb): ?>
      <span class="breadcrumb-sep">/</span>
      <?php if ($crumb['url'] !== ''): ?>
        <a href="<?= $crumb['url'] ?>"><?= e($crumb['name']) ?></a>
      <?php else: ?>
        <span class="breadcrumb-current"><?= e($crumb['name']) ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <div class="blob-actions">
    <span class="text-muted text-small"><?= human_size($size) ?> · <?= $isBinary ? '二进制' : count(explode("\n", $content)) . ' 行' ?></span>
    <a class="btn btn-sm" href="<?= repo_url($repo, '/raw/' . rawurlencode((string)$ref) . '/' . rawurlencode((string)$path)) ?>">
      <?= icon('eye', 14) ?> 原始
    </a>
    <a class="btn btn-sm" href="<?= repo_url($repo, '/archive?ref=' . rawurlencode((string)$ref)) ?>">
      <?= icon('download', 14) ?> 下载
    </a>
  </div>
</div>

<?php if ($isMarkdown): ?>
<div class="card readme-card">
  <div class="markdown-body"><?= $rendered ?></div>
</div>
<?php elseif ($isImage): ?>
<div class="card blob-image-card">
  <img src="<?= repo_url($repo, '/raw/' . rawurlencode((string)$ref) . '/' . rawurlencode((string)$path)) ?>"
       alt="<?= e($fileName) ?>" class="blob-image">
</div>
<?php elseif ($isBinary): ?>
<div class="card empty-state">
  <div class="empty-icon"><?= icon('file', 30) ?></div>
  <p class="empty-text">二进制文件（<?= human_size($size) ?>），不支持在线预览</p>
  <div class="empty-action">
    <a class="btn btn-sm" href="<?= repo_url($repo, '/raw/' . rawurlencode((string)$ref) . '/' . rawurlencode((string)$path)) ?>">下载文件</a>
  </div>
</div>
<?php elseif ($lines === []): ?>
<div class="card blob-card">
  <div class="blob-content"><pre><?= e($content) ?></pre></div>
</div>
<?php else: ?>
<div class="card blob-card">
  <table class="blob-table">
    <tbody>
      <?php foreach ($lines as $index => $lineHtml): ?>
      <tr>
        <td class="blob-ln"><?= $index + 1 ?></td>
        <td class="blob-code"><pre><?= $lineHtml ?></pre></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
