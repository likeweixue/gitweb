<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'issues', 'isStarred' => $isStarred]) ?>

<div class="page-head">
  <h1 class="page-title">新建 Issue</h1>
</div>

<div class="card form-card">
  <form method="post" action="<?= repo_url($repo, '/issues') ?>" class="form">
    <?= csrf_field() ?>
    <div class="form-group">
      <label class="form-label" for="title">标题 <span class="text-muted">*</span></label>
      <input class="form-control <?= error_for('title') ? 'is-invalid' : '' ?>" type="text" id="title" name="title"
             value="<?= e(old('title')) ?>" required maxlength="255" autofocus>
      <?php if (error_for('title')): ?><div class="form-error"><?= e(error_for('title')) ?></div><?php endif; ?>
    </div>
    <div class="form-group">
      <label class="form-label" for="body">内容（支持 Markdown）</label>
      <textarea class="form-control" id="body" name="body" rows="10" placeholder="描述问题、复现步骤、期望行为…"><?= e(old('body')) ?></textarea>
    </div>
    <?php if ($labels !== []): ?>
    <div class="form-group">
      <label class="form-label">标签</label>
      <div class="label-picker">
        <?php foreach ($labels as $label): ?>
        <label class="label-option">
          <input type="checkbox" name="labels[]" value="<?= (int)$label['id'] ?>"
                 <?= in_array((int)$label['id'], array_map('intval', (array)old('labels', [])), true) ? 'checked' : '' ?>>
          <span class="label" style="--label-color: <?= e((string)$label['color']) ?>"><?= e((string)$label['name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary btn-lg">提交 Issue</button>
      <a class="btn btn-lg" href="<?= repo_url($repo, '/issues') ?>">取消</a>
    </div>
  </form>
</div>
