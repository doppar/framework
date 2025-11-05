<?php

namespace Phaseolies\Error\Traces;

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

    public static function collectionFromEngine(array $traces)
    {
        $frames = [];

        foreach ($traces as $trace) {
            $frame = self::fromTrace($trace);

            if (!$frame->hasFile()) {
                continue;
            }

            $frames[] = $frame;
        }

        return dd($frames);
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

    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'short_file' => $this->getShortFile(),
            'line' => $this->line,
            'function' => $this->function,
            'class' => $this->class,
            'type' => $this->type,
            'is_vendor' => $this->isVendor(),
            'lines' => $this->getCodeLines(),
            'call_signature' => $this->getCallSignature(),
        ];
    }

    public static function fromTrace(array $trace): self
    {
        return new self($trace);
    }


    public static function collectionToArray(array $traces): array
    {
        return array_map(
            fn(Frame $frame) => $frame->toArray(),
            self::collection($traces)
        );
    }

    public static function collectionForView(array $traces): array
    {
        return self::collection($traces);
    }
}
