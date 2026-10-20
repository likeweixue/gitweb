<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'pulls', 'isStarred' => $isStarred]) ?>

<div class="pr-detail">
  <div class="card pr-head-card">
    <div class="pr-head">
      <h1 class="pr-title-lg">
        <?php if ((string)$pr['state'] === 'open'): ?><?= icon('pull-request', 20, 'icon-open') ?>
        <?php elseif ((string)$pr['state'] === 'merged'): ?><?= icon('git-merge', 20, 'icon-merged') ?>
        <?php else: ?><?= icon('x-circle', 20, 'icon-closed') ?><?php endif; ?>
        <?= e((string)$pr['title']) ?>
        <span class="pr-number">#<?= (int)$pr['number'] ?></span>
      </h1>
      <div class="pr-state-actions">
        <span class="badge <?= (string)$pr['state'] === 'open' ? 'badge-open' : ((string)$pr['state'] === 'merged' ? 'badge-merged' : 'badge-closed') ?>">
          <?= (string)$pr['state'] === 'open' ? '开放中' : ((string)$pr['state'] === 'merged' ? '已合并' : '已关闭') ?>
        </span>
        <?php if ((string)$pr['state'] === 'open' && !empty($canManage)): ?>
        <button type="button" class="btn btn-sm btn-danger-outline" data-toggle="pr-close" onclick="document.getElementById('pr-close-form').submit();"><?= icon('x-circle', 13) ?> 关闭</button>
        <?php elseif ((string)$pr['state'] !== 'open' && (!empty($canManage) || !empty($isAuthor)) && is_logged_in()): ?>
        <form method="post" action="<?= repo_url($repo, '/pulls/' . (int)$pr['number'] . '/reopen') ?>" class="inline-form">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm"><?= icon('pull-request', 13) ?> 重新打开</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <div class="pr-branch-line text-muted">
      <?= icon('branch', 14) ?> <strong><?= e((string)$pr['head_ref']) ?></strong>
      <?php if (!empty($headRepo) && ($headRepo['id'] ?? 0) !== ($repo['id'] ?? 0)): ?><span class="mono">(<?= e((string)$headRepo['owner_name']) ?>/<?= e((string)$headRepo['name']) ?>)</span><?php endif; ?>
      <?= icon('arrow-left', 14) ?>
      <strong><?= e((string)$pr['base_ref']) ?></strong>
      <span class="pr-branch-sep">·</span>
      <?php if ($diverge['missing']): ?>
        <span class="text-danger">分支数据缺失</span>
      <?php else: ?>
        <span class="pr-div-stat">+<?= (int)$diverge['ahead'] ?> 提交</span>
        <span class="pr-div-stat text-muted">−<?= (int)$diverge['behind'] ?> 落后</span>
        <span class="pr-div-stat <?= $diverge['mergeable'] ? 'text-success' : 'text-danger' ?>">
          <?= $diverge['mergeable'] ? icon('check', 13) . ' 可自动合并' : icon('alert', 13) . ' 存在冲突' ?>
        </span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ((string)$pr['state'] === 'merged' && $mergedBy !== null): ?>
  <div class="card pr-merge-note">
    <?= icon('git-merge', 15) ?> <strong><?= e((string)$mergedBy['username']) ?></strong> 合并了此 Pull request
    （<?= e((string)($pr['merge_strategy'] ?: 'merge')) ?>）<?= time_ago((string)$pr['merged_at']) ?>
  </div>
  <?php endif; ?>
  <?php if ((string)$pr['state'] === 'closed' && $closedBy !== null && empty($pr['merged_by'])): ?>
  <div class="card pr-merge-note">
    <?= icon('x-circle', 15) ?> <strong><?= e((string)$closedBy['username']) ?></strong> 关闭了此 Pull request <?= time_ago((string)$pr['closed_at']) ?>
  </div>
  <?php endif; ?>

  <!-- 合并 / 审查 操作区 -->
  <?php if ((string)$pr['state'] === 'open' && !$diverge['missing']): ?>
  <div class="pr-action-card card">
    <?php if (!empty($canManage)): ?>
    <form method="post" action="<?= repo_url($repo, '/pulls/' . (int)$pr['number'] . '/merge') ?>" class="pr-merge-form" id="pr-merge-box">
      <?= csrf_field() ?>
      <div class="pr-merge-row">
        <select class="form-control form-control-sm" name="strategy" aria-label="合并策略">
          <option value="merge">创建合并提交</option>
          <option value="squash">压缩为一个提交</option>
          <option value="rebase">Rebase</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm" <?= $diverge['mergeable'] ? '' : 'disabled' ?>>
          <?= icon('git-merge', 14) ?> 合并
        </button>
      </div>
      <?php if (!$diverge['mergeable']): ?>
      <p class="text-danger text-small">此 PR 当前无法自动合并，请在本地解决冲突后重试。</p>
      <?php endif; ?>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <form method="post" action="<?= repo_url($repo, '/pulls/' . (int)$pr['number'] . '/close') ?>" id="pr-close-form" class="hidden-form"><?= csrf_field() ?></form>

  <!-- PR 描述 -->
  <div class="card comment">
    <div class="comment-avatar"><?= avatar(['username' => (string)$pr['author_name']], 32) ?></div>
    <div class="comment-main">
      <div class="comment-head">
        <strong><?= e((string)$pr['author_name']) ?></strong>
        <span class="text-muted text-small">发起于 <?= fmt_date((string)$pr['created_at']) ?></span>
      </div>
      <?php if (trim((string)$pr['body']) !== ''): ?>
      <div class="comment-body markdown-body"><?= markdown((string)$pr['body']) ?></div>
      <?php else: ?>
      <div class="comment-body text-muted">（无描述）</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 提交列表 -->
  <?php if ($commits !== []): ?>
  <div class="card pr-commits">
    <div class="card-head"><h2 class="card-title"><?= icon('git-commit', 15) ?> 提交（<?= count($commits) ?>）</h2></div>
    <ul class="commit-list">
      <?php foreach ($commits as $commit): ?>
      <li class="commit-item">
        <?= avatar(['username' => (string)$commit['author_name']], 20) ?>
        <div class="commit-main">
          <a class="commit-subject" href="<?= repo_url($repo, '/commit/' . $commit['hash']) ?>"><?= e((string)$commit['subject']) ?></a>
          <div class="commit-meta text-muted text-small"><strong><?= e((string)$commit['author_name']) ?></strong> · <?= e(substr((string)$commit['hash'], 0, 7)) ?> · <?= time_ago((string)$commit['date']) ?></div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <!-- 文件差异 -->
  <?= partial('diff', ['diff' => $diff]) ?>

  <!-- 审查意见 -->
  <?php if ($reviews !== []): ?>
  <div class="card pr-reviews">
    <div class="card-head"><h2 class="card-title"><?= icon('check', 15) ?> 审查（<?= count($reviews) ?>）</h2></div>
    <?php foreach ($reviews as $rv): ?>
    <div class="review-item">
      <span class="review-state review-state-<?= e($rv['state']) ?>">
        <?php if ($rv['state'] === 'approved'): ?><?= icon('check', 14) ?> 通过
        <?php elseif ($rv['state'] === 'changes_requested'): ?><?= icon('x-circle', 14) ?> 请求修改
        <?php else: ?><?= icon('comment', 14) ?> 评论<?php endif; ?>
      </span>
      <strong><?= e((string)$rv['username']) ?></strong>
      <span class="text-muted text-small"><?= time_ago((string)$rv['created_at']) ?></span>
      <?php if (trim((string)$rv['body']) !== ''): ?>
      <div class="review-body markdown-body"><?= markdown((string)$rv['body']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 讨论 -->
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
    <?php if ((string)$pr['state'] === 'open'): ?>
    <div class="card comment-form-card">
      <div class="comment-avatar"><?= avatar(current_user(), 32) ?></div>
      <form method="post" action="<?= repo_url($repo, '/pulls/' . (int)$pr['number'] . '/review') ?>" class="comment-form pr-review-form">
        <?= csrf_field() ?>
        <div class="review-actions">
          <button type="submit" name="state" value="approved" class="btn btn-sm btn-success-outline"><?= icon('check', 13) ?> 通过审查</button>
          <button type="submit" name="state" value="changes_requested" class="btn btn-sm btn-danger-outline"><?= icon('x-circle', 13) ?> 请求修改</button>
          <button type="submit" name="state" value="commented" class="btn btn-sm">评论</button>
        </div>
        <textarea class="form-control" name="body" rows="4" placeholder="留下审查意见（支持 Markdown）"></textarea>
      </form>
    </div>
    <?php endif; ?>

    <div class="card comment-form-card">
      <div class="comment-avatar"><?= avatar(current_user(), 32) ?></div>
      <form method="post" action="<?= repo_url($repo, '/pulls/' . (int)$pr['number'] . '/comment') ?>" class="comment-form">
        <?= csrf_field() ?>
        <textarea class="form-control" name="body" rows="4" required placeholder="参与讨论（支持 Markdown）"></textarea>
        <div class="comment-form-actions">
          <button type="submit" class="btn btn-primary">提交评论</button>
        </div>
      </form>
    </div>
  <?php else: ?>
  <div class="card pr-login-note text-muted"><a href="<?= url('/login') ?>">登录</a> 后即可参与审查与讨论。</div>
  <?php endif; ?>
</div>
