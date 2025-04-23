<?php

namespace Phaseolies\Logger\Drivers;

use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Phaseolies\Logger\Contracts\AbstractHandler;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\SlackWebhookHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Slack log handler.
 */
class SlackLogHandler extends AbstractHandler implements LogHandlerInterface
{
    /**
     * Configures the Monolog handler for Slack logging.
     *
     * @param Logger $logger The Monolog logger instance.
     * @param string $channel The logging channel.
     * @return void
     */
    public function configureHandler(Logger $logger, string $channel): void
    {
        $webhookUrl = env('SLACK_WEBHOOK_URL');

        if (empty($webhookUrl)) {
            throw new \RuntimeException('Slack webhook URL is not configured.');
        }

        $slackHandler = new SlackWebhookHandler(
            $webhookUrl,
            null,
            config('logging.channels.slack.username'),
            true,
            config('logging.channels.slack.emoji'),
            false,
            true
        );

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message%\n",
            null,
            true,
            true
        );

        $slackHandler->setFormatter($formatter);

        $logger->pushHandler($slackHandler);
    }
}
