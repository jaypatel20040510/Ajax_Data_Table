<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ajax_data_table";

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection — no echo here; it would corrupt JSON responses
if (!$conn) {
    // Send error as JSON and exit cleanly
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Connection failed: ' . mysqli_connect_error()]);
    exit;
}
?>