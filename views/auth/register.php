<h1 class="auth-title">注册</h1>
<p class="auth-sub">加入 <?= e(site_name()) ?>，开始托管你的代码</p>

<form method="post" action="<?= url('/register') ?>" class="form">
  <?= csrf_field() ?>
  <div class="form-group">
    <label class="form-label" for="username">用户名</label>
    <input class="form-control <?= error_for('username') ? 'is-invalid' : '' ?>" type="text" id="username" name="username"
           value="<?= e(old('username')) ?>" required autofocus autocomplete="username" placeholder="字母、数字、连字符，1-39 字符">
    <?php if (error_for('username')): ?><div class="form-error"><?= e(error_for('username')) ?></div><?php endif; ?>
  </div>
  <div class="form-group">
    <label class="form-label" for="email">邮箱</label>
    <input class="form-control <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
           value="<?= e(old('email')) ?>" required autocomplete="email">
    <?php if (error_for('email')): ?><div class="form-error"><?= e(error_for('email')) ?></div><?php endif; ?>
  </div>
  <div class="form-group">
    <label class="form-label" for="password">密码</label>
    <input class="form-control <?= error_for('password') ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
           required autocomplete="new-password" placeholder="至少 8 位">
    <?php if (error_for('password')): ?><div class="form-error"><?= e(error_for('password')) ?></div><?php endif; ?>
  </div>
  <div class="form-group">
    <label class="form-label" for="password_confirmation">确认密码</label>
    <input class="form-control" type="password" id="password_confirmation" name="password_confirmation"
           required autocomplete="new-password">
  </div>
  <button type="submit" class="btn btn-primary btn-block">注册</button>
</form>

<p class="auth-alt">已有账号？<a href="<?= url('/login') ?>">直接登录</a></p>
