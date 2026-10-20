<div class="page-head">
  <h1 class="page-title">站点设置</h1>
</div>

<div class="card form-card">
  <form method="post" action="<?= url('/admin/settings') ?>" class="form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label" for="site_name">站点名称</label>
      <input class="form-control <?= error_for('site_name') ? 'is-invalid' : '' ?>" type="text" id="site_name" name="site_name"
             value="<?= e(old('site_name', (string)($settings['site_name'] ?? 'Gitweb'))) ?>" required maxlength="60">
      <?php if (error_for('site_name')): ?><div class="form-error"><?= e(error_for('site_name')) ?></div><?php endif; ?>
    </div>
    <div class="form-group">
      <label class="form-label" for="site_description">站点描述</label>
      <input class="form-control" type="text" id="site_description" name="site_description" maxlength="200"
             value="<?= e(old('site_description', (string)($settings['site_description'] ?? ''))) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">开放注册</label>
      <label class="radio-card">
        <input type="radio" name="allow_register" value="1" <?= (string)($settings['allow_register'] ?? '1') === '1' ? 'checked' : '' ?>>
        <span class="radio-card-body"><strong>允许</strong><span class="text-muted text-small">任何人都可以注册账号</span></span>
      </label>
      <label class="radio-card">
        <input type="radio" name="allow_register" value="0" <?= (string)($settings['allow_register'] ?? '1') === '0' ? 'checked' : '' ?>>
        <span class="radio-card-body"><strong>关闭</strong><span class="text-muted text-small">仅管理员可在后台创建账号</span></span>
      </label>
    </div>
    <div>
      <button type="submit" class="btn btn-primary">保存设置</button>
    </div>
  </form>
</div>
