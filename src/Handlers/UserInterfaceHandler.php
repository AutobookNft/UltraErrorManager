<?php

declare(strict_types=1);

namespace Ultra\ErrorManager\Handlers;

use Illuminate\Contracts\Session\Session; // Dependency for Session Store
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;
use Throwable; // Import Throwable

/**
 * 🎯 UserInterfaceHandler – Oracoded UI Error Preparation Handler (Verbose & GDPR Compliant)
 *
 * This handler prepares error information specifically for presentation within the
 * user interface. Its primary role is to take the resolved error details (especially
 * the user-safe message prepared by ErrorManager) and flash them into the session storage.
 * Subsequent mechanisms (like middleware injecting flashed data into views, view composers,
 * or client-side JavaScript reading session data/specific meta tags) are responsible
 * for actually rendering the error display (e.g., in a designated div, a SweetAlert modal,
 * or a toast notification). This handler ensures the necessary data is available
 * for the next request cycle's UI rendering phase.
 *
 * 🧱 Structure:
 * - Implements ErrorHandlerInterface.
 * - Requires the Session contract (`Illuminate\Contracts\Session\Session`) and the UI-specific
 *   configuration array (`error-manager.ui`) injected via the constructor.
 * - The `shouldHandle` method checks if the error is meant for UI display (not 'log-only')
 *   and if a user message is available.
 * - The `handle` method determines the intended display target (e.g., 'div', 'sweet-alert').
 *   It then uses the injected Session instance to flash:
 *     1. The primary user message under a target-specific key (e.g., `error_div`).
 *     2. Optionally, the error code under a similar target-specific key (e.g., `error_code_div`),
 *        if enabled in the configuration (`ui.show_error_codes`).
 *     3. A generic `error_info` array containing structured data (code, message, type, blocking, target)
 *        that frontend components might use for more advanced conditional rendering or styling.
 * - Includes a helper method (`getGenericErrorMessage`) to retrieve a fallback message using
 *   Laravel's translation system if needed.
 *
 * 📡 Communicates:
 * - **Writes** to the Session Store using the injected `Session` contract instance (`flash` method).
 * - **Reads** UI configuration settings from the `$uiConfig` array injected during construction.
 * - (Indirectly) reads translation strings via the `__()` helper in `getGenericErrorMessage`.
 *
 * 🧪 Testable:
 * - Core dependencies (`Session`, `uiConfig` array) are injected, making the handler highly mockable.
 * - Session interactions can be effectively tested using Laravel's built-in session testing utilities
 *   (e.g., `session()->shouldReceive('flash')->once()`).
 * - The `shouldHandle` logic is simple conditional checking based on configuration.
 * - The message retrieval logic relies on data passed in (`$errorConfig`) or the testable `__()` helper.
 *
 * 🛡️ GDPR Considerations:
 * - This handler **outputs data** (`@data-output`) to the session store. While the session is typically
 *   tied to the specific user making the request, the data stored (user message, error code)
 *   should be considered from a privacy perspective.
 * - **Crucially, it assumes the `user_message` provided in `$errorConfig` (prepared by `ErrorManager`)
 *   has been appropriately localized and **does not contain Personal Identifiable Information (PII)**.
 *   The primary GDPR risk associated with this handler stems from defining unsafe or overly detailed
 *   user messages in translation files or direct configuration settings. Review user-facing error messages carefully.
 * - Flashing the `errorCode` is generally considered low risk as it's symbolic, but exposure can be
 *   disabled via the `ui.show_error_codes` configuration setting for added caution.
 * - The `error_info` array flashed contains metadata and the user message, inheriting the same PII considerations as the message itself.
 */
final class UserInterfaceHandler implements ErrorHandlerInterface
{
    /**
     * 🧱 @dependency Session Store instance.
     * Used to flash error information for the next request's UI rendering.
     * @var Session
     */
    protected readonly Session $session;

    /**
     * 🧱 @dependency UI Configuration array.
     * Contains settings like 'default_display_mode', 'show_error_codes', 'generic_error_message'.
     * Injected from the 'error-manager.ui' config section.
     * @var array<string, mixed>
     */
    protected readonly array $uiConfig;

    /**
     * 🎯 Constructor: Injects Session contract and UI-specific configuration.
     *
     * @param Session $session Laravel's Session store contract instance.
     * @param array $uiConfig Configuration specific to UI handling (from 'error-manager.ui').
     */
    public function __construct(Session $session, array $uiConfig)
    {
        $this->session = $session;
        $this->uiConfig = $uiConfig;
    }

    /**
     * 🧠 Determine if this handler should process the given error.
     *
     * This method checks two main conditions:
     * 1. Is the error configured to be displayed in the UI (i.e., `msg_to` is not 'log-only')?
     * 2. Is there a user-facing message defined for this error (either directly in the config
     *    passed via `$errorConfig['user_message']` or via a translation key reference)?
     * Only if both conditions are met (or suitable defaults apply) should this handler proceed.
     *
     * @param array $errorConfig The resolved configuration array for the specific error being handled.
     * @return bool Returns `true` if the error is intended for UI display and has a message, `false` otherwise.
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Determine the intended display target, using the configured default if not specified for the error code.
        $displayTarget = $errorConfig['msg_to'] ?? $this->uiConfig['default_display_mode'] ?? 'div';

        // If explicitly set to 'log-only', this handler should not process it.
        if ($displayTarget === 'log-only') {
            return false;
        }

        // Check if a user-facing message exists. ErrorManager should have prepared 'user_message' if possible.
        $hasUserMessage = !empty($errorConfig['user_message']); // Check the prepared message first

        // As a fallback (though ErrorManager should handle this), check original config keys
        if (!$hasUserMessage) {
             $hasUserMessage = !empty($errorConfig['user_message_key']) || !empty($errorConfig['user_message']);
        }

        return $hasUserMessage;
    }

    /**
     * 📨 Handle the error by flashing the relevant user-facing information to the session.
     *
     * This method takes the resolved error details and uses the injected Session service
     * to store ("flash") data needed for displaying the error message during the *next*
     * HTTP request. It constructs session keys based on the intended display target
     * (e.g., 'error_div', 'error_code_sweet-alert') to allow different UI mechanisms
     * to pick up the correct data. It also flashes a generic 'error_info' array
     * containing structured metadata about the error for potentially more complex UI logic.
     * It relies on the `user_message` already being prepared and localized by ErrorManager.
     *
     * 📤 @data-output (Writes message, code, type, blocking level, target to Session Store)
     *
     * @param string $errorCode The symbolic error code (e.g., 'VALIDATION_ERROR').
     * @param array $errorConfig The resolved configuration metadata for the error, expected to contain the prepared 'user_message'.
     * @param array $context Contextual data (used primarily here if a fallback generic message needs translation parameters).
     * @param Throwable|null $exception Optional original throwable (not directly used by this handler).
     * @return void
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void
    {
        // Determine display target again (could be passed as argument if refactored)
        $displayTarget = $errorConfig['msg_to'] ?? $this->uiConfig['default_display_mode'] ?? 'div';

        // Safety guard if shouldHandle logic changes
        if ($displayTarget === 'log-only') {
            return;
        }

        // Retrieve the user message (should be pre-formatted by ErrorManager)
        // Use the helper for generic fallback only if absolutely necessary.
        $userMessage = $errorConfig['user_message'] ?? $this->getGenericErrorMessage($context);

        // Flash the main message to the session using a target-specific key.
        $this->session->flash("error_{$displayTarget}", $userMessage);

        // Optionally flash the error code if configured to do so.
        if ($this->uiConfig['show_error_codes'] ?? false) {
            $this->session->flash("error_code_{$displayTarget}", $errorCode);
        }

        // Flash a structured array with general info for potential use by view composers or JS.
        $this->session->flash('error_info', [
            'error_code'     => $errorCode,
            'message'        => $userMessage, // The exact message that was flashed
            'type'           => $errorConfig['type'] ?? 'error',
            'blocking'       => $errorConfig['blocking'] ?? 'blocking',
            'display_target' => $displayTarget,
        ]);
    }

    /**
     * 🧱 Get the generic fallback error message string.
     *
     * Retrieves the translation key for the generic error message from the UI config,
     * then uses Laravel's global `__()` helper (which resolves the Translator contract)
     * to get the translated string, passing any context for placeholder replacement.
     *
     * @param array $context Context for potential placeholder replacement in the generic message.
     * @return string The translated generic error message.
     */
    protected function getGenericErrorMessage(array $context = []): string
    {
        // Get the configured key, default to a known one if not set
        $genericKey = $this->uiConfig['generic_error_message'] ?? 'error-manager::errors.user.generic_error';
        // Use the __() helper for translation
        return __($genericKey, $context);
    }
}