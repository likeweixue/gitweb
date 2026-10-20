<div class="page-head">
  <h1 class="page-title">新建仓库</h1>
</div>

<div class="card form-card">
  <form method="post" action="<?= url('/new') ?>" class="form">
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label" for="name">仓库名称 <span class="text-muted">*</span></label>
      <div class="input-with-prefix">
        <span class="input-prefix"><?= e(current_user()['username']) ?>/</span>
        <input class="form-control <?= error_for('name') ? 'is-invalid' : '' ?>" type="text" id="name" name="name"
               value="<?= e(old('name')) ?>" required maxlength="100" placeholder="my-project" autofocus>
      </div>
      <?php if (error_for('name')): ?><div class="form-error"><?= e(error_for('name')) ?></div><?php endif; ?>
      <div class="form-hint">只能包含字母、数字、点、下划线和连字符，且不能以点开头。</div>
    </div>

    <div class="form-group">
      <label class="form-label" for="description">描述（可选）</label>
      <input class="form-control" type="text" id="description" name="description" maxlength="500"
             value="<?= e(old('description')) ?>" placeholder="简单介绍一下这个仓库">
    </div>

    <div class="form-group">
      <label class="form-label">可见性</label>
      <label class="radio-card">
        <input type="radio" name="visibility" value="public" <?= old('visibility', 'public') === 'public' ? 'checked' : '' ?>>
        <span class="radio-card-body">
          <strong><?= icon('globe', 15) ?> 公开</strong>
          <span class="text-muted text-small">任何人都可以查看和克隆</span>
        </span>
      </label>
      <label class="radio-card">
        <input type="radio" name="visibility" value="private" <?= old('visibility') === 'private' ? 'checked' : '' ?>>
        <span class="radio-card-body">
          <strong><?= icon('lock', 15) ?> 私有</strong>
          <span class="text-muted text-small">仅你与被授权的协作者可以访问</span>
        </span>
      </label>
    </div>

    <div class="form-group">
      <label class="checkbox">
        <input type="checkbox" name="init" value="1" <?= old('init', '1') === '1' ? 'checked' : '' ?>>
        初始化仓库（创建 README.md 并生成首次提交）
      </label>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary btn-lg">创建仓库</button>
      <a class="btn btn-lg" href="<?= url('/') ?>">取消</a>
    </div>
  </form>
</div>
