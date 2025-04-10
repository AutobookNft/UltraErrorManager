# UltraErrorManager (UEM) – Oracoded Error Management for Laravel

[![Latest Version](https://img.shields.io/badge/version-v1.0.0-blue.svg?style=flat-square)](https://github.com/AutobookNft/UltraErrorManager) <!-- Placeholder -->
[![Build Status](https://img.shields.io/badge/build-passing-brightgreen.svg?style=flat-square)](...) <!-- Placeholder -->
[![Coverage Status](https://img.shields.io/badge/coverage-XX%25-brightgreen.svg?style=flat-square)](...) <!-- Placeholder -->
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](https://opensource.org/licenses/MIT)

**UltraErrorManager (UEM)** is an enterprise-level, configuration-driven, and Oracode-compliant error management system designed for robust Laravel applications, specifically within the Ultra ecosystem (e.g., FlorenceEGI). It provides a centralized hub for catching, processing, logging, notifying, and potentially recovering from errors occurring throughout your application.

Built with **Dependency Injection** at its core, UEM is highly **testable**, **extensible** via custom handlers, and designed with **GDPR awareness** in mind.

---

## 📜 Core Philosophy: Oracode Doctrine

UEM adheres strictly to the **Oracode Doctrine**, ensuring code is not just functional but also **interrogable**, **explicitly intentional**, and **semantically coherent**. This means:

*   **Ultra Documentation Philosophy (UDP):** Every significant component explains *why* it exists. Code aims to be self-documenting through clear naming, structure, and targeted comments/annotations.
*   **AChaos Principle:** Embraces the flow from chaotic exploration (development, debugging) to structured, transmittable form (stable, documented code). The DI-first approach facilitates this by promoting decoupled components.
*   **Oracode Annotations:** Uses specific annotations (`@/#[🎯]`, `@[🧱]`, `@[📡]`, `@[🧪]`, `@[🛡️]`, `@[📥]`, `@[📤]`, etc.) within PHP docblocks and attributes to convey semantic meaning about purpose, structure, communication, testability, and GDPR implications.

*(For more details, refer to the main Oracode Doctrine documentation.)*

---

## ✨ Features

*   **Centralized Error Handling:** Single point (`ErrorManagerInterface`) for processing all application errors.
*   **Configuration-Driven:** Define error codes, types (critical, error, warning, notice), blocking levels (blocking, semi-blocking, not), messages (dev/user), HTTP status codes, display modes, and notification needs in `config/error-manager.php`.
*   **Extensible Handler Pattern:** Process errors through a pipeline of handlers. Comes with defaults for:
    *   Logging (via injected `UltraLogManager`)
    *   Database Persistence (`ErrorLog` model)
    *   Email Notifications (via injected `MailerContract`)
    *   Slack Notifications (via injected `HttpClientFactory`)
    *   User Interface Preparation (flashing data to session via injected `Session`)
    *   Automated Recovery Actions (placeholder logic, extensible)
    *   Error Simulation Logging (dev environments only)
*   **Dependency Injection First:** Core services (`ErrorManager`, Handlers) rely on DI, making them facade-free internally and highly testable.
*   **Oracode Compliant:** Internal codebase uses Oracode annotations and principles.
*   **GDPR Aware:** Includes annotations and promotes practices like context sanitization (see GDPR section below).
*   **Error Simulation:** Built-in service (`TestingConditionsManager`) and API/Dashboard UI to activate/deactivate specific error codes for testing application responses.
*   **Web Dashboard:** Provides a UI (`/error-manager/dashboard`) to view, filter, resolve, and analyze logged errors (requires database logging handler).
*   **Ultra Ecosystem Integration:** Designed to work seamlessly with `UltraLogManager` (for logging) and `UltraTranslationManager` (via `TranslatorContract` for messages).

---

## 💾 Installation

1.  **Require the Package:**
    ```bash
    composer require ultra/ultra-error-manager:dev-main
    ```
    *(Adjust version constraint as needed. `dev-main` assumes you are actively developing it.)*

2.  **Configure Repositories (if using VCS):** Ensure your application's `composer.json` includes the necessary VCS repositories if you are pulling dependencies like ULM or UTM directly from Git:
    ```json
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/AutobookNft/UltraLogManager"
        },
        {
            "type": "vcs",
            "url": "https://github.com/AutobookNft/UltraTranslationManager.git"
        },
        {
            "type": "vcs",
            "url": "https://github.com/AutobookNft/UltraErrorManager.git" // If needed
        }
        // Add other Ultra package repositories if necessary
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
    ```
    Then run `composer update`.

3.  **Publish Configuration:**
    ```bash
    php artisan vendor:publish --tag=error-manager-config
    ```
    Review and customize `config/error-manager.php` according to your needs (especially notification recipients, error definitions).

4.  **Publish & Run Migrations:**
    ```bash
    php artisan vendor:publish --tag=error-manager-migrations
    php artisan migrate
    ```
    This creates the `error_logs` table required by the `DatabaseLogHandler`.

5.  **Publish Translations (Optional):**
    ```bash
    php artisan vendor:publish --tag=error-manager-language
    ```
    Customize messages in `resources/lang/vendor/error-manager/{locale}/errors.php`.

6.  **Publish Views (Optional):**
    ```bash
    php artisan vendor:publish --tag=error-manager-views
    ```
    Customize the dashboard views in `resources/views/vendor/error-manager/`.

7.  **Register Middleware:** Add the `ErrorHandlingMiddleware` to your global HTTP middleware stack (usually in `app/Http/Kernel.php`'s `$middleware` group) to automatically catch and handle exceptions. Ensure it's placed appropriately, often near the top. Add `EnvironmentMiddleware` to specific routes (like the simulation API routes in `routes/api.php`) or route groups that need environment protection.
    ```php
    // app/Http/Kernel.php
    protected $middleware = [
        // ... other global middleware
        \Ultra\ErrorManager\Http\Middleware\ErrorHandlingMiddleware::class,
        // ... other global middleware
    ];

    protected $middlewareAliases = [ // Renamed from $routeMiddleware for clarity in modern Laravel
        // ... other aliases
        'environment' => \Ultra\ErrorManager\Http\Middleware\EnvironmentMiddleware::class,
        // ...
    ];
    ```

---

## ⚙️ Configuration

The main configuration file is `config/error-manager.php`. Key sections include:

*   **`default_handlers`**: Array of `ErrorHandlerInterface` class strings to register by default. Order can matter.
*   **`email_notification` / `slack_notification`**: Settings for enabling/disabling and configuring recipients, webhooks, etc. **Remember to set actual recipient/webhook URLs in your `.env` file.**
*   **`database_logging`**: Enable/disable DB logging, include traces, set max trace length.
*   **`ui`**: Default display mode, whether to show error codes to users, generic message key.
*   **`error_types`**: Define severity levels (critical, error, warning, notice) and their default behavior (log level, notify team, HTTP status).
*   **`blocking_levels`**: Define application flow impact (blocking, semi-blocking, not).
*   **`fallback_error` / `errors`**: The core dictionary mapping your application's symbolic error codes (e.g., `USER_NOT_FOUND`, `UPLOAD_FAILED`) to their specific configuration (type, blocking, messages, http status, notifications, recovery action). **This is where you define your application's error vocabulary.**

---

## 🚀 Basic Usage

There are three primary ways to trigger error handling:

**1. Dependency Injection (Recommended)**

Inject `Ultra\ErrorManager\Interfaces\ErrorManagerInterface` into your controllers, services, or other classes:

```php
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface;
use App\Exceptions\CustomDomainException; // Your specific exception

class MyService
{
    protected readonly ErrorManagerInterface $errorManager;

    public function __construct(ErrorManagerInterface $errorManager)
    {
        $this->errorManager = $errorManager;
    }

    public function processUserData(int $userId, array $data)
    {
        try {
            // ... some logic that might fail ...
            if (empty($data['email'])) {
                // Handle the error, providing context
                // Returns a Response or null (for non-blocking HTML)
                return $this->errorManager->handle(
                    'USER_DATA_INVALID',
                    ['user_id' => $userId, 'missing_field' => 'email']
                );
            }
            // ... more logic ...
        } catch (CustomDomainException $e) {
            // Handle a specific domain exception, passing it along
            return $this->errorManager->handle(
                'DOMAIN_RULE_VIOLATED',
                ['user_id' => $userId, 'rule' => $e->getRule()],
                $e // Pass the original exception
            );
        }
    }
}
```

```php
use Ultra\ErrorManager\Facades\UltraError;

// Simple handling
UltraError::handle('CACHE_FLUSH_FAILED');

// Handling with context and exception
try {
    // ...
} catch (\Exception $e) {
    UltraError::handle('EXTERNAL_API_UNREACHABLE', ['api_name' => 'Stripe'], $e);
}
```

```php
// Handle an error
ultra_error('QUEUE_JOB_FAILED', ['job_id' => $job->id, 'queue' => $job->queue]);

// Handle and force throwing an exception
try {
   // ...
} catch (\Exception $e) {
    ultra_error('CRITICAL_PROCESS_FAILURE', [], $e, true); // $throw = true
}
```

## 💡 Advanced Concepts

### Custom Error Handlers

You can extend UEM's functionality by creating your own error handlers:

1.  **Create a Class:** Implement the `Ultra\ErrorManager\Interfaces\ErrorHandlerInterface`.
2.  **Implement Methods:**
    *   `shouldHandle(array $errorConfig): bool`: Add logic here to determine if your handler should process the given error based on its configuration (e.g., type, code, custom flags).
    *   `handle(string $errorCode, array $errorConfig, array $context = [], ?Throwable $exception = null): void`: Implement the core logic of your handler (e.g., send metrics, log to a specific service, trigger a custom action).
3.  **Inject Dependencies:** Use constructor injection for any services your handler needs (Loggers, HTTP Clients, etc.). Remember to make your handler facade-free internally.
4.  **Register Your Handler:**
    *   **Statically (Recommended):** Add your handler's fully qualified class name to the `default_handlers` array in `config/error-manager.php`. Ensure your handler class and its dependencies can be resolved by the service container (you might need to add bindings in a Service Provider if dependencies aren't auto-discoverable, or update the `registerHandlers` method in `UltraErrorManagerServiceProvider`).
    *   **Dynamically:** You can register an instance dynamically if needed, although less common for default handlers:
        ```php
        resolve(ErrorManagerInterface::class)->registerHandler(new MyCustomHandler(/* dependencies */));
        ```

### Error Simulation (Testing)

UEM includes a system for simulating errors in non-production environments, crucial for testing how your application reacts.

*   **Service:** `Ultra\ErrorManager\Services\TestingConditionsManager` (bound as singleton `'ultra.testing-conditions'`).
*   **Facade:** `Ultra\ErrorManager\Facades\TestingConditions` (provides static access).
*   **Activation/Deactivation:**
    *   Use the Web Dashboard (`/error-manager/dashboard/simulations`).
    *   Use the API (`POST /api/errors/simulate/{errorCode}`, `DELETE /api/errors/simulate/{errorCode}`).
    *   Use the Facade/Service directly in tests: `TestingConditions::set('MY_ERROR', true);` or `TestingConditions::clear('MY_ERROR');`.
    *   Use the global helper: `simulate_error('MY_ERROR', true);`.
*   **Checking Simulation Status:**
    *   Inside your code (use cautiously, mainly for testing specific branches): `if (TestingConditions::isTesting('MY_ERROR')) { ... }`.
    *   The `ErrorSimulationHandler` (if active in dev environments) automatically logs whether a handled error was being actively simulated at the time.

### GDPR & Oracode Annotations

UEM's internal code utilizes specific Oracode annotations to enhance clarity and indicate potential areas of interest, especially regarding privacy:

*   `@/#[🛡️] @privacy-safe`: Code designed for safe data handling (e.g., sanitization routines).
*   `@/#[📥] @data-input`: Method receives potentially sensitive data (often via `$context` or request objects).
*   `@/#[📤] @data-output`: Method outputs potentially sensitive data (to logs, notifications, responses). Requires careful review.
*   `@/#[🪵] @log`: Indicates significant logging activity (useful for audit trails).
*   `@/#[🔥] @critical`: Marks operations requiring heightened attention (e.g., core error handling, security-related actions).
*   `@/#[🚨] @error-boundary`: Key points where errors are caught or managed centrally.
*   `@/#[🧼] @sanitizer`: Code specifically intended to clean, filter, or scrub data.

When working with `$context` or exception details passed through UEM handlers, always consider potential PII and ensure appropriate sanitization occurs *before* data is persisted (DB) or sent externally (Email, Slack).

### Dashboard

The web dashboard, accessible at `/error-manager/dashboard` (ensure proper middleware protection), provides a UI to:

*   View and filter logged errors (requires `DatabaseLogHandler`).
*   Inspect error details (context, exception, request info).
*   Manage error status (resolve/unresolve) and add notes.
*   Delete individual logs or purge old resolved logs based on retention needs.
*   View basic error statistics and trends.
*   Manage error simulations (activate/deactivate).

---