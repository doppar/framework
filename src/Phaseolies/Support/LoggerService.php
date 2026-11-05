<?php

namespace Phaseolies\Support;

use Phaseolies\DI\Container;
use Phaseolies\Logger\LogService;
use Phaseolies\Logger\Exceptions\UnsupportedLogDriverException;
use Phaseolies\Logger\Drivers\DefaultLogHandler;
use Phaseolies\Logger\Drivers\DailyLogHandler;
use Monolog\Logger;
use Phaseolies\Logger\Drivers\SingleLogHandler;
use Phaseolies\Logger\Drivers\SlackLogHandler;

class LoggerService extends LogService
{
    /**
     * The current logging channel.
     *
     * @var string
     */
    protected ?string $currentChannel = null;

    /**
     * Sets the logging channel.
     *
     * @param string $channel
     * @return self
     */
    public function channel(string $channel): self
    {
        match ($channel) {
            'daily' => $this->addHandler(new DailyLogHandler()),
            'stack' => $this->addHandler(new DefaultLogHandler()),
            'slack' => $this->addHandler(new SlackLogHandler()),
            'single' => $this->addHandler(new SingleLogHandler()),
            default => throw new UnsupportedLogDriverException("Unsupported log channel: $channel"),
        };

        $this->currentChannel = $channel;

        return $this;
    }

    /**
     * Logs an informational message.
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function info(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->info($message, $this->withExecutionContext($context));
    }

    /**
     * Logs a notice message.
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function notice(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->notice($message, $this->withExecutionContext($context));
    }

    /**
     * Logs a warning message.
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function warning(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->warning($message, $this->withExecutionContext($context));
    }

    /**
     * Logs an error message.
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function error(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->error($message, $this->withExecutionContext($context));
    }

    /**
     * Logs a debug message.
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function debug(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->debug($message, $this->withExecutionContext($context));
    }

    /**
     * Logs a critical message.
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function critical(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->critical($message, $this->withExecutionContext($context));
    }

    /**
     * Logs an alert message.
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function alert(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->alert($message, $this->withExecutionContext($context));
    }

    /**
     * Logs an emergency message.
     *
     * @param mixed $message
     * @param array $context
     * @return void
     */
    public function emergency(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->emergency($message, $this->withExecutionContext($context));
    }

    /**
     * Attach the active execution context (id, type, metadata) to a log
     *
     * @param array $context
     * @return array
     */
    protected function withExecutionContext(array $context): array
    {
        $execution = Container::getInstance()->executionContext();

        if ($execution === null || array_key_exists('execution', $context)) {
            return $context;
        }

        return $context + ['execution' => $execution->toArray()];
    }

    /**
     * Overrides reader to pass the dynamic channel.
     *
     * @return Logger
     */
    protected function reader(): Logger
    {
        $channel = $this->currentChannel;

        if ($channel === null) {
            match (env('LOG_CHANNEL', 'stack')) {
                'daily' => $this->addHandler(new DailyLogHandler()),
                'stack' => $this->addHandler(new DefaultLogHandler()),
                'slack' => $this->addHandler(new SlackLogHandler()),
                'single' => $this->addHandler(new SingleLogHandler()),
                default => throw new UnsupportedLogDriverException("Unsupported log channel: $channel"),
            };
        }

        $logger = parent::getLogger($channel);
        $this->reset();

        return $logger;
    }

    /**
     * Format the log message
     *
     * @param mixed $message
     * @return mixed
     */
    private function formatMessage(mixed $message): mixed
    {
        return is_array($message) ? json_encode($message) : $message;
    }
}
