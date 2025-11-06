<?php

namespace Phaseolies\Error;

use Phaseolies\Application;
use Phaseolies\Error\Traces\Frame;
use Phaseolies\Http\Controllers\Controller;
use Throwable;

class WebErrorRenderer
{
    /**
     * Render a detailed debug error page.
     *
     * @param Throwable $exception
     * @return void
     */
    public function renderDebug(Throwable $exception): string
    {
        $errorFile = $exception->getFile();
        $errorLine = $exception->getLine();

        $fileContent = file_exists($errorFile) ? file_get_contents($errorFile) : 'File not found.';
        $lines = explode("\n", $fileContent);

        $startLine = max(0, $errorLine - 10);
        $endLine = min(count($lines) - 1, $errorLine + 100);
        $displayedLines = array_slice($lines, $startLine, $endLine - $startLine + 1);

        $codeLines = [];

        foreach ($displayedLines as $index => $line) {
            $lineNumber = $startLine + $index + 1;
            $codeLines[] = [
                'number' => $lineNumber,
                'content' => $line,
                'is_error' => $lineNumber == $errorLine,
            ];
        }


        date_default_timezone_set(config('app.timezone'));

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $controller = new Controller();

        $basePath = base_path();

        $currentDir = __DIR__;

        $relative = str_replace($basePath . '/', '', $currentDir);

        $viewsPath = $relative . '/views';

        $controller->setViewFolder($viewsPath);

        return $controller->render('template', [
            'traces'          => Frame::extractFramesCollectionFromEngine($exception->getTrace()),
            'headers'         => ($this->getHeaders()),
            'error_message'   => $exception->getMessage(),
            'error_file'      => $errorFile,
            'error_line'      => $errorLine,
            'routing'          => $this->getRouteDetails(),
            'contents'        => $this->buildContents($codeLines),
            'php_version'     => PHP_VERSION,
            'doppar_version'  => Application::VERSION,
            'request_method'  => request()->getMethod(),
            'request_url'     => trim(request()->fullUrl(), '/'),
            'timestamp'       => now()->toDayDateTimeString(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'platform'        => php_uname(),
            'exception_class' => class_basename($exception),
            'status_code'     => $exception->getCode() ?: 500,
            'md_content' => $this->collectMarkdownContents($exception),
        ]);
    }

    private function collectMarkdownContents(?Throwable $exception = null): string
    {
        if (! $exception) {
            return '';
        }

        $errorFile = $exception->getFile();
        $errorLine = $exception->getLine();
        $errorMessage = $exception->getMessage();
        $trace = $exception->getTraceAsString();

        $request = request();

        $md = [];
        $md[] = "# ParseError – Internal Server Error";
        $md[] = '';
        $md[] = "**Message:** {$errorMessage}";
        $md[] = "**File:** `{$errorFile}`";
        $md[] = "**Line:** `{$errorLine}`";
        $md[] = '';
        $md[] = "---";
        $md[] = '';
        $md[] = "## Environment";
        $md[] = "- **PHP:** " . PHP_VERSION;
        $md[] = "- **Doppar:** " . Application::VERSION;
        $md[] = "- **Server:** " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown');
        $md[] = "- **Platform:** " . php_uname();
        $md[] = "- **Timestamp:** " . now()->toDayDateTimeString();
        $md[] = '';
        $md[] = "## Request";
        $md[] = "- **Method:** " . $request->getMethod();
        $md[] = "- **URL:** " . $request->fullUrl();
        $md[] = '';
        $md[] = "### Headers";
        foreach ($request->headers->all() as $key => $values) {
            $joined = implode(', ', $values);
            $md[] = "- **{$key}:** {$joined}";
        }
        $md[] = '';
        $md[] = "## Code Context";
        if (file_exists($errorFile)) {
            $file = file($errorFile);
            $start = max(0, $errorLine - 5);
            $slice = array_slice($file, $start, 10, true);
            $codeBlock = '';
            foreach ($slice as $lineNumber => $lineContent) {
                $prefix = ($lineNumber + 1 == $errorLine) ? '>> ' : '   ';
                $codeBlock .= $prefix . ($lineNumber + 1) . ' ' . rtrim($lineContent) . "\n";
            }
            $md[] = "```php";
            $md[] = trim($codeBlock);
            $md[] = "```";
        } else {
            $md[] = "_File not found._";
        }
        $md[] = '';
        $md[] = "## Stack Trace";
        $md[] = "```";
        $md[] = trim($trace);
        $md[] = "```";

        return implode("\n", $md);
    }


    private function getHeaders()
    {
        return array_map(function (array $header) {
            return implode(', ', $header);
        }, request()->headers->all());
    }


    public function getRouteDetails(): array
    {
        return [
            'params' => request()->getRouteParams()
        ];
    }

    private function buildContents($codeLines)
    {
        $contents = [];

        foreach ($codeLines as $line) {
            $class = $line['is_error'] ? 'code-line-error' : 'code-line';

            $content = Highlighter::make($line['content']);

            $contents[] = '<div class="' . $class . '">' .
                '<span class="code-line-number">' . $line['number'] . '</span>' .
                '<span class="code-line-content">' . $content . '</span>' .
                '</div>';
        }

        return implode("\n", $contents);
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
