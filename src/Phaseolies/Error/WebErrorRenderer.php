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

        $user = auth()->user();

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
            // 
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'platform'        => php_uname(),
            'memory_usage'    => memory_get_usage(true),
            'peack_memory_usage' => memory_get_peak_usage(true),
            'request_body'    => request()->except(['password', 'password_confirmation', 'token']),
            'user_info'       => $userInfo,
            // 
            'session_data'    => $this->getSessionData(),
            'cookies_data'    => $this->getCookiesData(),
            // 
            'exception_class' => class_basename($exception),
            'status_code'     => $exception->getCode() ?: 500,
            'md_content' => $mdReport->generate(),
        ]);
    }


    /**
     * Get session data with sensitive information hidden
     *
     * @return array
     */
    private function getSessionData(): array
    {
        try {
            // Check if session is started
            if (session_status() === PHP_SESSION_ACTIVE) {
                $sessionData = $_SESSION ?? [];

                // Try to get session from request helper if available
                if (function_exists('request') && method_exists(request(), 'session')) {
                    try {
                        $sessionData = request()->session()->all() ?? $sessionData;
                    } catch (\Exception $e) {
                        // Continue with $_SESSION
                    }
                }

                // Remove sensitive data
                $sensitiveKeys = ['password', 'token', 'api_key', 'secret', '_token', 'csrf_token', 'auth_password'];
                foreach ($sensitiveKeys as $key) {
                    if (isset($sessionData[$key])) {
                        $sessionData[$key] = '***HIDDEN***';
                    }
                }

                // Also check nested arrays
                foreach ($sessionData as $key => &$value) {
                    if (is_array($value)) {
                        foreach ($sensitiveKeys as $sensitiveKey) {
                            if (isset($value[$sensitiveKey])) {
                                $value[$sensitiveKey] = '***HIDDEN***';
                            }
                        }
                    }
                }

                return [
                    'id' => session_id() ?: null,
                    'data' => $sessionData,
                    'has_session' => true,
                ];
            }

            return ['has_session' => false];
        } catch (\Exception $e) {
            return ['has_session' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get cookies data with sensitive information hidden
     *
     * @return array
     */
    private function getCookiesData(): array
    {
        try {
            // Try to get cookies from request helper first
            $cookies = [];
            if (function_exists('request') && method_exists(request(), 'cookies')) {
                try {
                    $cookies = request()->cookies->all() ?? [];
                } catch (\Exception $e) {
                    // Fall back to $_COOKIE
                }
            }

            // Fallback to $_COOKIE if request cookies not available
            if (empty($cookies)) {
                $cookies = $_COOKIE ?? [];
            }

            // Remove sensitive cookies
            $sensitiveCookies = ['password', 'token', 'api_key', 'secret', 'auth_token', 'auth_password'];
            $filteredCookies = [];

            foreach ($cookies as $key => $value) {
                $lowerKey = strtolower($key);
                $isSensitive = false;

                foreach ($sensitiveCookies as $sensitive) {
                    if (strpos($lowerKey, $sensitive) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }

                $filteredCookies[$key] = $isSensitive ? '***HIDDEN***' : $value;
            }

            return $filteredCookies;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function setupController(): Controller
    {

        $controller = new Controller();

        $relative = str_replace(base_path() . '/', '', __DIR__);

        $viewsPath = $relative . '/views';

        $controller->setViewFolder($viewsPath);

        return $controller;
    }

    private function getHeaders(): array
    {
        return array_map(function (array $header) {
            return implode(', ', $header);
        }, request()->headers->all());
    }


    function clearOutputBufferIfActive(): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    public function getRouteDetails(): array
    {
        return [
            'params' => request()->getRouteParams()
        ];
    }

    private function getAuthContext(): array
    {

        return [];
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
