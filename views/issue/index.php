<?php partial('repo-header', ['repo' => $repo, 'permission' => $permission, 'tab' => 'issues', 'isStarred' => $isStarred]) ?>

<div class="issues-head">
  <form class="issues-filter" method="get" action="<?= repo_url($repo, '/issues') ?>">
    <div class="searchbox">
      <?= icon('search', 14) ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="搜索 Issue 标题">
    </div>
    <input type="hidden" name="state" value="<?= e($state) ?>">
  </form>
  <?php if ((int)$repo['has_issues'] === 1): ?>
  <a class="btn btn-primary" href="<?= repo_url($repo, '/issues/new') ?>"><?= icon('plus', 15) ?> 新建 Issue</a>
  <?php endif; ?>
</div>

<div class="tabs">
  <a href="<?= repo_url($repo, '/issues?state=open&q=' . rawurlencode($q)) ?>" class="tab <?= $state === 'open' ? 'active' : '' ?>">
    <?= icon('issue', 14) ?> 开放 <span class="counter"><?= (int)$openCount ?></span>
  </a>
  <a href="<?= repo_url($repo, '/issues?state=closed&q=' . rawurlencode($q)) ?>" class="tab <?= $state === 'closed' ? 'active' : '' ?>">
    <?= icon('check', 14) ?> 已关闭 <span class="counter"><?= (int)$closedCount ?></span>
  </a>
  <a href="<?= repo_url($repo, '/issues?state=all&q=' . rawurlencode($q)) ?>" class="tab <?= $state === 'all' ? 'active' : '' ?>">全部</a>
</div>

<div class="card">
  <?php if ($issues === []): ?>
    <?php partial('empty', [
      'icon' => 'issue',
      'message' => $q !== '' ? '没有匹配的 Issue' : ($state === 'closed' ? '没有已关闭的 Issue' : '没有开放的 Issue'),
    ]) ?>
  <?php else: ?>
    <table class="table issue-table">
      <tbody>
        <?php foreach ($issues as $issue): ?>
        <tr>
          <td class="issue-state-cell">
            <?= $issue['state'] === 'open' ? icon('issue', 16, 'icon-open') : icon('issue-closed', 16, 'icon-closed') ?>
          </td>
          <td>
            <a class="issue-title" href="<?= repo_url($repo, '/issues/' . (int)$issue['number']) ?>">
              <?= e((string)$issue['title']) ?>
            </a>
            <div class="issue-meta text-muted text-small">
              #<?= (int)$issue['number'] ?>
              由 <?= e((string)$issue['author_name']) ?> 创建于 <?= time_ago((string)$issue['created_at']) ?>
              <?php if (isset($commentCounts[(int)$issue['id']])): ?>
                · <?= icon('comment', 12) ?> <?= (int)$commentCounts[(int)$issue['id']] ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?= paginate($total, $page, $per, repo_url($repo, '/issues?state=' . $state . '&q=' . rawurlencode($q))) ?>
  <?php endif; ?>
</div>
