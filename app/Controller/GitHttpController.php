<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Logger;
use App\Model\AccessToken;
use App\Model\Repository;
use App\Model\User;

/**
 * Git HTTP 智能协议控制器
 *
 * 通过 CGI 方式调用 git http-backend，实现 clone / fetch / push：
 *   GET  /{owner}/{repo}.git/info/refs?service=git-upload-pack
 *   POST /{owner}/{repo}.git/git-upload-pack
 *   GET  /{owner}/{repo}.git/info/refs?service=git-receive-pack
 *   POST /{owner}/{repo}.git/git-receive-pack
 */
final class GitHttpController extends Controller
{
    /** 认证后的 git 用户名（REMOTE_USER） */
    private string $remoteUser = '';

    /** git http-backend 需要的 PATH_INFO 后缀（如 /info/refs） */
    private string $pathSuffix = '/info/refs';

    public function infoRefs(string $owner, string $repoParam): void
    {
        $repo = $this->loadRepo($owner, $repoParam);
        $service = (string)$this->req->get('service', '');
        if (!in_array($service, ['git-upload-pack', 'git-receive-pack'], true)) {
            $this->gitText(400, '无效的 service 参数（智能协议必需）');
        }
        $this->remoteUser = $this->authenticate($repo, $service);
        $this->pathSuffix = '/info/refs';
        $this->runBackend($repo);
    }

    public function uploadPack(string $owner, string $repoParam): void
    {
        $repo = $this->loadRepo($owner, $repoParam);
        $this->remoteUser = $this->authenticate($repo, 'git-upload-pack');
        $this->pathSuffix = '/git-upload-pack';
        $this->runBackend($repo);
    }

    public function receivePack(string $owner, string $repoParam): void
    {
        $repo = $this->loadRepo($owner, $repoParam);
        $this->remoteUser = $this->authenticate($repo, 'git-receive-pack');
        $this->pathSuffix = '/git-receive-pack';
        $this->runBackend($repo);
    }

    /** 认证并返回 REMOTE_USER 用户名 */
    private function authenticate(array $repo, string $service): string
    {
        $isWrite = $service === 'git-receive-pack';
        $userId = null;
        $username = '';

        $basic = $this->req->basicAuth();
        if ($basic !== null) {
            [$login, $secret] = $basic;
            $user = User::findByLogin($login);
            if ($user !== null && (string)$user['status'] !== 'banned') {
                if (password_verify($secret, (string)$user['password_hash'])) {
                    $userId = (int)$user['id'];
                    $username = (string)$user['username'];
                } else {
                    // 令牌认证（用户名 + 访问令牌）
                    $tokenUser = AccessToken::verify($secret);
                    if ($tokenUser !== null && (int)$tokenUser['id'] === (int)$user['id']) {
                        $userId = (int)$user['id'];
                        $username = (string)$user['username'];
                    }
                }
            }
            if ($userId === null) {
                $this->unauthorized('用户名或密码/访问令牌错误');
            }
        }

        if ($isWrite) {
            if ($userId === null) {
                $this->unauthorized('推送需要身份认证');
            }
            if (!Repository::canWrite($userId, $repo)) {
                $this->gitText(403, '没有该仓库的写入权限');
            }
        } else {
            if ($userId === null && (int)$repo['is_private'] === 1) {
                $this->unauthorized('该仓库为私有仓库，请先认证');
            }
            if ($userId !== null && !Repository::canRead($userId, $repo)) {
                $this->gitText(403, '无权访问该仓库');
            }
        }

        return $username;
    }

    private function unauthorized(string $message): void
    {
        header('WWW-Authenticate: Basic realm="Gitweb"');
        $this->gitText(401, $message);
    }

    private function gitText(int $code, string $message): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message . "\n";
        exit;
    }

    private function loadRepo(string $owner, string $repoParam): array
    {
        $name = (string)preg_replace('/\.git$/', '', $repoParam);
        $repo = Repository::findByPath($owner, $name);
        if ($repo === null) {
            $this->gitText(404, '仓库不存在');
        }
        return $repo;
    }

    /** 以 CGI 方式运行 git http-backend 并转发响应 */
    private function runBackend(array $repo): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $childEnv = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value)) {
                $childEnv[$key] = $value;
            }
        }
        $childEnv = array_merge($childEnv, [
            'GIT_PROJECT_ROOT'    => (string)Config::get('git.repos_root'),
            'GIT_HTTP_EXPORT_ALL' => '1',
            // PATH_INFO 必须是完整路径（含 /info/refs 等后缀），git http-backend 据此路由
            'PATH_INFO'           => '/' . Repository::relPath($repo) . $this->pathSuffix,
            'REQUEST_METHOD'      => $this->req->method(),
            'QUERY_STRING'        => $this->req->queryString(),
            'CONTENT_TYPE'        => $this->req->contentType(),
            'CONTENT_LENGTH'      => $this->req->contentLength(),
            'REMOTE_USER'         => $this->remoteUser,
            'GIT_COMMITTER_NAME'  => $this->remoteUser !== '' ? $this->remoteUser : 'anonymous',
            'GIT_COMMITTER_EMAIL' => $this->remoteUser !== '' ? $this->remoteUser . '@gitweb.local' : 'anonymous@gitweb.local',
        ]);

        $command = (string)Config::get('git.http_backend', 'git http-backend');
        $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $spec, $pipes, null, $childEnv);
        if (!is_resource($process)) {
            $this->gitText(500, '无法启动 git http-backend');
        }

        // POST：透传请求体
        if ($this->req->method() === 'POST') {
            $input = @fopen('php://input', 'rb');
            if (is_resource($input)) {
                stream_copy_to_stream($input, $pipes[0]);
                fclose($input);
            }
        }
        fclose($pipes[0]);

        // 读取 CGI 响应头（以 \r\n\r\n 结束）
        $buffer = '';
        while (!feof($pipes[1]) && !str_contains($buffer, "\r\n\r\n")) {
            $chunk = fread($pipes[1], 8192);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $buffer .= $chunk;
        }

        $separator = strpos($buffer, "\r\n\r\n");
        if ($separator === false) {
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            Logger::error('git http-backend 无有效响应: ' . $stderr);
            $this->gitText(500, 'git http-backend 响应异常');
        }

        $headerText = substr($buffer, 0, $separator);
        $body = substr($buffer, $separator + 4);
        $status = 200;

        foreach (explode("\r\n", $headerText) as $line) {
            if ($line === '') {
                continue;
            }
            if (stripos($line, 'Status:') === 0) {
                $parts = explode(' ', trim(substr($line, 7)), 2);
                $status = (int)($parts[0] ?? 200);
                continue;
            }
            header($line);
        }
        http_response_code($status);

        echo $body;
        while (!feof($pipes[1])) {
            $chunk = fread($pipes[1], 65536);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            echo $chunk;
            @flush();
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
}
