# Ultra Error Manager

A comprehensive enterprise-level error management system for Laravel applications. Part of the Ultra ecosystem.

## Features

- **Centralized Error Handling**: Manages all errors through a single, consistent system
- **Modular Architecture**: Extensible system with specialized handlers
- **Custom Error Types**: Define your own error types with specific behaviors
- **Localization Support**: Multilingual error messages for users and developers
- **Error Dashboard**: Visual monitoring and management of system errors
- **Slack Integration**: Real-time notifications for critical errors
- **Error Simulation**: Tools for testing error handling in development
- **Automatic Recovery**: Smart recovery actions for specific error types
- **Comprehensive Statistics**: Track and analyze error trends
- **Database Logging**: Store errors for analysis and reporting

## Installation

```bash
composer require ultra/errormanager
```

Publish the configuration and assets:

```bash
php artisan vendor:publish --provider="Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider"
```

Run the migrations:

```bash
php artisan migrate
```

## Basic Usage

### Handling Errors

```php
use Ultra\ErrorManager\Facades\UltraError;

try {
    // Your code that might throw an exception
} catch (\Exception $e) {
    return UltraError::handle('UNEXPECTED_ERROR', [
        'custom_data' => 'Additional context',
    ], $e);
}
```

Or use the helper function:

```php
try {
    // Your code that might throw an exception
} catch (\Exception $e) {
    return ultra_error('UNEXPECTED_ERROR', [
        'custom_data' => 'Additional context',
    ], $e);
}
```

### Defining Custom Errors

You can define custom errors in the `config/error-manager.php` file:

```php
'errors' => [
    'CUSTOM_ERROR_CODE' => [
        'type' => 'critical',
        'blocking' => 'semi-blocking',
        'dev_message_key' => 'error-manager::errors.dev.custom_error',
        'user_message_key' => 'error-manager::errors.user.custom_error',
        'http_status_code' => 500,
        'devTeam_email_need' => true,
        'msg_to' => 'sweet-alert',
        'recovery_action' => 'custom_recovery',
    ],
],
```

### Simulating Errors (Development Only)

```php
use Ultra\ErrorManager\Facades\TestingConditions;

// Activate error simulation
TestingConditions::setCondition('VIRUS_FOUND', true);

// Or use the helper function
simulate_error('VIRUS_FOUND');
```

## Middleware Integration

Add the error handling middleware to your HTTP kernel:

```php
// app/Http/Kernel.php

protected $middlewareGroups = [
    'web' => [
        // Other middleware...
        \Ultra\ErrorManager\Http\Middleware\ErrorHandlingMiddleware::class,
    ],
];
```

## Creating Custom Handlers

Create a new handler by implementing the `ErrorHandlerInterface`:

```php
<?php

namespace App\ErrorHandlers;

use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface;

class CustomHandler implements ErrorHandlerInterface
{
    /**
     * Determine if this handler should handle the error
     */
    public function shouldHandle(array $errorConfig): bool
    {
        // Your logic to determine if this handler should be used
        return true;
    }
    
    /**
     * Handle the error
     */
    public function handle(string $errorCode, array $errorConfig, array $context = [], \Throwable $exception = null): void
    {
        // Your custom error handling logic
    }
}
```

Register your custom handler:

```php
use Ultra\ErrorManager\Facades\UltraError;
use App\ErrorHandlers\CustomHandler;

// In a service provider
UltraError::registerHandler(new CustomHandler());
```

## Dashboard

The error dashboard is available at `/error-manager/dashboard` and provides:

- Overview of all errors
- Filtering by type, code, and status
- Detailed error information
- Error statistics and trends
- Management tools (resolve, delete, etc.)

## Integration with Ultra Upload Manager

Ultra Error Manager seamlessly integrates with Ultra Upload Manager:

```php
// In your upload controller
use Ultra\ErrorManager\Facades\UltraError;

public function uploadFile(Request $request)
{
    try {
        // Upload logic...
    } catch (VirusFoundException $e) {
        return UltraError::handle('VIRUS_FOUND', [
            'fileName' => $fileName,
        ], $e);
    } catch (Exception $e) {
        return UltraError::handle('ERROR_DURING_FILE_UPLOAD', [
            'fileName' => $fileName,
        ], $e);
    }
}
```

## License

The Ultra Error Manager is open-sourced software licensed under the [MIT license](LICENSE).