<?php
require_once __DIR__ . '/db_conn.php';

Auth::requireMaster();

// API URL
$url = "https://connect.arklytics.in/master/register_phone"; // Your API URL

// Certificate Content (Directly Pasted)
$base64Certificate = base64_encode(<<<CERT
-----BEGIN CERTIFICATE-----
CnAKLAjE7+z2v+vVAhIGZW50OndhIhNBcmtseXRpY3MgU29sdXRpb25zUIG0ybwGGkAWY0O4QICyz7oVVZMFxhNtrgu8hAZN37lYelfnnDSlu3aAPjCecZE6WbKz3BlN3QkIn3lBeS8kcz+0iCqKFQEKEi5tdXWKhaytW+BEh7Ofq2Qgk13j4FnC2JzyBT1OrTxxkFzzYImZIAQD/GK0iMkd-----END CERTIFICATE-----
CERT
);

// API Headers
$headers = [
    "Authorization: Bearer " . Config::require('META_ACCESS_TOKEN'),
    "Content-Type: application/json"
];

// API Data
$data = [
    "cc" => "91", // Country Code
    "phone_number" => "7799677557", // Phone number to register
    "method" => "sms", // Verification method: 'sms' or 'voice'
    "certificate" => $base64Certificate // Base64-encoded certificate
];

// Initialize cURL
$ch = curl_init($url);

// Set cURL Options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // To return the response
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Set headers
curl_setopt($ch, CURLOPT_POST, true); // HTTP POST method
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Add JSON body

// Execute cURL Request
$response = curl_exec($ch);

// Check for Errors
if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch);
} else {
    // Parse and Display the Response
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP Status Code: " . $http_status . "\n";
    echo "Response: " . $response . "\n";
}

// Close cURL
curl_close($ch);
?>
