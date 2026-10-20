<div class="settings">
  <aside class="settings-nav card">
    <a href="<?= url('/settings') ?>" class="settings-nav-item active"><?= icon('user', 15) ?> 基本资料</a>
    <a href="<?= url('/settings/ssh') ?>" class="settings-nav-item"><?= icon('key', 15) ?> SSH 公钥</a>
    <a href="<?= url('/settings/tokens') ?>" class="settings-nav-item"><?= icon('shield', 15) ?> 访问令牌</a>
  </aside>

  <div class="settings-main">
    <section class="card">
      <div class="card-head"><h2 class="card-title">基本资料</h2></div>
      <form method="post" action="<?= url('/settings/profile') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="display_name">昵称</label>
          <input class="form-control <?= error_for('display_name') ? 'is-invalid' : '' ?>" type="text" id="display_name"
                 name="display_name" value="<?= e(old('display_name', (string)$user['display_name'])) ?>" maxlength="80">
          <?php if (error_for('display_name')): ?><div class="form-error"><?= e(error_for('display_name')) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label" for="bio">简介</label>
          <textarea class="form-control" id="bio" name="bio" rows="3" maxlength="500"><?= e(old('bio', (string)$user['bio'])) ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="location">所在地</label>
            <input class="form-control" type="text" id="location" name="location" value="<?= e(old('location', (string)$user['location'])) ?>" maxlength="80">
          </div>
          <div class="form-group">
            <label class="form-label" for="website">网站</label>
            <input class="form-control <?= error_for('website') ? 'is-invalid' : '' ?>" type="url" id="website" name="website"
                   value="<?= e(old('website', (string)$user['website'])) ?>" placeholder="https://">
            <?php if (error_for('website')): ?><div class="form-error"><?= e(error_for('website')) ?></div><?php endif; ?>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">保存资料</button>
      </form>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">修改密码</h2></div>
      <form method="post" action="<?= url('/settings/password') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="current_password">当前密码</label>
          <input class="form-control <?= error_for('current_password') ? 'is-invalid' : '' ?>" type="password" id="current_password"
                 name="current_password" required autocomplete="current-password">
          <?php if (error_for('current_password')): ?><div class="form-error"><?= e(error_for('current_password')) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="new_password">新密码</label>
            <input class="form-control <?= error_for('new_password') ? 'is-invalid' : '' ?>" type="password" id="new_password"
                   name="new_password" required autocomplete="new-password" placeholder="至少 8 位">
            <?php if (error_for('new_password')): ?><div class="form-error"><?= e(error_for('new_password')) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="new_password_confirmation">确认新密码</label>
            <input class="form-control" type="password" id="new_password_confirmation" name="new_password_confirmation"
                   required autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">修改密码</button>
      </form>
    </section>

    <section class="card">
      <div class="card-head"><h2 class="card-title">账号信息</h2></div>
      <table class="table">
        <tr><th>用户名</th><td><?= e($user['username']) ?></td></tr>
        <tr><th>邮箱</th><td><?= e($user['email']) ?></td></tr>
        <tr><th>角色</th><td><?= $user['role'] === 'admin' ? '管理员' : '普通用户' ?></td></tr>
        <tr><th>注册时间</th><td><?= fmt_date((string)$user['created_at']) ?></td></tr>
      </table>
    </section>
  </div>
</div>
