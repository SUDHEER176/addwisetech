<?php
require_once 'vendor/autoload.php';

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
$client->addScope("email");
$client->addScope("profile");

header('Location: ' . $client->createAuthUrl());
exit;
