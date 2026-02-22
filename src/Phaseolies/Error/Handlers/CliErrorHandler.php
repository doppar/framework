<?php

namespace Phaseolies\Error\Handlers;

use Throwable;
use Phaseolies\Error\Contracts\ErrorHandlerInterface;

class CliErrorHandler implements ErrorHandlerInterface
{
    /**
     * Maximum number of stack frames to display.
     *
     * @var int
     */
    protected int $maxFrames = 10;

    /**
     * Number of lines of source context to show around the error line.
     *
     * @var int
     */
    protected int $sourceContextLines = 5;

    /**
     * Handle the exception by rendering it to the console.
     * 
     * @param Throwable $exception
     * @return void
     */
    public function handle(Throwable $exception): void
    {
        $this->renderException($exception);

        exit(1);
    }

    /**
     * Check if this handler supports the current environment (CLI).
     *
     * @return bool
     */
    public function supports(): bool
    {
        return PHP_SAPI === 'cli' || defined('STDIN');
    }

    /**
     * Render the full exception output, including chained causes.
     *
     * @param Throwable $exception
     * @param bool $isCause
     * @return void
     */
    protected function renderException(Throwable $exception, bool $isCause = false): void
    {
        // Recurse to show the root cause first
        if ($exception->getPrevious()) {
            $this->renderException($exception->getPrevious(), true);
            $this->writeln();
        }

        $label = $isCause ? 'Caused by' : 'Error';
        $class = get_class($exception);
        $message = $exception->getMessage();
        $file = $this->relativePath($exception->getFile());
        $line = $exception->getLine();

        // Header
        $this->writeln();
        $this->writeln($this->bg('  ' . strtoupper($label) . '  ', 'white', 'red') . '  ' . $this->fg($class, 'red', bold: true));
        $this->writeln();

        // Message
        foreach (explode("\n", wordwrap($message, 72, "\n")) as $msgLine) {
            $this->writeln('  ' . $this->fg($msgLine, 'white'));
        }
        $this->writeln();

        // Location
        $this->writeln(
            '  ' . $this->fg('at ', 'gray') .
            $this->fg($file, 'green') .
            $this->fg(':', 'gray') .
            $this->fg((string) $line, 'yellow')
        );
        $this->writeln();

        // Source preview
        $this->renderSourceContext($exception->getFile(), $line);

        // Stack trace
        $this->renderStackTrace($exception);
    }

    /**
     * Render a snippet of the source file around the error line.
     *
     * @param string $file
     * @param int $errorLine
     * @return void
     */
    protected function renderSourceContext(string $file, int $errorLine): void
    {
        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $total = count($lines);
        $start = max(0, $errorLine - $this->sourceContextLines - 1);
        $end = min($total - 1, $errorLine + $this->sourceContextLines - 1);
        $gutterWidth = strlen((string) ($end + 1));

        $this->separator();

        for ($i = $start; $i <= $end; $i++) {
            $lineNum = $i + 1;
            $isError = $lineNum === $errorLine;
            $gutter = str_pad((string) $lineNum, $gutterWidth, ' ', STR_PAD_LEFT);
            $code = $this->expandTabs($lines[$i] ?? '');

            if ($isError) {
                $this->write($this->fg(' ► ', 'red', bold: true));
                $this->write($this->fg($gutter, 'red'));
                $this->write($this->fg(' │ ', 'red'));
                $this->writeln($this->fg($code, 'white'));
            } else {
                $this->write($this->fg('   ', 'gray'));
                $this->write($this->fg($gutter, 'gray'));
                $this->write($this->fg(' │ ', 'gray'));
                $this->writeln($this->fg($code, 'gray'));
            }
        }

        $this->separator();
        $this->writeln();
    }

    /**
     * Render the formatted stack trace.
     *
     * @param Throwable $exception
     * @return void
     */
    protected function renderStackTrace(Throwable $exception): void
    {
        $frames = $exception->getTrace();
        $count = min(count($frames), $this->maxFrames);
        $hidden = max(0, count($frames) - $this->maxFrames);

        $this->writeln('  ' . $this->fg('Stack trace:', 'yellow', bold: true));
        $this->writeln();

        for ($i = 0; $i < $count; $i++) {
            $frame = $frames[$i];
            $number = str_pad((string) ($i + 1), 3, ' ', STR_PAD_LEFT);

            $location = isset($frame['file'])
                ? $this->relativePath($frame['file']) . ':' . ($frame['line'] ?? '?')
                : '[internal]';

            $call = $this->formatCall($frame);

            $this->writeln(
                $this->fg($number, 'gray') . '  ' .
                $this->fg($location, 'green') . "\n" .
                '       ' . $this->fg($call, 'white')
            );
        }

        if ($hidden > 0) {
            $this->writeln('       ' . $this->fg("… {$hidden} more frame(s) hidden", 'gray'));
        }

        $this->writeln();
    }

    /**
     * Format a single stack frame's call signature.
     *
     * @param array $frame
     * @return void
     */
    protected function formatCall(array $frame): string
    {
        $call = '';

        if (isset($frame['class'])) {
            $call .= $frame['class'] . ($frame['type'] ?? '::');
        }

        $call .= ($frame['function'] ?? '{closure}') . '(';

        if (!empty($frame['args'])) {
            $args = array_map(fn($arg) => $this->formatArg($arg), $frame['args']);
            $call .= implode(', ', $args);
        }

        $call .= ')';

        return $call;
    }

    /**
     * Format a single argument value for display.
     *
     * @param mixed $arg
     * @return string
     */
    protected function formatArg(mixed $arg): string
    {
        return match (true) {
            is_null($arg)    => 'null',
            is_bool($arg)    => $arg ? 'true' : 'false',
            is_int($arg)     => (string) $arg,
            is_float($arg)   => (string) $arg,
            is_string($arg)  => '"' . (strlen($arg) > 30 ? substr($arg, 0, 27) . '...' : $arg) . '"',
            is_array($arg)   => 'array(' . count($arg) . ')',
            is_object($arg)  => get_class($arg),
            default          => gettype($arg),
        };
    }

    /**
     * Write raw text to STDERR without a newline.
     *
     * @param string $text
     * @return void
     */
    protected function write(string $text): void
    {
        fwrite(STDERR, $text);
    }

    /**
     * Write text to STDERR followed by a newline.
     *
     * @param string $text
     * @return void
     */
    protected function writeln(string $text = ''): void
    {
        fwrite(STDERR, $text . PHP_EOL);
    }

    /**
     * Output a visual separator line in gray color.
     *
     * @return void
     */
    protected function separator(): void
    {
        $this->writeln($this->fg('   ' . str_repeat('─', 70), 'gray'));
    }

    /**
     * Apply ANSI foreground color formatting to text.
     *
     * @param string $text
     * @param string $color
     * @param bool $bold
     * @return string
     */
    protected function fg(string $text, string $color, bool $bold = false): string
    {
        $codes = [
            'black'  => '30', 'red'    => '31', 'green' => '32',
            'yellow' => '33', 'blue'   => '34', 'magenta' => '35',
            'cyan'   => '36', 'white'  => '37', 'gray'  => '90',
        ];

        $code = $codes[$color] ?? '37';
        $prefix = $bold ? "\033[1;{$code}m" : "\033[{$code}m";

        return $prefix . $text . "\033[0m";
    }

    /**
     * Apply ANSI foreground and background color formatting to text.
     *
     * @param string $text
     * @param string $fg
     * @param string $bg
     * @return string
     */
    protected function bg(string $text, string $fg, string $bg): string
    {
        $fgCodes = ['white' => '37', 'black' => '30'];
        $bgCodes = ['red' => '41', 'yellow' => '43', 'blue' => '44'];

        return "\033[1;" . ($fgCodes[$fg] ?? '37') . ';' . ($bgCodes[$bg] ?? '41') . "m" . $text . "\033[0m";
    }

    /**
     * Convert an absolute path to a relative path based on current working directory.
     *
     * @param string $path
     * @return string
     */
    protected function relativePath(string $path): string
    {
        $cwd = getcwd();
        if ($cwd && str_starts_with($path, $cwd)) {
            return '.' . substr($path, strlen($cwd));
        }
        return $path;
    }

    /**
     * Replace tab characters with spaces.
     *
     * @param string $line
     * @param int
     * @return string
     */
    protected function expandTabs(string $line, int $tabSize = 4): string
    {
        return str_replace("\t", str_repeat(' ', $tabSize), $line);
    }
}