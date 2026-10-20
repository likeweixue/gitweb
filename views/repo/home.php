<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'code', 'forkedFrom' => $forkedFrom, 'isStarred' => $isStarred]) ?>

<?php if ($empty): ?>
<div class="card empty-repo">
  <div class="empty-icon"><?= icon('repo', 30) ?></div>
  <h3>这是一个空仓库</h3>
  <p class="text-muted">通过以下命令推送你的代码：</p>
  <pre class="clone-block"><code>git remote add origin <?= e($cloneUrls['https']) ?>
git push -u origin main</code></pre>
  <?php if ($permission === 'owner'): ?>
  <p class="text-muted text-small">想初始化 README？删除后重新创建并勾选“初始化 README”即可。</p>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="clone-bar card">
  <div class="clone-tabs">
    <button type="button" class="clone-tab active" data-clone-tab="https">HTTPS</button>
    <button type="button" class="clone-tab" data-clone-tab="ssh">SSH</button>
  </div>
  <div class="clone-url-row">
    <input type="text" class="clone-url mono" id="cloneUrlHttps" value="<?= e($cloneUrls['https']) ?>" readonly
           aria-label="HTTPS clone 地址">
    <input type="text" class="clone-url mono" id="cloneUrlSsh" value="<?= e($cloneUrls['ssh']) ?>" readonly hidden
           aria-label="SSH clone 地址">
    <button type="button" class="btn btn-sm" id="copyCloneBtn" data-copy="<?= e($cloneUrls['https']) ?>">
      <?= icon('copy', 14) ?> 复制
    </button>
    <a class="btn btn-sm" href="<?= repo_url($repo, '/archive?ref=' . rawurlencode((string)$defaultRef)) ?>">
      <?= icon('download', 14) ?> 下载
    </a>
  </div>
  <?php if (is_logged_in()): ?>
  <p class="clone-hint text-muted text-small">
    推送时使用个人访问令牌作为密码（<a href="<?= url('/settings/tokens') ?>">获取令牌</a>），或配置 SSH 公钥（<a href="<?= url('/settings/ssh') ?>">去配置</a>）。
  </p>
  <?php endif; ?>
</div>

<?php if ($lastCommit !== null): ?>
<div class="latest-commit">
  <div class="latest-commit-main">
    <?= avatar(['username' => (string)$lastCommit['author_name']], 20) ?>
    <strong><?= e((string)$lastCommit['author_name']) ?></strong>
    <a class="commit-msg" href="<?= repo_url($repo, '/commit/' . $lastCommit['hash']) ?>"><?= e(str_limit((string)$lastCommit['subject'], 72)) ?></a>
  </div>
  <div class="latest-commit-meta">
    <a class="commit-sha mono" href="<?= repo_url($repo, '/commit/' . $lastCommit['hash']) ?>"><?= e((string)$lastCommit['short']) ?></a>
    <span class="text-muted text-small"><?= time_ago((string)$lastCommit['date']) ?></span>
    <a class="text-muted text-small" href="<?= repo_url($repo, '/commits/' . rawurlencode((string)$defaultRef)) ?>">
      <?= icon('history', 13) ?> 历史
    </a>
  </div>
</div>
<?php endif; ?>

<table class="file-table card">
  <tbody>
    <?php foreach ($entries as $entry): ?>
    <?php $entryCommit = App\Lib\Git::lastCommitForPath($diskPath, (string)$defaultRef, (string)$entry['name']); ?>
    <tr>
      <td class="file-name">
        <?php if ($entry['type'] === 'tree'): ?>
          <?= icon('folder', 15, 'icon-folder') ?>
          <a href="<?= repo_url($repo, '/tree/' . rawurlencode((string)$defaultRef) . '/' . rawurlencode((string)$entry['name'])) ?>"><?= e($entry['name']) ?></a>
        <?php else: ?>
          <?= icon('file', 15, 'icon-file') ?>
          <a href="<?= repo_url($repo, '/blob/' . rawurlencode((string)$defaultRef) . '/' . rawurlencode((string)$entry['name'])) ?>"><?= e($entry['name']) ?></a>
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

<?php if ($readme !== null): ?>
<div class="card readme-card">
  <div class="readme-head"><?= icon('book', 15) ?> <?= e($readme['name']) ?></div>
  <div class="markdown-body"><?= $readmeHtml ?></div>
</div>
<?php endif; ?>
<?php endif; ?>
