<?php

namespace Phaseolies\Error\Traces;

use Phaseolies\Error\Utils\Highlighter;

class Frame
{
    private string $file;
    private int $line;
    private string $function;
    private string $class;
    private string $type;
    private array $args;

    public function __construct(array $trace)
    {
        $this->file = $trace['file'] ?? '';
        $this->line = $trace['line'] ?? 0;
        $this->function = $trace['function'] ?? '';
        $this->class = $trace['class'] ?? '';
        $this->type = $trace['type'] ?? '';
        $this->args = $trace['args'] ?? [];
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getShortFile(): string
    {
        $basePath = base_path();
        return str_replace($basePath . '/', '', $this->file);
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getFunction(): string
    {
        return $this->function;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function isVendor(): bool
    {
        return strpos($this->file, 'doppar/framework') !== false;
    }

    public function hasFile(): bool
    {
        return !empty($this->file);
    }

    public function fileExists(): bool
    {
        return $this->hasFile() && file_exists($this->file);
    }

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

    public function getCallSignature(): string
    {
        if (empty($this->class)) {
            return $this->function . '()';
        }

        return $this->class . $this->type . $this->function . '()';
    }

    public static function fromTrace(array $trace): static
    {
        return new static($trace);
    }

    public function getCodeLinesContent(): string
    {
        if (!$this->fileExists() || $this->getLine() <= 0) {
            return '<div class="text-neutral-500 text-sm p-2">No code available</div>';
        }

        $fileLines = file($this->getFile());

        $start = max(0, $this->getLine() - 4);
        $end = min(count($fileLines), $this->getLine() + 3);

        $output = [];

        for ($i = $start; $i < $end; $i++) {
            $number = $i + 1;

            $highlight = $number === $this->getLine();

            $classes = $highlight
                ? 'bg-red-500/10 border-l-2 border-l-red-500 text-red-700 dark:text-red-400'
                : 'text-neutral-600 dark:text-neutral-400';

            $output[] = sprintf(
                '<div class="flex py-0.5 px-2 %s">
                <span class="inline-block w-10 text-right pr-3 text-neutral-400 select-none shrink-0">%d</span>
                <code class="flex-1 whitespace-pre font-mono text-xs">%s</code>
            </div>',
                $classes,
                $number,
                Highlighter::make($fileLines[$i])
            );
        }

        return implode("\n", $output);
    }


    // https://www.php.net/manual/en/language.oop5.magic.php#object.set-state
    public static function __set_state($data): Frame
    {
        return new static($data);
    }
}
