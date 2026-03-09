<?php

namespace Phaseolies\Error;

use Throwable;
use Phaseolies\Support\LoggerService;
use Phaseolies\Error\Factory\ErrorHandlerFactory;
use Phaseolies\Http\Response;

class ErrorHandler
{
    /**
     * Initialize and register the application's error handling logic.
     *
     * @return void
     */
    public static function handle(): void
    {
        self::configureErrorReporting();
        self::configureShutdownReporting();

        set_exception_handler(function (Throwable $exception) {
            $loggerException = self::logException($exception);
            $activeException = $loggerException ?? $exception;

            self::triggerBeforeException($activeException);
            self::dispatch($activeException);
        });
    }

    /**
     * Dispatch the exception to the appropriate handler.
     *
     * @param Throwable $exception
     * @return void
     */
    protected static function dispatch(Throwable $exception): void
    {
        $handler = ErrorHandlerFactory::getSupportedHandler();

        if ($handler) {
            $handler->handle($exception);
        } else {
            self::handleFallback($exception);
        }
    }

    /**
     * Log an exception using the application's logger.
     *
     * @param Throwable $exception
     * @return Throwable|null
     */
    protected static function logException(Throwable $exception): ?Throwable
    {
        try {
            $logMessage = sprintf(
                "Error: %s\nFile: %s\nLine: %d\nTrace: %s",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );

            app(LoggerService::class)
                ->channel(env('LOG_CHANNEL', 'stack'))
                ->error($logMessage);

            return null;
        } catch (Throwable $e) {
            return new \RuntimeException(
                sprintf(
                    'Logger unavailable: %s | Original error: %s in %s on line %d',
                    $e->getMessage(),
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Last resort fallback handler when no specific handler is available.
     *
     * @param Throwable $exception
     * @return void
     */
    protected static function handleFallback(Throwable $exception): void
    {
        $code = $exception->getCode();
        $httpCode = ($code >= 400 && $code < 600) ? $code : 500;

        abort($httpCode, "An error occurred. Please try again later.");
    }

    /**
     * Configure PHP error handler to convert runtime errors to exceptions.
     *
     * @return void
     */
    protected static function configureErrorReporting(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            if (str_starts_with($message, 'fsockopen():')) {
                return false;
            }

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /**
     * Configure shutdown handler for fatal errors that bypass set_error_handler
     *
     * @return void
     */
    protected static function configureShutdownReporting(): void
    {
        register_shutdown_function(function () {
            $error = error_get_last();

            if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                return;
            }

            if (str_starts_with($error['message'], 'fsockopen():')) {
                return;
            }

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $exception = new \ErrorException(
                "A fatal error occurred: " . $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            $loggerException = static::logException($exception);
            $activeException = $loggerException ?? $exception;

            static::triggerBeforeException($activeException);
            static::dispatch($activeException);
        });
    }

    /**
     * Executes the user-defined hook before the main error handling flow
     *
     * @param Throwable $exception
     * @return void
     */
    protected static function triggerBeforeException(Throwable $exception): void
    {
        try {
            $beforeExceptionClass = \App\Http\Exceptions\BeforeExceptionHandler::class;

            if (!class_exists($beforeExceptionClass)) {
                return;
            }

            $before = app($beforeExceptionClass);

            if (!$before->supports()) {
                return;
            }

            $response = app(Response::class);
            $statusCode = $exception instanceof \Phaseolies\Http\Exceptions\HttpException
                ? $exception->getStatusCode()
                : 500;

            $response->setExceptionError($exception, $statusCode);
            $before->handle($exception);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[Doppar] BeforeExceptionHandler failed: %s in %s on line %d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
    }
}
