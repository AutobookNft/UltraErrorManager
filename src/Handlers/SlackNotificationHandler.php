<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Http\Client\Response; // For type hint
use Illuminate\Http\Request;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\UltraLogManager\UltraLogManager;
use Throwable;
use Illuminate\Support\Facades\Route; // Keep for route checking

/**
 * 🎯 SlackNotificationHandler – Oracoded Error Alert Handler for Slack (GDPR Hardened & Complete)
 *
 * Sends configurable, formatted error notifications to a Slack webhook URL.
 * Uses injected dependencies and sanitizes/truncates data before sending.
 * Inclusion of sensitive details is controlled via configuration.
 *
 * 🧱 Structure:
 * - Implements ErrorHandlerInterface.
 * - Requires HttpClientFactory, ULM Logger, Request, and config arrays injected.
 * - Checks config to determine if notification should send.
 * - Prepares Slack message payload using Blocks API, applying sanitization
 *   (`sanitizeContextForSlack`) and conditional inclusion based on config flags.
 * - Truncates context and trace snippets for Slack readability.
 * - Sends payload via HTTP POST using injected client factory.
 * - Logs internal sending success/errors via ULM Logger.
 *
 * 📡 Communicates:
 * - With Slack API via HTTP POST (`@data-output`).
 * - With ULM Logger for internal logging (`@log`).
 * - Reads Request data via injected Request.
 *
 * 🧪 Testable:
 * - Dependencies mockable. Http client fake applicable.
 * - Message preparation and sanitization testable.
 *
 * 🛡️ GDPR Considerations:
 * - Sends data externally to Slack (`@data-output`). Risk depends on included details.
 * - Context is sanitized (`@privacy-safe`, `@sanitizer`) using a configurable key list.
 * - Inclusion of IP, User Details (ID only by default), Context, Trace is OFF or limited by default. Enable/Increase limits with caution.
 */
final class SlackNotificationHandler implements ErrorHandlerInterface
{
    protected readonly HttpClientFactory $httpClientFactory;
    protected readonly UltraLogManager $ulmLogger;
    protected readonly Request $request;
    protected readonly array $slackConfig;
    protected readonly string $appName;
    protected readonly string $environment;

    /**
     * 🎯 Constructor: Injects dependencies required for Slack notifications.
     * @param HttpClientFactory $httpClientFactory Factory for HTTP client instances.
     * @param UltraLogManager $ulmLogger Logger for internal operations.
     * @param Request $request The current HTTP request instance.
     * @param array $slackConfig Configuration array (from 'error-manager.slack_notification').
     * @param string $appName Application name.
     * @param string $environment Application environment.
     */
    public function __construct(
        HttpClientFactory $httpClientFactory,
        UltraLogManager $ulmLogger,
        Request $request,
        array $slackConfig,
        string $appName,
        string $environment
    ) {
        $this->httpClientFactory = $httpClientFactory;
        $this->ulmLogger = $ulmLogger;
        $this->request = $request;
        $this->slackConfig = $slackConfig;
        $this->appName = $appName;
        $this->environment = $environment;
    }

    /**
     * 🧠 Determine if this handler should handle the error based on config.
     * @param array $errorConfig Resolved error configuration.
     * @return bool True if a Slack notification should be sent.
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Notify if specifically requested OR if it's critical and notify_all_critical is enabled (default true)
        $errorRequiresNotification = ($this->slackConfig['notify_all_critical'] ?? true && ($errorConfig['type'] ?? 'error') === 'critical')
                                     || ($errorConfig['notify_slack'] ?? false);

        $notificationsEnabled = $this->slackConfig['enabled'] ?? false;
        $webhookConfigured = !empty($this->slackConfig['webhook_url']);

        return $errorRequiresNotification && $notificationsEnabled && $webhookConfigured;
    }

    /**
     * 💬 Handle the error by sending a sanitized notification to Slack.
     * 📤 @data-output (To Slack API)
     * 🪵 @log (Logs sending status/errors via ULM)
     * 🔥 @critical (Notification delivery can be critical)
     *
     * @param string $errorCode The symbolic error code.
     * @param array $errorConfig The configuration metadata for the error.
     * @param array $context Contextual data potentially containing PII.
     * @param Throwable|null $exception Optional original throwable.
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        $webhookUrl = $this->slackConfig['webhook_url']; // Assumed valid from shouldHandle

        try {
            $payload = $this->prepareSlackMessage($errorCode, $errorConfig, $context, $exception);

            $response = $this->httpClientFactory->timeout(15)
                         ->post($webhookUrl, $payload);

            $this->logSlackResponse($errorCode, $response);

        } catch (Throwable $e) {
            $this->ulmLogger->error("UEM SlackHandler: Exception during Slack notification preparation or sending.", [
                'original_error_code' => $errorCode,
                'slack_handler_exception' => ['message' => $e->getMessage(), 'class' => get_class($e)],
            ]);
             // Consider triggering UEM_SLACK_SEND_FAILED here if ErrorManager is injected
        }
    }

    /**
     * 🧱 Prepare Slack message payload using the Blocks API, applying sanitization and limits.
     * 🛡️ Sanitizes context, conditionally includes sensitive details based on config.
     *
     * @param string $errorCode Error code identifier.
     * @param array $errorConfig Error configuration.
     * @param array $context Original contextual data.
     * @param Throwable|null $exception Optional original exception.
     * @return array Slack message payload.
     */
    protected function prepareSlackMessage(string $errorCode, array $errorConfig, array $context, ?Throwable $exception): array
    {
        $color = $this->getColorForErrorType($errorConfig['type'] ?? 'error');
        $titleText = "🚨 {$this->appName} (`{$this->environment}`) Error: {$errorCode}";

        // Fields Block
        $fields = [
            ['type' => 'mrkdwn', 'text' => "*Type:*\n`" . ucfirst($errorConfig['type'] ?? 'error') . "`"],
            ['type' => 'mrkdwn', 'text' => "*Blocking:*\n`" . ($errorConfig['blocking'] ?? 'unknown') . "`"],
            ['type' => 'mrkdwn', 'text' => "*Time:*\n" . now()->format('Y-m-d H:i:s \U\T\C')],
            ['type' => 'mrkdwn', 'text' => "*URL:*\n<" . $this->request->fullUrl() . "|Link>"],
        ];
        if ($this->slackConfig['include_ip_address'] ?? false) {
            $fields[] = ['type' => 'mrkdwn', 'text' => "*IP Address:*\n`" . $this->request->ip() . "`"];
        }
        if (($this->slackConfig['include_user_details'] ?? false) && isset($context['user_id'])) {
            $fields[] = ['type' => 'mrkdwn', 'text' => "*User ID:*\n`" . $context['user_id'] . "`"];
        }

        // Message Block
        $devMessage = $errorConfig['message'] ?? $errorConfig['dev_message'] ?? 'No specific developer message.';
        $messageSection = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Message:*\n>" . $this->sanitizeStringValue($devMessage, 1000)]];

        // Exception Blocks (Conditional)
        $exceptionBlocks = [];
        if ($exception) {
            $exceptionBlocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Exception:*\n```" . get_class($exception) . ": " . $this->sanitizeStringValue($exception->getMessage(), 500) . "```"]];
            $exceptionBlocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Location:*\n`" . $exception->getFile() . ":{$exception->getLine()}`"]];

            if ($this->slackConfig['include_trace_snippet'] ?? false) {
                $traceLines = explode("\n", $exception->getTraceAsString());
                $maxLines = $this->slackConfig['trace_max_lines'] ?? 10;
                $limitedTrace = implode("\n", array_slice($traceLines, 0, $maxLines));
                if (count($traceLines) > $maxLines) $limitedTrace .= "\n[...]";
                if (mb_strlen($limitedTrace) > 2800) $limitedTrace = mb_substr($limitedTrace, 0, 2800) . "\n[... TRUNCATED ...]";
                $exceptionBlocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "*Trace Snippet (Top {$maxLines}):*\n```" . $limitedTrace . "```"]];
            }
        }

        // Context Blocks (Conditional & Sanitized)
        $contextBlocks = [];
        if (($this->slackConfig['include_context'] ?? true) && !empty($context)) {
            $sanitizedContext = $this->sanitizeContextForSlack($context);
            $contextString = json_encode($sanitizedContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $maxLength = $this->slackConfig['context_max_length'] ?? 1500;
            $truncated = false;
            if (mb_strlen($contextString) > $maxLength) {
                $contextString = mb_substr($contextString, 0, $maxLength - 20) . "\n... [TRUNCATED]";
                $truncated = true;
            }
            $contextText = "*Context" . ($truncated ? " (Truncated)" : "") . ":*\n```" . $contextString . "```";
            $contextBlocks[] = ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $contextText]];
        }

        // Action Blocks (Conditional)
        $actionBlocks = [];
        if (Route::has('error-manager.dashboard.index')) {
            $dashboardUrl = route('error-manager.dashboard.index', ['code' => $errorCode]);
            $actionBlocks[] = ['type' => 'actions', 'elements' => [
                ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'View Dashboard', 'emoji' => true], 'url' => $dashboardUrl, 'style' => 'primary'],
            ]];
        }

        // Assemble Payload
        $payload = [
            'attachments' => [[
                'color' => $color,
                'blocks' => array_merge(
                    [['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => $titleText, 'emoji' => true]]],
                    [['type' => 'section', 'fields' => $fields]],
                    [$messageSection],
                    $exceptionBlocks,
                    $contextBlocks,
                    [['type' => 'divider']],
                    $actionBlocks
                ),
            ]],
            'username' => $this->slackConfig['username'] ?? $this->appName . ' Error Bot',
            'icon_emoji' => $this->slackConfig['icon_emoji'] ?? ':boom:',
        ];
        if (!empty($this->slackConfig['channel'])) {
            $payload['channel'] = $this->slackConfig['channel'];
        }

        return $payload;
    }

    /**
     * 🔐 Sanitize context data specifically for Slack notifications.
     * 🛡️ @privacy-safe 🧼 @sanitizer
     * @param array $context The context array to sanitize.
     * @return array The sanitized context array.
     */
    protected function sanitizeContextForSlack(array $context): array
    {
        $defaultSensitiveKeys = ['password', 'secret', 'token', 'auth', 'key', 'credentials', 'authorization', 'php_auth_user', 'php_auth_pw', 'credit_card', 'cvv', 'api_key'];
        $sensitiveKeys = $this->slackConfig['context_sensitive_keys'] ?? $defaultSensitiveKeys;
        $sensitiveKeys = array_map('strtolower', $sensitiveKeys);
        $maxStringLength = $this->slackConfig['context_string_max_length'] ?? 200;

        $sanitized = [];
        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string)$key);

            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                 $sanitized[$key] = '[Array:' . count($value) . ' items]'; // Simplified for Slack
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeStringValue($value, $maxStringLength);
            } elseif (is_scalar($value) || is_null($value)) {
                $sanitized[$key] = $value;
            } elseif (is_object($value)) {
                $sanitized[$key] = '[Object:' . get_class($value) . ']';
            } else {
                $sanitized[$key] = '[Unserializable]';
            }
        }
        return $sanitized;
    }

    /**
     * 🧱 Get Slack attachment color based on UEM error type.
     * @param string $errorType
     * @return string Hex color code or Slack name.
     */
    protected function getColorForErrorType(string $errorType): string
    {
        $mapping = [
            'critical' => 'danger', 'error' => '#FFA500',
            'warning' => 'warning', 'notice' => '#439FE0',
        ];
        return $mapping[strtolower($errorType)] ?? '#808080';
    }

    /**
     * ✂️ Basic string sanitization (truncate, remove null bytes).
     * 🛡️ @privacy-safe 🧼 @sanitizer
     * @param string $value
     * @param int $maxLength
     * @return string
     */
     protected function sanitizeStringValue(string $value, int $maxLength = 500): string
     {
         // Truncate if exceeds max length
         if (mb_strlen($value) > $maxLength) {
             // Ensure sufficient room for the truncation marker
             $value = mb_substr($value, 0, max(0, $maxLength - 16)) . '...[TRUNCATED]';
         }
         // Remove null bytes
         return str_replace("\0", '', $value);
     }

     /**
      * 🪵 Log the response from the Slack API call using injected ULM logger.
      * @param string $errorCode
      * @param Response $response
      * @return void
      */
     private function logSlackResponse(string $errorCode, Response $response): void
     {
         if (!$response->successful()) {
             $this->ulmLogger->warning("UEM SlackHandler: Failed to send notification.", [
                 'errorCode' => $errorCode,
                 'slack_status' => $response->status(),
                 'slack_response_body' => $response->body(),
             ]);
             // Consider triggering UEM_SLACK_SEND_FAILED
         } else {
             $this->ulmLogger->info("UEM SlackHandler: Notification sent successfully.", ['errorCode' => $errorCode]);
         }
     }
}