<?php

namespace Phaseolies\Error;

use Phaseolies\Application;
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
        $trace = $exception->getTrace()[0];

        // dd($trace, $trace['file'], $exception->getTraceAsString());

        $errorTrace = $exception->getTraceAsString();
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
                    '<div class="inline-flex w-full bg-red-500/10 border-l-2 border-l-red-500 py-0.5">
                        <span class="w-12 text-right pr-3 text-neutral-500 select-none shrink-0">' . $lineNumber . '</span>
                        <span class="flex-1">' . htmlspecialchars($line) . '</span>
                    </div>';
            } else {
                $highlightedLines[] = '<div class="inline-flex w-full hover:bg-neutral-100 dark:hover:bg-white/5 py-0.5"><span class="w-12 text-right pr-3 text-neutral-500 select-none shrink-0">' . $lineNumber . '</span><span class="flex-1">' . htmlspecialchars($line) . '</span></div>';
            }
        }

        $formattedCode = implode("\n", $highlightedLines);
        date_default_timezone_set(config('app.timezone'));
        $fileExtension = pathinfo($errorFile, PATHINFO_EXTENSION);
        $languageClass = "language-$fileExtension";

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo str_replace(
            [
                '{{ error_message }}',
                '{{ error_file }}',
                '{{ error_line }}',
                '{{ error_trace }}',
                '{{ file_content }}',
                '{{ file_extension }}',
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
                $errorMessage,
                $errorFile,
                $errorLine,
                nl2br(htmlspecialchars($errorTrace)),
                $formattedCode,
                $languageClass,
                PHP_VERSION,
                Application::VERSION,
                request()->getMethod(),
                trim(request()->fullUrl(), '/'),
                now()->toDayDateTimeString(),
                $_SERVER['SERVER_SOFTWARE'],
                php_uname(),
                class_basename($exception),
                $errorCode
            ],
            file_get_contents(__DIR__ . '/error_page_template.html')
        );
    }

    public function buildTraceFrames(array $traces)
    {
        return array_map(function ($trace) {
            '<div class="trace-frame">

            <div>';
        }, $traces);
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
