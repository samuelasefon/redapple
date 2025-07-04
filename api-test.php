<?php
// Include standardized error handler
require_once 'error-handler.php';
setupApiErrorHandling();

// Create response object
$response = [
    'success' => true,
    'message' => 'API test successful',
    'server' => [
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'php_version' => phpversion(),
        'time' => date('Y-m-d H:i:s'),
        'is_cpanel' => (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'cPanel') !== false || 
                       file_exists('/usr/local/cpanel')) ? 'Yes' : 'No'
    ]
];

// Test database connection
try {
    require_once 'db_connect.php';
    
    if ($conn->ping()) {
        $response['database'] = [
            'connection' => 'Success',
            'server' => $conn->server_info,
            'version' => $conn->server_version
        ];
        
        // Check if login_attempts table exists
        $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
        $response['database']['login_attempts_table'] = ($result && $result->num_rows > 0) ? 'Exists' : 'Missing';
    } else {
        $response['database'] = [
            'connection' => 'Failed',
            'error' => 'Database server is not responding'
        ];
    }
    
    $conn->close();
} catch (Exception $e) {
    $response['database'] = [
        'connection' => 'Failed',
        'error' => $e->getMessage()
    ];
}

// Output response as JSON
echo json_encode($response, JSON_PRETTY_PRINT);
?>
