<?php
/**
 * Common error handling functions for API responses
 */

/**
 * Ensure that errors in the database connection are returned as JSON
 * Call this function at the start of any API file
 */
function setupApiErrorHandling() {
    // Start output buffering to prevent accidental output
    ob_start();
    
    // Set content type to JSON
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    // Set custom error and exception handlers
    set_error_handler('apiErrorHandler');
    set_exception_handler('apiExceptionHandler');
    
    // Register shutdown function to catch fatal errors
    register_shutdown_function('apiShutdownHandler');
}

/**
 * Custom error handler for API responses
 */
function apiErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return false;
    }
    
    $error = [
        'success' => false,
        'message' => 'Server error occurred',
        'debug' => [
            'error' => $errstr,
            'file' => basename($errfile),
            'line' => $errline
        ]
    ];
    
    error_log("PHP Error: $errstr in $errfile on line $errline");
    
    // Only send error details in development environment
    if (!isProduction()) {
        echo json_encode($error);
    } else {
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
    
    ob_end_flush();
    exit(1);
}

/**
 * Custom exception handler for API responses
 */
function apiExceptionHandler($exception) {
    $error = [
        'success' => false,
        'message' => 'Server exception occurred',
        'debug' => [
            'message' => $exception->getMessage(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine()
        ]
    ];
    
    error_log("PHP Exception: " . $exception->getMessage() . " in " . 
              $exception->getFile() . " on line " . $exception->getLine());
    
    // Only send error details in development environment
    if (!isProduction()) {
        echo json_encode($error);
    } else {
        echo json_encode(['success' => false, 'message' => 'Server exception occurred']);
    }
    
    ob_end_flush();
    exit(1);
}

/**
 * Shutdown handler to catch fatal errors
 */
function apiShutdownHandler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $errorResponse = [
            'success' => false,
            'message' => 'Fatal server error occurred'
        ];
        
        if (!isProduction()) {
            $errorResponse['debug'] = [
                'error' => $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ];
        }
        
        error_log("PHP Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}");
        
        // Clear any existing output
        if (ob_get_length()) ob_clean();
        
        // Set JSON header again in case it got lost
        header('Content-Type: application/json');
        echo json_encode($errorResponse);
    }
    
    // Flush any remaining output
    if (ob_get_length()) ob_end_flush();
}

/**
 * Check if the environment is production
 */
function isProduction() {
    return (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'cPanel') !== false || 
           file_exists('/usr/local/cpanel'));
}
?>
