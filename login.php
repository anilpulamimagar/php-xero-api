<?php
session_start();
require_once 'config.php';

// Generate a random state value to prevent CSRF attacks
$state = bin2hex(random_bytes(16));
$_SESSION['xero_oauth_state'] = $state;

// Build the Xero authorization URL
$params = http_build_query([
    'response_type' => 'code',
    'client_id'     => XERO_CLIENT_ID,
    'redirect_uri'  => XERO_REDIRECT_URI,
    'scope'         => XERO_SCOPES,
    'state'         => $state,
]);

header('Location: ' . XERO_AUTH_URL . '?' . $params);
exit;
