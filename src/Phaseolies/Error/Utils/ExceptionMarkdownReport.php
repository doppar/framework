<?php

namespace Phaseolies\Error\Utils;

use Throwable;
use Phaseolies\Application;
use Phaseolies\Http\Request;

class ExceptionMarkdownReport
{
    protected Throwable $exception;
    protected Request $request;

    public function __construct(Throwable $exception)
    {
        $this->exception = $exception;
        $this->request = app('request');
    }

    public function generate(): string
    {
        $md = [];

        // Header & Basic Info
        $md[] = "# Exception Report";
        $md[] = '';
        $md[] = "## Exception Details";
        $md[] = "- **Class:** " . get_class($this->exception);
        $md[] = "- **Message:** " . $this->exception->getMessage();
        $md[] = "- **File:** `" . $this->exception->getFile() . "`";
        $md[] = "- **Line:** `" . $this->exception->getLine() . "`";
        $md[] = "- **Code:** `" . ($this->exception->getCode() ?: 0) . "`";
        $md[] = '';

        // Environment info
        $md[] = "## Environment";
        $md[] = "- **PHP Version:** " . PHP_VERSION;
        $md[] = "- **Doppar Version:** " . Application::VERSION;
        $md[] = "- **Server Software:** " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown');
        $md[] = "- **Platform:** " . php_uname();
        $md[] = "- **Timestamp:** " . now()->toDayDateTimeString();
        $md[] = '';

        // Request's Info
        $md[] = "## Request";
        $md[] = "- **Method:** " . $this->request->getMethod();
        $md[] = "- **URL:** " . $this->request->fullUrl();
        $md[] = '';

        // Request's Headers
        $md[] = "### Headers";
        foreach ($this->request->headers->all() as $header => $values) {
            $joined = implode(', ', $values);
            $md[] = "- **{$header}:** {$joined}";
        }
        $md[] = '';

        // Route Parameters (if any)
        if ($routeParams = $this->request->getRouteParams()) {
            
            $md[] = "### Route Parameters";
            
            foreach ($routeParams as $key => $value) {
                $md[] = "- **{$key}:** " . (is_scalar($value) ? $value : json_encode($value));
            }
            
            $md[] = '';
        }

        // Code Context Around Error Line
        $md[] = "## Code Context";
        $file = $this->exception->getFile();
        $line = $this->exception->getLine();

        if (file_exists($file)) {
            $lines = file($file);
            $start = max(0, $line - 6);
            $slice = array_slice($lines, $start, 12, true);
            $codeBlock = '';

            foreach ($slice as $lineNumber => $codeLine) {
                $lineNum = $lineNumber + 1;
                $prefix = $lineNum === $line ? '>> ' : '   ';
                $codeBlock .= $prefix . $lineNum . ' ' . rtrim($codeLine) . "\n";
            }

            $md[] = "```php";
            $md[] = rtrim($codeBlock);
            $md[] = "```";
        } else {
            $md[] = "_File not found or unreadable._";
        }
        $md[] = '';

        // Stack Trace as string heere
        $md[] = "## Stack Trace";
        $md[] = "```";
        $md[] = $this->exception->getTraceAsString();
        $md[] = "```";

        return implode("\n", $md);
    }
}
