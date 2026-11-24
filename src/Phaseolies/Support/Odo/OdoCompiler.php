<?php

namespace Phaseolies\Support\Odo;

trait OdoCompiler
{
    /**
     * The file extension for ODO templates (e.g., ".odo.php")
     *
     * @var string|null
     */
    protected $fileExtension;

    /**
     * Path to the view folder containing ODO templates
     *
     * @var string|null
     */
    protected $viewFolder;

    /**
     * The format used when echoing variables (e.g., 'e(%s)')
     *
     * @var string|null
     */
    protected $echoFormat;

    /**
     * Custom compiler extensions registered for ODO
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * Registered ODO directives (e.g., #datetime)
     *
     * @var array
     */
    public static $directives = [];

    /**
     * Compile ODO statements (directives like #if, #foreach, etc.)
     *
     * @param string $statement
     * @return string
     */
    protected function compileStatements($statement): string
    {
        // Get the directive prefix (default: #)
        $prefix = preg_quote($this->directivePrefix, '/');

        // Match directives: #directive or #directive(...)
        $pattern = '/(?:(?<!\w)|(?<=\w)(?=' . $prefix . '(?:end\w+\b|\w+[ \t]*\()))'
            . $prefix . '(' . $prefix . '?\w+(?:->\w+)?)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x';

        return preg_replace_callback($pattern, function ($match) {
            $directiveName = $match[1];
            $isCompiled = false;

            // Handle escaped directives (##directive becomes #directive)
            if (str_starts_with($directiveName, $this->directivePrefix)) {
                return substr($match[0], 1);
            }

            // Check for built-in compile methods
            if (method_exists($this, $method = 'compile' . ucfirst($directiveName))) {
                $match[0] = $this->{$method}(isset($match[3]) ? $match[3] : '');
                $isCompiled = true;
            }

            // Check for custom directives
            if (isset(self::$directives[$directiveName])) {
                $expression = isset($match[3]) ? $match[3] : '';

                if ((isset($expression[0]) && '(' === $expression[0])
                    && (isset($expression[strlen($expression) - 1]) && ')' === $expression[strlen($expression) - 1])
                ) {
                    $expression = substr($expression, 1, -1);
                }

                $match[0] = call_user_func(self::$directives[$directiveName], trim($expression));
                $isCompiled = true;
            }

            // If compiled, return as-is
            // If not compiled and has no expression, preserve original with whitespace
            return $isCompiled ? $match[0] : (isset($match[3]) ? $match[0] : $match[0] . $match[2]);
        }, $statement);
    }

    /**
     * Compile ODO comments (e.g., [[-- comment --]])
     *
     * @param string $comment
     * @return string
     */
    protected function compileComments($comment): string
    {
        $open = preg_quote($this->openCommentTag, '/');
        $close = preg_quote($this->closeCommentTag, '/');

        return preg_replace('/' . $open . '((.|\s)*?)' . $close . '/', '<?php /*$1*/ ?>', $comment);
    }

    /**
     * Compile ODO echo statements
     *
     * @param string $string
     * @return string
     */
    protected function compileEchos($string): string
    {
        // Compile escaped echoes (e.g., [[[ $var ]]])
        $string = $this->replaceEchoTags($string, $this->openEscapedEchoTag, $this->closeEscapedEchoTag, false, function (string $expression): string {
            return '<?php echo $this->e(' . $this->compileEchoDefaults($expression) . ') ?>';
        });

        // Compile unescaped/raw echoes (e.g., [[! $var !]])
        $string = $this->replaceEchoTags($string, $this->openRawEchoTag, $this->closeRawEchoTag, false, function (string $expression): string {
            return '<?php echo ' . $this->compileEchoDefaults($expression) . ' ?>';
        });

        return $this->replaceEchoTags($string, $this->openEchoTag, $this->closeEchoTag, true, function (string $expression): string {
            return '<?php echo ' . sprintf($this->echoFormat, $this->compileEchoDefaults($expression)) . ' ?>';
        });
    }

    /**
     * Replace every echo tag pair in a template.
     *
     * @param string $string
     * @param string $open
     * @param string $close
     * @param bool $allowEscape
     * @param callable(string): string $compile
     * @return string
     */
    protected function replaceEchoTags(string $string, string $open, string $close, bool $allowEscape, callable $compile): string
    {
        $result = '';
        $offset = 0;
        $openLength = strlen($open);
        $closeLength = strlen($close);

        while (($start = strpos($string, $open, $offset)) !== false) {
            $prefixLength = strlen($this->directivePrefix);

            if (
                $allowEscape
                && $start >= $prefixLength
                && substr($string, $start - $prefixLength, $prefixLength) === $this->directivePrefix
            ) {
                $result .= substr($string, $offset, $start - $offset - $prefixLength) . $open;
                $offset = $start + $openLength;

                continue;
            }

            $end = $this->findEchoEnd($string, $start + $openLength, $close);

            if ($end === null) {
                break;
            }

            $expression = trim(substr($string, $start + $openLength, $end - $start - $openLength));
            $after = $end + $closeLength;

            $newline = '';

            if (preg_match('/\G\r?\n/', $string, $match, 0, $after)) {
                $newline = $match[0] . $match[0];
                $after += strlen($match[0]);
            }

            $result .= substr($string, $offset, $start - $offset);
            $result .= $expression === '' ? $open . $close : $compile($expression) . $newline;
            $offset = $after;
        }

        return $result . substr($string, $offset);
    }

    /**
     * Find where an echo expression ends
     *
     * @param string $string
     * @param int $position
     * @param string $close
     * @return int|null
     */
    protected function findEchoEnd(string $string, int $position, string $close): ?int
    {
        $length = strlen($string);
        $closeLength = strlen($close);
        $depth = 0;
        $quote = null;

        for ($i = $position; $i < $length; $i++) {
            $char = $string[$i];

            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($depth === 0 && substr_compare($string, $close, $i, $closeLength) === 0) {
                return $i;
            }

            match ($char) {
                '\'', '"' => $quote = $char,
                '[', '(', '{' => $depth++,
                ']', ')', '}' => $depth = max(0, $depth - 1),
                default => null,
            };
        }

        return null;
    }

    /**
     * Compile default echo expressions (handle 'or' operator)
     *
     * @param string $string
     * @return string
     */
    public function compileEchoDefaults($string): string
    {
        return preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $string);
    }

    /**
     * Compile user-defined extensions
     *
     * @param string $string
     * @return string
     */
    protected function compileExtensions($string): string
    {
        foreach ($this->extensions as $compiler) {
            $string = $compiler($string, $this);
        }

        return $string;
    }

    /**
     * PHP blocks set aside while the rest of the template is compiled.
     *
     * @var array<string, string>
     */
    protected array $protectedPhpBlocks = [];

    /**
     * Set #php ... #endphp blocks aside before compiling
     *
     * @param string $string
     * @return string
     */
    public function protectPhpBlocks(string $string): string
    {
        $prefix = preg_quote($this->directivePrefix, '/');

        return preg_replace_callback('/(?<!' . $prefix . ')' . $prefix . 'php(?![ \t]*\()(?!\w)(.*?)' . $prefix . 'endphp/s', function ($matches) {
            $key = '@__odo_php_block_' . count($this->protectedPhpBlocks) . '__@';
            $this->protectedPhpBlocks[$key] = $this->phpBlock($matches[1]);

            return $key;
        }, $string);
    }

    /**
     * Put the PHP blocks set aside by protectPhpBlocks() back.
     *
     * @param string $string
     * @return string
     */
    public function restorePhpBlocks(string $string): string
    {
        if ($this->protectedPhpBlocks !== []) {
            $string = strtr($string, $this->protectedPhpBlocks);
            $this->protectedPhpBlocks = [];
        }

        return $string;
    }

    /**
     * Replace PHP blocks (e.g., #php ... #endphp)
     *
     * @param string $string
     * @return string
     */
    public function replacePhpBlocks($string): string
    {
        $prefix = preg_quote($this->directivePrefix, '/');

        // Match #php ... #endphp blocks (not escaped with ##)
        $string = preg_replace_callback('/(?<!' . $prefix . ')' . $prefix . 'php(.*?)' . $prefix . 'endphp/s', function ($matches) {
            return $this->phpBlock($matches[1]);
        }, $string);

        return $string;
    }

    /**
     * Wrap block code in PHP tags. "<?php" must be followed by whitespace
     *
     * @param string $code
     * @return string
     */
    protected function phpBlock(string $code): string
    {
        $before = $code !== '' && ctype_space($code[0]) ? '' : ' ';
        $after = $code !== '' && ctype_space($code[strlen($code) - 1]) ? '' : ' ';

        return '<?php' . $before . $code . $after . '?>';
    }

    /**
     * Escape variables for HTML output
     *
     * @param string|array|null $string
     * @param string $charset
     * @return string|null
     */
    public function e(mixed $string, ?string $charset = null): ?string
    {
        if ($string === null) {
            return null;
        }

        if (is_array($string)) {
            $string = implode(' ', $string);
        }

        return htmlspecialchars((string)$string, ENT_QUOTES, $charset ?? 'UTF-8');
    }
}
