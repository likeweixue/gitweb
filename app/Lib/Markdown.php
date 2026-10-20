<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * 零依赖 Markdown 渲染器（GFM 子集），输出安全 HTML。
 *
 * 安全模型：输入中的原始 HTML 一律转义，只输出本类自己生成的标签；
 * 所有 URL 经 sanitizeUrl() 白名单清洗（http/https/mailto/锚点/相对路径），
 * 阻断 javascript:、data:、vbscript: 及含控制字符的伪协议。
 * 渲染整体包 try/catch，任何内部错误都回退为转义的段落文本。
 * 块级递归与行内递归均有深度上限（20 / 8），超出按普通文本处理，防止病态嵌套打爆栈。
 */
final class Markdown
{
    /** 块级递归深度上限 */
    private const MAX_DEPTH = 20;

    /** 行内递归深度上限 */
    private const MAX_INLINE_DEPTH = 8;

    /** 围栏代码块语言别名归一化 */
    private const LANG_ALIASES = [
        'js' => 'javascript', 'node' => 'javascript', 'ts' => 'javascript', 'typescript' => 'javascript',
        'py' => 'python', 'python3' => 'python',
        'sh' => 'bash', 'shell' => 'bash', 'zsh' => 'bash',
        'yml' => 'yaml',
        'md' => 'markdown',
        'rb' => 'ruby',
        'golang' => 'go',
        'xml' => 'html', 'xhtml' => 'html',
    ];

    /** emoji 短码表 */
    private const EMOJI = [
        'smile' => "\u{1F604}",
        '+1' => "\u{1F44D}",
        '-1' => "\u{1F44E}",
        'tada' => "\u{1F389}",
        'heart' => "\u{2764}\u{FE0F}",
        'rocket' => "\u{1F680}",
        'eyes' => "\u{1F440}",
        'fire' => "\u{1F525}",
        'warning' => "\u{26A0}\u{FE0F}",
        'bug' => "\u{1F41B}",
    ];

    /** ASCII 标点（反斜杠转义对象） */
    private const PUNCT = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';

    /** 渲染 GFM 子集为安全 HTML；任何内部错误都回退为转义段落 */
    public static function render(string $text): string
    {
        try {
            self::ensureHighlighter();
            if ($text === '') {
                return '';
            }
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            if (str_starts_with($text, "\xEF\xBB\xBF")) {
                $text = substr($text, 3);
            }
            $lines = explode("\n", $text);
            $ctx = ['seen' => []];
            $i = 0;
            return self::parseBlocks($lines, $i, 0, $ctx);
        } catch (\Throwable) {
            return '<p>' . self::esc($text) . '</p>';
        }
    }

    /** 确保 Highlighter 可用：优先 autoload，其次同目录加载；缺失则降级纯转义 */
    private static function ensureHighlighter(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        if (class_exists(Highlighter::class, true)) {
            return;
        }
        $path = __DIR__ . '/Highlighter.php';
        if (is_file($path)) {
            @include_once $path;
        }
    }

    // ---------------------------------------------------------------- 块级

    /** 块级主循环：$i 为行游标（引用传递），$depth 为嵌套深度 */
    private static function parseBlocks(array $lines, int &$i, int $depth, array &$ctx): string
    {
        $n = count($lines);
        if ($depth >= self::MAX_DEPTH) {
            // 超出深度上限：剩余行按普通文本处理
            $rest = implode("\n", array_slice($lines, $i));
            $i = $n;
            return '<p>' . self::esc($rest) . "</p>\n";
        }
        $out = '';
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                continue;
            }
            // 围栏代码块（3 个及以上反引号或波浪号）
            if (preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $m)) {
                $out .= self::fencedCode($lines, $i, $m[1], $m[2]);
                continue;
            }
            // ATX 标题
            if (preg_match('/^ {0,3}(#{1,6})(?:[ \t]+(.*?))?[ \t]*$/', $line, $m)) {
                $level = strlen($m[1]);
                $raw = (string) ($m[2] ?? '');
                $raw = (string) preg_replace('/[ \t]+#+[ \t]*$/', '', $raw);
                $id = self::anchor($raw, $ctx);
                $idAttr = $id !== '' ? ' id="' . $id . '"' : '';
                $out .= '<h' . $level . $idAttr . '>' . self::inline($raw, 0) . '</h' . $level . ">\n";
                $i++;
                continue;
            }
            // 水平线
            if (preg_match('/^ {0,3}(?:-{3,}|\*{3,}|_{3,})[ \t]*$/', $line)) {
                $out .= "<hr>\n";
                $i++;
                continue;
            }
            // 引用块
            if (preg_match('/^ {0,3}>/', $line)) {
                $out .= self::blockquote($lines, $i, $depth, $ctx);
                continue;
            }
            // 列表
            if (preg_match('/^ {0,3}(?:[-*+]|\d{1,9}[.)])(?:[ \t]+|$)/', $line)) {
                $out .= self::listBlock($lines, $i, $depth, $ctx);
                continue;
            }
            // 缩进代码块（4 空格或 1 个 tab）
            if (preg_match('/^(?: {4}|\t)/', $line)) {
                $out .= self::indentedCode($lines, $i);
                continue;
            }
            // GFM 表格（当前行含 | 且下一行是对齐分隔行）
            if (str_contains($line, '|') && isset($lines[$i + 1]) && self::isDelimRow($lines[$i + 1])) {
                $out .= self::table($lines, $i);
                continue;
            }
            // 段落
            $out .= self::paragraph($lines, $i);
        }
        return $out;
    }

    /** 围栏代码块：info string 第一个词为语言，调用 Highlighter */
    private static function fencedCode(array $lines, int &$i, string $fence, string $info): string
    {
        $n = count($lines);
        $fenceChar = $fence[0];
        $fenceLen = strlen($fence);
        $closeRe = '/^ {0,3}' . preg_quote($fenceChar, '/') . '{' . $fenceLen . ',}[ \t]*$/';
        $content = [];
        $i++;
        while ($i < $n) {
            if (preg_match($closeRe, $lines[$i])) {
                $i++;
                break;
            }
            $content[] = $lines[$i];
            $i++;
        }
        $lang = '';
        if (preg_match('/^\s*([A-Za-z0-9_+#.-]+)/', $info, $lm)) {
            $lang = self::normalizeLang(strtolower($lm[1]));
        }
        $code = implode("\n", $content);
        if ($code !== '') {
            $code .= "\n";
        }
        $class = $lang !== '' ? ' class="language-' . $lang . '"' : '';
        $html = ($lang !== '' && class_exists(Highlighter::class)) ? Highlighter::highlight($code, $lang) : self::esc($code);
        return '<pre><code' . $class . '>' . $html . '</code></pre>' . "\n";
    }

    /** 4 空格缩进代码块 */
    private static function indentedCode(array $lines, int &$i): string
    {
        $n = count($lines);
        $content = [];
        $blank = 0;
        while ($i < $n) {
            $l = $lines[$i];
            if (preg_match('/^(?: {4}|\t)/', $l)) {
                for ($k = 0; $k < $blank; $k++) {
                    $content[] = '';
                }
                $blank = 0;
                $content[] = (string) preg_replace('/^(?: {4}|\t)/', '', $l, 1);
                $i++;
            } elseif (trim($l) === '') {
                $blank++;
                $i++;
            } else {
                break;
            }
        }
        $code = implode("\n", $content);
        if ($code !== '') {
            $code .= "\n";
        }
        return '<pre><code>' . self::esc($code) . '</code></pre>' . "\n";
    }

    /** 引用块：剥离 > 前缀后递归解析（支持嵌套） */
    private static function blockquote(array $lines, int &$i, int $depth, array &$ctx): string
    {
        $n = count($lines);
        $inner = [];
        while ($i < $n) {
            $l = $lines[$i];
            if (preg_match('/^ {0,3}>[ ]?(.*)$/', $l, $m)) {
                $inner[] = $m[1];
                $i++;
            } else {
                break;
            }
        }
        while ($inner !== [] && trim($inner[count($inner) - 1]) === '') {
            array_pop($inner);
        }
        $j = 0;
        return '<blockquote>' . self::parseBlocks($inner, $j, $depth + 1, $ctx) . '</blockquote>' . "\n";
    }

    /** 列表块（无序 / 有序 / 嵌套 / 任务列表） */
    private static function listBlock(array $lines, int &$i, int $depth, array &$ctx): string
    {
        $n = count($lines);
        preg_match('/^( {0,3})(([-*+])|(\d{1,9})[.)])(?:[ \t]+(.*))?$/', $lines[$i], $m);
        $indent = strlen($m[1]);
        $ordered = $m[3] === '';
        $markerLen = strlen($m[2]);
        $contentIndent = $indent + $markerLen + 1; // 标记后一个空格为内容缩进（近似）
        $items = [];
        $loose = false;

        while ($i < $n) {
            $l = $lines[$i];
            if (preg_match('/^( {0,3})(([-*+])|(\d{1,9})[.)])(?:[ \t]+(.*))?$/', $l, $m2)) {
                $mIndent = strlen($m2[1]);
                $sameType = ($m2[3] === '') === $ordered;
                if ($mIndent === $indent && $sameType) {
                    $items[] = [$m2[5] ?? ''];
                    $i++;
                    continue;
                }
                if ($mIndent > $indent) {
                    // 更深缩进：作为当前项内容（嵌套列表由递归解析识别）
                    $items[count($items) - 1][] = $l;
                    $i++;
                    continue;
                }
                break; // 更浅缩进：列表结束
            }
            if (trim($l) === '') {
                // 空行：预看后续决定归属
                $j = $i + 1;
                while ($j < $n && trim($lines[$j]) === '') {
                    $j++;
                }
                if ($j < $n) {
                    $next = $lines[$j];
                    $nextIndent = strlen($next) - strlen(ltrim($next, ' '));
                    if ($nextIndent > $indent) {
                        $loose = true; // 项内空行
                        $items[count($items) - 1][] = '';
                        $i++;
                        continue;
                    }
                    // 同类型同級新项前的空行 → 松散列表
                    if (preg_match('/^( {0,3})(([-*+])|(\d{1,9})[.)])(?:[ \t]+|$)/', $next, $m3)
                        && strlen($m3[1]) === $indent
                        && ($m3[3] === '') === $ordered) {
                        $loose = true;
                        $i++;
                        continue;
                    }
                }
                break; // 空行后无有效延续：列表结束
            }
            // 普通行：更深缩进为项延续，否则为段落惰性续行
            $items[count($items) - 1][] = $l;
            $i++;
        }

        $html = $ordered ? '<ol>' . "\n" : '<ul>' . "\n";
        foreach ($items as $itemLines) {
            $html .= self::renderItem($itemLines, $contentIndent, $loose, $depth, $ctx);
        }
        return $html . ($ordered ? '</ol>' . "\n" : '</ul>' . "\n");
    }

    /** 渲染单个列表项 */
    private static function renderItem(array $itemLines, int $contentIndent, bool $loose, int $depth, array &$ctx): string
    {
        // 去除内容缩进（首行已是标记后内容）
        $dedented = [];
        foreach ($itemLines as $k => $l) {
            $dedented[] = $k === 0 ? $l : self::dedent($l, $contentIndent);
        }
        // 任务列表：[ ] 或 [x]
        $checkbox = '';
        if (preg_match('/^\[([ xX])\](?:[ \t]+(.*))?$/', $dedented[0], $tm)) {
            $checked = strtolower($tm[1]) === 'x' ? ' checked' : '';
            $checkbox = '<input type="checkbox" disabled' . $checked . '> ';
            $dedented[0] = (string) ($tm[2] ?? '');
        }
        // 单行且非空 → 行内渲染（松散列表包 p）；多行 → 递归块解析
        if (count($dedented) === 1 && trim($dedented[0]) !== '') {
            $content = self::inline($dedented[0], 0);
            return '<li>' . $checkbox . ($loose ? '<p>' . $content . '</p>' : $content) . "</li>\n";
        }
        $j = 0;
        $inner = self::parseBlocks($dedented, $j, $depth + 1, $ctx);
        // 紧凑项：唯一段落去掉 p 包裹（如含嵌套列表时保持与 GitHub 一致的紧凑输出）
        if (!$loose && str_starts_with($inner, '<p>') && substr_count($inner, '<p>') === 1) {
            $end = strpos($inner, "</p>\n");
            if ($end !== false) {
                $inner = substr($inner, 3, $end - 3) . substr($inner, $end + 5);
            }
        }
        return '<li>' . $checkbox . $inner . "</li>\n";
    }

    /** GFM 表格 */
    private static function table(array $lines, int &$i): string
    {
        $n = count($lines);
        $header = self::splitRow($lines[$i]);
        $delims = self::splitRow($lines[$i + 1]);
        $i += 2;
        $aligns = [];
        foreach ($delims as $d) {
            $left = str_starts_with($d, ':');
            $right = str_ends_with($d, ':');
            $aligns[] = $left && $right ? 'center' : ($right ? 'right' : ($left ? 'left' : ''));
        }
        $body = [];
        while ($i < $n) {
            $l = $lines[$i];
            if (trim($l) === '' || !str_contains($l, '|')) {
                break;
            }
            $body[] = self::splitRow($l);
            $i++;
        }
        $html = "<table>\n<thead>\n<tr>\n";
        foreach ($header as $k => $cell) {
            $style = ($aligns[$k] ?? '') !== '' ? ' style="text-align:' . $aligns[$k] . '"' : '';
            $html .= '<th' . $style . '>' . self::inline($cell, 0) . "</th>\n";
        }
        $html .= "</tr>\n</thead>\n";
        if ($body !== []) {
            $html .= "<tbody>\n";
            foreach ($body as $row) {
                $html .= "<tr>\n";
                for ($k = 0; $k < count($header); $k++) {
                    $style = ($aligns[$k] ?? '') !== '' ? ' style="text-align:' . $aligns[$k] . '"' : '';
                    $html .= '<td' . $style . '>' . self::inline((string) ($row[$k] ?? ''), 0) . "</td>\n";
                }
                $html .= "</tr>\n";
            }
            $html .= "</tbody>\n";
        }
        return $html . "</table>\n";
    }

    /** 段落：收集行直到空行或块级起始 */
    private static function paragraph(array $lines, int &$i): string
    {
        $n = count($lines);
        $buf = [];
        while ($i < $n) {
            $l = $lines[$i];
            if (trim($l) === '') {
                break;
            }
            if ($buf !== [] && (self::interruptsParagraph($l)
                || (str_contains($l, '|') && isset($lines[$i + 1]) && self::isDelimRow($lines[$i + 1])))) {
                break;
            }
            $buf[] = $l;
            $i++;
        }
        return '<p>' . self::inline(implode("\n", $buf), 0) . "</p>\n";
    }

    /** 行是否能中断段落 */
    private static function interruptsParagraph(string $line): bool
    {
        if (preg_match('/^ {0,3}(?:`{3,}|~{3,})/', $line)) {
            return true;
        }
        if (preg_match('/^ {0,3}#{1,6}(?:[ \t]|$)/', $line)) {
            return true;
        }
        if (preg_match('/^ {0,3}>/', $line)) {
            return true;
        }
        if (preg_match('/^ {0,3}(?:[-*+]|\d{1,9}[.)])(?:[ \t]|$)/', $line)) {
            return true;
        }
        return (bool) preg_match('/^ {0,3}(?:-{3,}|\*{3,}|_{3,})[ \t]*$/', $line);
    }

    /** 表格对齐分隔行 */
    private static function isDelimRow(string $line): bool
    {
        return (bool) preg_match('/^ {0,3}\|?[ \t]*:?-+:?[ \t]*(?:\|[ \t]*:?-+:?[ \t]*)*\|?[ \t]*$/', $line);
    }

    /** 拆分表格行（容忍首尾竖线与转义竖线） */
    private static function splitRow(string $line): array
    {
        $line = trim($line);
        $line = (string) preg_replace('/^\|/', '', $line);
        $line = (string) preg_replace('/\|$/', '', $line);
        $cells = preg_split('/(?<!\\\\)\|/', $line) ?: [];
        return array_map(static fn (string $c): string => trim(str_replace('\\|', '|', $c)), $cells);
    }

    /** 按指定宽度去除行首缩进（1 个 tab 计 1 个单元） */
    private static function dedent(string $line, int $width): string
    {
        $len = strlen($line);
        $k = 0;
        $removed = 0;
        while ($k < $len && $removed < $width && ($line[$k] === ' ' || $line[$k] === "\t")) {
            $removed++;
            $k++;
        }
        return substr($line, $k);
    }

    // ---------------------------------------------------------------- 行内

    /**
     * 行内扫描器：按位置逐字符尝试规则，未命中字符一律转义。
     * 规则用 A 修饰符锚定在当前位置，无回溯扫描，整体 O(n)。
     */
    private static function inline(string $text, int $depth): string
    {
        if ($depth > self::MAX_INLINE_DEPTH) {
            return self::esc($text);
        }
        $len = strlen($text);
        $out = '';
        $i = 0;
        // 文本中不存在 ]( 时跳过链接解析（避免 [[[[ 类病态输入的重复扫描）
        $hasLink = str_contains($text, '](');
        while ($i < $len) {
            $c = $text[$i];
            // 反斜杠转义（标点）
            if ($c === '\\' && $i + 1 < $len && str_contains(self::PUNCT, $text[$i + 1])) {
                $out .= self::esc($text[$i + 1]);
                $i += 2;
                continue;
            }
            // 硬换行：行尾两个及以上空格
            if ($c === ' ' && preg_match('/\G {2,}\n/A', $text, $m, 0, $i)) {
                $out .= "<br>\n";
                $i += strlen($m[0]);
                continue;
            }
            // 硬换行：行尾反斜杠
            if ($c === '\\' && $i + 1 < $len && $text[$i + 1] === "\n") {
                $out .= "<br>\n";
                $i += 2;
                continue;
            }
            // 代码 span
            if ($c === '`') {
                $run = 1;
                while ($i + $run < $len && $text[$i + $run] === '`') {
                    $run++;
                }
                $close = self::findBacktickRun($text, $i + $run, $run);
                if ($close !== null) {
                    $content = substr($text, $i + $run, $close - $i - $run);
                    $content = str_replace("\n", ' ', $content);
                    if (strlen($content) >= 2 && $content[0] === ' ' && $content[-1] === ' ' && trim($content) !== '') {
                        $content = substr($content, 1, -1);
                    }
                    $out .= '<code>' . self::esc($content) . '</code>';
                    $i = $close + $run;
                    continue;
                }
                $out .= self::esc(str_repeat('`', $run));
                $i += $run;
                continue;
            }
            // 图片
            if ($hasLink && $c === '!' && $i + 1 < $len && $text[$i + 1] === '[') {
                $link = self::parseLink($text, $i + 1, $depth, true);
                if ($link !== null) {
                    [$html, $next] = $link;
                    $out .= $html;
                    $i = $next;
                    continue;
                }
            }
            // 链接
            if ($hasLink && $c === '[') {
                $link = self::parseLink($text, $i, $depth, false);
                if ($link !== null) {
                    [$html, $next] = $link;
                    $out .= $html;
                    $i = $next;
                    continue;
                }
            }
            // 尖括号自动链接 <http(s)://...>
            if ($c === '<' && preg_match('/\G<https?:\/\/[^>\s<]+>/A', $text, $m, 0, $i)) {
                $url = substr($m[0], 1, -1);
                $out .= '<a href="' . self::esc(self::sanitizeUrl($url)) . '">' . self::esc($url) . '</a>';
                $i += strlen($m[0]);
                continue;
            }
            // 裸 URL 自动链接
            if (($c === 'h' || $c === 'H') && preg_match('/\Ghttps?:\/\/[^\s<]+/Ai', $text, $m, 0, $i)) {
                $url = self::trimUrl($m[0]);
                $extra = substr($m[0], strlen($url));
                $out .= '<a href="' . self::esc(self::sanitizeUrl($url)) . '">' . self::esc($url) . '</a>' . self::esc($extra);
                $i += strlen($url);
                continue;
            }
            // @提及（仅行首 / 空白 / ( 之后）
            if ($c === '@' && ($i === 0 || $text[$i - 1] === ' ' || $text[$i - 1] === "\t" || $text[$i - 1] === "\n" || $text[$i - 1] === '(')) {
                if (preg_match('/\G@([A-Za-z0-9_-]{1,39})/A', $text, $mm, 0, $i)) {
                    $user = $mm[1];
                    $out .= '<a href="/' . self::esc($user) . '">@' . self::esc($user) . '</a>';
                    $i += strlen($mm[0]);
                    continue;
                }
            }
            // emoji 短码
            if ($c === ':' && preg_match('/\G:([a-z0-9_+-]{1,20}):/A', $text, $em, 0, $i) && isset(self::EMOJI[$em[1]])) {
                $out .= self::EMOJI[$em[1]];
                $i += strlen($em[0]);
                continue;
            }
            // 粗体 / 斜体 / 删除线
            if ($c === '*' || $c === '_' || $c === '~') {
                $em = self::parseEmphasis($text, $i, $depth);
                if ($em !== null) {
                    [$html, $next] = $em;
                    $out .= $html;
                    $i = $next;
                    continue;
                }
            }
            // 默认：转义一个完整字符（UTF-8 安全，不按字节拆切多字节字符）
            if (preg_match('/\G./us', $text, $m, 0, $i)) {
                $out .= self::esc($m[0]);
                $i += strlen($m[0]);
                continue;
            }
            $out .= self::esc($c);
            $i++;
        }
        return $out;
    }

    /** 查找长度恰为 $run 的反引号闭合串，返回起始位置或 null */
    private static function findBacktickRun(string $text, int $from, int $run): ?int
    {
        $len = strlen($text);
        $i = $from;
        while ($i < $len) {
            if ($text[$i] !== '`') {
                $i++;
                continue;
            }
            $j = $i;
            while ($j < $len && $text[$j] === '`') {
                $j++;
            }
            if ($j - $i === $run) {
                return $i;
            }
            $i = $j;
        }
        return null;
    }

    /** 解析 [文字](url "title") 或 ![alt](url)，返回 [html, 下一位置] 或 null */
    private static function parseLink(string $text, int $i, int $depth, bool $image): ?array
    {
        $len = strlen($text);
        // 括号配对找文本结束 ]
        $depthBr = 1;
        $j = $i + 1;
        while ($j < $len) {
            $ch = $text[$j];
            if ($ch === '\\' && $j + 1 < $len) {
                $j += 2;
                continue;
            }
            if ($ch === '[') {
                $depthBr++;
            } elseif ($ch === ']') {
                $depthBr--;
                if ($depthBr === 0) {
                    break;
                }
            }
            $j++;
        }
        if ($depthBr !== 0 || $j >= $len) {
            return null;
        }
        $textPart = substr($text, $i + 1, $j - $i - 1);
        if ($j + 1 >= $len || $text[$j + 1] !== '(') {
            return null;
        }
        // 配对括号找目标结束 ）
        $paren = 1;
        $k = $j + 2;
        $inQuote = null;
        while ($k < $len) {
            $ch = $text[$k];
            if ($inQuote !== null) {
                if ($ch === $inQuote) {
                    $inQuote = null;
                }
            } elseif ($ch === '"' || $ch === "'") {
                $inQuote = $ch;
            } elseif ($ch === '(') {
                $paren++;
            } elseif ($ch === ')') {
                $paren--;
                if ($paren === 0) {
                    break;
                }
            }
            $k++;
        }
        if ($paren !== 0) {
            return null;
        }
        $inner = trim(substr($text, $j + 2, $k - $j - 2));
        $url = '';
        $title = '';
        if ($inner !== '') {
            if ($inner[0] === '<') {
                $gt = strpos($inner, '>');
                if ($gt !== false) {
                    $url = substr($inner, 1, $gt - 1);
                    $title = self::parseTitle(trim(substr($inner, $gt + 1)));
                    if ($title === null) {
                        return null; // 非法 title，链接不成立
                    }
                } else {
                    $url = $inner;
                }
            } elseif (preg_match('/^(\S+)(?:[ \t]+(.*))?$/s', $inner, $um)) {
                $url = $um[1];
                $title = self::parseTitle((string) ($um[2] ?? ''));
                if ($title === null) {
                    return null; // 非法 title（如 java\tscript: 混淆），链接不成立
                }
            }
        }
        $url = self::sanitizeUrl($url);
        $titleAttr = $title !== '' ? ' title="' . self::esc($title) . '"' : '';
        if ($image) {
            return ['<img src="' . self::esc($url) . '" alt="' . self::esc($textPart) . '"' . $titleAttr . '>', $k + 1];
        }
        return ['<a href="' . self::esc($url) . '"' . $titleAttr . '>' . self::inline($textPart, $depth + 1) . '</a>', $k + 1];
    }

    /** 解析链接标题（引号或圆括号包裹）；空串表示无标题；null 表示非法 */
    private static function parseTitle(string $rest): ?string
    {
        if ($rest === '') {
            return '';
        }
        if (preg_match('/^"(.*)"$/s', $rest, $m) || preg_match("/^'(.*)'$/s", $rest, $m) || preg_match('/^\((.*)\)$/s', $rest, $m)) {
            return $m[1];
        }
        return null;
    }

    /** ASCII 单词字符（用于下划线强调的词内判断；不用 ctype_alnum，其对高位字节行为不确定） */
    private static function isWordChar(string $ch): bool
    {
        return ($ch >= 'a' && $ch <= 'z') || ($ch >= 'A' && $ch <= 'Z') || ($ch >= '0' && $ch <= '9') || $ch === '_';
    }

    /** 解析强调：** 粗体、__ 粗体、* 斜体、_ 斜体、~~ 删除线、*** 粗斜体 */
    private static function parseEmphasis(string $text, int $i, int $depth): ?array
    {
        $c = $text[$i];
        $len = strlen($text);
        // 下划线不做词内强调
        if ($c === '_' && $i > 0 && self::isWordChar($text[$i - 1])) {
            return null;
        }
        // 单个 ~ 不是删除线
        if ($c === '~' && ($i + 1 >= $len || $text[$i + 1] !== '~')) {
            return null;
        }
        $markerLen = ($i + 1 < $len && $text[$i + 1] === $c) ? 2 : 1;
        $triple = $markerLen === 2 && $i + 2 < $len && $text[$i + 2] === $c;
        if ($triple) {
            $markerLen = 3;
        }
        $marker = str_repeat($c, $markerLen);
        $close = strpos($text, $marker, $i + $markerLen);
        while ($close !== false) {
            if ($c === '_') {
                $after = $close + $markerLen;
                if ($after < $len && self::isWordChar($text[$after])) {
                    $close = strpos($text, $marker, $close + 1);
                    continue;
                }
            }
            break;
        }
        if ($close === false || $close === $i + $markerLen) {
            return null;
        }
        $content = substr($text, $i + $markerLen, $close - $i - $markerLen);
        if ($content === '') {
            return null;
        }
        if ($triple) {
            $html = '<strong><em>' . self::inline($content, $depth + 1) . '</em></strong>';
        } elseif ($markerLen === 2) {
            $html = '<' . ($c === '~' ? 'del' : 'strong') . '>' . self::inline($content, $depth + 1) . '</' . ($c === '~' ? 'del' : 'strong') . '>';
        } else {
            $html = '<em>' . self::inline($content, $depth + 1) . '</em>';
        }
        return [$html, $close + $markerLen];
    }

    /** 裸 URL 尾部标点修整（成对括号保护） */
    private static function trimUrl(string $url): string
    {
        while ($url !== '' && str_contains('.,;:!?\'"', $url[-1])) {
            $url = substr($url, 0, -1);
        }
        while ($url !== '' && $url[-1] === ')') {
            if (substr_count($url, ')') > substr_count($url, '(')) {
                $url = substr($url, 0, -1);
            } else {
                break;
            }
        }
        return $url;
    }

    // ---------------------------------------------------------------- 安全

    /**
     * URL 清洗：仅放行 http:、https:、mailto:、# 开头、/ 开头与相对路径；
     * 阻断 javascript:、data:、vbscript: 等及含控制字符 / 空白的伪协议。
     */
    private static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }
        // 去掉可能用于绕过检测的控制字符与空白后再判定
        $clean = (string) preg_replace('/[\x00-\x20\x7f]/', '', $url);
        $probe = strtolower(str_replace('\\', '/', $clean));
        if (preg_match('#^([a-z][a-z0-9+.\-]*):#', $probe, $m)) {
            // 显式协议：白名单外一律替换为 #
            return in_array($m[1] . ':', ['http:', 'https:', 'mailto:'], true) ? $clean : '#';
        }
        // 形如 /javascript: 或 .\data: 的伪装协议
        if (preg_match('#^[./]*(?:javascript|vbscript|data|file|about|blob):#i', $probe)) {
            return '#';
        }
        return $url; // 相对路径、#锚点、/ 开头
    }

    /** 生成标题锚点 id（小写、空格转-、去特殊字符、同页去重） */
    private static function anchor(string $text, array &$ctx): string
    {
        $t = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $text);
        $t = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $t);
        $t = (string) preg_replace('/[`*_~#>\[\]()!]/', '', $t);
        $t = mb_strtolower(trim($t));
        $t = str_replace(' ', '-', $t);
        $t = (string) preg_replace('/[^\p{L}\p{N}_-]+/u', '', $t);
        $t = (string) preg_replace('/-+/', '-', $t);
        $t = trim($t, '-');
        if ($t === '') {
            return '';
        }
        if (isset($ctx['seen'][$t])) {
            $ctx['seen'][$t]++;
            return $t . '-' . $ctx['seen'][$t];
        }
        $ctx['seen'][$t] = 0;
        return $t;
    }

    /** 围栏语言别名归一化 */
    private static function normalizeLang(string $lang): string
    {
        return self::LANG_ALIASES[$lang] ?? $lang;
    }

    /** HTML 转义 */
    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
