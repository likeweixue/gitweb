<div class="settings">
  <aside class="settings-nav card">
    <a href="<?= url('/settings') ?>" class="settings-nav-item"><?= icon('user', 15) ?> 基本资料</a>
    <a href="<?= url('/settings/ssh') ?>" class="settings-nav-item"><?= icon('key', 15) ?> SSH 公钥</a>
    <a href="<?= url('/settings/tokens') ?>" class="settings-nav-item active"><?= icon('shield', 15) ?> 访问令牌</a>
  </aside>

  <div class="settings-main">
    <?php if (!empty($newToken)): ?>
    <section class="card card-highlight">
      <div class="card-head"><h2 class="card-title"><?= icon('alert', 16) ?> 新令牌（仅显示一次）</h2></div>
      <div class="token-reveal">
        <code class="mono token-value"><?= e($newToken) ?></code>
        <button class="btn btn-sm" type="button" data-copy="<?= e($newToken) ?>"><?= icon('copy', 13) ?> 复制</button>
      </div>
      <p class="text-muted text-small">请立即复制并妥善保存，刷新页面后将无法再次查看。</p>
    </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head">
        <h2 class="card-title">访问令牌</h2>
      </div>
      <p class="text-muted settings-intro">
        访问令牌用于 HTTPS 协议的 Git 操作：将令牌作为密码使用
        （<code>git clone https://用户名:令牌@<?= e((string)config('git.ssh_host')) ?>/用户名/仓库名.git</code>）。
      </p>

      <?php if ($tokens === []): ?>
        <?php partial('empty', ['icon' => 'shield', 'message' => '还没有创建访问令牌']) ?>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr><th>名称</th><th>权限</th><th>最近使用</th><th>创建时间</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($tokens as $token): ?>
            <tr>
              <td><?= e($token['name']) ?></td>
              <td><span class="badge"><?= e($token['scopes']) ?></span></td>
              <td class="text-muted text-small"><?= time_ago($token['last_used_at']) ?></td>
              <td class="text-muted text-small"><?= fmt_date((string)$token['created_at']) ?></td>
              <td class="text-right">
                <form method="post" action="<?= url('/settings/tokens/delete') ?>" class="inline-form"
                      data-confirm="确定吊销该令牌？使用该令牌的客户端将立即失效。">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$token['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash', 13) ?> 吊销</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">创建令牌</h2></div>
      <form method="post" action="<?= url('/settings/tokens') ?>" class="form form-inline">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="name">令牌名称</label>
          <input class="form-control" type="text" id="name" name="name" maxlength="120" placeholder="例如：CI 构建机" required>
        </div>
        <button type="submit" class="btn btn-primary">创建令牌</button>
      </form>
    </section>
  </div>
</div>
