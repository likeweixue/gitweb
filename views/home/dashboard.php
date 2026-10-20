<div class="page-head">
  <h1 class="page-title">仪表盘</h1>
  <a class="btn btn-primary" href="<?= url('/new') ?>"><?= icon('plus', 15) ?> 新建仓库</a>
</div>

<div class="dash-grid">
  <section class="card dash-card">
    <div class="card-head">
      <h2 class="card-title"><?= icon('repo', 16) ?> 我的仓库</h2>
      <a class="card-link" href="<?= url('/' . rawurlencode((string)$user['username'])) ?>">查看全部</a>
    </div>
    <?php if ($repos === []): ?>
      <div class="dash-empty">还没有仓库，<a href="<?= url('/new') ?>">创建第一个</a></div>
    <?php else: ?>
      <ul class="dash-repo-list">
        <?php foreach ($repos as $repo): ?>
        <li>
          <?= icon('repo', 15, (int)$repo['is_private'] === 1 ? 'icon-private' : 'icon-public') ?>
          <a href="<?= repo_url($repo) ?>"><?= e($repo['name']) ?></a>
          <?php if ((int)$repo['is_private'] === 1): ?><span class="badge badge-private">私有</span><?php endif; ?>
          <span class="text-muted text-small dash-repo-time"><?= time_ago(!empty($repo['pushed_at']) ? (string)$repo['pushed_at'] : (string)$repo['created_at']) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="card dash-card">
    <div class="card-head">
      <h2 class="card-title"><?= icon('star', 16) ?> 我 Star 的</h2>
    </div>
    <?php if ($starred === []): ?>
      <div class="dash-empty">去 <a href="<?= url('/explore') ?>">探索</a> 发现感兴趣的项目</div>
    <?php else: ?>
      <ul class="dash-repo-list">
        <?php foreach ($starred as $repo): ?>
        <li>
          <?= icon('star', 15, 'icon-starred') ?>
          <a href="<?= repo_url($repo) ?>"><?= e($repo['owner_name']) ?>/<?= e($repo['name']) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="card dash-card">
  <div class="card-head">
    <h2 class="card-title"><?= icon('terminal', 16) ?> 快速上手</h2>
  </div>
  <div class="dash-quick">
    <p>1. <a href="<?= url('/new') ?>">创建仓库</a> → 2. 添加远端 → 3. 推送代码</p>
    <pre><code>git remote add origin <?= e(clone_urls(['owner_name' => (string)$user['username'], 'name' => 'my-repo'])['https']) ?>
git push -u origin main</code></pre>
    <p class="text-muted text-small">提示：在 <a href="<?= url('/settings/tokens') ?>">设置 → 访问令牌</a> 创建令牌后，可用令牌代替密码进行 HTTPS 推送；也可以在 <a href="<?= url('/settings/ssh') ?>">SSH 公钥</a> 中添加密钥使用 SSH 协议。</p>
  </div>
</section>
