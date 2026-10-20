<?php
/**
 * Gitweb 配置文件模板
 * 使用方法：复制为 config.php 后按需修改
 *   cp config.example.php config.php
 */

return [
    'app' => [
        'name'      => 'Gitweb',                 // 站点名称
        'url'       => 'https://gitweb.example.com', // 站点完整 URL（用于生成 clone 链接，务必改为实际域名）
        'debug'     => false,                    // 生产环境必须关闭
        'timezone'  => 'Asia/Shanghai',
        'base_path' => '',                       // 安装在子目录时填写，如 /gitweb
    ],

    'db' => [
        'driver'      => 'mysql',                // mysql（生产）| sqlite（仅本地开发/测试）
        'host'        => '127.0.0.1',
        'port'        => 3306,
        'database'    => 'gitweb',
        'username'    => 'gitweb',
        'password'    => 'change-me',
        'charset'     => 'utf8mb4',
        'unix_socket' => '',                     // 使用 Unix Socket 连接时填写，如 /tmp/mysql.sock

        // SQLite 模式（driver=sqlite 时生效）
        'sqlite_path' => __DIR__ . '/storage/gitweb.sqlite',
    ],

    'git' => [
        'bin'           => 'git',                // git 可执行文件（需在 PATH 中或写绝对路径）
        'repos_root'    => __DIR__ . '/storage/repositories', // bare 仓库根目录（必须在 Web 根目录之外！）
        'http_backend'  => 'git http-backend',   // HTTP 智能协议后端
        'ssh_enabled'   => true,                 // 是否开放 SSH 协议（需按 README 配置 authorized_keys）
        'ssh_host'      => 'gitweb.example.com', // SSH clone 地址主机名
        'ssh_user'      => 'git',                // SSH clone 地址用户
        'shell_path'    => __DIR__ . '/bin/gitweb-shell', // SSH 强制命令脚本
    ],

    'session' => [
        'name'     => 'gitweb_session',
        'lifetime' => 7200,                      // 会话有效期（秒）
    ],

    'security' => [
        'login_max_attempts' => 5,               // 登录失败锁定阈值
        'login_lock_minutes' => 15,              // 锁定时长（分钟）
        'remember_days'      => 30,              // 记住我有效期（天）
    ],
];
