<?php

namespace Phaseolies\Error;

use Symfony\Component\Console\Output\ConsoleOutput;
use Phaseolies\Http\Response;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Support\LoggerService;

class ErrorHandler
{
    public static function handle(): void
    {
        self::configureErrorReporting();
        self::configureShutdownReporting();

        set_exception_handler(function ($exception) {
            $errorMessage = $exception->getMessage();
            $errorFile = $exception->getFile();
            $errorLine = $exception->getLine();
            $errorTrace = $exception->getTraceAsString();
            $errorCode = $exception->getCode();

            $logMessage = "Error: " . $exception->getMessage();
            $logMessage .= "\nFile: " . $exception->getFile();
            $logMessage .= "\nLine: " . $exception->getLine();
            $logMessage .= "\nTrace: " . $exception->getTraceAsString();

            app(LoggerService::class)
                ->channel(env('LOG_CHANNEL', 'stack'))
                ->error($logMessage);

            if (request()->isAjax() || request()->is('/api/*')) {
                if ($exception instanceof HttpResponseException) {
                    $responseErrors = $exception->getValidationErrors();
                    $statusCode = $exception->getStatusCode();
                    self::sendJsonErrorResponse(
                        $errorFile,
                        $errorLine,
                        $errorTrace,
                        $statusCode === 0 ? 500 : $statusCode,
                        $responseErrors
                    );
                    return;
                } else {
                    self::sendJsonErrorResponse(
                        $errorFile,
                        $errorLine,
                        $errorTrace,
                        $errorCode === 0 ? 500 : $errorCode,
                        $errorMessage
                    );
                    return;
                }
            }

            if (PHP_SAPI === 'cli' || defined('STDIN')) {
                $output = new ConsoleOutput();
                $section = $output->section();

                $section->writeln([
                    '',
                    '<fg=red;options=bold><bg=red;fg=white;> ERROR OCCURRED </></>',
                    ''
                ]);

                $section->writeln([
                    sprintf('<fg=red;>✖ ERROR:</> <fg=red>%s</>', $errorMessage),
                    sprintf('<fg=red>📁 FILE:</> <fg=white>%s</>', $errorFile),
                    sprintf('<fg=red>📍 LINE:</> <fg=white>%d</>', $errorLine),
                ]);

                exit(1);
            }

            if (env('APP_DEBUG') === "true") {
                $fileContent = file_exists($errorFile) ? file_get_contents($errorFile) : 'File not found.';
                $lines = explode("\n", $fileContent);
                $startLine = max(0, $errorLine - 10);
                $endLine = min(count($lines) - 1, $errorLine + 100);
                $displayedLines = array_slice($lines, $startLine, $endLine - $startLine + 1);

                $highlightedLines = [];
                foreach ($displayedLines as $index => $line) {
                    $lineNumber = $startLine + $index + 1;
                    if ($lineNumber == $errorLine) {
                        $highlightedLines[] = '<span class="line-number highlight">' . $lineNumber . '</span><span class="highlight">' . htmlspecialchars($line) . '</span>';
                    } else {
                        $highlightedLines[] = '<span class="line-number">' . $lineNumber . '</span>' . htmlspecialchars($line);
                    }
                }

                $formattedCode = implode("\n", $highlightedLines);

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
                        '{{ request_method }}',
                        '{{ request_url }}',
                        '{{ timestamp }}',
                        '{{ server_software }}',
                        '{{ platform }}',
                        '{{ exception_class }}'
                    ],
                    [
                        $errorMessage,
                        $errorFile,
                        $errorLine,
                        nl2br(htmlspecialchars($errorTrace)),
                        $formattedCode,
                        $languageClass,
                        PHP_VERSION,
                        request()->getMethod(),
                        request()->fullUrl(),
                        now()->toDayDateTimeString(),
                        $_SERVER['SERVER_SOFTWARE'],
                        php_uname(),
                        class_basename($exception)
                    ],
                    file_get_contents(__DIR__ . '/error_page_template.blade.php')
                );
            } else {
                abort(500, "Something went wrong");
            }
        });
    }

    /**
     * Display the warning errors and others minor errors
     *
     * @return void
     */
    protected static function configureErrorReporting(): void
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            if (strpos($message, 'fsockopen():') === 0) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /**
     * Display E_COMPILE_ERROR and E_ERROR types errors
     *
     * @return void
     */
    protected static function configureShutdownReporting(): void
    {
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                if (strpos($error['message'], 'fsockopen():') === 0) {
                    return;
                }
                throw new \ErrorException("A fatal error occurred: " . $error['message']);
            }
        });
    }

    /**
     * Send a JSON error response for AJAX requests.
     *
     * @param string $errorFile
     * @param int $errorLine
     * @param string $errorTrace
     * @param int $statusCode
     * @param array|null $responseErrors
     */
    public static function sendJsonErrorResponse(
        string $errorFile,
        int $errorLine,
        string $errorTrace,
        int $statusCode,
        mixed $errorMessage = null
    ): void {

        if (
            $statusCode === Response::HTTP_TOO_MANY_REQUESTS ||
            $statusCode === Response::HTTP_UNPROCESSABLE_ENTITY ||
            $statusCode === Response::HTTP_UNAUTHORIZED ||
            $statusCode === 419
        ) {
            $response = [
                'success' => false,
                'message' => $errorMessage
            ];
        } else {
            $response = [
                'success' => false,
                'message' => $errorMessage,
                'error' => [
                    'file' => $errorFile,
                    'line' => $errorLine,
                    'trace' => $errorTrace,
                ],
            ];
        }

        header('Content-Type: application/json');
        http_response_code($statusCode);

        echo json_encode($response, JSON_UNESCAPED_SLASHES);

        exit;
    }
}
