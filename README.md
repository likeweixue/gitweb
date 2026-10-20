# Gitweb

一套零依赖、可自托管的 Git 平台，类似 GitHub / Gitee。PHP 8 + MySQL 8，无需 Docker、无需 Composer，传到任意支持 PHP 的主机即可运行。

> 整体 UI 采用 **dsh-startup-screen** 视觉语言：暗色工作台 + 暖白「技术档案」底板 + 橙强调 + 红蓝色散故障，并带有六幕开机启动动画。

## 特性

- **Git 核心**：bare 仓库管理、Fork；HTTPS 智能协议（`git http-backend`）与 SSH（`gitweb-shell`）双协议 clone / fetch / push
- **协作**：**Pull Request**（分支对比、讨论评论、审查 approve / request changes、三种合并策略 merge / squash / rebase、跨仓库 fork PR）、Issues、标签、协作者（读 / 写 / 管理）、私有仓库、Star / Fork
- **分支管理**：Web 端新建 / 删除分支
- **账户**：注册 / 登录 / 记住我 / 防爆破 / 个人访问令牌 / SSH 公钥 / identicon 头像（零图片资源）
- **代码浏览**：目录树、文件高亮（13 种语言）、Markdown（GFM 子集）渲染、提交历史、差异对比、分支 / 标签
- **管理后台**：用户 / 仓库 / 配额 / 设置、统计
- **安全**：全站 CSRF、参数化 SQL、登录防爆破

## 技术栈

- PHP 8（strict_types），零 Composer 依赖，PSR-4 简化自动加载
- MySQL 8（生产）/ SQLite（本地开发测试）双驱动，PDO
- 前端：服务端渲染 + 原生 CSS / JS，无构建
- 部署：OpenResty / Apache + PHP-FPM，自带 `bin/install.php` 一键安装

## 快速开始（本地开发）

```bash
cp config.example.php config.php      # 按需修改（默认已配 SQLite）
php bin/install.php --admin=admin --email=you@example.com --password=yourpass
php -S 127.0.0.1:8899 public/index.php
```

浏览器打开 http://127.0.0.1:8899 。首次访问会播放开机启动动画；同一会话内只播一次，访问 `?splash=1` 可强制重播。

## 部署（生产）

1. 将项目传到服务器，`cp config.example.php config.php` 并改为 MySQL 驱动
2. `php bin/install.php` 完成建库与初始管理员
3. Web 根目录指向 `public/`，配置 `deploy/openresty.conf` 或等价规则把请求转发到 `public/index.php`
4. 配置 SSH（`bin/gitweb-shell` + `bin/ssh-check.php`）、cron 统计（`bin/cron-stats.php`）
5. 详见 `docs/guide.md`

## 目录结构

```
app/        PHP 框架与业务逻辑（Core / Lib / Model / Controller）
views/      PHP 视图（layouts / partials / 各页面）
public/     前端入口与静态资源（assets/css · assets/js）
bin/        安装、SSH shell、cron 等脚本
deploy/     部署配置示例
schema.*.sql   MySQL / SQLite 建库脚本
```

## 许可

MIT © WorkBuddy × likeweixue
