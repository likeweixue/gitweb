<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'code', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <h2 class="page-subtitle"><?= icon('branch', 16) ?> 分支 <span class="counter"><?= count($branches) ?></span></h2>
</div>

<?php if ($permission === 'owner' || $permission === 'admin' || $permission === 'write'): ?>
<div class="card branch-create">
  <h3 class="card-title"><?= icon('branch', 16) ?> 新建分支</h3>
  <form method="post" action="<?= repo_url($repo, '/branches') ?>" class="branch-create-form">
    <?= csrf_field() ?>
    <input type="text" class="form-control" name="name" placeholder="新分支名，如 feature/xxx" required maxlength="250" pattern="[A-Za-z0-9._/-]+" title="仅允许字母数字 . _ / -">
    <select class="form-control" name="from" aria-label="基于分支">
      <?php foreach ($branches as $branch): ?>
      <option value="<?= e($branch['name']) ?>" <?= (string)$branch['name'] === (string)$defaultRef ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">创建</button>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <table class="table">
    <thead>
      <tr><th>分支</th><th>最新提交</th><th>更新时间</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($branches as $branch): ?>
      <tr>
        <td>
          <a href="<?= repo_url($repo, '/tree/' . rawurlencode((string)$branch['name'])) ?>">
            <?= icon('branch', 14) ?> <strong><?= e($branch['name']) ?></strong>
          </a>
          <?php if ((string)$branch['name'] === (string)$defaultRef): ?><span class="badge badge-default">默认</span><?php endif; ?>
        </td>
        <td class="text-muted text-small mono"><?= e(substr((string)$branch['sha'], 0, 7)) ?></td>
        <td class="text-muted text-small"><?= time_ago((string)$branch['date']) ?></td>
        <td class="text-right">
          <?php if ((string)$branch['name'] !== (string)$defaultRef && in_array($permission, ['owner', 'admin', 'write'], true)): ?>
            <form method="post" action="<?= repo_url($repo, '/branches/delete') ?>" class="branch-delete-form" onsubmit="return confirm('确定删除分支 <?= e($branch['name']) ?>？该操作不可恢复。');">
              <?= csrf_field() ?>
              <input type="hidden" name="branch" value="<?= e($branch['name']) ?>">
              <button type="submit" class="btn btn-sm btn-danger-outline" title="删除分支"><?= icon('trash', 13) ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($branches === []): ?>
        <tr><td colspan="4" class="dash-empty">暂无分支</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
