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

        $traces = Frame::collectionFromEngine($exception->getTrace());

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
            'error_message'   => $exception->getMessage(),
            'error_file'      => $errorFile,
            'error_line'      => $errorLine,
            'contents'      => $this->buildContents($codeLines),
            'traces'          => $traces,
            'php_version'     => PHP_VERSION,
            'doppar_version'  => Application::VERSION,
            'request_method'  => request()->getMethod(),
            'request_url'     => trim(request()->fullUrl(), '/'),
            'timestamp'       => now()->toDayDateTimeString(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'platform'        => php_uname(),
            'exception_class' => class_basename($exception),
            'status_code'     => $exception->getCode() ?: 500,
        ]);
    }

    private function processTraces(array $traces): array
    {
        $processed = [];

        // dd($traces);



        foreach ($traces as $trace) {

            if (!array_key_exists('file', $trace)) {
                continue;
            }

            $file = $trace['file'];

            $processed[] = [
                'file' => $file,
                'short_file' => $this->shortenPath($file),
                'line' => $trace['line'] ?? 0,
                'function' => $trace['function'] ?? '',
                'class' => $trace['class'] ?? '',
                'type' => $trace['type'] ?? '',
                'is_vendor' => strpos($file, 'doppar/framework') !== false,
                'lines' => $this->buildTraceFrameContent($trace),
            ];
        }

        return $processed;
    }

    private function buildTraceFrameContent(array $trace)
    {
        if (!file_exists($trace['file']) || $trace['line'] <= 0) {
            return [];
        }

        $fileLines = file($trace['file']);

        $startLine = max(0, $trace['line'] - 4);
        $endLine = min(count($fileLines), $trace['line'] + 3);


        return  array_slice($fileLines, $startLine, $endLine - $startLine);
    }


    private function shortenPath(string $path): string
    {
        $basePath = base_path();
        return str_replace($basePath . '/', '', $path);
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
