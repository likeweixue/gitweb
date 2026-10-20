# 更新日志

本项目的版本号约定：每次迭代 +1（betaN）。

## beta3（2026-10-21）

新增 Pull Request 协作流程与分支管理（最「GitHub 化」的核心能力）。

### Pull Request
- PR 列表页（`/repo/pulls`）：按 open/closed/all 过滤，显示状态徽章、作者、源/目标分支、ahead/behind
- 新建 PR（`/repo/pulls/new`）：选择 base（要合并到）/ head（来源）分支，支持跨仓库 fork PR，可选合并策略（merge / squash / rebase）
- PR 详情页（`/repo/pulls/{number}`）：提交列表、文件差异（复用 diff 渲染）、讨论评论、审查（approve / request changes / comment）、合并/关闭/重开
- 合并策略在 bare 仓库无工作树下实现：`merge` 生成两父合并提交、`squash` 单提交压合、`rebase` 线性重放；冲突时拒绝合并并提示
- 跨仓库 PR：临时 `fetch` head 仓到 base 仓再 diff / 合并，不污染 base 仓长驻引用

### 分支管理
- 分支列表页新增「新建分支」（基于某分支）、每行「删除分支」（危险操作二次确认）
- 对应端点：`POST /repo/branches`、`POST /repo/branches/delete`

### 数据库
- 新增 `pull_requests` / `pull_request_comments` / `pull_request_reviews` 三张表（`schema.sqlite.sql` / `schema.mysql.sql`）
- 新增幂等迁移脚本 `bin/migrate.php`（双驱动，供已部署实例升级）

> 注：beta2 规划中的组织/团队、Webhook、通知等功能尚未实现，将在后续迭代推进。

## beta2（2026-10-20）

整体 UI 改造为 dsh-startup-screen 视觉语言（参考 WorkBuddy 资料库 `dsh-startup-screen-main` 设计）。

### 设计系统（暗色工作台主题）
- 暗色近黑画布 + 网格底纹，暖白「技术档案」底板，橙 `#ff7500` 强调色，红 `#ff3b30` / 蓝 `#2971b8` 色散故障色
- 等宽英文小标签（kicker / eyebrow）、色散故障标题（glitch，红蓝 RGB 错位）、环形准星（reticle）母题
- 「POWERED BY WORKBUDDY」页脚、品牌标改为橙色并带辉光
- 全站组件（顶栏 / 卡片 / 按钮 / 表单 / 表格 / 徽章 / 代码块 / 差异 / 设置 / 后台）适配暗色主题，保留全部原有类名，模板无需改动

### 开机启动动画（splash）
- 六幕开机序列：黑幕 GITWEB 标识 → 过曝洗白 → 暖白技术档案底板（品牌块 + 五步授权，英文副标题逐字打字）→ 色散故障揭幕 → 欢迎
- 环形准星自转、扫描线、进度条、可跳过（Esc / 点击 / SKIP 按钮）
- 按会话播放一次（sessionStorage），`?splash=1` 可强制重播；`prefers-reduced-motion` 与无 JS 时安全兜底（不会把用户留在黑屏）
- 新增文件：`public/assets/css/splash.css`、`public/assets/js/splash.js`、`views/partials/splash.php`

### 落地页
- 新增英文等宽小标签（kicker）、色散故障主标题，右侧演示终端改为暗色主题配色

> 注：beta2 规划中的 PR / 组织团队 / Webhook / 通知等功能尚未实现，将在后续迭代推进。

## beta1（2026-10-20）

首个可用版本，完成 Git 托管平台核心能力。

### 平台基础
- PHP 8 + MySQL 8 零依赖架构（无 Composer），前端控制器 + 正则路由器
- 数据库双驱动：MySQL 8（生产）/ SQLite（本地开发测试）
- 用户系统：注册 / 登录 / 退出 / 会话 / 记住我（30 天）/ 登录防爆破（5 次锁 15 分钟）/ 封禁即时失效
- 个人主页、资料设置、修改密码、identicon 头像（按用户名生成，零图片资源）
- 全站 CSRF 防护、参数化 SQL、错误页（403/404/500）、日志

### Git 核心
- bare 仓库管理：创建（可选初始化 README）、重命名、转让、删除、Fork（bare clone）
- HTTPS 智能协议：`git http-backend` CGI 转发，支持 clone / fetch / push
  - Basic 认证：密码或个人访问令牌（`gw_` 前缀，SHA-256 哈希存储）
  - 公开仓库匿名只读，私有仓库认证访问，推送按写权限拦截
- SSH 协议：`gitweb-shell` 强制命令 + `git-shell` 白名单 + `ssh-check.php` 数据库权限校验 + 路径穿越防护
- post-receive 钩子自动刷新统计，`cron-stats.php` 兜底

### Web 界面
- 仓库首页：clone 地址（HTTPS/SSH 切换 + 一键复制）、最新提交、文件列表（含每条目最后提交）、README 渲染
- 代码浏览：目录树 / 文件内容（13 种语言语法高亮、行号）/ Markdown 渲染 / 图片预览 / 原始文件 / 打包下载（zip、tar.gz）
- 版本历史：提交列表分页、提交详情 + Unified Diff（红绿高亮、统计、二进制/重命名识别）
- 分支列表、标签列表、分支/标签对比（提交范围 + 差异）
- Releases：基于 Git Tag 创建发布说明、Pre-release 标记、源码下载
- Issues：创建 / 评论（Markdown）/ 标签 / 关闭 / 重开 / 搜索 / 分页
- 协作者：读 / 写 / 管理三级权限
- Star / Fork / 探索页（仓库 + 用户搜索、多维度排序）/ 仪表盘
- 管理后台：数据统计、用户管理（封禁/解封/管理员）、仓库管理（可见性/删除）、站点设置（名称/注册开关）

### 工程
- `bin/install.php` 一键安装（环境自检 + 结构导入 + 管理员创建）
- `deploy/openresty.conf` 生产配置示例（大推送、流式转发、安全加固）
- `public/.htaccess` Apache 支持
- 自绘 SVG 线性图标（feather 风格）、浅色圆角设计系统、响应式布局

---

下一迭代（beta2）计划：Pull Request、组织/团队、Webhook、通知中心。
