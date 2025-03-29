/**
* /home/fabio/sandbox/UltraUploadSandbox/packages/ultra/errormanager/resources/ts/index.ts
*
* Ultra Error Manager - Main Entry Point
*
* This file serves as the main entry point for the Ultra Error Manager client-side package.
* It exports all the components and utilities needed to integrate UEM into client applications.
*
* The exported components include:
* - ErrorManager: The main class for handling errors
* - ErrorDisplayHandler: The handler for displaying errors
* - ErrorConfigLoader: Utility for loading error configurations
* - Types and interfaces for type safety
*
* This module follows the facade pattern to provide a simplified interface
* to the complex error management subsystem.
*
* @module UltraErrorManager
* @version 1.0.0
*/

// Export the main ErrorManager class and singleton instance
import { ErrorManager, ultraError } from './ErrorManager';
export { ErrorManager, ultraError };

// Export the ErrorDisplayHandler
import { ErrorDisplayHandler } from './handlers/ErrorDisplayHandler';
export { ErrorDisplayHandler };

// Export the ErrorConfigLoader and singleton instance
import { ErrorConfigLoader, errorConfig } from './utils/ErrorConfigLoader';
export { ErrorConfigLoader, errorConfig };

// Export all types and interfaces
export * from './interfaces/ErrorTypes';

/**
* Initialize the Ultra Error Manager
* This is a convenience function to setup UEM in one call
*
* @param {Object} options - Initialization options
* @param {boolean} options.loadConfig - Whether to load error configurations
* @param {string} options.defaultDisplayMode - Default display mode for errors
* @returns {Promise<void>} A promise that resolves when initialization is complete
*/
export async function initializeUEM(options: {
   loadConfig?: boolean,
   defaultDisplayMode?: string
} = {}): Promise<void> {
   return ultraError.initialize(options);
}

/**
* Handle a client-side error
* This is a convenience function that delegates to ultraError.handleClientError
*
* @param {string} errorCode - The error code
* @param {Object} context - Additional context for the error
* @param {Error} originalError - The original error object if available
*/
export function handleClientError(
   errorCode: string,
   context: Record<string, any> = {},
   originalError?: Error
): void {
   ultraError.handleClientError(errorCode, context, originalError);
}

/**
* Handle a server error response
* This is a convenience function that delegates to ultraError.handleServerError
*
* @param {Object} response - The error response from server
* @param {Object} context - Additional context for the error
*/
export function handleServerError(
   response: {
       error: string;
       message: string;
       blocking: string;
       display_mode: string;
       details?: any;
   },
   context: Record<string, any> = {}
): void {
   ultraError.handleServerError(response, context);
}

/**
* Create a wrapped fetch function that automatically handles errors
*
* @param {RequestInfo} input - The request URL or Request object
* @param {RequestInit} init - The request init options
* @returns {Promise<Response>} The fetch response
*/
export async function safeFetch(
   input: RequestInfo,
   init?: RequestInit
): Promise<Response> {
   try {
       const response = await fetch(input, init);

       if (!response.ok) {
           const contentType = response.headers.get('content-type');

           if (contentType && contentType.includes('application/json')) {
               try {
                   const errorData = await response.json();

                   if (errorData.error) {
                       // This looks like a UEM error response
                       ultraError.handleServerError(errorData);
                   } else {
                       // This is some other kind of JSON error
                       ultraError.handleClientError('UNEXPECTED_ERROR', {
                           status: response.status,
                           statusText: response.statusText,
                           errorData: errorData
                       });
                   }
               } catch (jsonError) {
                   // Failed to parse JSON
                   ultraError.handleClientError('JSON_ERROR', {
                       status: response.status,
                       statusText: response.statusText,
                       url: typeof input === 'string' ? input : input.url
                   });
               }
           } else {
               // Not a JSON response
               ultraError.handleClientError('SERVER_ERROR', {
                   status: response.status,
                   statusText: response.statusText,
                   url: typeof input === 'string' ? input : input.url
               });
           }
       }

       return response;
   } catch (error) {
       // Network or other fetch error
       ultraError.handleClientError('NETWORK_ERROR', {
           url: typeof input === 'string' ? input : input.url
       }, error instanceof Error ? error : undefined);

       throw error; // Re-throw to allow caller to handle it
   }
}

// Add event listener setup function for convenience
export function onUltraError(
   callback: (event: CustomEvent) => void
): () => void {
   const handler = (event: Event) => {
       callback(event as CustomEvent);
   };

   document.addEventListener('ultraError', handler);

   // Return a function to remove the listener
   return () => {
       document.removeEventListener('ultraError', handler);
   };
}

// Export a simplified API object for convenience
export const UEM = {
   initialize: initializeUEM,
   handleClientError,
   handleServerError,
   safeFetch,
   onError: onUltraError,
   getErrorConfig: (errorCode: string) => errorConfig.getErrorConfig(errorCode),
   getAllErrorCodes: () => errorConfig.getAllErrorCodes(),
};

// Default export for ESM compatibility
export default UEM;
