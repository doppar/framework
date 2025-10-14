<?php

namespace Phaseolies\Error;

use Throwable;
use Phaseolies\Support\LoggerService;
use Phaseolies\Error\Factory\ErrorHandlerFactory;

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

        set_exception_handler(function ($exception) {
            self::logException($exception);

            $handler = ErrorHandlerFactory::getSupportedHandler();

            if ($handler) {
                $handler->handle($exception);
            } else {
                self::handleFallback($exception);
            }
        });
    }

    /**
     * Log an exception using the application's logger.
     *
     * @param Throwable $exception
     * @return void
     */
    protected static function logException(Throwable $exception): void
    {
        $logMessage = "Error: " . $exception->getMessage();
        $logMessage .= "\nFile: " . $exception->getFile();
        $logMessage .= "\nLine: " . $exception->getLine();
        $logMessage .= "\nTrace: " . $exception->getTraceAsString();

        app(LoggerService::class)
            ->channel(env('LOG_CHANNEL', 'stack'))
            ->error($logMessage);
    }

    protected static function handleFallback(Throwable $exception): void
    {
        abort(500, "An error occurred. Please try again later.");

        exit(1);
    }

    /**
     * Default fallback handler if no specific handler supports the context.
     *
     * @param Throwable $exception
     * @return void
     */
    protected static function configureErrorReporting(): void
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            if (strpos($message, 'fsockopen():') === 0) {
                return false;
            }

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    /**
     * Configure handling of PHP runtime warnings and minor errors.
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

                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                throw new \ErrorException("A fatal error occurred: " . $error['message']);
            }
        });
    }
}
