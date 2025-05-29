<?php

namespace Phaseolies\Support;

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
     * @param string $channel The channel name (e.g., 'stack', 'daily', etc.).
     * @return self Returns the current instance for method chaining.
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
     * @param mixed $message The message to log.
     * @param array $context Additional context data.
     * @return void
     */
    public function info(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->info($message, $context);
    }

    /**
     * Logs a notice message.
     *
     * @param mixed $message The message to log.
     * @param array $context Additional context data.
     * @return void
     */
    public function notice(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->notice($message, $context);
    }

    /**
     * Logs a warning message.
     *
     * @param mixed $message The message to log.
     * @param array $context Additional context data.
     * @return void
     */
    public function warning(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->warning($message, $context);
    }

    /**
     * Logs an error message.
     *
     * @param mixed $message The message to log.
     * @param array $context Additional context data.
     * @return void
     */
    public function error(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->error($message, $context);
    }

    /**
     * Logs a debug message.
     *
     * @param mixed $message The message to log.
     * @param array $context Additional context data.
     * @return void
     */
    public function debug(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->debug($message, $context);
    }

    /**
     * Logs a critical message.
     *
     * @param mixed $message The message to log.
     * @param array $context Additional context data.
     * @return void
     */
    public function critical(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->critical($message, $context);
    }

    /**
     * Logs an alert message.
     *
     * @param mixed $message The message to log.
     * @param array $context Additional context data.
     * @return void
     */
    public function alert(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->alert($message, $context);
    }

    /**
     * Logs an emergency message.
     *
     * @param mixed $message The message to log.
     * @param array $context Additional context data.
     * @return void
     */
    public function emergency(mixed $message, array $context = []): void
    {
        $message = $this->formatMessage($message);

        $this->reader()->emergency($message, $context);
    }

    /**
     * Overrides reader to pass the dynamic channel.
     *
     * @return Logger The configured Monolog logger instance.
     */
    protected function reader(): Logger
    {
        $channel = $this->currentChannel ?? 'stack';

        $this->reset();

        if ($this->currentChannel === null) {
            $this->addHandler(new DefaultLogHandler());
        }

        return parent::getLogger($channel);
    }

    /**
     * @param mixed $message
     * @return mixed
     */
    private function formatMessage(mixed $message): mixed
    {
        return is_array($message) ? json_encode($message) : $message;
    }
}
