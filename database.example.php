<?php
// Database configuration example
// Rename this file to database.php and fill in your actual database credentials

$host = "localhost";      // Database host
$username = "username";   // Database username
$password = "password";   // Database password
$dbname = "dbname";      // Database name

// Create connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
