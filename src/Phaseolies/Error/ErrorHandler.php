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
                    sprintf('<bg=red;fg=white;options=bold>  🚨  %s  </>', $errorMessage),
                    '',
                ]);

                $section->writeln(sprintf(
                    '  <fg=yellow>📂 File:</> <href=file://%s><fg=white;options=underscore>%s</></>',
                    $errorFile,
                    $errorFile
                ));
                $section->writeln(sprintf(
                    '  <fg=yellow>📌 Line:</> <fg=white>%d</>',
                    $errorLine
                ));

                $traceLines = explode("\n", $errorTrace);
                $shortTrace = array_slice($traceLines, 0, 2);
                $hasMore = count($traceLines) > 2;

                $section->writeln('');
                $section->writeln('  <fg=blue;options=bold>🔍 Stack Trace:</>');
                $section->writeln('  <fg=gray>┌──────────────────────────────────────────────────────────┐</>');

                foreach ($shortTrace as $line) {
                    if (preg_match('/#\d+\s+(.*?)(\((\d+)\))?:/', $line, $matches)) {
                        $file = $matches[1];
                        $lineNumber = $matches[3] ?? '';
                        $formatted = preg_replace(
                            '/(#\d+\s+)(.*?)(\((\d+)\))?:/',
                            '$1<href=file://$2><fg=cyan;options=underscore>$2</></>$3:',
                            $line
                        );
                        $section->writeln(sprintf('  <fg=gray>│</> %-56s <fg=gray>│</>', $formatted));
                    } else {
                        $section->writeln(sprintf('  <fg=gray>│</> %-56s <fg=gray>│</>', $line));
                    }
                }

                if ($hasMore) {
                    $remaining = count($traceLines) - 2;
                    $section->writeln(sprintf(
                        '  <fg=gray>│</> <fg=yellow>... %d more</>%s <fg=gray>│</>',
                        $remaining,
                        str_repeat(' ', 56 - 38 - strlen((string)$remaining))
                    ));
                }

                $section->writeln('  <fg=gray>└──────────────────────────────────────────────────────────┘</>');
                if (str_contains($errorMessage, 'undefined variable')) {
                    $varName = preg_match('/undefined variable (\$\w+)/i', $errorMessage, $matches)
                        ? $matches[1]
                        : '$variable';

                    $section->writeln('');
                    $section->writeln('  <fg=green>💡 Quick Fix:</> Define the variable before using it:');
                    $section->writeln(sprintf('      <fg=white>%s = "default_value";</>', $varName));
                }
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
                ob_end_clean();
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
                $customPath = base_path("resources/views/errors/500.blade.php");
                $errorPage = base_path("vendor/doppar/framework/src/Phaseolies/Support/View/errors/500.blade.php");
                http_response_code(500);
                if (file_exists($customPath)) {
                    include $customPath;
                } elseif (file_exists($errorPage)) {
                    include $errorPage;
                }
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
            $statusCode === Response::HTTP_UNAUTHORIZED
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
