<?php

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Support\Facades\Session;
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;

/**
 * User Interface Handler
 *
 * This handler is responsible for preparing user-facing error messages
 * and storing them for display in the appropriate UI element.
 *
 * @package Ultra\ErrorManager\Handlers
 */
class UserInterfaceHandler implements ErrorHandlerInterface
{
    /**
     * Determine if this handler should handle the error
     *
     * @param array $errorConfig Error configuration
     * @return bool True if this handler should process the error
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Only handle errors that have a user message and display target
        return isset($errorConfig['user_message']) ||
               isset($errorConfig['user_message_key']) ||
               isset($errorConfig['msg_to']);
    }

    /**
     * Handle the error by preparing user interface messages
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @param \Throwable|null $exception Original exception if available
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], \Throwable $exception = null): void
    {
        // Get display target (div, sweet-alert, etc.)
        $displayTarget = $errorConfig['msg_to'] ?? config('error-manager.ui.default_display_mode', 'div');

        // Skip if set to log-only
        if ($displayTarget === 'log-only') {
            return;
        }

        // Get the user message
        $userMessage = $this->getUserMessage($errorCode, $errorConfig, $context);

        // Flash error message to session for the appropriate display target
        Session::flash("error_{$displayTarget}", $userMessage);

        // If configured to show error codes to users, also include the code
        if (config('error-manager.ui.show_error_codes', false)) {
            Session::flash("error_code_{$displayTarget}", $errorCode);
        }

        // Store full error info for potential use in views or API responses
        Session::flash('error_info', [
            'error_code' => $errorCode,
            'message' => $userMessage,
            'type' => $errorConfig['type'] ?? 'error',
            'blocking' => $errorConfig['blocking'] ?? 'blocking',
            'display_target' => $displayTarget,
        ]);
    }

    /**
     * Get the user-facing error message with placeholders replaced
     *
     * @param string $errorCode Error code identifier
     * @param array $errorConfig Error configuration
     * @param array $context Contextual data
     * @return string Formatted user message
     */
    protected function getUserMessage(string $errorCode, array $errorConfig, array $context): string
    {
        // First try to use a translation key if present
        if (isset($errorConfig['user_message_key'])) {
            $message = __($errorConfig['user_message_key'], $context);
        }
        // Otherwise use direct message if present
        elseif (isset($errorConfig['user_message'])) {
            $message = $errorConfig['user_message'];

            // Replace placeholders in user message
            foreach ($context as $key => $value) {
                if (is_string($value) || is_numeric($value)) {
                    $message = str_replace(":{$key}", $value, $message);
                }
            }
        }
        // Use a generic fallback message if no specific one is available
        else {
            $message = __(config('error-manager.ui.generic_error_message', 'An error has occurred.'));
        }

        return $message;
    }
}
