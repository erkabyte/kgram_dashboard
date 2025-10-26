<?php
/**
 * Dropbox Connection Test Script
 * 
 * This script helps test the Dropbox connection and configuration
 * Run this from your project root: php test_dropbox.php
 */

require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "=== Dropbox Connection Test ===\n\n";

// Check if Dropbox environment variables are set
$requiredVars = ['DROPBOX_APP_KEY', 'DROPBOX_APP_SECRET', 'DROPBOX_ACCESS_TOKEN'];
$missingVars = [];

foreach ($requiredVars as $var) {
    if (empty($_ENV[$var])) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    echo "❌ Missing environment variables:\n";
    foreach ($missingVars as $var) {
        echo "   - $var\n";
    }
    echo "\nPlease add these to your .env file.\n";
    exit(1);
}

echo "✅ Environment variables are set\n\n";

// Test Dropbox API connection
echo "Testing Dropbox API connection...\n";

$accessToken = $_ENV['DROPBOX_ACCESS_TOKEN'];
$baseUrl = 'https://api.dropboxapi.com/2';

try {
    // Test account info endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/users/get_current_account');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    // Send empty JSON object as body - this endpoint expects POST with valid JSON
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode === 200) {
        $accountInfo = json_decode($response, true);
        echo "✅ Dropbox connection successful!\n";
        echo "   Account: " . $accountInfo['name']['display_name'] . "\n";
        echo "   Email: " . $accountInfo['email'] . "\n";
        echo "   Country: " . $accountInfo['country'] . "\n";
    } else {
        echo "❌ Dropbox connection failed (HTTP $httpCode)\n";
        echo "   Response: $response\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error testing Dropbox connection: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
