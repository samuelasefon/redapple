<?php
// Include required files
include 'db_connect.php';
include 'notification_config.php';
require_once 'vendor/autoload.php';
require_once 'websocket_server.php'; // Include the file with Pusher config

header('Content-Type: application/json');

// Set custom error log path
$logPath = __DIR__ . '/error.log';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $logPath);
error_log("send_notification.php: Script started");

// Create response object
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    // Get POST data
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';

    // Validate input
    if ($id <= 0) {
        throw new Exception("Invalid ID provided");
    }

    if (empty($type) || empty($status)) {
        throw new Exception("Type and status are required");
    }

    // Validate status
    $validStatuses = ['pending', 'accepted', 'rejected'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception("Invalid status provided");
    }

    // Update status in the database
    $sql = "UPDATE login_attempts SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("si", $status, $id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update status: " . $stmt->error);
    }

    $stmt->close();

    // Send real-time WebSocket notification using Pusher
    try {
        // Send WebSocket message via Pusher
        sendWebSocketUpdate($id, $status);
        error_log("WebSocket notification sent for attempt ID: $id with status: $status");
    } catch (Exception $e) {
        error_log("Failed to send WebSocket notification: " . $e->getMessage());
        // Continue execution even if WebSocket notification fails
    }

    // Send Telegram notification for admin
    try {
        $message = "🔄 STATUS UPDATE 🔄\nAttempt ID: {$id}\nType: {$type}\nStatus: {$status}\nTime: " . date('Y-m-d H:i:s');
        sendToTelegram($message);
        error_log("Telegram notification sent for status update.");
    } catch (Exception $e) {
        error_log("Failed to send Telegram notification: " . $e->getMessage());
        // Continue execution even if Telegram notification fails
    }

    $response = ['success' => true, 'message' => 'Status updated successfully'];

} catch (Exception $e) {
    error_log("Exception in send_notification.php: " . $e->getMessage());
    $response = ['success' => false, 'message' => $e->getMessage()];
} finally {
    if (isset($conn)) {
        $conn->close();
    }
    
    echo json_encode($response);
}
?>