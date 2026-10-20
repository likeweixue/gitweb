# 更新日志

本项目的版本号约定：每次迭代 +1（betaN）。

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
