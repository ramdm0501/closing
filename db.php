<?php
// Database connection settings
$host = "localhost";
$db   = "closing";
$user = "root";   // Change this if your username is different
$pass = "";       // Change this if you have a password

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Use $conn variable to run queries in your pages
?>
