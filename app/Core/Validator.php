<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 表单验证器
 * 规则：required|min:3|max:39|email|url|in:a,b|numeric|int|alpha_dash|username|reponame|regex:/.../|same:field|confirmed|unique:table,column|different:field|color
 */
final class Validator
{
    /** @var array<string, string> */
    private static array $labels = [
        'username'             => '用户名',
        'email'                => '邮箱',
        'password'             => '密码',
        'password_confirmation' => '确认密码',
        'current_password'     => '当前密码',
        'new_password'         => '新密码',
        'name'                 => '名称',
        'title'                => '标题',
        'body'                 => '内容',
        'description'          => '描述',
        'display_name'         => '昵称',
        'bio'                  => '简介',
        'location'             => '所在地',
        'website'              => '网站',
        'permission'           => '权限',
        'tag_name'             => '标签名',
        'color'                => '颜色',
        'q'                    => '关键词',
        'key'                  => '密钥',
        'url'                  => '地址',
    ];

    /** @return array<string, string> 字段 => 错误信息 */
    public static function make(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            foreach (explode('|', (string)$ruleString) as $rule) {
                if ($rule === '') {
                    continue;
                }
                $parts = explode(':', $rule, 2);
                $name = $parts[0];
                $param = $parts[1] ?? null;

                $pass = match ($name) {
                    'required'   => $value !== null && $value !== '' && $value !== [],
                    'min'        => is_string($value) && mb_strlen($value) >= (int)$param,
                    'max'        => is_string($value) && mb_strlen($value) <= (int)$param,
                    'email'      => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'url'        => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#', $value) === 1,
                    'in'         => in_array((string)$value, explode(',', (string)$param), true),
                    'numeric'    => is_numeric($value),
                    'int'        => filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'alpha_dash' => is_string($value) && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1,
                    'username'   => is_string($value) && preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/', $value) === 1,
                    'reponame'   => is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $value) === 1,
                    'regex'      => is_string($value) && @preg_match((string)$param, $value) === 1,
                    'same'       => $value === ($data[$param] ?? null),
                    'confirmed'  => $value === ($data[$field . '_confirmation'] ?? null),
                    'different'  => $value !== ($data[$param] ?? null),
                    'color'      => is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1,
                    'unique'     => self::checkUnique((string)$param, $value),
                    default      => true,
                };

                if (!$pass) {
                    $errors[$field] = self::message($name, $field, $param);
                    break;
                }
            }
        }
        return $errors;
    }

    private static function checkUnique(string $param, mixed $value): bool
    {
        [$table, $column] = array_pad(explode(',', $param, 2), 2, null);
        if ($table === null || $column === null || $value === null || $value === '') {
            return false;
        }
        return Database::count($table, "$column = ?", [$value]) === 0;
    }

    private static function message(string $rule, string $field, ?string $param): string
    {
        $label = self::$labels[$field] ?? $field;
        return match ($rule) {
            'required'   => "{$label}不能为空",
            'min'        => "{$label}至少需要 {$param} 个字符",
            'max'        => "{$label}不能超过 {$param} 个字符",
            'email'      => '请输入有效的邮箱地址',
            'url'        => '请输入有效的网址（需以 http:// 或 https:// 开头）',
            'in'         => '请选择有效的选项',
            'numeric'    => '请输入数字',
            'int'        => '请输入整数',
            'alpha_dash' => '仅允许字母、数字、下划线和连字符',
            'username'   => '用户名只能包含字母、数字和连字符，不能以连字符开头或结尾（1-39 字符）',
            'reponame'   => '仓库名只能包含字母、数字、点、下划线和连字符，且不能以点开头（1-100 字符）',
            'regex'      => '格式不正确',
            'same'       => '两次输入不一致',
            'confirmed'  => '两次输入不一致',
            'unique'     => "该{$label}已被占用",
            'different'  => '不能与当前值相同',
            'color'      => '请选择有效的颜色（#RRGGBB）',
            default      => '格式不正确',
        };
    }
}
