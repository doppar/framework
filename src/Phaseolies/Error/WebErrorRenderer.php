<?php

namespace Phaseolies\Error;

use Phaseolies\Application;
use Phaseolies\Support\View\View;
use Throwable;

class WebErrorRenderer
{
    /**
     * Render a detailed debug error page.
     *
     * @param Throwable $exception
     * @return void
     */
    public function renderDebug(Throwable $exception): void
    {
        $errorMessage = $exception->getMessage();
        $errorFile = $exception->getFile();
        $errorLine = $exception->getLine();
        $errorCode = $exception->getCode() ?: 500;

        $fileContent = file_exists($errorFile) ? file_get_contents($errorFile) : 'File not found.';
        $lines = explode("\n", $fileContent);
        
        $startLine = max(0, $errorLine - 10);
        $endLine = min(count($lines) - 1, $errorLine + 100);
        $displayedLines = array_slice($lines, $startLine, $endLine - $startLine + 1);

         $highlightedLines = [];
        foreach ($displayedLines as $index => $line) {
            $lineNumber = $startLine + $index + 1;
            if ($lineNumber == $errorLine) {
                $highlightedLines[] =
                    '<div class="code-line-error">
                        <span class="code-line-number">' . $lineNumber . '</span>
                        <span class="code-line-content">' . htmlspecialchars($line) . '</span>
                    </div>';
            } else {
                $highlightedLines[] = '<div class="code-line"><span class="code-line-number">' . $lineNumber . '</span><span class="code-line-content">' . htmlspecialchars($line) . '</span></div>';
            }
        }

        $formattedCode = implode("\n", $highlightedLines);

        $traceFramesHtml = $this->buildTraceFrames($exception->getTrace());
        
        date_default_timezone_set(config('app.timezone'));

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        // dd((new View)->render(file_get_contents(__DIR__ . '/views/template.blade.php')));

        echo str_replace(
            [
                '{{ error_message }}',
                '{{ error_file }}',
                '{{ error_line }}',
                '{{ error_trace }}',
                '{{ file_content }}',
                '{{ php_version }}',
                '{{ doppar_version }}',
                '{{ request_method }}',
                '{{ request_url }}',
                '{{ timestamp }}',
                '{{ server_software }}',
                '{{ platform }}',
                '{{ exception_class }}',
                '{{ status_code }}'
            ],
            [
                htmlspecialchars($errorMessage),
                htmlspecialchars($errorFile),
                $errorLine,
                $traceFramesHtml,
                $formattedCode,
                PHP_VERSION,
                Application::VERSION,
                htmlspecialchars(request()->getMethod()),
                htmlspecialchars(trim(request()->fullUrl(), '/')),
                htmlspecialchars(now()->toDayDateTimeString()),
                htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'),
                htmlspecialchars(php_uname()),
                htmlspecialchars(class_basename($exception)),
                $errorCode
            ],
            file_get_contents(__DIR__ . '/views/template.blade.php')
        );
    }

    private function buildTraceFrames(array $traces): string
    {
        if (empty($traces)) {
            return '<div class="text-neutral-500 text-sm p-4">No stack trace available</div>';
        }

        $html = '<div class="space-y-2">';

        foreach ($traces as $index => $trace) {
            $file = $trace['file'] ?? 'unknown';
            $line = $trace['line'] ?? 0;
            $function = $trace['function'] ?? '';
            $class = $trace['class'] ?? '';
            $type = $trace['type'] ?? '';

            $signature = $class ? $class . $type . $function . '()' : $function . '()';
            
            $isVendor = strpos($file, 'doppar/framework') !== false; // a hack until completing this work, cz the framework code now isn't under vendor, is at framework/*
            
            
            $vendorClass = $isVendor ? 'vendor-frame' : '';
            $shortFile = $this->shortenPath($file);
            $filePreview = $this->getFilePreview($file, $line);

            $html .= sprintf(
                '<div class="trace-frame %s" data-frame="%d">
                    <div class="trace-frame-header" onclick="toggleTraceFrame(%d)">
                        <span class="trace-frame-number">%d</span>
                        <div class="trace-frame-info">
                            <div class="trace-frame-signature">%s</div>
                            <div class="trace-frame-path">%s:%s</div>
                        </div>
                        <svg class="trace-frame-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div class="frame-content trace-frame-content hidden">
                        %s
                    </div>
                </div>',
                $vendorClass,
                $index,
                $index,
                $index + 1,
                htmlspecialchars($signature),
                htmlspecialchars($shortFile),
                $line,
                $filePreview
            );
        }

        $html .= '</div>';

        return $html;
    }

    private function shortenPath(string $path): string
    {
        $basePath = dirname(__DIR__, 3);
        return str_replace($basePath . '/', '', $path);
    }

    private function getFilePreview(string $file, int $line): string
    {
        if (!file_exists($file) || $line <= 0) {
            return '<div class="p-3 text-sm text-neutral-500">File preview not available</div>';
        }

        $lines = file($file);
        $startLine = max(0, $line - 4);
        $endLine = min(count($lines), $line + 3);

        $html = '<div class="trace-frame-preview">';

        for ($i = $startLine; $i < $endLine; $i++) {
            $lineNumber = $i + 1;
            $lineContent = $lines[$i] ?? '';
            $isHighlight = $lineNumber === $line;
            $lineClass = $isHighlight ? 'preview-line-error' : 'preview-line';

            $html .= sprintf(
                '<div class="%s"><span class="preview-line-number">%d</span><span class="preview-line-content">%s</span></div>',
                $lineClass,
                $lineNumber,
                htmlspecialchars($lineContent)
            );
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a simple production-safe error response.
     *
     * @param Throwable $exception
     * @return void
     */
    public function renderProduction(Throwable $exception): void
    {
        abort(500, "Something went wrong");
    }
}