<h1 class="auth-title">登录</h1>
<p class="auth-sub">欢迎回到 <?= e(site_name()) ?></p>

<form method="post" action="<?= url('/login') ?>" class="form">
  <?= csrf_field() ?>
  <div class="form-group">
    <label class="form-label" for="username">用户名 / 邮箱</label>
    <input class="form-control <?= error_for('username') ? 'is-invalid' : '' ?>" type="text" id="username" name="username"
           value="<?= e(old('username')) ?>" required autofocus autocomplete="username">
    <?php if (error_for('username')): ?><div class="form-error"><?= e(error_for('username')) ?></div><?php endif; ?>
  </div>
  <div class="form-group">
    <label class="form-label" for="password">密码</label>
    <input class="form-control <?= error_for('password') ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
           required autocomplete="current-password">
    <?php if (error_for('password')): ?><div class="form-error"><?= e(error_for('password')) ?></div><?php endif; ?>
  </div>
  <label class="checkbox">
    <input type="checkbox" name="remember" value="1" <?= old('remember') === '1' ? 'checked' : '' ?>> 记住我（30 天）
  </label>
  <button type="submit" class="btn btn-primary btn-block">登录</button>
</form>

<p class="auth-alt">还没有账号？<a href="<?= url('/register') ?>">立即注册</a></p>
