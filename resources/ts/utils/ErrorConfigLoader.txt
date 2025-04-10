/**
* /home/fabio/sandbox/UltraUploadSandbox/packages/ultra/errormanager/resources/ts/utils/ErrorConfigLoader.ts
*
* Ultra Error Manager - Error Configuration Loader
*
* This module is responsible for loading and managing error configurations from the server.
* It fetches error definitions, error types, and blocking levels, then provides an interface
* for accessing these configurations throughout the application.
*
* The module implements the Singleton pattern to ensure a single source of truth for
* error configurations and uses caching to minimize network requests.
*
* Key features:
* - Lazy loading of configurations
* - Proper error handling and fallbacks
* - Type-safe access to configurations
* - Event-based notification when configs are loaded
* - Graceful handling of configuration failures
*
* @module UltraErrorManager/Utils
* @version 1.0.0
*/

import {
    ErrorConfig,
    ErrorTypeConfig,
    BlockingLevelConfig
 } from '../interfaces/ErrorTypes';

 /**
 * Interface for error configuration response from the server
 */
 interface ErrorConfigResponse {
    errors: Record<string, ErrorConfig>;
    types: Record<string, ErrorTypeConfig>;
    blocking_levels: Record<string, BlockingLevelConfig>;
 }

 /**
 * Handles fetching and caching error configurations from the server
 */
 export class ErrorConfigLoader {
    private static instance: ErrorConfigLoader;
    private errorDefinitions: Record<string, ErrorConfig> = {};
    private errorTypes: Record<string, ErrorTypeConfig> = {};
    private blockingLevels: Record<string, BlockingLevelConfig> = {};
    private isLoaded: boolean = false;
    private loadPromise: Promise<void> | null = null;
    private failedAttempts: number = 0;
    private readonly MAX_RETRY_ATTEMPTS = 3;

    /**
     * Private constructor for singleton pattern implementation
     * Prevents direct construction calls with 'new' operator
     */
    private constructor() {}

    /**
     * Get the singleton instance of ErrorConfigLoader
     * Creates a new instance if one doesn't exist yet
     *
     * @returns {ErrorConfigLoader} The singleton instance
     */
    public static getInstance(): ErrorConfigLoader {
        if (!ErrorConfigLoader.instance) {
            ErrorConfigLoader.instance = new ErrorConfigLoader();
        }
        return ErrorConfigLoader.instance;
    }

    /**
     * Load error configurations from the server
     * Implements caching and retry logic
     *
     * @param {boolean} forceReload - Whether to force reload even if already loaded
     * @returns {Promise<void>} A promise that resolves when loading is complete
     */
    public loadConfig(forceReload: boolean = false): Promise<void> {
        // If already loaded and not forced to reload, return immediately
        if (this.isLoaded && !forceReload) {
            return Promise.resolve();
        }

        // If loading is in progress and not forced, return the existing promise
        if (this.loadPromise && !forceReload) {
            return this.loadPromise;
        }

        // Reset loaded state if forcing reload
        if (forceReload) {
            this.isLoaded = false;
            this.failedAttempts = 0;
        }

        // Start a new loading process
        this.loadPromise = new Promise<void>((resolve, reject) => {
            this.fetchErrorConfigurations()
                .then(data => {
                    this.errorDefinitions = data.errors || {};
                    this.errorTypes = data.types || {};
                    this.blockingLevels = data.blocking_levels || {};
                    this.isLoaded = true;
                    this.failedAttempts = 0;

                    // Trigger event to notify that configuration has been loaded
                    this.dispatchConfigLoadedEvent();

                    if (typeof window !== 'undefined' && (window as any).envMode === 'local') {
                        console.log('Error configurations loaded:', {
                            definitions: this.errorDefinitions,
                            types: this.errorTypes,
                            blockingLevels: this.blockingLevels
                        });
                    }

                    resolve();
                })
                .catch(error => {
                    console.error('Error loading error configurations:', error);
                    this.loadPromise = null;
                    this.failedAttempts++;

                    // If we haven't exceeded max retry attempts, try again with exponential backoff
                    if (this.failedAttempts < this.MAX_RETRY_ATTEMPTS) {
                        const retryDelay = Math.pow(2, this.failedAttempts) * 1000; // Exponential backoff
                        console.log(`Retrying error config load in ${retryDelay}ms (attempt ${this.failedAttempts + 1}/${this.MAX_RETRY_ATTEMPTS})`);

                        setTimeout(() => {
                            this.loadConfig()
                                .then(resolve)
                                .catch(reject);
                        }, retryDelay);
                    } else {
                        // After max retries, load fallback configurations and resolve anyway
                        console.warn('Max retry attempts reached. Using fallback error configurations.');
                        this.loadFallbackConfigurations();
                        this.isLoaded = true; // Mark as loaded even though we're using fallbacks
                        this.dispatchConfigLoadedEvent();
                        resolve(); // Resolve successfully even with fallbacks
                    }
                });
        });

        return this.loadPromise;
    }

    /**
     * Fetches error configurations from the server
     *
     * @returns {Promise<ErrorConfigResponse>} A promise that resolves to the error configurations
     */
    private fetchErrorConfigurations(): Promise<ErrorConfigResponse> {
        return new Promise<ErrorConfigResponse>((resolve, reject) => {
            fetch('/api/error-definitions', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin' // Include cookies for authentication
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Failed to load error configurations: ${response.status}`);
                }
                return response.json();
            })
            .then(data => resolve(data))
            .catch(error => reject(error));
        });
    }

    /**
     * Loads fallback configurations to use when server fetch fails
     * Provides a minimal set of critical error definitions
     */
    private loadFallbackConfigurations(): void {
        // Fallback error types
        this.errorTypes = {
            'critical': {
                log_level: 'critical',
                notify_team: true,
                http_status: 500
            },
            'error': {
                log_level: 'error',
                notify_team: false,
                http_status: 400
            },
            'warning': {
                log_level: 'warning',
                notify_team: false,
                http_status: 400
            },
            'notice': {
                log_level: 'notice',
                notify_team: false,
                http_status: 200
            }
        };

        // Fallback blocking levels
        this.blockingLevels = {
            'blocking': {
                terminate_request: true,
                clear_session: false
            },
            'semi-blocking': {
                terminate_request: false,
                flash_session: true
            },
            'not': {
                terminate_request: false,
                flash_session: true
            }
        };

        // Fallback error definitions for critical errors
        this.errorDefinitions = {
            'UNEXPECTED_ERROR': {
                type: 'critical',
                blocking: 'semi-blocking',
                http_status_code: 500,
                devTeam_email_need: true,
                msg_to: 'sweet-alert'
            },
            'NETWORK_ERROR': {
                type: 'error',
                blocking: 'semi-blocking',
                http_status_code: 503,
                devTeam_email_need: false,
                msg_to: 'sweet-alert'
            },
            'VALIDATION_ERROR': {
                type: 'warning',
                blocking: 'semi-blocking',
                http_status_code: 400,
                devTeam_email_need: false,
                msg_to: 'div'
            }
        };
    }

    /**
     * Get configuration for a specific error code
     *
     * @param {string} errorCode - The error code to get configuration for
     * @returns {ErrorConfig|null} The error configuration or null if not found
     */
    public getErrorConfig(errorCode: string): ErrorConfig | null {
        if (!this.isLoaded) {
            console.warn(`Attempted to access error configuration before loading: ${errorCode}`);
            return null;
        }

        return this.errorDefinitions[errorCode] || null;
    }

    /**
     * Get configuration for a specific error type
     *
     * @param {string} type - The error type to get configuration for (critical, error, warning, notice)
     * @returns {ErrorTypeConfig|null} The error type configuration or null if not found
     */
    public getErrorTypeConfig(type: string): ErrorTypeConfig | null {
        if (!this.isLoaded) {
            console.warn(`Attempted to access error type configuration before loading: ${type}`);
            return null;
        }

        return this.errorTypes[type] || null;
    }

    /**
     * Get configuration for a specific blocking level
     *
     * @param {string} level - The blocking level to get configuration for (blocking, semi-blocking, not)
     * @returns {BlockingLevelConfig|null} The blocking level configuration or null if not found
     */
    public getBlockingLevelConfig(level: string): BlockingLevelConfig | null {
        if (!this.isLoaded) {
            console.warn(`Attempted to access blocking level configuration before loading: ${level}`);
            return null;
        }

        return this.blockingLevels[level] || null;
    }

    /**
     * Check if configurations have been loaded
     *
     * @returns {boolean} True if configurations have been loaded, false otherwise
     */
    public isConfigLoaded(): boolean {
        return this.isLoaded;
    }

    /**
     * Get all available error codes
     *
     * @returns {string[]} Array of all error codes
     */
    public getAllErrorCodes(): string[] {
        return Object.keys(this.errorDefinitions);
    }

    /**
     * Get all error definitions grouped by their type
     * Useful for generating error documentation or UI
     *
     * @returns {Record<string, ErrorConfig[]>} Object with error type as key and array of errors as value
     */
    public getErrorsByType(): Record<string, ErrorConfig[]> {
        const result: Record<string, ErrorConfig[]> = {};

        for (const [code, config] of Object.entries(this.errorDefinitions)) {
            const type = config.type;
            if (!result[type]) {
                result[type] = [];
            }

            // Add the code to the config object for reference
            const configWithCode = { ...config, code };
            result[type].push(configWithCode as ErrorConfig);
        }

        return result;
    }

    /**
     * Dispatches a custom event when configurations are loaded
     */
    private dispatchConfigLoadedEvent(): void {
        if (typeof document !== 'undefined') {
            try {
                const event = new CustomEvent('errorConfigLoaded', {
                    detail: {
                        timestamp: new Date().toISOString(),
                        errorCount: Object.keys(this.errorDefinitions).length
                    },
                    bubbles: true,
                    cancelable: true
                });

                document.dispatchEvent(event);
            } catch (e) {
                console.warn('Failed to dispatch errorConfigLoaded event:', e);
            }
        }
    }

    /**
     * Gets the CSRF token from the meta tag or window object
     *
     * @returns {string} The CSRF token
     */
    private getCsrfToken(): string {
        // Try to get from window object
        if (typeof window !== 'undefined' && (window as any).csrfToken) {
            return (window as any).csrfToken;
        }

        // Try to get from meta tag
        if (typeof document !== 'undefined') {
            const metaEl = document.querySelector('meta[name="csrf-token"]');
            if (metaEl) {
                return metaEl.getAttribute('content') || '';
            }
        }

        console.warn('CSRF token not found');
        return '';
    }
 }

 // Export a singleton instance for easier usage across the application
 export const errorConfig = ErrorConfigLoader.getInstance();
