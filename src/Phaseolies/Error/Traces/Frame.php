<?php

namespace Phaseolies\Error\Traces;

use Phaseolies\Error\Utils\Highlighter;
use Phaseolies\Error\Utils\PathResolver;

class Frame
{
    /**
     * Absolute path of the file for this frame
     *
     * @var string
     */
    private string $file;

    /**
     * Line number in the file where this frame occurred
     *
     * @var int
     */
    private int $line;

    /**
     * Name of the function invoked
     *
     * @var string
     */
    private string $function;

    /**
     * Name of the class (if applicable)
     *
     * @var string
     */
    private string $class;

    /**
     * Type of call (e.g., '->', '::')
     *
     * @var string
     */
    private string $type;

    /**
     * Arguments passed to the function or method
     *
     * @var array
     */
    private array $args;

    /**
     * Create a new Frame instance from a raw trace array.
     *
     * @param array $trace
     */
    public function __construct(array $trace)
    {
        $this->file = $trace['file'] ?? '';
        $this->line = $trace['line'] ?? 0;
        $this->function = $trace['function'] ?? '';
        $this->class = $trace['class'] ?? '';
        $this->type = $trace['type'] ?? '';
        $this->args = $trace['args'] ?? [];
    }

    /**
     * Get the absolute file path for this frame
     *
     * @return string
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Get the file path as a namespace-style path (e.g. "App/Http/Controllers/HomeController.php"),
     * falling back to a path relative to the application's base path.
     *
     * @return string
     */
    public function getShortFile(): string
    {
        return PathResolver::toDisplayPath($this->file);
    }

    /**
     * Get the line number where this frame occurred
     *
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Get the function name associated with this frame
     *
     * @return string
     */
    public function getFunction(): string
    {
        return $this->function;
    }

    /**
     * Get the class name associated with this frame
     *
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Get the call type (e.g., '->' or '::')
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the function or method arguments
     *
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Determine if this frame belongs to vendor (framework) code.
     *
     * @return bool
     */
    public function isVendor(): bool
    {
        return strpos($this->file, '/vendor/') !== false
            || strpos($this->file, 'doppar/framework') !== false
            || strpos($this->file, 'package/doppar/src') !== false;
    }

    /**
     * Check if this frame has a valid file path
     *
     * @return bool
     */
    public function hasFile(): bool
    {
        return !empty($this->file);
    }

    /**
     * Check if the referenced file exists on disk
     *
     * @return bool
     */
    public function fileExists(): bool
    {
        return $this->hasFile() && file_exists($this->file);
    }

    /**
     * Create a collection of Frame instances from a raw trace array.
     *
     * @param array $traces
     * @return array<Frame>
     */
    public static function extractFramesCollectionFromEngine(array $traces)
    {
        $frames = [];

        foreach ($traces as $trace) {
            $frame = self::fromTrace($trace);

            if (!$frame->hasFile()) {
                continue;
            }

            $frames[] = $frame;
        }

        return $frames;
    }

    /**
     * Retrieve the surrounding code lines near the error line.
     *
     * @return array
     */
    public function getCodeLines(): array
    {
        if (!$this->fileExists() || $this->line <= 0) {
            return [];
        }

        $fileLines = file($this->file);
        $startLine = max(0, $this->line - 4);
        $endLine = min(count($fileLines), $this->line + 3);

        return array_slice($fileLines, $startLine, $endLine - $startLine);
    }

    /**
     * Get a formatted string representing the method or function call.
     *
     * @return string
     */
    public function getCallSignature(): string
    {
        if (empty($this->class)) {
            return $this->function . '()';
        }

        return $this->class . $this->type . $this->function . '()';
    }

    /**
     * Create a Frame instance from trace data.
     *
     * @param array $trace
     * @return static
     */
    public static function fromTrace(array $trace): static
    {
        return new static($trace);
    }

    /**
     * Render syntax-highlighted code lines surrounding the frame line as HTML.
     *
     * @return string
     */
    public function getCodeLinesContent(): string
    {
        if (!$this->fileExists() || $this->getLine() <= 0) {
            return '<div class="frame-no-code">No source available for this frame.</div>';
        }

        $fileLines = file($this->getFile());

        $start = max(0, $this->getLine() - 4);
        $end = min(count($fileLines), $this->getLine() + 3);

        $output = [];

        for ($i = $start; $i < $end; $i++) {
            $number = $i + 1;

            $class = $number === $this->getLine() ? 'code-line-error' : 'code-line';

            $output[] = sprintf(
                '<div class="%s"><span class="code-line-number">%d</span><span class="code-line-content">%s</span></div>',
                $class,
                $number,
                Highlighter::make($fileLines[$i])
            );
        }

        return implode("\n", $output);
    }

    /**
     * Reconstruct a Frame object from an exported state.
     *
     * @see https://www.php.net/manual/en/language.oop5.magic.php#object.set-state
     * @param array $data
     * @return Frame
     */
    public static function __set_state($data): Frame
    {
        return new static($data);
    }
}
