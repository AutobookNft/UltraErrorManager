<?php

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;

/**
 * Slack Notification Handler
 *
 * This handler sends notifications to Slack when critical errors occur.
 *
 * @package Ultra\ErrorManager\Handlers
 */
class SlackNotificationHandler implements ErrorHandlerInterface
{
    /**
     * Determine if this handler should handle the error
     *
     * @param array $errorConfig Error configuration
     * @return bool True if this handler should process the error
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Only notify about critical errors or errors specifically configured for Slack
        if (($errorConfig['type'] ?? '') !== 'critical' && !($errorConfig['notify_slack'] ?? false)) {
            return false;
        }

        // Check if Slack notifications are enabled and webhook URL is configured
        return Config::get('error-manager.slack_notification.enabled', false) &&
               !empty(Config::get('error-manager.slack_notification.webhook_url'));
    }

    /**
     * Handle the error by sending a Slack notification
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], \Throwable $exception = null): void
    {
        $webhookUrl = Config::get('error-manager.slack_notification.webhook_url');

        if (empty($webhookUrl)) {
            Log::warning("Ultra Error Manager: Slack webhook URL not configured");
            return;
        }

        try {
            // Prepare the Slack message
            $message = $this->prepareSlackMessage($errorCode, $errorConfig, $context, $exception);

            // Send the notification
            $response = Http::post($webhookUrl, $message);

            if (!$response->successful()) {
                Log::warning("Ultra Error Manager: Failed to send Slack notification", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } else {
                Log::info("Ultra Error Manager: Slack notification sent for error [{$errorCode}]");
            }
        } catch (\Exception $e) {
            Log::error("Ultra Error Manager: Exception during Slack notification", [
                'error_code' => $errorCode,
                'notification_exception' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ]);
        }
    }

    /**
     * Prepare Slack message payload
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return array Slack message payload
     */
    protected function prepareSlackMessage(string $errorCode, array $errorConfig, array $context, ?\Throwable $exception): array
    {
        $appName = Config::get('app.name', 'Application');
        $environment = app()->environment();

        // Get color based on error type
        $color = $this->getColorForErrorType($errorConfig['type'] ?? 'error');

        // Prepare basic message text
        $message = "Error in *{$appName}* ({$environment}): `{$errorCode}`";

        // Prepare message blocks
        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $message,
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Error Type:*\n" . ucfirst($errorConfig['type'] ?? 'error'),
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Blocking Level:*\n" . ($errorConfig['blocking'] ?? 'unknown'),
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Time:*\n" . now()->format('Y-m-d H:i:s'),
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*URL:*\n" . request()->fullUrl(),
                    ],
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Error Message:*\n" . ($errorConfig['dev_message'] ?? 'No message available'),
                ],
            ],
        ];

        // Add exception information if available
        if ($exception) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Exception:*\n```" . get_class($exception) . ": " . $exception->getMessage() . "```",
                ],
            ];

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Location:*\n" . $exception->getFile() . ":" . $exception->getLine(),
                ],
            ];
        }

        // Add context data if not empty
        if (!empty($context)) {
            $contextText = "*Context:*\n```" . json_encode($context, JSON_PRETTY_PRINT) . "```";

            // Slack has a limit on text size, so truncate if necessary
            if (strlen($contextText) > 3000) {
                $contextText = substr($contextText, 0, 2970) . "...\n```";
            }

            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $contextText,
                ],
            ];
        }

        // Add divider and action buttons
        $blocks[] = [
            'type' => 'divider',
        ];

        $blocks[] = [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'View in Dashboard',
                        'emoji' => true,
                    ],
                    'url' => route('error-manager.dashboard') . "?code={$errorCode}", // Assumes you have a dashboard route
                    'style' => 'primary',
                ],
            ],
        ];

        // Return the complete message payload
        return [
            'attachments' => [
                [
                    'color' => $color,
                    'blocks' => $blocks,
                ],
            ],
        ];
    }

    /**
     * Get Slack color based on error type
     *
     * @param string $errorType
     * @return string Hex color code
     */
    protected function getColorForErrorType(string $errorType): string
    {
        $colors = [
            'critical' => '#FF0000', // Red
            'error' => '#FFA500',    // Orange
            'warning' => '#FFFF00',  // Yellow
            'notice' => '#0000FF',   // Blue
            'info' => '#00FF00',     // Green
        ];

        return $colors[$errorType] ?? '#808080'; // Default to gray
    }
}
