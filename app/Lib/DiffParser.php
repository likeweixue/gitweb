<?php
declare(strict_types=1);

namespace App\Lib;

/**
 * Unified Diff 解析器（git diff-tree / git diff 输出）
 */
final class DiffParser
{
    /**
     * 解析 diff 文本
     *
     * @return array{files: array<int, array<string, mixed>>, additions: int, deletions: int}
     */
    public static function parse(string $diff): array
    {
        $result = ['files' => [], 'additions' => 0, 'deletions' => 0];
        if (trim($diff) === '') {
            return $result;
        }

        $lines = explode("\n", $diff);
        $fileIndex = -1;
        $hunkIndex = -1;

        foreach ($lines as $line) {
            if (str_starts_with($line, 'diff --git')) {
                $result['files'][] = [
                    'old_path' => null,
                    'new_path' => null,
                    'status'   => 'modified',
                    'binary'   => false,
                    'hunks'    => [],
                ];
                $fileIndex = count($result['files']) - 1;
                $hunkIndex = -1;
                continue;
            }
            if ($fileIndex < 0) {
                continue;
            }

            // 通过引用直接修改结果数组（PHP 值传递陷阱）
            $file = &$result['files'][$fileIndex];

            if (str_starts_with($line, 'new file mode')) {
                $file['status'] = 'added';
            } elseif (str_starts_with($line, 'deleted file mode')) {
                $file['status'] = 'deleted';
            } elseif (str_starts_with($line, 'rename from ')) {
                $file['status'] = 'renamed';
                $file['old_path'] = substr($line, strlen('rename from '));
            } elseif (str_starts_with($line, 'rename to ')) {
                $file['status'] = 'renamed';
                $file['new_path'] = substr($line, strlen('rename to '));
            } elseif (str_starts_with($line, 'copy to ')) {
                $file['status'] = 'renamed';
                $file['new_path'] = substr($line, strlen('copy to '));
            } elseif (str_starts_with($line, 'Binary files ') || str_starts_with($line, 'GIT binary patch')) {
                $file['binary'] = true;
            } elseif (str_starts_with($line, '--- ')) {
                $path = substr($line, 4);
                $file['old_path'] = $path === '/dev/null' ? null : self::stripPrefix($path);
            } elseif (str_starts_with($line, '+++ ')) {
                $path = substr($line, 4);
                $file['new_path'] = $path === '/dev/null' ? null : self::stripPrefix($path);
            } elseif (str_starts_with($line, '@@')) {
                $file['hunks'][] = ['header' => $line, 'lines' => []];
                $hunkIndex = count($file['hunks']) - 1;
            } elseif ($hunkIndex >= 0 && isset($line[0]) && in_array($line[0], [' ', '+', '-', '\\'], true)) {
                $type = $line[0];
                $file['hunks'][$hunkIndex]['lines'][] = ['type' => $type, 'content' => substr($line, 1)];
                if ($type === '+') {
                    $result['additions']++;
                } elseif ($type === '-') {
                    $result['deletions']++;
                }
            }

            unset($file); // 及时解除引用，避免后续 append 被污染
        }

        // 兜底路径提取与展示名
        foreach ($result['files'] as $index => $file) {
            if ($file['new_path'] === null && $file['old_path'] === null) {
                $result['files'][$index]['display_path'] = '未知文件';
            } else {
                $result['files'][$index]['display_path'] = (string)($file['new_path'] ?? $file['old_path']);
            }
            if ($file['old_path'] !== null && $file['new_path'] !== null
                && $file['old_path'] !== $file['new_path'] && $file['status'] === 'modified') {
                $result['files'][$index]['status'] = 'renamed';
            }
        }

        return $result;
    }

    private static function stripPrefix(string $path): string
    {
        if (str_starts_with($path, 'a/') || str_starts_with($path, 'b/')) {
            return substr($path, 2);
        }
        return $path;
    }
}
