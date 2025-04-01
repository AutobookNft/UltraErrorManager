<?php

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;

/**
 * Email Notification Handler
 *
 * This handler is responsible for sending email notifications to the
 * development team when critical errors occur.
 *
 * @package Ultra\ErrorManager\Handlers
 */
class EmailNotificationHandler implements ErrorHandlerInterface
{
    /**
     * Determine if this handler should handle the error
     *
     * @param array $errorConfig Error configuration
     * @return bool True if this handler should process the error
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Only handle errors that require dev team email notification
        if (!($errorConfig['devTeam_email_need'] ?? false)) {
            return false;
        }

        // Also check if email notifications are enabled globally
        return Config::get('error-manager.email_notification.enabled', true);
    }

    /**
     * Handle the error by sending an email notification
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?\Throwable $exception = null): void
    {
        // Get recipient from config
        $recipient = Config::get('error-manager.email_notification.to');
        if (!$recipient) {
            return;
        }

        // Prepare email data
        $subject = $this->prepareSubject($errorCode, $errorConfig);
        $data = $this->prepareEmailData($errorCode, $errorConfig, $context, $exception);

        // Send the email
        try {
            Mail::send('error-manager::emails.error-notification', $data, function ($message) use ($recipient, $subject) {
                $message->to($recipient)
                    ->subject($subject);

                $from = Config::get('error-manager.email_notification.from', []);
                if (!empty($from['address'])) {
                    $message->from($from['address'], $from['name'] ?? 'Error Monitoring System');
                }
            });
        } catch (\Exception $e) {
            // Log error but don't re-throw - we don't want errors in error handling
            \Log::error("Failed to send error notification email: {$e->getMessage()}", [
                'original_error' => $errorCode,
                'email_exception' => [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ]);
        }
    }

    /**
     * Prepare email subject line
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @return string Formatted subject line
     */
    protected function prepareSubject(string $errorCode, array $errorConfig): string
    {
        $prefix = Config::get('error-manager.email_notification.subject_prefix', '[ERROR] ');
        $appName = Config::get('app.name', 'Application');
        $env = app()->environment();

        return "{$prefix}{$appName} ({$env}): {$errorCode}";
    }

    /**
     * Prepare data for the email template
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return array Data for the email template
     */
    protected function prepareEmailData(string $errorCode, array $errorConfig, array $context, ?\Throwable $exception): array
    {
        $data = [
            'appName' => Config::get('app.name', 'Application'),
            'environment' => app()->environment(),
            'errorCode' => $errorCode,
            'errorType' => $errorConfig['type'] ?? 'error',
            'message' => $errorConfig['dev_message'] ?? 'Error occurred',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'context' => $context,
            'requestUrl' => request()->fullUrl(),
            'requestMethod' => request()->method(),
            'userAgent' => request()->userAgent(),
            'userIp' => request()->ip(),
        ];

        // Add authenticated user info if available
        if (auth()->check()) {
            $data['userId'] = auth()->id();
            $data['userName'] = auth()->user()->name ?? 'Unknown';
            $data['userEmail'] = auth()->user()->email ?? 'Unknown';
        }

        // Add exception information if present
        if ($exception) {
            $data['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return $data;
    }
}
