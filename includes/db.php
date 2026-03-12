<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u525340418_work');
define('DB_PASS', 'Keith082703');
define('DB_NAME', 'u525340418_work');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");
?> 