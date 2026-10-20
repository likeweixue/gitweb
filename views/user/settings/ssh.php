<div class="settings">
  <aside class="settings-nav card">
    <a href="<?= url('/settings') ?>" class="settings-nav-item"><?= icon('user', 15) ?> 基本资料</a>
    <a href="<?= url('/settings/ssh') ?>" class="settings-nav-item active"><?= icon('key', 15) ?> SSH 公钥</a>
    <a href="<?= url('/settings/tokens') ?>" class="settings-nav-item"><?= icon('shield', 15) ?> 访问令牌</a>
  </aside>

  <div class="settings-main">
    <section class="card">
      <div class="card-head">
        <h2 class="card-title">SSH 公钥</h2>
      </div>
      <p class="text-muted settings-intro">
        添加 SSH 公钥后，即可通过 <code>git@<?= e((string)config('git.ssh_host')) ?>:用户名/仓库名.git</code> 执行 clone / push。
        本地生成密钥：<code>ssh-keygen -t ed25519</code>，公钥内容位于 <code>~/.ssh/id_ed25519.pub</code>。
      </p>

      <?php if ($keys === []): ?>
        <?php partial('empty', ['icon' => 'key', 'message' => '还没有添加 SSH 公钥']) ?>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr><th>标题</th><th>指纹</th><th>最近使用</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($keys as $key): ?>
            <tr>
              <td><?= e($key['title'] !== '' ? $key['title'] : '未命名') ?></td>
              <td class="text-muted text-small mono"><?= e($key['fingerprint']) ?></td>
              <td class="text-muted text-small"><?= time_ago($key['last_used_at']) ?></td>
              <td class="text-right">
                <form method="post" action="<?= url('/settings/ssh/delete') ?>" class="inline-form"
                      data-confirm="确定删除该 SSH 公钥？">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$key['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-danger"><?= icon('trash', 13) ?> 删除</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">添加公钥</h2></div>
      <form method="post" action="<?= url('/settings/ssh') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="title">标题</label>
          <input class="form-control" type="text" id="title" name="title" maxlength="120" placeholder="例如：MacBook Pro">
        </div>
        <div class="form-group">
          <label class="form-label" for="public_key">公钥内容</label>
          <textarea class="form-control mono" id="public_key" name="public_key" rows="3" required
                    placeholder="ssh-ed25519 AAAA... user@host"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">添加公钥</button>
      </form>
    </section>
  </div>
</div>
