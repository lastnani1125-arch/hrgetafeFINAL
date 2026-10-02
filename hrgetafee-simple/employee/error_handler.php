<?php
/**
 * Error Handler and Logging System
 */

if (!defined('LOG_DIR')) {
    define('LOG_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR);
}

// Create logs directory if not exists
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// Log Error Function
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] ERROR: $message" . PHP_EOL;
    @file_put_contents(LOG_DIR . 'errors.log', $log_entry, FILE_APPEND);
}

// Log Info Function
function logInfo($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] INFO: $message" . PHP_EOL;
    @file_put_contents(LOG_DIR . 'info.log', $log_entry, FILE_APPEND);
}

// Log Database Query (use sparingly in production)
function logDatabaseQuery($query) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] QUERY: $query" . PHP_EOL;
    @file_put_contents(LOG_DIR . 'database.log', $log_entry, FILE_APPEND);
}

// Global Error Handler
if (!defined('ERROR_HANDLER_REGISTERED')) {
    define('ERROR_HANDLER_REGISTERED', true);
    
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        $error_message = "[$errno] $errstr in $errfile on line $errline";
        logError($error_message);
        return true;
    });
    
    set_exception_handler(function($exception) {
        $error_message = "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine();
        logError($error_message);
    });
}
?>