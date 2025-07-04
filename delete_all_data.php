<?php
include 'db_connect.php';

header('Content-Type: application/json');

// Ensure no output before JSON response
ob_start();

// Log the incoming request for debugging
error_log("delete_all_data.php: Request received");

// Log database connection status
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

try {
    // Delete all data from the login_attempts table
    $sql = "DELETE FROM login_attempts";

    // Log the SQL query execution
    error_log("Executing query: DELETE FROM login_attempts");

    if ($conn->query($sql) === TRUE) {
        error_log("All data deleted successfully.");
        echo json_encode(['success' => true, 'message' => 'All data deleted successfully.']);
    } else {
        error_log("Error deleting data: " . $conn->error);
        throw new Exception("Error deleting data: " . $conn->error);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

// End of script
ob_end_flush();
?>