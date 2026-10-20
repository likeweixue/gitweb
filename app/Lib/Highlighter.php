<?php

declare(strict_types=1);

namespace App\Lib;

/**
 * 零依赖语法高亮器（highlight.js 风格的子集实现）。
 *
 * 实现方式：每语言一组有序正则，用 preg_match_all + PREG_SET_ORDER|PREG_OFFSET_CAPTURE
 * 一次性扫描出所有 token（组序号即规则序号，未命中组为空串，索引可靠）；
 * 多行构造（块注释 / heredoc / 三引号字符串等）用简单状态机跨行保持。
 * 说明：PHP 的 preg_split + PREG_SPLIT_DELIM_CAPTURE 只返回到“最后一个参与匹配的组”，
 * stride 不固定无法可靠索引，故扫描核心选用 preg_match_all（等价语义）。
 * 所有正则均为线性结构（无嵌套量词），防止灾难性回溯；tokenize 整体包 try/catch，
 * 任何异常 / PCRE 错误都回退为纯转义文本；每轮循环必然消费输入，杜绝死循环。
 */
final class Highlighter
{
    /** 语言别名 → 规范名 */
    private const ALIASES = [
        'js' => 'javascript', 'node' => 'javascript', 'ts' => 'javascript', 'typescript' => 'javascript',
        'html' => 'html', 'xhtml' => 'html', 'xml' => 'html',
        'py' => 'python', 'python3' => 'python',
        'sh' => 'bash', 'shell' => 'bash', 'zsh' => 'bash',
        'yml' => 'yaml',
        'md' => 'markdown',
        'golang' => 'go',
        'rb' => 'ruby',
        'php' => 'php',
        'css' => 'css',
        'json' => 'json',
        'sql' => 'sql',
        'java' => 'java',
    ];

    /** 通用数字字面量（线性、无回溯风险） */
    private const NUM = '(?<![\w.])(?:0[xX][0-9a-fA-F]+|0[bB][01]+|0[oO][0-7]+|\d+(?:_\d+)*(?:\.\d+(?:_\d+)*)?(?:[eE][+-]?\d+)?|\.\d+(?:_\d+)*)';

    /**
     * 每语言规则表。
     * rules: 有序 [正则, class]，可选第三元素为多行状态键。
     * multi: 状态键 → [class, 结束正则]；结束正数为 ':label' 时按起始匹配动态生成（heredoc）。
     * 约束：rules 中的正则不得包含捕获组（只用 (?:...)、环视、字符类），否则组索引错位。
     */
    private const LANGUAGES = [
        'php' => [
            'rules' => [
                ['\/\/[^\n]*', 'hljs-comment'],
                ['#(?!\[)[^\n]*', 'hljs-comment'],
                ['\/\*', 'hljs-comment', 'php-comment'],
                ['<<<[ \t]*["\']?[A-Za-z_]\w*["\']?', 'hljs-string', 'php-heredoc'],
                ['"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                ['\'(?:\\\\.|[^\'\\\\\n])*\'', 'hljs-string'],
                ['\$\{[^}\n]*\}|\$\$?[A-Za-z_]\w*', 'hljs-variable'],
                [self::NUM, 'hljs-number'],
                ['\b(?:abstract|and|as|break|callable|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|enddeclare|endfor|endforeach|endif|endswitch|endwhile|enum|extends|final|finally|fn|for|foreach|function|global|goto|if|implements|include|include_once|instanceof|insteadof|interface|match|namespace|new|or|private|protected|public|readonly|require|require_once|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield)\b', 'hljs-keyword'],
                ['\b(?:true|false|null)\b', 'hljs-literal'],
                ['\b(?:array|list|isset|empty|exit|die|eval|self|parent|this)\b', 'hljs-built_in'],
                ['(?<=\bfunction[ \t])[A-Za-z_]\w*', 'hljs-title'],
                ['(?<=->)[A-Za-z_]\w*', 'hljs-property'],
                ['\b[A-Za-z_]\w*(?=[ \t]*\()', 'hljs-function'],
                ['=>|->|::|\?\?=|<=>|<=|>=|===|!==|==|!=|&&|\|\||\+\+|--|\?->|\+=|-=|\*=|\/=|\.=|%=|<<|>>|[-+*\/%=<>!&|^~?:.]', 'hljs-operator'],
            ],
            'multi' => [
                'php-comment' => ['hljs-comment', '/\*\//'],
                'php-heredoc' => ['hljs-string', ':label'],
            ],
        ],
        'javascript' => [
            'rules' => [
                ['\/\/[^\n]*', 'hljs-comment'],
                ['\/\*', 'hljs-comment', 'js-comment'],
                ['`', 'hljs-string', 'js-template'],
                ['"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                ['\'(?:\\\\.|[^\'\\\\\n])*\'', 'hljs-string'],
                [self::NUM, 'hljs-number'],
                ['\b(?:var|let|const|function|return|if|else|for|while|do|switch|case|default|break|continue|new|class|extends|super|typeof|instanceof|delete|void|in|of|yield|await|async|try|catch|finally|throw|import|export|from|as|static|get|set|this)\b', 'hljs-keyword'],
                ['\b(?:true|false|null|undefined|NaN|Infinity)\b', 'hljs-literal'],
                ['\b(?:console|Math|JSON|Object|Array|String|Number|Boolean|Promise|window|document|globalThis|Date|RegExp|Map|Set|WeakMap|WeakSet|Symbol|Error|TypeError|RangeError|parseInt|parseFloat|isNaN|isFinite|setTimeout|setInterval|clearTimeout|clearInterval|require|module|exports|process|Buffer|fetch|alert|confirm|prompt)\b', 'hljs-built_in'],
                ['(?<=\bfunction[ \t])[A-Za-z_$][\w$]*', 'hljs-title'],
                ['(?<=\.)[A-Za-z_$][\w$]*', 'hljs-property'],
                ['[A-Za-z_$][\w$]*(?=[ \t]*\()', 'hljs-function'],
                ['=>|\.\.\.|===|!==|==|!=|<=|>=|&&|\|\||\?\?=|\?\?\.|\?\.|\*\*|\+\+|--|[-+*\/%=<>!&|^~?:]', 'hljs-operator'],
            ],
            'multi' => [
                'js-comment' => ['hljs-comment', '/\*\//'],
                'js-template' => ['hljs-string', '/`/'],
            ],
        ],
        'css' => [
            'rules' => [
                ['\/\*', 'hljs-comment', 'css-comment'],
                ['url\((?:[^)\n]*)\)', 'hljs-string'],
                ['"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                ['\'(?:\\\\.|[^\'\\\\\n])*\'', 'hljs-string'],
                ['@[\w-]+', 'hljs-keyword'],
                ['!important\b', 'hljs-literal'],
                ['#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b', 'hljs-number'],
                ['(?<![\w.])(?:-?\d+(?:\.\d+)?|-?\.\d+)[a-zA-Z%]*', 'hljs-number'],
                ['[A-Za-z_-]+(?=[ \t]*\()', 'hljs-built_in'],
                ['[A-Za-z_-]+(?=[ \t]*:)', 'hljs-attr'],
                [':[a-zA-Z_-]+', 'hljs-keyword'],
                ['[.#][A-Za-z_-][\w-]*', 'hljs-tag'],
                ['[>+~*]', 'hljs-operator'],
            ],
            'multi' => [
                'css-comment' => ['hljs-comment', '/\*\//'],
            ],
        ],
        'html' => [
            'rules' => [
                ['<!--', 'hljs-comment', 'html-comment'],
                ['(?i)<!doctype[^>\n]*>', 'hljs-keyword'],
                ['<\/?[A-Za-z][\w:-]*', 'hljs-tag'],
                ['\/?>', 'hljs-tag'],
                ['&[a-zA-Z#][a-zA-Z0-9]*;', 'hljs-literal'],
                ['[A-Za-z_][\w:-]*(?=[ \t]*=)', 'hljs-attr'],
                ['"[^"\n]*"', 'hljs-string'],
                ['\'[^\'\n]*\'', 'hljs-string'],
                ['=', 'hljs-operator'],
            ],
            'multi' => [
                'html-comment' => ['hljs-comment', '/-->/'],
            ],
        ],
        'json' => [
            'rules' => [
                ['"(?:\\\\.|[^"\\\\\n])*"(?=[ \t]*:)', 'hljs-attr'],
                ['"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                [self::NUM, 'hljs-number'],
                ['\b(?:true|false|null)\b', 'hljs-literal'],
            ],
            'multi' => [],
        ],
        'python' => [
            'rules' => [
                ['#[^\n]*', 'hljs-comment'],
                ['"""', 'hljs-string', 'py-docstring-d'],
                ['\'\'\'', 'hljs-string', 'py-docstring-s'],
                ['(?:[rRbBuUfF]{0,2})"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                ['(?:[rRbBuUfF]{0,2})\'(?:\\\\.|[^\'\\\\\n])*\'', 'hljs-string'],
                [self::NUM, 'hljs-number'],
                ['\b(?:def|class|return|if|elif|else|for|while|with|as|import|from|pass|break|continue|lambda|try|except|finally|raise|yield|global|nonlocal|assert|del|in|is|not|and|or|async|await|match|case)\b', 'hljs-keyword'],
                ['\b(?:True|False|None)\b', 'hljs-literal'],
                ['\b(?:self|cls)\b', 'hljs-variable'],
                ['\b(?:print|len|range|str|int|float|list|dict|set|tuple|bool|open|type|isinstance|issubclass|super|enumerate|zip|map|filter|min|max|sum|abs|sorted|any|all|repr|format|input|id|hash|iter|next|reversed|round|divmod|pow|bin|oct|hex|chr|ord|vars|dir|getattr|setattr|hasattr|delattr|callable|staticmethod|classmethod|property|Exception|ValueError|TypeError|KeyError|IndexError|RuntimeError|AttributeError|StopIteration|NotImplementedError|OSError)\b', 'hljs-built_in'],
                ['@[A-Za-z_]\w*(?:\.[A-Za-z_]\w*)*', 'hljs-keyword'],
                ['(?<=\bdef[ \t])[A-Za-z_]\w*', 'hljs-title'],
                ['[A-Za-z_]\w*(?=[ \t]*\()', 'hljs-function'],
                ['->|:=|\*\*|\/\/|==|!=|<=|>=|[-+*\/%=<>!&|^~@]', 'hljs-operator'],
            ],
            'multi' => [
                'py-docstring-d' => ['hljs-string', '/"""/'],
                'py-docstring-s' => ['hljs-string', '/\'\'\'/'],
            ],
        ],
        'bash' => [
            'rules' => [
                ['(?<![^\s])#[^\n]*', 'hljs-comment'],
                ['<<-?[ \t]*["\']?[A-Za-z_]\w*["\']?', 'hljs-string', 'bash-heredoc'],
                ['"', 'hljs-string', 'bash-dquote'],
                ['\'', 'hljs-string', 'bash-squote'],
                ['\$\{[^}\n]*\}|\$\$?[A-Za-z_]\w*|\$[@#?*$!0-9-]', 'hljs-variable'],
                ['\b(?:if|then|else|elif|fi|for|while|until|do|done|case|esac|in|function|select|time|coproc|\[\[|\]\])\b', 'hljs-keyword'],
                ['\b(?:true|false|null)\b', 'hljs-literal'],
                ['\b(?:echo|cd|ls|cp|mv|rm|mkdir|rmdir|touch|cat|grep|egrep|sed|awk|find|xargs|sort|uniq|head|tail|wc|cut|tr|printf|read|export|local|declare|typeset|set|unset|shift|eval|exec|source|alias|unalias|jobs|wait|bg|fg|kill|trap|shopt|let|exit|return|test|sudo|apt|yum|dnf|brew|git|docker|npm|node|python|python3|pip|curl|wget|tar|gzip|zip|unzip|chmod|chown|ln|df|du|ps|top|env|which|man|install|systemctl|service|ssh|scp|rsync|make|gcc|clang|go|cargo|mvn|gradle)\b', 'hljs-built_in'],
                ['(?<![\w.])(?:\d+(?:_\d+)*)', 'hljs-number'],
                ['(?<=\bfunction[ \t])[A-Za-z_]\w*', 'hljs-title'],
                ['[A-Za-z_]\w*(?=[ \t]*\(\s*\))', 'hljs-function'],
                ['&&|\|\||>>|<<|[|&;<>]', 'hljs-operator'],
            ],
            'multi' => [
                'bash-heredoc' => ['hljs-string', ':label'],
                'bash-dquote' => ['hljs-string', '/(?:\\\\.|[^"\\\\\n])*"/'],
                'bash-squote' => ['hljs-string', '/[^\'\n]*\'/'],
            ],
        ],
        'sql' => [
            'rules' => [
                ['\/\*', 'hljs-comment', 'sql-comment'],
                ['(?<![^\s])--[^\n]*', 'hljs-comment'],
                ['(?<![^\s])#[^\n]*', 'hljs-comment'],
                ['\'(?:\'\'|\\\\.|[^\'\\\n])*\'', 'hljs-string'],
                ['"(?:""|\\\\.|[^"\\\n])*"', 'hljs-string'],
                ['`[^`\n]*`', 'hljs-variable'],
                [self::NUM, 'hljs-number'],
                ['(?i)\b(?:select|from|where|insert|into|values|update|delete|create|drop|alter|table|index|view|join|left|right|inner|outer|full|cross|on|as|group|by|order|having|limit|offset|distinct|and|or|not|is|in|between|like|exists|case|when|then|else|end|union|all|primary|key|foreign|references|default|constraint|check|unique|asc|desc|with|returning|set|using|begin|commit|rollback|transaction|grant|revoke|truncate|cascade|if|while|loop|declare|cursor|fetch|procedure|trigger|database|schema|column|add|modify|rename|to|natural|intersect|except|any|some|cast|convert|collate|escape)\b', 'hljs-keyword'],
                ['(?i)\b(?:true|false|null)\b', 'hljs-literal'],
                ['(?i)\b(?:int|integer|bigint|smallint|tinyint|decimal|numeric|float|double|real|bit|boolean|char|varchar|nchar|nvarchar|text|clob|blob|date|time|timestamp|datetime|interval|year|serial|bigserial|uuid|json|jsonb|xml|enum|money)\b', 'hljs-built_in'],
                ['\b[A-Za-z_]\w*(?=[ \t]*\()', 'hljs-function'],
                ['<>|!=|<=|>=|\|\||::|[-+*\/%=<>]', 'hljs-operator'],
            ],
            'multi' => [
                'sql-comment' => ['hljs-comment', '/\*\//'],
            ],
        ],
        'yaml' => [
            'rules' => [
                ['(?<![^\s])#[^\n]*', 'hljs-comment'],
                ['^---|^\.\.\.', 'hljs-keyword'],
                ['[A-Za-z_][\w.-]*(?=[ \t]*:(?:[ \t]|$))', 'hljs-attr'],
                ['\'(?:\'\'|\\\\.|[^\'\\\n])*\'', 'hljs-string'],
                ['"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                ['&[A-Za-z_]\w*', 'hljs-variable'],
                ['\*[A-Za-z_]\w*', 'hljs-variable'],
                ['\b(?:true|false|null|yes|no|on|off)\b', 'hljs-literal'],
                ['(?<![\w.])(?:-?\d+(?:\.\d+)?|-?\.\d+)(?:[eE][+-]?\d+)?', 'hljs-number'],
                ['!!?[A-Za-z_][\w:\/-]*', 'hljs-keyword'],
                ['[|>]', 'hljs-operator'],
            ],
            'multi' => [],
        ],
        'markdown' => [
            'rules' => [
                ['^(?:```|~~~)', 'hljs-keyword'],
                ['^#{1,6}(?=[ \t]|$)', 'hljs-keyword'],
                ['^ {0,3}>', 'hljs-keyword'],
                ['^ {0,3}(?:[-*+]|\d+\.)(?=[ \t])', 'hljs-literal'],
                ['`[^`\n]+`', 'hljs-string'],
                ['\*\*[^*\n]+\*\*', 'hljs-keyword'],
                ['__[^_\n]+__', 'hljs-keyword'],
                ['\*[^*\n]+\*', 'hljs-literal'],
                ['_[^_\n]+_', 'hljs-literal'],
                ['~~[^~\n]+~~', 'hljs-literal'],
                ['\[[^\]\n]*\]\([^)\n]*\)', 'hljs-function'],
                ['!\[[^\]\n]*\]\([^)\n]*\)', 'hljs-attr'],
            ],
            'multi' => [],
        ],
        'go' => [
            'rules' => [
                ['\/\/[^\n]*', 'hljs-comment'],
                ['\/\*', 'hljs-comment', 'go-comment'],
                ['`', 'hljs-string', 'go-raw'],
                ['"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                ['\'(?:\\\\.|[^\'\\\\\n])*\'', 'hljs-string'],
                [self::NUM, 'hljs-number'],
                ['\b(?:break|case|chan|const|continue|default|defer|else|fallthrough|for|func|go|goto|if|import|interface|map|package|range|return|select|struct|switch|type|var)\b', 'hljs-keyword'],
                ['\b(?:true|false|nil|iota)\b', 'hljs-literal'],
                ['\b(?:append|cap|close|complex|copy|delete|imag|len|make|new|panic|print|println|real|recover|min|max|clear|any|error|string|bool|byte|rune|int|int8|int16|int32|int64|uint|uint8|uint16|uint32|uint64|uintptr|float32|float64|complex64|complex128)\b', 'hljs-built_in'],
                ['(?<=\bfunc[ \t])[A-Za-z_]\w*', 'hljs-title'],
                ['(?<=\.)[A-Za-z_]\w*', 'hljs-property'],
                ['\b[A-Za-z_]\w*(?=[ \t]*\()', 'hljs-function'],
                [':=|\.\.\.|<-|&&|\|\||==|!=|<=|>=|<<|>>|[-+*\/%=<>!&|^~]', 'hljs-operator'],
            ],
            'multi' => [
                'go-comment' => ['hljs-comment', '/\*\//'],
                'go-raw' => ['hljs-string', '/`/'],
            ],
        ],
        'ruby' => [
            'rules' => [
                ['^=begin', 'hljs-comment', 'rb-comment'],
                ['#[^\n]*', 'hljs-comment'],
                ['"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                ['\'(?:\\\\.|[^\'\\\\\n])*\'', 'hljs-string'],
                ['::', 'hljs-operator'],
                ['@[A-Za-z_]\w*', 'hljs-variable'],
                ['@@[A-Za-z_]\w*', 'hljs-variable'],
                ['\$[A-Za-z_]\w*', 'hljs-variable'],
                [self::NUM, 'hljs-number'],
                ['\b(?:def|end|if|elsif|else|unless|while|until|for|in|do|begin|rescue|ensure|raise|return|yield|class|module|and|or|not|then|case|when|new|alias|undef|break|next|redo|retry|defined\?)\b', 'hljs-keyword'],
                ['\b(?:true|false|nil)\b', 'hljs-literal'],
                ['\b(?:self|super)\b', 'hljs-variable'],
                ['\b(?:require|require_relative|attr_accessor|attr_reader|attr_writer|include|extend|public|private|protected|lambda|proc|loop|catch|throw|puts|print|p|pp|freeze|frozen\?|send|respond_to\?|is_a\?|kind_of\?|instance_of\?|object_id|inspect|tap)\b', 'hljs-built_in'],
                [':@?[A-Za-z_]\w*[?!=]?', 'hljs-string'],
                ['(?<=\bdef[ \t])[A-Za-z_]\w*[?!=]?', 'hljs-title'],
                ['[A-Za-z_]\w*(?=[ \t]*\()', 'hljs-function'],
                ['=>|->|==|===|!=|=~|!~|<=>|<=|>=|&&|\|\||\.\.\.|::|\*\*|[-+*\/%=<>!&|^~?]', 'hljs-operator'],
            ],
            'multi' => [
                'rb-comment' => ['hljs-comment', '/^=end/'],
            ],
        ],
        'java' => [
            'rules' => [
                ['\/\/[^\n]*', 'hljs-comment'],
                ['\/\*', 'hljs-comment', 'java-comment'],
                ['"(?:\\\\.|[^"\\\\\n])*"', 'hljs-string'],
                ['\'(?:\\\\.|[^\'\\\\\n])*\'', 'hljs-string'],
                [self::NUM, 'hljs-number'],
                ['@[A-Za-z_]\w*', 'hljs-keyword'],
                ['\b(?:abstract|assert|break|case|catch|class|const|continue|default|do|double|else|enum|extends|final|finally|float|for|goto|if|implements|import|instanceof|int|interface|long|native|new|package|private|protected|public|record|return|sealed|short|static|strictfp|super|switch|synchronized|this|throw|throws|transient|try|var|void|volatile|while|yield|permits)\b', 'hljs-keyword'],
                ['\b(?:true|false|null)\b', 'hljs-literal'],
                ['\b(?:String|System|Integer|Double|Float|Long|Short|Byte|Character|Boolean|Object|List|Map|Set|ArrayList|HashMap|HashSet|LinkedList|TreeMap|TreeSet|Collection|Collections|Arrays|Optional|Stream|Exception|RuntimeException|Error|Throwable|Math|Thread|Runnable|StringBuilder|StringBuffer|Comparable|Iterable|Iterator|Number|Void)\b', 'hljs-built_in'],
                ['\b[A-Za-z_]\w*(?=[ \t]*\()', 'hljs-function'],
                ['->|::|\+\+|--|\+=|-=|\*=|\/=|%=|==|!=|<=|>=|&&|\|\||[-+*\/%=<>!&|^~?:]', 'hljs-operator'],
            ],
            'multi' => [
                'java-comment' => ['hljs-comment', '/\*\//'],
            ],
        ],
    ];

    /** token 数量上限，超出即回退纯文本（防失控） */
    private const TOKEN_BUDGET = 200000;

    /** 编译缓存：规范语言名 → [组合正则, rules, multi] */
    private static array $compiled = [];

    /** 判断是否支持某语言（含别名） */
    public static function supports(string $lang): bool
    {
        return isset(self::LANGUAGES[self::normalize($lang)]);
    }

    /** 高亮整段代码，返回转义安全的 HTML */
    public static function highlight(string $code, string $lang): string
    {
        if ($code === '') {
            return '';
        }
        $norm = self::normalize($lang);
        if (!isset(self::LANGUAGES[$norm])) {
            return self::esc($code);
        }
        try {
            $tokens = self::tokenize($code, self::compile($norm));
        } catch (\Throwable) {
            return self::esc($code);
        }
        // 与 highlightLines() 同一策略：跨行 token 在行边界处闭合 span、下一行重开
        return implode("\n", self::renderLines($tokens));
    }

    /**
     * 按行高亮：返回与 highlight() 内容一致、但按行切分的 HTML 数组。
     * 跨行的注释 / heredoc / 多行字符串状态保持，span 在行内闭合、下一行重开，
     * 每行独立完整；implode("\n", 结果) === highlight()。
     */
    public static function highlightLines(string $code, string $lang): array
    {
        if ($code === '') {
            return [];
        }
        $norm = self::normalize($lang);
        if (!isset(self::LANGUAGES[$norm])) {
            return array_map(static fn (string $l): string => self::esc($l), explode("\n", $code));
        }
        try {
            $tokens = self::tokenize($code, self::compile($norm));
        } catch (\Throwable) {
            return array_map(static fn (string $l): string => self::esc($l), explode("\n", $code));
        }
        return self::renderLines($tokens);
    }

    /** 别名归一化 */
    private static function normalize(string $lang): string
    {
        $lang = strtolower(trim($lang));
        return self::ALIASES[$lang] ?? $lang;
    }

    /** 编译语言规则为组合正则（带缓存） */
    private static function compile(string $norm): array
    {
        if (isset(self::$compiled[$norm])) {
            return self::$compiled[$norm];
        }
        $spec = self::LANGUAGES[$norm];
        $parts = [];
        foreach ($spec['rules'] as $rule) {
            $parts[] = '(' . $rule[0] . ')';
        }
        $combined = '/' . implode('|', $parts) . '/';
        // 编译期自检：正则必须可编译
        if (@preg_match($combined, '') === false) {
            throw new \RuntimeException('bad pattern for ' . $norm);
        }
        self::$compiled[$norm] = [$combined, $spec['rules'], $spec['multi']];
        return self::$compiled[$norm];
    }

    /**
     * 状态机分词：逐行处理，多行状态跨行保持。
     * 返回 token 数组 [class|null, text]（text 可含换行）。
     */
    private static function tokenize(string $code, array $spec): array
    {
        [$combined, $rules, $multi] = $spec;
        $tokens = [];
        $state = null; // ['class' => ..., 'end' => 结束正则]
        $lines = explode("\n", $code);
        $last = count($lines) - 1;
        foreach ($lines as $idx => $line) {
            $seg = $idx < $last ? $line . "\n" : $line;
            if ($state !== null) {
                // 多行状态中：结束正则自带 ^ 的（heredoc 标签等）即要求行首，其余任意位置
                if (preg_match($state['end'], $seg, $m, PREG_OFFSET_CAPTURE)) {
                    $closeEnd = $m[0][1] + strlen($m[0][0]);
                    $tokens[] = [$state['class'], substr($seg, 0, $closeEnd)];
                    $state = null;
                    $rest = substr($seg, $closeEnd);
                    if ($rest !== '') {
                        self::tokenizeSegment($rest, $tokens, $state, $combined, $rules, $multi);
                    }
                } else {
                    $tokens[] = [$state['class'], $seg];
                }
            } else {
                self::tokenizeSegment($seg, $tokens, $state, $combined, $rules, $multi);
            }
            if (count($tokens) > self::TOKEN_BUDGET) {
                throw new \RuntimeException('token budget exceeded');
            }
        }
        return $tokens;
    }

    /**
     * 对单行片段做根规则扫描。
     * 单次 match_all + 顺序遍历；多行开始规则行内闭合时跳过其间匹配，
     * 若被跳过的匹配跨越闭合点（注释结束符被拆成星号与行注释），则从闭合点重分割。
     * 每轮必然消费输入，常见路径 O(n)。
     */
    private static function tokenizeSegment(string $seg, array &$tokens, ?array &$state, string $combined, array $rules, array $multi): void
    {
        $len = strlen($seg);
        $offset = 0;
        while ($offset < $len) {
            $rest = substr($seg, $offset);
            $count = preg_match_all($combined, $rest, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            if ($count === false || preg_last_error() !== PREG_NO_ERROR) {
                throw new \RuntimeException('pcre error');
            }
            if ($count === 0) {
                $tokens[] = [null, $rest];
                return;
            }
            $n = count($rules);
            $pos = 0;        // 已处理的段内位置
            $skipUntil = -1; // 多行 token 内部需跳过的匹配终点
            $rescan = false; // 是否有匹配跨越闭合点，需要重分割
            for ($i = 0; $i < $count; $i++) {
                $set = $m[$i];
                $start = $set[0][1];
                $text = $set[0][0];
                $end = $start + strlen($text);
                if ($start < $skipUntil) {
                    if ($end > $skipUntil) {
                        $rescan = true; // 匹配跨越闭合点，从闭合点重新扫描
                    }
                    continue;
                }
                if ($start > $pos) {
                    $tokens[] = [null, substr($rest, $pos, $start - $pos)];
                }
                // 确定命中的规则：第一个非空组
                $ruleIdx = -1;
                for ($j = 1; $j <= $n; $j++) {
                    if ($set[$j][0] !== '') {
                        $ruleIdx = $j - 1;
                        break;
                    }
                }
                if ($ruleIdx >= 0 && isset($rules[$ruleIdx][2])) {
                    $def = $multi[$rules[$ruleIdx][2]];
                    $endRe = $def[1] === ':label' ? self::labelEnd($text) : $def[1];
                    $after = substr($rest, $end);
                    if (preg_match($endRe, $after, $em, PREG_OFFSET_CAPTURE)) {
                        // 行内闭合：begin + 内容 + 结束 合并为一个 token
                        $closeLen = $em[0][1] + strlen($em[0][0]);
                        $tokens[] = [$def[0], substr($rest, $start, strlen($text) + $closeLen)];
                        $skipUntil = $end + $closeLen;
                        $pos = $skipUntil;
                        continue;
                    }
                    // 未闭合：begin + 行尾剩余进入多行状态
                    $tokens[] = [$def[0], $text];
                    if ($after !== '') {
                        $tokens[] = [$def[0], $after];
                    }
                    $state = ['class' => $def[0], 'end' => $endRe];
                    return;
                }
                $tokens[] = [$ruleIdx >= 0 ? $rules[$ruleIdx][1] : null, $text];
                $pos = $end;
            }
            if ($pos < strlen($rest)) {
                if ($rescan) {
                    $offset += $pos; // 从闭合点重分割剩余部分
                    continue;
                }
                $tokens[] = [null, substr($rest, $pos)];
            }
            return;
        }
    }

    /** 由 heredoc 起始匹配生成结束正则（闭合标签必须位于行首） */
    private static function labelEnd(string $beginText): string
    {
        if (preg_match('/<<<?[ \t]*["\']?([A-Za-z_]\w*)/', $beginText, $lm)) {
            return '/^[ \t]*' . $lm[1] . '(?![\w])/';
        }
        return '/(?!x)x/'; // 不可达：永不匹配
    }

    /**
     * token 数组 → 按行 HTML：跨行 token 在换行处闭合 span、下一行重开，
     * 保证每行独立完整；implode("\n", 结果) 即 highlight() 的输出。
     */
    private static function renderLines(array $tokens): array
    {
        $lines = [''];
        foreach ($tokens as [$class, $text]) {
            $pieces = explode("\n", $text);
            $tail = array_pop($pieces);
            foreach ($pieces as $piece) {
                $lines[count($lines) - 1] .= self::wrap($class, $piece);
                $lines[] = '';
            }
            $lines[count($lines) - 1] .= self::wrap($class, $tail);
        }
        return $lines;
    }

    /** 单个 token 包装（空文本不产生 span） */
    private static function wrap(?string $class, string $text): string
    {
        if ($text === '') {
            return '';
        }
        $esc = self::esc($text);
        return $class === null ? $esc : '<span class="' . $class . '">' . $esc . '</span>';
    }

    /** HTML 转义 */
    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
