<?php
// Simple database connection test
echo "<h1>Database Connection Test</h1>";

// Try connecting to the database
try {
    include 'db_connect.php';
    
    if ($conn->connect_error) {
        echo "<p style='color: red;'>Database connection failed: " . $conn->connect_error . "</p>";
    } else {
        echo "<p style='color: green;'>Database connection successful!</p>";
        
        // Check for login_attempts table
        $tableCheck = $conn->query("SHOW TABLES LIKE 'login_attempts'");
        if ($tableCheck->num_rows > 0) {
            echo "<p style='color: green;'>login_attempts table exists.</p>";
            
            // Check table structure
            $tableStructure = $conn->query("DESCRIBE login_attempts");
            echo "<h2>Table Structure:</h2>";
            echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
            
            while ($row = $tableStructure->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['Field']}</td>";
                echo "<td>{$row['Type']}</td>";
                echo "<td>{$row['Null']}</td>";
                echo "<td>{$row['Key']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            // Check for sample data
            $dataCheck = $conn->query("SELECT COUNT(*) as count FROM login_attempts");
            $dataCount = $dataCheck->fetch_assoc();
            echo "<p>Number of records in login_attempts: {$dataCount['count']}</p>";
            
        } else {
            echo "<p style='color: red;'>login_attempts table does not exist!</p>";
            
            // Show SQL to create the table
            echo "<h2>SQL to create the table:</h2>";
            echo "<pre>
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `question` text NOT NULL,
  `otp_plain` varchar(11) NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            </pre>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>