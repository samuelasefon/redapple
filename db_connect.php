<?php
// For cPanel deployment, use the appropriate configuration
$is_cpanel = (strpos($_SERVER['SERVER_SOFTWARE'], 'cPanel') !== false || file_exists('/usr/local/cpanel'));

if ($is_cpanel) {
    // cPanel database settings - Update these with your actual values
    $servername = "localhost"; // Usually 'localhost' on cPanel
    $username = "digibxqi_saolas";  // Replace with your cPanel database username
    $password = "Tinuade9ja@";  // Replace with your cPanel database password
    $dbname = "digibxqi_bank_system";        // Replace with your cPanel database name
} else {
    // Local development settings
    $servername = "127.0.0.1";
    $username = "root";
    $password = "";
    $dbname = "bank_system";
}

// Create connection with proper error handling
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        
        // Only output JSON if this is an API request (not an HTML page load)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strpos($_SERVER['REQUEST_URI'], 'login-capture.php') !== false || 
            strpos($_SERVER['REQUEST_URI'], 'otp-verification.php') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        } else {
            die("Connection failed: " . $conn->connect_error);
        }
    }
} catch (Exception $e) {
    error_log("Database connection exception: " . $e->getMessage());
    
    // Only output JSON if this is an API request (not an HTML page load)
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
        strpos($_SERVER['REQUEST_URI'], 'login-capture.php') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'otp-verification.php') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    } else {
        die("Connection error. Please try again later.");
    }
}
?>