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
  <?php partial('ref-select', ['branches' => $branches, 'tags' => $tags, 'defaultRef' => $defaultRef, 'repo' => $repo, 'current' => $ref, 'switchBase' => repo_url($repo, '/tree/')]) ?>
</div>

<?php if ($lastCommit !== null): ?>
<div class="latest-commit latest-commit-slim">
  <div class="latest-commit-main">
    <?= avatar(['username' => (string)$lastCommit['author_name']], 18) ?>
    <strong><?= e((string)$lastCommit['author_name']) ?></strong>
    <a class="commit-msg" href="<?= repo_url($repo, '/commit/' . $lastCommit['hash']) ?>"><?= e(str_limit((string)$lastCommit['subject'], 72)) ?></a>
  </div>
  <span class="text-muted text-small"><?= time_ago((string)$lastCommit['date']) ?></span>
</div>
<?php endif; ?>

<table class="file-table card">
  <tbody>
    <?php if ($path !== ''): ?>
    <tr>
      <td class="file-name" colspan="3">
        <?php $parentPath = dirname($path); $parentUrl = $parentPath === '.' ? repo_url($repo, '/tree/' . rawurlencode((string)$ref)) : repo_url($repo, '/tree/' . rawurlencode((string)$ref) . '/' . implode('/', array_map('rawurlencode', explode('/', $parentPath)))); ?>
        <a href="<?= $parentUrl ?>"><?= icon('arrow-left', 14) ?> ..</a>
      </td>
    </tr>
    <?php endif; ?>
    <?php foreach ($entries as $entry): ?>
    <?php $entryCommit = App\Lib\Git::lastCommitForPath($diskPath, (string)$ref, ($path !== '' ? $path . '/' : '') . (string)$entry['name']); ?>
    <tr>
      <td class="file-name">
        <?php if ($entry['type'] === 'tree'): ?>
          <?= icon('folder', 15, 'icon-folder') ?>
          <a href="<?= repo_url($repo, '/tree/' . rawurlencode((string)$ref) . '/' . rawurlencode(($path !== '' ? $path . '/' : '') . (string)$entry['name'])) ?>"><?= e($entry['name']) ?></a>
        <?php else: ?>
          <?= icon('file', 15, 'icon-file') ?>
          <a href="<?= repo_url($repo, '/blob/' . rawurlencode((string)$ref) . '/' . rawurlencode(($path !== '' ? $path . '/' : '') . (string)$entry['name'])) ?>"><?= e($entry['name']) ?></a>
        <?php endif; ?>
      </td>
      <td class="file-commit">
        <?php if ($entryCommit !== null): ?>
          <a class="text-muted" href="<?= repo_url($repo, '/commit/' . $entryCommit['hash']) ?>"><?= e(str_limit((string)$entryCommit['subject'], 60)) ?></a>
        <?php endif; ?>
      </td>
      <td class="file-size text-muted text-right text-small">
        <?= $entry['size'] !== null ? human_size($entry['size']) : '' ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
