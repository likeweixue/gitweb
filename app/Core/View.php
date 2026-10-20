<?php
declare(strict_types=1);

namespace App\Core;

/**
 * 简易 PHP 模板引擎（extract + include，支持布局）
 */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'main'): string
    {
        $file = GW_APP_ROOT . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("视图不存在: $template");
        }
        $content = self::includeFile($file, $data);

        if ($layout !== null) {
            $layoutFile = GW_APP_ROOT . '/views/layouts/' . $layout . '.php';
            if (!is_file($layoutFile)) {
                throw new \RuntimeException("布局不存在: $layout");
            }
            $content = self::includeFile($layoutFile, array_merge($data, ['content' => $content]));
        }
        return $content;
    }

    private static function includeFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string)ob_get_clean();
    }
}
