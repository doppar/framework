<?php

namespace Phaseolies\Error;

use Phaseolies\Application;
use Phaseolies\Error\Traces\Frame;
use Phaseolies\Error\Utils\ExceptionMarkdownReport;
use Phaseolies\Error\Utils\Highlighter;
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

        $user = auth()?->user();

        $userInfo = $user ? [
            'id' => $user->id,
            'email' => $user->email ?? 'N/A',
        ] : null;

        date_default_timezone_set(config('app.timezone'));

        $mdReport = new ExceptionMarkdownReport($exception);

        // setup the controller to point out to different views location
        $controller = $this->setupController();

        // to start a fresh view
        $this->clearOutputBufferIfActive();

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
            'memory_usage'    => memory_get_usage(true),
            'peack_memory_usage' => memory_get_peak_usage(true),
            'request_body'    => request()->except(['password', 'password_confirmation', 'token']),
            'user_info'       => $userInfo,
            'exception_class' => class_basename($exception),
            'status_code'     => $exception->getCode() ?: 500,
            'md_content' => $mdReport->generate(),
        ]);
    }

    /**
     * Initialize and configure a Controller instance.
     *
     * @return Controller
     */
    private function setupController(): Controller
    {
        $controller = new Controller();

        $relative = str_replace(base_path() . '/', '', __DIR__);

        $viewsPath = $relative . '/views';

        $controller->setViewFolder($viewsPath);

        return $controller;
    }

    /**
     * Retrieve all HTTP request headers as an array of strings.
     *
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return array_map(
            fn(array $header): string => implode(', ', $header),
            request()->headers->all()
        );
    }


    /**
     * Clear the active PHP output buffer, if one exists.
     *
     * @return void
     */
    function clearOutputBufferIfActive(): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Get the current request route details
     *
     * @return array
     */
    public function getRouteDetails(): array
    {
        return [
            'params' => request()->getRouteParams()
        ];
    }

    /**
     * Build formatted HTML code content from an array of code lines.
     *
     * @param array $codeLines
     * @return string
     */
    private function buildContents(array $codeLines): string
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
