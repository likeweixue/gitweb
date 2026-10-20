<section class="hero">
  <div class="hero-text">
    <h1 class="hero-title">属于你自己的<br>代码托管平台</h1>
    <p class="hero-sub">
      Gitweb 是一套零依赖的自托管 Git 平台：PHP 8 + MySQL 8，无需 Docker、无需 Composer，
      一条命令即可在你自己的服务器上运行。代码浏览、Issues、协作者、SSH/HTTPS 双协议推送，开箱即用。
    </p>
    <div class="hero-actions">
      <a class="btn btn-primary btn-lg" href="<?= url('/register') ?>">立即注册</a>
      <a class="btn btn-lg" href="<?= url('/explore') ?>">探索仓库</a>
    </div>
    <div class="hero-code">
      <div class="hero-code-head"><?= icon('terminal', 14) ?> 推送你的第一个仓库</div>
      <pre><code>git remote add origin https://<?= e(parse_url((string)config('app.url'), PHP_URL_HOST) ?: 'your-site.com') ?>/<?= is_logged_in() ? e(current_user()['username']) : 'your-name' ?>/my-project.git
git push -u origin main</code></pre>
    </div>
  </div>
  <div class="hero-visual" aria-hidden="true">
    <svg viewBox="0 0 320 240" class="hero-svg">
      <rect x="8" y="8" width="304" height="224" rx="12" fill="#f6f8fa" stroke="#d1d9e0"/>
      <rect x="8" y="8" width="304" height="34" rx="12" fill="#ffffff" stroke="#d1d9e0"/>
      <circle cx="26" cy="25" r="4" fill="#ff7b72"/>
      <circle cx="40" cy="25" r="4" fill="#ffa657"/>
      <circle cx="54" cy="25" r="4" fill="#57ab5a"/>
      <text x="76" y="29" font-size="11" fill="#59636e" font-family="monospace">bash — git push</text>
      <g font-family="monospace" font-size="11" fill="#1f2328">
        <text x="26" y="66">$ git remote add origin https://site/you/app.git</text>
        <text x="26" y="88">$ git push -u origin main</text>
        <text x="26" y="118" fill="#1a7f37">Enumerating objects: 24, done.</text>
        <text x="26" y="138" fill="#1a7f37">Writing objects: 100% (24/24), done.</text>
        <text x="26" y="158" fill="#0969da">To https://site/you/app.git</text>
        <text x="26" y="178" fill="#0969da"> * [new branch]      main -&gt; main</text>
        <text x="26" y="208" fill="#59636e">✔ 推送成功，去网页端看看代码吧</text>
      </g>
    </svg>
  </div>
</section>

<section class="features">
  <div class="feature">
    <div class="feature-icon"><?= icon('terminal', 22) ?></div>
    <h3>零依赖部署</h3>
    <p>PHP 8 + MySQL 8，不用 Docker、不用 Composer，传到任意支持 PHP 的主机就能跑。</p>
  </div>
  <div class="feature">
    <div class="feature-icon"><?= icon('code', 22) ?></div>
    <h3>代码浏览</h3>
    <p>目录树、文件高亮、Markdown 渲染、提交历史、差异对比、分支标签，一应俱全。</p>
  </div>
  <div class="feature">
    <div class="feature-icon"><?= icon('key', 22) ?></div>
    <h3>双协议推送</h3>
    <p>HTTPS（密码/访问令牌）与 SSH（公钥）均可 clone / fetch / push，与 GitHub 体验一致。</p>
  </div>
  <div class="feature">
    <div class="feature-icon"><?= icon('issue', 22) ?></div>
    <h3>协作与权限</h3>
    <p>Issues、标签、协作者（读/写/管理）、私有仓库、Star / Fork，团队协作基础能力齐备。</p>
  </div>
</section>
