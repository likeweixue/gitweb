<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'releases', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <h2 class="page-subtitle"><?= icon('package', 16) ?> Releases</h2>
</div>

<?php if ($latest !== null): ?>
<div class="card release-latest">
  <div class="release-latest-tag">
    <?= icon('tag', 15) ?> <strong><?= e((string)$latest['tag_name']) ?></strong>
    <span class="badge badge-latest">Latest</span>
  </div>
  <h3 class="release-title"><?= e((string)$latest['name']) ?></h3>
  <p class="text-muted text-small">
    <?= e((string)$latest['author_name']) ?> 发布于 <?= fmt_date((string)$latest['created_at']) ?>
  </p>
  <?php if ((string)$latest['body'] !== ''): ?>
  <div class="markdown-body release-body"><?= markdown((string)$latest['body']) ?></div>
  <?php endif; ?>
  <div class="release-assets">
    <a class="btn btn-sm" href="<?= repo_url($repo, '/archive?ref=' . rawurlencode((string)$latest['tag_name']) . '&format=zip') ?>">
      <?= icon('download', 13) ?> Source code (zip)
    </a>
    <a class="btn btn-sm" href="<?= repo_url($repo, '/archive?ref=' . rawurlencode((string)$latest['tag_name']) . '&format=tar.gz') ?>">
      <?= icon('download', 13) ?> tar.gz
    </a>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-head"><h3 class="card-title">全部 Releases <span class="counter"><?= count($releases) ?></span></h3></div>
  <?php if ($releases === []): ?>
    <?php partial('empty', ['icon' => 'package', 'message' => '暂无 Release，先从下方创建一个']) ?>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>版本</th><th>说明</th><th>发布者</th><th>时间</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($releases as $release): ?>
      <tr>
        <td>
          <a href="<?= repo_url($repo, '/tree/' . rawurlencode((string)$release['tag_name'])) ?>"><strong class="mono"><?= e((string)$release['tag_name']) ?></strong></a>
          <?php if ((int)$release['is_prerelease'] === 1): ?><span class="badge badge-pre">Pre-release</span><?php endif; ?>
        </td>
        <td class="text-muted"><?= e(str_limit((string)$release['name'], 50)) ?></td>
        <td class="text-muted text-small"><?= e((string)$release['author_name']) ?></td>
        <td class="text-muted text-small"><?= time_ago((string)$release['created_at']) ?></td>
        <td class="text-right">
          <?php if (in_array($permission, ['owner', 'admin', 'write'], true)): ?>
          <form method="post" action="<?= repo_url($repo, '/releases/' . (int)$release['id'] . '/delete') ?>" class="inline-form"
                data-confirm="确定删除该 Release？">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash', 13) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php if (in_array($permission, ['owner', 'admin', 'write'], true) && $unreleasedTags !== []): ?>
<div class="card">
  <div class="card-head"><h3 class="card-title">创建 Release</h3></div>
  <form method="post" action="<?= repo_url($repo, '/releases') ?>" class="form">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="tag_name">选择标签</label>
        <select class="form-control" id="tag_name" name="tag_name" required>
          <?php foreach ($unreleasedTags as $tag): ?>
          <option value="<?= e((string)$tag['name']) ?>"><?= e((string)$tag['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">仅显示尚未创建 Release 的标签；通过 <code>git tag v1.0.0 && git push --tags</code> 创建新标签。</div>
      </div>
      <div class="form-group">
        <label class="form-label" for="name">标题</label>
        <input class="form-control" type="text" id="name" name="name" maxlength="150" placeholder="默认与标签同名">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label" for="body">发布说明（Markdown）</label>
      <textarea class="form-control" id="body" name="body" rows="6" placeholder="## 更新内容&#10;- 新功能 A&#10;- 修复 B"></textarea>
    </div>
    <label class="checkbox">
      <input type="checkbox" name="prerelease" value="1"> 标记为 Pre-release（预发布）
    </label>
    <div>
      <button type="submit" class="btn btn-primary">发布 Release</button>
    </div>
  </form>
</div>
<?php endif; ?>
