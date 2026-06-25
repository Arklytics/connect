<?php
// Start session if required
session_start();

// Database connection if needed
require 'db_conn.php';

// Get the requested URI
$request = $_SERVER['REQUEST_URI'];

// Routing logic
if ($request == '/master/login') {
    header('location: ' . app_url('master/login'));
}  else {
    // 404 handler
    echo '404 Page Not Found';
    http_response_code(404);
}
?>
