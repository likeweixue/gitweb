#!/usr/bin/env php
<?php
/**
 * Gitweb 安装脚本
 *
 * 用法：
 *   php bin/install.php                                             交互式安装
 *   php bin/install.php --admin=admin --email=a@b.c --password=xxx   非交互安装
 *   php bin/install.php --force                                    清空并重建数据库（危险）
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$configFile = $root . '/config.php';

if (!is_file($configFile)) {
    fwrite(STDERR, "缺少 config.php，请先执行：cp config.example.php config.php 并配置数据库。\n");
    exit(1);
}
$config = require $configFile;

// ---------- 参数 ----------
$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        $kv = substr($arg, 2);
        if (str_contains($kv, '=')) {
            [$k, $v] = explode('=', $kv, 2);
            $options[$k] = $v;
        } else {
            $options[$kv] = true;
        }
    }
}

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}
function ok(string $msg): void
{
    fwrite(STDOUT, "  \033[32m✔\033[0m $msg\n");
}
function fail(string $msg): never
{
    fwrite(STDERR, "  \033[31m✘\033[0m $msg\n");
    exit(1);
}
function prompt(string $label, string $default = ''): string
{
    fwrite(STDOUT, "  $label" . ($default !== '' ? " [$default]" : '') . ': ');
    $line = fgets(STDIN);
    $value = $line === false ? '' : trim($line);
    return $value === '' ? $default : $value;
}

out('');
out('=== Gitweb 安装程序 ===');
out('');

// ---------- 环境检查 ----------
if (PHP_VERSION_ID < 80000) {
    fail('需要 PHP 8.0 或更高版本，当前 ' . PHP_VERSION);
}
ok('PHP ' . PHP_VERSION);

$driver = (string)($config['db']['driver'] ?? 'mysql');
if ($driver === 'sqlite') {
    if (!extension_loaded('pdo_sqlite')) {
        fail('缺少 pdo_sqlite 扩展');
    }
    ok('pdo_sqlite');
} else {
    if (!extension_loaded('pdo_mysql')) {
        fail('缺少 pdo_mysql 扩展');
    }
    ok('pdo_mysql');
}
foreach (['mbstring', 'json', 'ctype', 'filter'] as $ext) {
    if (!extension_loaded($ext)) {
        fail("缺少 $ext 扩展");
    }
}
if (in_array('proc_open', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
    fail('proc_open 被禁用，Git 功能依赖该函数');
}
ok('proc_open 可用');

$gitBin = (string)($config['git']['bin'] ?? 'git');
$gitOut = [];
$gitCode = 1;
@exec(escapeshellarg($gitBin) . ' --version 2>&1', $gitOut, $gitCode);
if ($gitCode !== 0) {
    fail("无法执行 git（$gitBin），请确认已安装并在 PATH 中");
}
ok('git ' . (string)($gitOut[0] ?? ''));

// ---------- 目录 ----------
$dirs = [
    $root . '/storage',
    $root . '/storage/repositories',
    $root . '/storage/logs',
    $root . '/storage/cache',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail("无法创建目录: $dir");
    }
}
@chmod($root . '/storage/repositories', 0775);
ok('存储目录就绪');

// ---------- 数据库 ----------
$schemaFile = $driver === 'sqlite' ? $root . '/schema.sqlite.sql' : $root . '/schema.mysql.sql';
if (!is_file($schemaFile)) {
    fail("缺少结构文件: $schemaFile");
}

try {
    if ($driver === 'sqlite') {
        $sqlitePath = (string)($config['db']['sqlite_path'] ?? $root . '/storage/gitweb.sqlite');
        $pdo = new PDO('sqlite:' . $sqlitePath);
    } else {
        $c = $config['db'];
        $socket = (string)($c['unix_socket'] ?? '');
        $dsn = $socket !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $c['database'], $c['charset'] ?? 'utf8mb4')
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], (int)($c['port'] ?? 3306), $c['database'], $c['charset'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, (string)($c['username'] ?? ''), (string)($c['password'] ?? ''));
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ok('数据库连接成功');
} catch (PDOException $e) {
    fail('数据库连接失败: ' . $e->getMessage());
}

// 检测是否已安装
$installed = false;
try {
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'");
        $installed = ((int)$stmt->fetchColumn()) > 0;
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
        $stmt->execute();
        $installed = ((int)$stmt->fetchColumn()) > 0;
    }
} catch (PDOException) {
    $installed = false;
}

if ($installed && empty($options['force'])) {
    fail('数据库已安装（检测到 users 表）。如需重置请追加 --force 参数。');
}

if (!empty($options['force'])) {
    out('  正在重置数据库…');
    try {
        if ($driver === 'sqlite') {
            $pdo = null;
            @unlink((string)($config['db']['sqlite_path'] ?? ''));
            $pdo = new PDO('sqlite:' . (string)($config['db']['sqlite_path'] ?? ''));
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } else {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec('DROP TABLE `' . $table . '`');
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    } catch (PDOException $e) {
        fail('重置失败: ' . $e->getMessage());
    }
}

// ---------- 导入结构 ----------
$sql = (string)file_get_contents($schemaFile);
$statements = [];
foreach (explode("\n", $sql) as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
        continue;
    }
    $statements[] = $line;
}
$statements = array_filter(array_map('trim', explode(';', implode("\n", $statements))), static fn ($s) => $s !== '');

try {
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
    ok('数据表导入完成（' . count($statements) . ' 条语句）');
} catch (PDOException $e) {
    fail('导入失败: ' . $e->getMessage() . "\n  SQL: " . substr($statement, 0, 200));
}

// ---------- 管理员 ----------
$adminUser = isset($options['admin']) ? (string)$options['admin'] : prompt('管理员用户名', 'admin');
if (!preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/', $adminUser)) {
    fail('管理员用户名格式不合法');
}
$adminEmail = isset($options['email']) ? (string)$options['email'] : prompt('管理员邮箱', 'admin@' . ($_SERVER['SERVER_NAME'] ?? 'gitweb.local'));
if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
    fail('邮箱格式不合法');
}
$adminPass = isset($options['password']) ? (string)$options['password'] : prompt('管理员密码（至少 8 位）');
if (strlen($adminPass) < 8) {
    fail('密码至少 8 位');
}

try {
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name, role, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$adminUser, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), $adminUser, 'admin', 'active']);
    ok("管理员创建成功：$adminUser");
} catch (PDOException $e) {
    fail('创建管理员失败: ' . $e->getMessage());
}

// ---------- 完成 ----------
out('');
out('=== 安装完成 ===');
out('');
out('下一步：');
out('  1. 启动服务（开发）：  php -S 127.0.0.1:8899 -t public public/index.php');
out('  2. 访问站点并登录：    ' . (string)($config['app']['url'] ?? 'http://127.0.0.1:8899'));
out('  3. 生产部署请参阅 README.md（OpenResty、PHP-FPM、SSH、cron）');
out('');
