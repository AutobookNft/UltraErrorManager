// /home/fabio/libraries/UltraErrorManager/resources/ts/utils/ErrorConfigLoader.ts
// Oracode/GDPR Refactoring by Padmin D. Curtis

// --- Oracode Refined Module Doc ---
/**
 * 🎯 Purpose: Provides a centralized, singleton service for fetching, caching,
 *    and accessing client-side error configurations (definitions, types, blocking levels)
 *    retrieved from a server endpoint (`/api/error-definitions`). Implements lazy loading,
 *    caching, retry logic with exponential backoff, and fallback mechanisms to ensure
 *    configurations are available even if the initial fetch fails. Notifies the application
 *    when configurations are successfully loaded or when fallback is used.
 *
 * 🧱 Structure:
 *    - Implements the Singleton pattern (`getInstance`).
 *    - Manages internal state: `errorDefinitions`, `errorTypes`, `blockingLevels`, `isLoaded`, `loadPromise`, `failedAttempts`.
 *    - Core method: `loadConfig()` handles lazy loading, caching, retries, and fallback.
 *    - Private method: `fetchErrorConfigurations()` performs the actual API call via `fetch`.
 *    - Private method: `loadFallbackConfigurations()` provides default minimal configs.
 *    - Private method: `dispatchConfigLoadedEvent()` notifies via `CustomEvent`.
 *    - Private method: `getCsrfToken()` retrieves CSRF token for the fetch request.
 *    - Public getter methods: `getErrorConfig`, `getErrorTypeConfig`, `getBlockingLevelConfig`, `isConfigLoaded`, `getAllErrorCodes`, `getErrorsByType`.
 *    - Uses types from `../interfaces/ErrorTypes`.
 *
 * 📡 Communicates:
 *    - Sends GET request to `/api/error-definitions` via `fetch` (`fetchErrorConfigurations`).
 *    - Reads global `document` and `window` to find CSRF token (`getCsrfToken`).
 *    - Dispatches `errorConfigLoaded` CustomEvent on `document` (`dispatchConfigLoadedEvent`).
 *    - Logs status, warnings, and errors to the browser `console`.
 *
 * 🧪 Testable:
 *    - Singleton pattern makes isolated unit testing slightly complex; often requires testing the shared instance or mocking `getInstance`.
 *    - Core dependencies: Browser `fetch` API (needs mocking, e.g., using `fetch-mock` or Jest/Vitest mocks).
 *    - Core dependencies: Global `document` (for CSRF, event dispatch) & `window` (for CSRF, `envMode` check) - need DOM simulation (JSDOM) or mocking.
 *    - Core dependencies: `console` (spyable/mockable).
 *    - Core dependencies: `setTimeout` (needs timer mocks in test runners like Jest/Vitest for retry logic).
 *    - Internal state can be inspected via getter methods. `loadConfig`'s async nature requires async testing patterns.
 *
 * 🛡️ GDPR Considerations:
 *    - The *process* of loading configuration is generally low-risk regarding PII.
 *    - **However, the *content* loaded from `/api/error-definitions` (specifically within `ErrorConfig` objects like `dev_message`, `user_message`)
 *      *could* contain PII if not properly managed server-side.** This loader transports that data. Consumers (like ErrorManager)
 *      are responsible for handling the retrieved configuration data appropriately.
 *    - The fallback configurations (`loadFallbackConfigurations`) are hardcoded and contain no PII.
 *    - Logs internal operations to `console` (`@log`), which is acceptable in development but should be reviewed for production transports if any are used.
 *    - CSRF token retrieval does not handle PII.
 *
 * @module UltraErrorManager/Utils
 * @version 1.0.1 // Version bumped post-refactor
 */

import {
    ErrorConfig,
    ErrorTypeConfig,
    BlockingLevelConfig
} from '../interfaces/ErrorTypes'; // Correct path assuming structure

/**
 * 🧱 Interface defining the expected JSON structure returned by the
 *    `/api/error-definitions` endpoint.
 * @private
 */
interface ErrorConfigResponse {
    /** Record mapping error codes (string) to their ErrorConfig objects. */
    errors: Record<string, ErrorConfig>;
    /** Record mapping error type names (string) to their ErrorTypeConfig objects. */
    types: Record<string, ErrorTypeConfig>;
    /** Record mapping blocking level names (string) to their BlockingLevelConfig objects. */
    blocking_levels: Record<string, BlockingLevelConfig>;
}

/**
 * Handles fetching, caching, and providing access to UEM client-side configurations.
 * Follows the Singleton pattern.
 */
export class ErrorConfigLoader {
    /** @private The singleton instance. */
    private static instance: ErrorConfigLoader;

    /** @private Cache for loaded error code definitions. */
    private errorDefinitions: Record<string, ErrorConfig> = {};
    /** @private Cache for loaded error type configurations. */
    private errorTypes: Record<string, ErrorTypeConfig> = {};
    /** @private Cache for loaded blocking level configurations. */
    private blockingLevels: Record<string, BlockingLevelConfig> = {};

    /** @private Flag indicating if configurations have been successfully loaded (or fallback applied). */
    private isLoaded: boolean = false;
    /** @private Stores the promise returned by an ongoing `loadConfig` call to prevent concurrent requests. */
    private loadPromise: Promise<void> | null = null;
    /** @private Counter for failed fetch attempts for retry logic. */
    private failedAttempts: number = 0;
    /** @private Maximum number of retry attempts for fetching configurations. */
    private readonly MAX_RETRY_ATTEMPTS = 3;
    /** @private The API endpoint URL for fetching error configurations. */
    private readonly CONFIG_ENDPOINT = '/api/error-definitions'; // Define as constant

    /**
     * 🎯 Private constructor: Enforces Singleton pattern.
     * @private
     */
    private constructor() {
         console.info('[UEM ConfigLoader] Singleton Instantiated');
    }

    /**
     * 🎯 Get the singleton instance of ErrorConfigLoader.
     * 🧱 Creates a new instance if one doesn't exist yet (Singleton pattern).
     * @public
     * @static
     * @returns {ErrorConfigLoader} The singleton instance.
     */
    public static getInstance(): ErrorConfigLoader {
        if (!ErrorConfigLoader.instance) {
            ErrorConfigLoader.instance = new ErrorConfigLoader();
        }
        return ErrorConfigLoader.instance;
    }

    /**
     * 🎯 Initiates the loading of error configurations from the server endpoint.
     * 🧠 Implements lazy loading (only fetches once unless forced), caching (stores loaded data),
     *    retry logic with exponential backoff on failure, and fallback to default configurations
     *    if fetching fails after max retries.
     * 📡 Communicates with the server via `fetchErrorConfigurations`. Dispatches `errorConfigLoaded` event on success/fallback.
     * 🔄 @mutation Modifies internal state (`isLoaded`, `loadPromise`, caches, `failedAttempts`).
     * 🪵 Logs loading process, retries, and errors via `console`.
     * @public
     * @async
     * @param {boolean} [forceReload=false] - If true, bypasses the cache and forces a new fetch attempt.
     * @returns {Promise<void>} A promise that resolves when loading is complete (either successfully or via fallback),
     *                          or rejects if an unexpected error occurs during the promise handling itself (less common).
     * @testability Requires mocking `fetch`, `setTimeout`, `document.dispatchEvent`. Needs async tests.
     */
    public loadConfig(forceReload: boolean = false): Promise<void> {
        // 1. Cache Check: Return immediately if loaded and not forced
        if (this.isLoaded && !forceReload) {
            console.debug('[UEM ConfigLoader] Configurations already loaded.');
            return Promise.resolve();
        }

        // 2. Concurrency Check: Return existing promise if load is in progress and not forced
        if (this.loadPromise && !forceReload) {
            console.debug('[UEM ConfigLoader] Configuration loading already in progress.');
            return this.loadPromise;
        }

        // 3. Reset State if Forcing Reload
        if (forceReload) {
            console.info('[UEM ConfigLoader] Forcing configuration reload.');
            this.isLoaded = false;
            this.failedAttempts = 0;
            this.loadPromise = null; // Clear existing promise to force new fetch
            // Clear caches? Desirable if config might have actually changed server-side.
            this.errorDefinitions = {};
            this.errorTypes = {};
            this.blockingLevels = {};
        }

        // 4. Initiate Loading Process (using existing/new promise)
        // Ensure loadPromise is only created once per load attempt sequence
        if (!this.loadPromise) {
            console.info(`[UEM ConfigLoader] Starting configuration load (Attempt ${this.failedAttempts + 1}).`);
            this.loadPromise = new Promise<void>((resolve, reject) => {
                this.fetchErrorConfigurations()
                    .then(data => {
                        // --- Success Path ---
                        console.info('[UEM ConfigLoader] Successfully fetched configurations from server.');
                        // Store fetched data in caches
                        this.errorDefinitions = data.errors || {};
                        this.errorTypes = data.types || {};
                        this.blockingLevels = data.blocking_levels || {};
                        // Update state
                        this.isLoaded = true;
                        this.failedAttempts = 0; // Reset attempts on success
                        // Clear the promise *after* resolving to allow future loads
                        this.loadPromise = null;

                        this.dispatchConfigLoadedEvent(); // Notify application

                        // Log loaded data only in local environment for debugging
                        if (typeof window !== 'undefined' && (window as any).envMode === 'local') {
                            console.debug('[UEM ConfigLoader] Loaded Data:', {
                                definitions: this.errorDefinitions,
                                types: this.errorTypes,
                                blockingLevels: this.blockingLevels
                            });
                        }
                        resolve(); // Resolve the main promise
                    })
                    .catch(error => {
                         // --- Error Path ---
                        console.error(`[UEM ConfigLoader] Fetch attempt ${this.failedAttempts + 1} failed:`, error.message || error);
                        this.failedAttempts++;
                        // Clear promise immediately on failure to allow retry mechanism to create a new one if needed
                        this.loadPromise = null;

                        // --- Retry Logic ---
                        if (this.failedAttempts < this.MAX_RETRY_ATTEMPTS) {
                            const retryDelay = Math.pow(2, this.failedAttempts) * 1000; // Exponential backoff (2s, 4s)
                            console.warn(`[UEM ConfigLoader] Retrying configuration load in ${retryDelay}ms (Attempt ${this.failedAttempts + 1}/${this.MAX_RETRY_ATTEMPTS}).`);

                            setTimeout(() => {
                                // Recursively call loadConfig to handle caching/concurrency checks again
                                // Importantly, link the retry promise back to the *original* promise's resolve/reject
                                this.loadConfig()
                                    .then(resolve) // Resolve original promise on successful retry
                                    .catch(reject); // Reject original promise if retry chain ultimately fails (shouldn't happen due to fallback)
                            }, retryDelay);
                        } else {
                             // --- Fallback Logic ---
                            console.error(`[UEM ConfigLoader] Max retry attempts (${this.MAX_RETRY_ATTEMPTS}) reached. Loading fallback configurations.`);
                            this.loadFallbackConfigurations();
                            this.isLoaded = true; // Mark as loaded (with fallback data)
                            this.dispatchConfigLoadedEvent(); // Notify application about fallback load
                            resolve(); // Resolve the promise successfully, indicating fallback is active
                        }
                    });
            });
        }

        return this.loadPromise;
    }

    /**
     * 📡 Performs the actual HTTP fetch request to the configuration endpoint.
     * 🧷 Handles potential network or HTTP errors.
     * @private
     * @async
     * @returns {Promise<ErrorConfigResponse>} A promise resolving to the parsed JSON response or rejecting on error.
     * @testability Requires mocking `fetch`.
     */
    private async fetchErrorConfigurations(): Promise<ErrorConfigResponse> {
        const csrfToken = this.getCsrfToken(); // Retrieve CSRF token

        // Use async/await for cleaner fetch logic
        try {
            const response = await fetch(this.CONFIG_ENDPOINT, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken, // Include CSRF token
                    'X-Requested-With': 'XMLHttpRequest' // Standard header for AJAX requests
                },
                // credentials: 'same-origin' // Often needed if backend relies on session cookies for auth/CSRF
            });

            if (!response.ok) {
                 // Throw an error with status text for better debugging
                 throw new Error(`HTTP error ${response.status} - ${response.statusText}`);
            }

            // Check content type before parsing JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                 throw new Error(`Expected JSON response but received Content-Type: ${contentType}`);
            }

            const data = await response.json();

            // Basic validation of response structure (optional but recommended)
            if (!data || typeof data.errors !== 'object' || typeof data.types !== 'object' || typeof data.blocking_levels !== 'object') {
                throw new Error('Invalid configuration structure received from server.');
            }

            return data as ErrorConfigResponse; // Type assertion after validation

        } catch (error: any) { // Catch any error during fetch/parsing
             console.error(`[UEM ConfigLoader] fetchErrorConfigurations error:`, error);
             // Re-throw the error to be caught by the loadConfig catch block
             throw error;
        }
    }

    /**
     * 🧱 Loads a minimal set of fallback configurations if the server fetch fails repeatedly.
     * 🧷 Ensures basic functionality for critical errors even without server connection.
     * @private
     * @returns {void}
     */
    private loadFallbackConfigurations(): void {
        console.warn('[UEM ConfigLoader] Applying hardcoded fallback configurations.');
        // Fallback error types
        this.errorTypes = {
            'critical': { log_level: 'critical', notify_team: true, http_status: 500 },
            'error':    { log_level: 'error',    notify_team: false, http_status: 400 },
            'warning':  { log_level: 'warning',  notify_team: false, http_status: 400 },
            'notice':   { log_level: 'notice',   notify_team: false, http_status: 200 }
        };

        // Fallback blocking levels
        this.blockingLevels = {
            'blocking':      { terminate_request: true, clear_session: false },
            'semi-blocking': { terminate_request: false, flash_session: true },
            'not':           { terminate_request: false, flash_session: true }
        };

        // Fallback error definitions - include only the most essential ones
        this.errorDefinitions = {
            // Essential fallback for undefined codes, references fallback message keys
            'UNDEFINED_ERROR_CODE': {
                type: 'critical', blocking: 'blocking', http_status_code: 500,
                devTeam_email_need: true, msg_to: 'sweet-alert', notify_slack: true, // Ensure notifications are attempted
                dev_message_key: 'error-manager::errors.dev.undefined_error_code', // Reference trans key
                user_message_key: 'error-manager::errors.user.undefined_error_code' // Reference trans key
            },
            // Fallback used if UNDEFINED_ERROR_CODE lookup *also* fails (shouldn't happen with this structure)
            'FALLBACK_ERROR': {
                type: 'critical', blocking: 'blocking', http_status_code: 500,
                devTeam_email_need: true, msg_to: 'sweet-alert', notify_slack: true,
                dev_message_key: 'error-manager::errors.dev.fallback_error',
                user_message_key: 'error-manager::errors.user.fallback_error'
            },
            // Fatal error if even the fallback_error config entry is missing (defined in main config, not here)
            'FATAL_FALLBACK_FAILURE': { // Used by ErrorManager if 'fallback_error' itself is missing
                 type: 'critical', blocking: 'blocking', http_status_code: 500,
                 devTeam_email_need: true, msg_to: 'sweet-alert', notify_slack: true,
                 dev_message_key: 'error-manager::errors.dev.fatal_fallback_failure',
                 user_message_key: 'error-manager::errors.user.fatal_fallback_failure'
            },
            // Basic critical errors likely encountered client-side
            'UNEXPECTED_ERROR': {
                type: 'critical', blocking: 'semi-blocking', http_status_code: 500,
                devTeam_email_need: true, msg_to: 'sweet-alert', notify_slack: true,
                dev_message_key: 'error-manager::errors.dev.unexpected_error',
                user_message_key: 'error-manager::errors.user.unexpected_error'
            },
            'NETWORK_ERROR': { // Often happens if API is down
                type: 'error', blocking: 'semi-blocking', http_status_code: 503, // Service Unavailable
                devTeam_email_need: true, msg_to: 'sweet-alert', notify_slack: true, // Notify if API unreachable
                dev_message_key: 'error-manager::errors.dev.network_error', // Add keys if not present
                user_message_key: 'error-manager::errors.user.network_error'
            },
            'JSON_ERROR': { // Error parsing response
                 type: 'error', blocking: 'semi-blocking', http_status_code: 500, // Indicates server sent bad JSON
                 devTeam_email_need: true, msg_to: 'div', notify_slack: true,
                 dev_message_key: 'error-manager::errors.dev.json_error',
                 user_message_key: 'error-manager::errors.user.json_error'
             },
             'VALIDATION_ERROR': { // Common user input issue
                 type: 'warning', blocking: 'semi-blocking', http_status_code: 422, // Unprocessable Entity
                 devTeam_email_need: false, msg_to: 'div', notify_slack: false,
                 dev_message_key: 'error-manager::errors.dev.validation_error',
                 user_message_key: 'error-manager::errors.user.validation_error'
             },
        };
    }

    /**
     * 📡 Get configuration for a specific error code from the cache.
     * 🪵 Logs a warning if accessed before configurations are loaded.
     * @public
     * @param {string} errorCode - The error code to retrieve configuration for.
     * @returns {ErrorConfig | null} The configuration object or null if not found or not loaded.
     */
    public getErrorConfig(errorCode: string): ErrorConfig | null {
        if (!this.isLoaded) {
            // Allow access even if loading failed (using fallback data), but warn if loading never *completed*
            if (!this.loadPromise && this.failedAttempts === 0) { // Only warn if loading hasn't even started/finished
                console.warn(`[UEM ConfigLoader] Attempted to access config for '${errorCode}' before loading initiated/completed.`);
            }
        }
        return this.errorDefinitions[errorCode] || null;
    }

    /**
     * 📡 Get configuration for a specific error type (severity level) from the cache.
     * 🪵 Logs a warning if accessed before configurations are loaded.
     * @public
     * @param {string} type - The error type name (e.g., 'critical', 'error').
     * @returns {ErrorTypeConfig | null} The configuration object or null if not found or not loaded.
     */
    public getErrorTypeConfig(type: string): ErrorTypeConfig | null {
         if (!this.isLoaded && !this.loadPromise && this.failedAttempts === 0) {
            console.warn(`[UEM ConfigLoader] Attempted to access type config for '${type}' before loading initiated/completed.`);
        }
        return this.errorTypes[type] || null;
    }

    /**
     * 📡 Get configuration for a specific blocking level from the cache.
     * 🪵 Logs a warning if accessed before configurations are loaded.
     * @public
     * @param {string} level - The blocking level name (e.g., 'blocking', 'not').
     * @returns {BlockingLevelConfig | null} The configuration object or null if not found or not loaded.
     */
    public getBlockingLevelConfig(level: string): BlockingLevelConfig | null {
        if (!this.isLoaded && !this.loadPromise && this.failedAttempts === 0) {
            console.warn(`[UEM ConfigLoader] Attempted to access blocking level config for '${level}' before loading initiated/completed.`);
        }
        return this.blockingLevels[level] || null;
    }

    /**
     * 📡 Check if configurations have been loaded (or fallback applied).
     * @public
     * @returns {boolean} True if loaded or fallback is active, false otherwise.
     */
    public isConfigLoaded(): boolean {
        return this.isLoaded;
    }

    /**
     * 📡 Get an array of all loaded/fallback error codes.
     * @public
     * @returns {string[]} Array of defined error code strings.
     */
    public getAllErrorCodes(): string[] {
         if (!this.isLoaded && !this.loadPromise && this.failedAttempts === 0) {
             console.warn('[UEM ConfigLoader] Accessed getAllErrorCodes before loading initiated/completed; list may be empty.');
         }
        return Object.keys(this.errorDefinitions);
    }

    /**
     * 📡 Get all loaded/fallback error definitions grouped by their type.
     * Useful for UI elements like simulation dashboards. Includes the error code within each config object.
     * @public
     * @returns {Record<string, (ErrorConfig & { code: string })[]>} Object mapping error type names to arrays of their error configs (with code added).
     */
    public getErrorsByType(): Record<string, (ErrorConfig & { code: string })[]> {
         if (!this.isLoaded && !this.loadPromise && this.failedAttempts === 0) {
              console.warn('[UEM ConfigLoader] Accessed getErrorsByType before loading initiated/completed; object may be empty.');
         }
        const result: Record<string, (ErrorConfig & { code: string })[]> = {};

        for (const [code, config] of Object.entries(this.errorDefinitions)) {
            const type = config.type ?? 'unknown'; // Use 'unknown' if type is missing
            if (!result[type]) {
                result[type] = [];
            }
            // Add the 'code' property directly to the config object for convenience
            result[type].push({ ...config, code });
        }
        // Optional: Sort keys alphabetically?
        // const sortedResult: Record<string, (ErrorConfig & { code: string })[]> = {};
        // Object.keys(result).sort().forEach(key => sortedResult[key] = result[key]);
        // return sortedResult;
        return result;
    }

    /**
     * 📡 Dispatches a 'errorConfigLoaded' CustomEvent on the document.
     * Notifies the application that configurations are ready (either loaded or fallback applied).
     * @private
     */
    private dispatchConfigLoadedEvent(): void {
        // Check for browser environment
        if (typeof document === 'undefined' || typeof CustomEvent === 'undefined') {
             console.info('[UEM ConfigLoader] Skipping event dispatch in non-browser environment.');
            return;
        }

        try {
            const event = new CustomEvent('errorConfigLoaded', {
                detail: {
                    timestamp: new Date().toISOString(),
                    errorCount: Object.keys(this.errorDefinitions).length,
                    usingFallback: this.failedAttempts >= this.MAX_RETRY_ATTEMPTS // Indicate if fallback data is active
                },
                bubbles: true, // Allow event to bubble
                cancelable: false // Typically not cancelable
            });
            document.dispatchEvent(event);
             console.info('[UEM ConfigLoader] Dispatched "errorConfigLoaded" event.');
        } catch (e) {
            console.warn('[UEM ConfigLoader] Failed to dispatch "errorConfigLoaded" event:', e);
        }
    }

    /**
     * 🧱 Retrieves the CSRF token from standard locations (`window` object or meta tag).
     * 📡 Reads global `window` and `document`.
     * 🪵 Logs a warning if the token is not found.
     * @private
     * @returns {string} The CSRF token string, or an empty string if not found.
     */
    private getCsrfToken(): string {
        // 1. Try window object (if backend injects it)
        if (typeof window !== 'undefined' && (window as any).csrfToken) {
            const token = (window as any).csrfToken;
            if (typeof token === 'string' && token.length > 0) {
                 // console.debug('[UEM ConfigLoader] Found CSRF token in window object.');
                return token;
            }
        }

        // 2. Try meta tag (standard Laravel way)
        if (typeof document !== 'undefined') {
            const metaElement = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]'); // Use type assertion
            if (metaElement) {
                const token = metaElement.getAttribute('content');
                if (token && token.length > 0) {
                     // console.debug('[UEM ConfigLoader] Found CSRF token in meta tag.');
                    return token;
                }
            }
        }

        console.warn('[UEM ConfigLoader] CSRF token not found in window.csrfToken or meta[name="csrf-token"]. API requests might fail.');
        return ''; // Return empty string if not found
    }
} // End of ErrorConfigLoader class

// --- Export Singleton Instance ---
/**
 * 🎯 Exported singleton instance of the ErrorConfigLoader.
 * Provides convenient global access to load and retrieve error configurations.
 * Usage: `import { errorConfig } from './utils/ErrorConfigLoader'; errorConfig.loadConfig();`
 */
export const errorConfig = ErrorConfigLoader.getInstance();