<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'pulls', 'isStarred' => $isStarred]) ?>

<div class="repo-toolbar">
  <h2 class="page-subtitle"><?= icon('pull-request', 16) ?> 新建 Pull request</h2>
</div>

<div class="card pr-new-card">
  <form method="post" action="<?= repo_url($repo, '/pulls') ?>" class="form pr-new-form">
    <?= csrf_field() ?>

    <div class="pr-new-branches">
      <div class="form-group">
        <label class="form-label" for="base_ref">base（要合并进的分支）</label>
        <select class="form-control" id="base_ref" name="base_ref">
          <?php foreach ($branches as $b): ?>
          <option value="<?= e($b) ?>" <?= $b === $defaultRef ? 'selected' : '' ?>><?= e($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <span class="pr-new-arrow"><?= icon('arrow-left', 18) ?></span>
      <div class="form-group">
        <label class="form-label" for="head_ref">head（你的改动分支）</label>
        <select class="form-control" id="head_ref" name="head_ref">
          <?php foreach ($branches as $b): ?>
          <option value="<?= e($b) ?>" <?= $b === $headDefault ? 'selected' : '' ?>><?= e($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if ($forks !== []): ?>
    <div class="form-group">
      <label class="form-label" for="head_repo_id">源仓库（默认本仓库；可选你的 fork）</label>
      <select class="form-control" id="head_repo_id" name="head_repo_id">
        <option value="<?= (int)$repo['id'] ?>">本仓库（<?= e($repo['owner_name']) ?>/<?= e($repo['name']) ?>）</option>
        <?php foreach ($forks as $f): ?>
        <option value="<?= (int)$f['id'] ?>"><?= e($f['owner_name']) ?>/<?= e($f['name']) ?>（我的 fork）</option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label class="form-label" for="title">标题</label>
      <input type="text" class="form-control" id="title" name="title" maxlength="255" placeholder="留空则自动使用 head 分支最新提交信息">
    </div>

    <div class="form-group">
      <label class="form-label" for="body">描述（可选，支持 Markdown）</label>
      <textarea class="form-control" id="body" name="body" rows="6" placeholder="说明这个 PR 做了什么、为什么……"></textarea>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">创建 Pull request</button>
      <a class="btn" href="<?= repo_url($repo, '/pulls') ?>">取消</a>
    </div>
  </form>
</div>
