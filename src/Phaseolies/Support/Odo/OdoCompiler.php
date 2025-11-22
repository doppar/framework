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
    protected static $directives = [];

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
        $pattern = '/\B' . $prefix . '(' . $prefix . '?\w+(?:->\w+)?)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x';

        return preg_replace_callback($pattern, function ($match) {
            $directiveName = $match[1];

            // Handle escaped directives (##directive becomes #directive)
            if (str_starts_with($directiveName, $this->directivePrefix)) {
                return substr($match[0], 1);
            }

            // Check for built-in compile methods
            if (method_exists($this, $method = 'compile' . ucfirst($directiveName))) {
                $match[0] = $this->{$method}(isset($match[3]) ? $match[3] : '');
            }

            // Check for custom directives
            if (isset(self::$directives[$directiveName])) {
                $expression = isset($match[3]) ? $match[3] : '';

                // Remove surrounding parentheses
                if ((isset($expression[0]) && '(' === $expression[0])
                    && (isset($expression[strlen($expression) - 1]) && ')' === $expression[strlen($expression) - 1])
                ) {
                    $expression = substr($expression, 1, -1);
                }

                if ($expression !== '' && $expression !== '()') {
                    $match[0] = call_user_func(self::$directives[$directiveName], trim($expression));
                }
            }

            return isset($match[3]) ? $match[0] : $match[0] . $match[2];
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
        // Escape tags for regex
        $openEscaped = preg_quote($this->openEscapedEchoTag, '/');
        $closeEscaped = preg_quote($this->closeEscapedEchoTag, '/');
        $openRaw = preg_quote($this->openRawEchoTag, '/');
        $closeRaw = preg_quote($this->closeRawEchoTag, '/');
        $openEcho = preg_quote($this->openEchoTag, '/');
        $closeEcho = preg_quote($this->closeEchoTag, '/');

        // Compile escaped echoes (e.g., [[[ $var ]]])
        $string = preg_replace_callback('/' . $openEscaped . '\s*(.+?)\s*' . $closeEscaped . '(\r?\n)?/s', function ($matches) {
            $whitespace = empty($matches[2]) ? '' : $matches[2] . $matches[2];
            return '<?php echo $this->e(' . $this->compileEchoDefaults($matches[1]) . ') ?>' . $whitespace;
        }, $string);

        // Compile unescaped/raw echoes (e.g., [[! $var !]])
        $string = preg_replace_callback('/' . $openRaw . '\s*(.+?)\s*' . $closeRaw . '(\r?\n)?/s', function ($matches) {
            $whitespace = empty($matches[2]) ? '' : $matches[2] . $matches[2];
            return '<?php echo ' . $this->compileEchoDefaults($matches[1]) . ' ?>' . $whitespace;
        }, $string);

        // Compile regular echoes (e.g., [[ $var ]])
        // Check for escaped echo tags (e.g., #[[ becomes [[)
        $string = preg_replace_callback('/(' . $prefix = preg_quote($this->directivePrefix, '/') . ')?' . $openEcho . '\s*(.+?)\s*' . $closeEcho . '(\r?\n)?/s', function ($matches) {
            $whitespace = empty($matches[3]) ? '' : $matches[3] . $matches[3];

            // If prefixed with directive symbol, escape it
            if (!empty($matches[1])) {
                return substr($matches[0], 1);
            }

            return '<?php echo '
                . sprintf($this->echoFormat, $this->compileEchoDefaults($matches[2]))
                . ' ?>' . $whitespace;
        }, $string);

        return $string;
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
            return "<?php{$matches[1]}?>";
        }, $string);

        return $string;
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
