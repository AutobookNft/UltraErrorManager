<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Authenticatable; // For User type hint
use Illuminate\Http\Request;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Ultra\UltraLogManager\UltraLogManager;
use Throwable;

/**
 * 🎯 EmailNotificationHandler – Oracoded Error Alert Handler (GDPR Hardened)
 *
 * Sends configurable email notifications for specific errors. Uses injected
 * dependencies and sanitizes context data before sending to enhance GDPR compliance.
 * Inclusion of sensitive details (IP, User, Context, Trace) is controlled via configuration.
 *
 * 🧱 Structure:
 * - Implements ErrorHandlerInterface.
 * - Requires MailerContract, ULM Logger, Request, AuthFactory, config arrays injected.
 * - Checks config to determine if notification should send.
 * - Prepares email data, sanitizing context (`sanitizeContextForEmail`) and conditionally
 *   including details based on config flags (`include_*`).
 * - Sends email via injected Mailer.
 * - Logs internal errors via ULM Logger.
 *
 * 📡 Communicates:
 * - With Mail system via MailerContract (`@data-output`).
 * - With Auth system via AuthFactory.
 * - With ULM Logger for internal logging (`@log`).
 * - Reads Request data via injected Request.
 *
 * 🧪 Testable:
 * - Dependencies mockable. Mail fakes applicable.
 * - Sanitization and data preparation logic testable.
 *
 * 🛡️ GDPR Considerations:
 * - Sends data externally (`@data-output`). Risk depends on included details.
 * - Context is sanitized (`@privacy-safe`, `@sanitizer`) before inclusion using a configurable key list.
 * - Inclusion of IP, User Agent, User Details, Context, Trace is OFF by default. Enable with caution.
 */
final class EmailNotificationHandler implements ErrorHandlerInterface
{
    protected readonly MailerContract $mailer;
    protected readonly UltraLogManager $ulmLogger;
    protected readonly Request $request;
    protected readonly AuthFactory $auth;
    protected readonly array $emailConfig; // Full 'email_notification' config section
    protected readonly string $appName;
    protected readonly string $environment;

    /**
     * 🎯 Constructor: Injects dependencies required for email notifications.
     * @param MailerContract $mailer Laravel's Mailer contract instance.
     * @param UltraLogManager $ulmLogger Logger for internal handler operations.
     * @param Request $request The current HTTP request instance.
     * @param AuthFactory $auth Factory to get authentication guard information.
     * @param array $emailConfig Configuration array (from 'error-manager.email_notification').
     * @param string $appName The application name (from 'app.name').
     * @param string $environment The current application environment.
     */
    public function __construct(
        MailerContract $mailer,
        UltraLogManager $ulmLogger,
        Request $request,
        AuthFactory $auth,
        array $emailConfig,
        string $appName,
        string $environment
    ) {
        $this->mailer = $mailer;
        $this->ulmLogger = $ulmLogger;
        $this->request = $request;
        $this->auth = $auth;
        $this->emailConfig = $emailConfig;
        $this->appName = $appName;
        $this->environment = $environment;
    }

    /**
     * 🧠 Determine if this handler should handle the error based on config.
     * @param array $errorConfig Resolved error configuration.
     * @return bool True if an email notification should be sent.
     */
    public function shouldHandle(array $errorConfig): bool
    {
        $errorRequiresNotification = $errorConfig['devTeam_email_need'] ?? false;
        $notificationsEnabled = $this->emailConfig['enabled'] ?? false;
        $hasRecipient = !empty($this->emailConfig['to']);

        // Log why it might not be handled if needed for debugging config issues
        // if ($errorRequiresNotification && $notificationsEnabled && !$hasRecipient) {
        //     $this->ulmLogger->debug('UEM EmailHandler: Skipping email, recipient not configured.', ['errorCode' => $errorConfig['error_code'] ?? 'N/A']);
        // }

        return $errorRequiresNotification && $notificationsEnabled && $hasRecipient;
    }

    /**
     * ✉️ Handle the error by sending a sanitized and configurable email notification.
     * 📤 @data-output (To Mail System)
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
        $recipient = $this->emailConfig['to']; // Already checked in shouldHandle

        try {
            $subject = $this->prepareSubject($errorCode);
            // Prepare data, applying sanitization and conditional inclusion
            $data = $this->prepareEmailData($errorCode, $errorConfig, $context, $exception);
            $from = $this->emailConfig['from'] ?? [];

            $this->mailer->send('error-manager::emails.error-notification', $data, function ($message) use ($recipient, $subject, $from) {
                $message->to($recipient)->subject($subject);
                if (!empty($from['address'])) {
                    $message->from($from['address'], $from['name'] ?? $this->appName . ' Errors');
                }
            });

            $this->ulmLogger->info('UEM EmailHandler: Notification email sent.', [
                'recipient' => $recipient, 'errorCode' => $errorCode
            ]);

        } catch (Throwable $e) {
            $this->ulmLogger->error("UEM EmailHandler: Failed to send notification email.", [
                'original_error_code' => $errorCode, 'recipient' => $recipient,
                'email_handler_exception' => ['message' => $e->getMessage(), 'class' => get_class($e)],
                 // Avoid logging full trace of email sending failure unless debugging mailer issues
                 // 'trace' => $e->getTraceAsString(),
            ]);
             // Trigger UEM_EMAIL_SEND_FAILED? Needs ErrorManager instance injected. For now, just log.
             // resolve(ErrorManagerInterface::class)->handle('UEM_EMAIL_SEND_FAILED', [...], $e);
        }
    }

    /**
     * 🧱 Prepare email subject line.
     * @param string $errorCode Error code identifier.
     * @return string Formatted subject line.
     */
    protected function prepareSubject(string $errorCode): string
    {
        $prefix = $this->emailConfig['subject_prefix'] ?? '[UEM Error] ';
        return "{$prefix}{$this->appName} ({$this->environment}): {$errorCode}";
    }

    /**
     * 🧱 Prepare data array for the email view, applying sanitization and config flags.
     * 🛡️ Applies sanitization to context and conditionally includes sensitive fields.
     *
     * @param string $errorCode Error code identifier.
     * @param array $errorConfig Error configuration.
     * @param array $context Original contextual data.
     * @param Throwable|null $exception Optional original exception.
     * @return array Data array for the email view.
     */
    protected function prepareEmailData(string $errorCode, array $errorConfig, array $context, ?Throwable $exception): array
    {
        $authGuard = $this->auth->guard();
        $user = $authGuard->user();

        $data = [
            'appName'       => $this->appName,
            'environment'   => $this->environment,
            'errorCode'     => $errorCode,
            'errorType'     => $errorConfig['type'] ?? 'error',
            'message'       => $errorConfig['message'] ?? 'Error occurred', // Dev message
            'timestamp'     => now()->format('Y-m-d H:i:s'),
            // Request details (conditional)
            'requestUrl'    => $this->request->fullUrl(), // Always include URL? It's useful context.
            'requestMethod' => $this->request->method(),
        ];

        // Conditionally include IP Address
        if ($this->emailConfig['include_ip_address'] ?? false) {
            $data['userIp'] = $this->request->ip();
        } else {
            $data['userIp'] = '[Redacted by Config]';
        }

        // Conditionally include User Agent
        if ($this->emailConfig['include_user_agent'] ?? false) {
            $data['userAgent'] = $this->request->userAgent() ?? 'N/A';
        } else {
            $data['userAgent'] = '[Redacted by Config]';
        }

        // Conditionally include User Details
        $data['userId'] = null;
        $data['userName'] = '[Redacted by Config]';
        $data['userEmail'] = '[Redacted by Config]';
        if (($this->emailConfig['include_user_details'] ?? false) && $user instanceof Authenticatable) {
            $data['userId'] = $authGuard->id();
            $data['userName'] = $user->name ?? ($user->username ?? 'N/A'); // Adapt to your user model
            $data['userEmail'] = $user->email ?? 'N/A'; // Adapt to your user model
        } elseif ($authGuard->check()) {
             // If details are off, but user is logged in, at least include the ID? Or redact all?
             // For now, include ID if logged in, even if full details are off.
             $data['userId'] = $authGuard->id();
        }


        // Conditionally include Sanitized Context
        if ($this->emailConfig['include_context'] ?? true) {
            $data['context'] = $this->sanitizeContextForEmail($context); // Sanitize before including
        } else {
            $data['context'] = ['message' => '[Context Redacted by Config]'];
        }

        // Conditionally include Exception Details (with Trace limiting)
        $data['exception'] = null; // Default to null
        if ($exception) {
             $exceptionData = [
                 'class'   => get_class($exception),
                 'message' => $this->sanitizeStringValue($exception->getMessage(), 1000), // Sanitize/limit message
                 'file'    => $exception->getFile(),
                 'line'    => $exception->getLine(),
                 'trace'   => '[Trace Redacted by Config]', // Default trace state
             ];
             if ($this->emailConfig['include_trace'] ?? false) {
                 $traceLines = explode("\n", $exception->getTraceAsString());
                 $maxLines = $this->emailConfig['trace_max_lines'] ?? 30;
                 $limitedTrace = implode("\n", array_slice($traceLines, 0, $maxLines));
                 if (count($traceLines) > $maxLines) {
                     $limitedTrace .= "\n[... Trace Truncated to {$maxLines} lines ...]";
                 }
                 $exceptionData['trace'] = $limitedTrace;
             }
             $data['exception'] = $exceptionData;
        }

        return $data;
    }

    /**
     * 🔐 Sanitize context data specifically for email notifications.
     * Uses sensitive keys defined in the 'email_notification' config section.
     *
     * 🛡️ @privacy-safe Helper for context sanitization.
     * 🧼 @sanitizer
     *
     * @param array $context The context array to sanitize.
     * @return array The sanitized context array.
     */
    protected function sanitizeContextForEmail(array $context): array
    {
        $defaultSensitiveKeys = ['password', 'secret', 'token', 'auth', 'key', 'credentials', 'authorization', 'php_auth_user', 'php_auth_pw', 'credit_card', 'cvv', 'api_key'];
        // Use keys specific to email config, or fallback to defaults
        $sensitiveKeys = $this->emailConfig['context_sensitive_keys'] ?? $defaultSensitiveKeys;
        $sensitiveKeys = array_map('strtolower', $sensitiveKeys);

        $sanitized = [];
        foreach ($context as $key => $value) {
            $lowerKey = strtolower((string)$key);

             // Dynamic check - maybe less aggressive for email than DB? Optional.
             // if (str_contains($lowerKey, 'password') || ...) { ... }

            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeContextForEmail($value); // Recursive
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeStringValue($value, 500); // Limit string length in email context
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
      * ✂️ Basic string sanitization (shared logic).
      * @param string $value
      * @param int $maxLength
      * @return string
      */
     protected function sanitizeStringValue(string $value, int $maxLength = 500): string
     {
         if (mb_strlen($value) > $maxLength) {
             $value = mb_substr($value, 0, $maxLength - 16) . '...[TRUNCATED]';
         }
         return str_replace("\0", '', $value);
     }
}