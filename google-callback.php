<?php
require_once 'vendor/autoload.php';
session_start();

use Google\Service\Oauth2 as Google_Service_Oauth2;
use Google\Client;

// Load environment variables from .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    $clientID = $envVars['GOOGLE_CLIENT_ID'] ?? '';
    $clientSecret = $envVars['GOOGLE_CLIENT_SECRET'] ?? '';
    $redirectUri = $envVars['GOOGLE_REDIRECT_URI'] ?? '';
} else {
    die('Environment file not found. Please create a .env file with your credentials.');
}

$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);

if (isset($_GET['code'])) {
    try {
        // Add scopes if not already set
        $client->addScope('email');
        $client->addScope('profile');
        
        // Set the access type to offline to get a refresh token
        $client->setAccessType('offline');
        
        // Set prompt to consent to ensure we get a refresh token
        $client->setPrompt('consent');
        
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        
        if (!isset($token['access_token'])) {
            throw new Exception('No access token received from Google');
        }
        
        $client->setAccessToken($token['access_token']);
        
        // Get user info
        $oauth2 = new Google_Service_Oauth2($client);
        $userinfo = $oauth2->userinfo->get();
        
        if (!$userinfo || !$userinfo->email) {
            throw new Exception('Could not get user info from Google');
        }
        
        // Check if user exists in your DB, if not, create them
        $conn = new mysqli('localhost', 'root', '', 'addwise');
        if ($conn->connect_error) {
            throw new Exception('Database connection failed: ' . $conn->connect_error);
        }
        
        $email = $conn->real_escape_string($userinfo->email);
        $name = $conn->real_escape_string($userinfo->name);
        
        $result = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($result->num_rows == 0) {
            // Register new user (no password needed)
            $conn->query("INSERT INTO users (email, is_verified) VALUES ('$email', 1)");
            $user_id = $conn->insert_id;
        } else {
            $row = $result->fetch_assoc();
            $user_id = $row['id'];
        }
        
        // Log in user
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['login_time'] = time();
        
        header("Location: dashboard.php");
        exit;
        
    } catch (Exception $e) {
        // Log the error
        error_log('Google Auth Error: ' . $e->getMessage());
        die('Authentication failed: ' . htmlspecialchars($e->getMessage()));
    }
} else {
    echo "Google authentication failed: No authorization code received.";
}