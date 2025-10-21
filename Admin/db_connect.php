<?php
/*
 * Database connection parameters
 * Change these values according to your local environment
 */
define('DB_SERVER', 'localhost');       // Most commonly 'localhost'
define('DB_USERNAME', 'root');          // Your database username
define('DB_PASSWORD', '');              // Your database password (often empty for 'root' on local systems)
define('DB_NAME', 'collabbuddy');       // The name of your database (based on your SQL file)

/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // If connection fails, stop execution and display error
    die("ERROR: Could not connect to the database. " . $conn->connect_error);
}

// Optional: Set character set to UTF-8 for proper Malayalam and other language support
$conn->set_charset("utf8mb4");

// Note: The variable $conn is now available for use in other files (like admin_login.php)
?>