<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'settings', 'isStarred' => $isStarred]) ?>

<div class="settings">
  <aside class="settings-nav card">
    <a href="<?= repo_url($repo, '/settings') ?>" class="settings-nav-item"><?= icon('settings', 15) ?> 常规</a>
    <a href="<?= repo_url($repo, '/settings/collaborators') ?>" class="settings-nav-item active"><?= icon('users', 15) ?> 协作者</a>
  </aside>

  <div class="settings-main">
    <section class="card">
      <div class="card-head"><h2 class="card-title">协作者 <span class="counter"><?= count($collaborators) ?></span></h2></div>
      <p class="text-muted settings-intro">
        协作者可以按权限访问该仓库：<span class="badge">read</span> 读取 ·
        <span class="badge">write</span> 推送 · <span class="badge">admin</span> 管理（不含删除/转让）。
      </p>
      <?php if ($collaborators === []): ?>
        <?php partial('empty', ['icon' => 'users', 'message' => '还没有协作者']) ?>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>用户</th><th>权限</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($collaborators as $collab): ?>
            <tr>
              <td>
                <?= avatar($collab, 24) ?>
                <a href="<?= url('/' . rawurlencode((string)$collab['username'])) ?>"><?= e((string)$collab['username']) ?></a>
              </td>
              <td><span class="badge"><?= e((string)$collab['permission']) ?></span></td>
              <td class="text-right">
                <form method="post" action="<?= repo_url($repo, '/settings/collaborators/remove') ?>" class="inline-form"
                      data-confirm="确定移除该协作者？">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$collab['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash', 13) ?> 移除</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">添加协作者</h2></div>
      <form method="post" action="<?= repo_url($repo, '/settings/collaborators') ?>" class="form form-inline">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="username">用户名</label>
          <input class="form-control" type="text" id="username" name="username" maxlength="39" required placeholder="对方用户名">
        </div>
        <div class="form-group">
          <label class="form-label" for="permission">权限</label>
          <select class="form-control" id="permission" name="permission">
            <option value="read">read（读取）</option>
            <option value="write">write（推送）</option>
            <option value="admin">admin（管理）</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">添加</button>
      </form>
    </section>
  </div>
</div>
