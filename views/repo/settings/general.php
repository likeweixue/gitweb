<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'settings', 'isStarred' => $isStarred]) ?>

<div class="settings">
  <aside class="settings-nav card">
    <a href="<?= repo_url($repo, '/settings') ?>" class="settings-nav-item active"><?= icon('settings', 15) ?> 常规</a>
    <a href="<?= repo_url($repo, '/settings/collaborators') ?>" class="settings-nav-item"><?= icon('users', 15) ?> 协作者</a>
  </aside>

  <div class="settings-main">
    <section class="card">
      <div class="card-head"><h2 class="card-title">常规设置</h2></div>
      <form method="post" action="<?= repo_url($repo, '/settings/general') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="description">描述</label>
          <input class="form-control" type="text" id="description" name="description" maxlength="500"
                 value="<?= e(old('description', (string)$repo['description'])) ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="homepage">主页链接</label>
          <input class="form-control <?= error_for('homepage') ? 'is-invalid' : '' ?>" type="url" id="homepage" name="homepage"
                 value="<?= e(old('homepage', (string)$repo['homepage'])) ?>" placeholder="https://">
          <?php if (error_for('homepage')): ?><div class="form-error"><?= e(error_for('homepage')) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="default_branch">默认分支</label>
            <select class="form-control" id="default_branch" name="default_branch">
              <?php foreach ($branches as $branch): ?>
              <option value="<?= e((string)$branch['name']) ?>" <?= (string)$branch['name'] === (string)$repo['default_branch'] ? 'selected' : '' ?>>
                <?= e((string)$branch['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="visibility">可见性</label>
            <select class="form-control" id="visibility" name="visibility">
              <option value="public" <?= (int)$repo['is_private'] === 0 ? 'selected' : '' ?>>公开</option>
              <option value="private" <?= (int)$repo['is_private'] === 1 ? 'selected' : '' ?>>私有</option>
            </select>
          </div>
        </div>
        <label class="checkbox">
          <input type="checkbox" name="has_issues" value="1" <?= (int)$repo['has_issues'] === 1 ? 'checked' : '' ?>> 启用 Issues
        </label>
        <div>
          <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
      </form>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">重命名</h2></div>
      <form method="post" action="<?= repo_url($repo, '/settings/rename') ?>" class="form form-inline"
            data-confirm="重命名会改变仓库地址，确定继续？">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="new_name">新名称</label>
          <input class="form-control" type="text" id="new_name" name="new_name" maxlength="100"
                 value="<?= e((string)$repo['name']) ?>" required>
        </div>
        <button type="submit" class="btn btn-danger">重命名</button>
      </form>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">转让仓库</h2></div>
      <form method="post" action="<?= repo_url($repo, '/settings/transfer') ?>" class="form form-inline"
            data-confirm="转让后你将失去属主权限（协作者关系保留），确定继续？">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="new_owner">接收方用户名</label>
          <input class="form-control" type="text" id="new_owner" name="new_owner" maxlength="39" required placeholder="用户名">
        </div>
        <button type="submit" class="btn btn-danger">转让</button>
      </form>
    </section>

    <section class="card card-danger">
      <div class="card-head"><h2 class="card-title card-title-danger"><?= icon('alert', 16) ?> 危险区域</h2></div>
      <form method="post" action="<?= repo_url($repo, '/settings/delete') ?>" class="form"
            data-confirm="此操作将永久删除仓库及其全部代码、Issues，不可恢复！">
        <?= csrf_field() ?>
        <p class="text-muted">删除后无法恢复。如确认删除，请输入 <code class="mono"><?= e((string)$repo['owner_name']) ?>/<?= e((string)$repo['name']) ?></code>：</p>
        <div class="form-group">
          <input class="form-control" type="text" name="confirm" required placeholder="<?= e((string)$repo['owner_name']) ?>/<?= e((string)$repo['name']) ?>">
        </div>
        <button type="submit" class="btn btn-danger"><?= icon('trash', 14) ?> 删除此仓库</button>
      </form>
    </section>
  </div>
</div>
