<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'issues', 'isStarred' => $isStarred]) ?>

<div class="issue-detail">
  <div class="card issue-detail-card">
    <div class="issue-detail-head">
      <h1 class="issue-detail-title">
        <?= $issue['state'] === 'open' ? icon('issue', 18, 'icon-open') : icon('issue-closed', 18, 'icon-closed') ?>
        <?= e((string)$issue['title']) ?>
        <span class="issue-number">#<?= (int)$issue['number'] ?></span>
      </h1>
      <div class="issue-state-actions">
        <span class="badge <?= $issue['state'] === 'open' ? 'badge-open' : 'badge-closed' ?>">
          <?= $issue['state'] === 'open' ? '开放中' : '已关闭' ?>
        </span>
        <?php if ((!empty($canManage) || (int)$issue['author_id'] === (int)(current_user()['id'] ?? 0)) && is_logged_in()): ?>
        <form method="post" action="<?= repo_url($repo, '/issues/' . (int)$issue['number'] . '/state') ?>" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="state" value="<?= $issue['state'] === 'open' ? 'closed' : 'open' ?>">
          <button type="submit" class="btn btn-sm">
            <?= $issue['state'] === 'open' ? icon('check', 13) . ' 关闭 Issue' : icon('issue', 13) . ' 重新打开' ?>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="issue-labels-row">
      <?php foreach ($labels as $label): ?>
      <span class="label" style="--label-color: <?= e((string)$label['color']) ?>"><?= e((string)$label['name']) ?></span>
      <?php endforeach; ?>
    </div>

    <div class="comment">
      <div class="comment-avatar"><?= avatar(['username' => (string)$issue['author_name']], 32) ?></div>
      <div class="comment-main">
        <div class="comment-head">
          <strong><?= e((string)$issue['author_name']) ?></strong>
          <span class="text-muted text-small">创建于 <?= fmt_date((string)$issue['created_at']) ?></span>
        </div>
        <?php if (trim((string)$issue['body']) !== ''): ?>
        <div class="comment-body markdown-body"><?= markdown((string)$issue['body']) ?></div>
        <?php else: ?>
        <div class="comment-body text-muted">（无内容）</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($issue['state'] === 'closed' && $closer !== null): ?>
  <div class="card issue-closed-note">
    <?= icon('check', 15) ?>
    <strong><?= e((string)$closer['username']) ?></strong> 关闭了该 Issue（<?= time_ago($issue['closed_at']) ?>）
  </div>
  <?php endif; ?>

  <?php foreach ($comments as $comment): ?>
  <div class="card comment">
    <div class="comment-avatar"><?= avatar(['username' => (string)$comment['username']], 32) ?></div>
    <div class="comment-main">
      <div class="comment-head">
        <strong><?= e((string)$comment['username']) ?></strong>
        <span class="text-muted text-small">评论于 <?= fmt_date((string)$comment['created_at']) ?></span>
      </div>
      <div class="comment-body markdown-body"><?= markdown((string)$comment['body']) ?></div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (is_logged_in()): ?>
  <div class="card comment-form-card">
    <div class="comment-avatar"><?= avatar(current_user(), 32) ?></div>
    <form method="post" action="<?= repo_url($repo, '/issues/' . (int)$issue['number'] . '/comment') ?>" class="comment-form">
      <?= csrf_field() ?>
      <textarea class="form-control" name="body" rows="5" required placeholder="写下你的评论（支持 Markdown）"></textarea>
      <div class="comment-form-actions">
        <button type="submit" class="btn btn-primary">提交评论</button>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="card issue-login-note text-muted">
    <a href="<?= url('/login') ?>">登录</a> 后即可参与讨论。
  </div>
  <?php endif; ?>

  <?php if (!empty($canManage) && $allLabels !== []): ?>
  <div class="card">
    <div class="card-head"><h2 class="card-title">管理标签</h2></div>
    <form method="post" action="<?= repo_url($repo, '/issues/' . (int)$issue['number'] . '/labels') ?>" class="form">
      <?= csrf_field() ?>
      <div class="label-picker">
        <?php $currentLabelIds = array_column($labels, 'id'); ?>
        <?php foreach ($allLabels as $label): ?>
        <label class="label-option">
          <input type="checkbox" name="labels[]" value="<?= (int)$label['id'] ?>"
                 <?= in_array((int)$label['id'], array_map('intval', $currentLabelIds), true) ? 'checked' : '' ?>>
          <span class="label" style="--label-color: <?= e((string)$label['color']) ?>"><?= e((string)$label['name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-sm">保存标签</button>
    </form>
  </div>
  <?php endif; ?>
</div>
