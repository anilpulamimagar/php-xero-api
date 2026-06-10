<?php
session_start();
require_once 'config.php';

// Verify state to prevent CSRF
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['xero_oauth_state']) {
    die('Invalid state parameter. Possible CSRF attack.');
}
unset($_SESSION['xero_oauth_state']);

// Check for errors from Xero
if (isset($_GET['error'])) {
    die('Xero auth error: ' . htmlspecialchars($_GET['error']));
}

// Ensure we received an auth code
if (!isset($_GET['code'])) {
    die('No authorization code received from Xero.');
}

$code = $_GET['code'];

// Exchange the auth code for an access token
$credentials = base64_encode(XERO_CLIENT_ID . ':' . XERO_CLIENT_SECRET);

$ch = curl_init(XERO_TOKEN_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'   => 'authorization_code',
        'code'         => $code,
        'redirect_uri' => XERO_REDIRECT_URI,
    ]),
]);

$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$tokenData = json_decode($response, true);

if ($curlError) {
    die('cURL error: ' . htmlspecialchars($curlError));
}

if ($httpCode !== 200 || empty($tokenData['access_token'])) {
    die('Failed to get access token (HTTP ' . $httpCode . '): ' . htmlspecialchars($response));
}

// Store tokens in session
$_SESSION['xero_access_token']  = $tokenData['access_token'];
$_SESSION['xero_refresh_token'] = $tokenData['refresh_token'] ?? null;
$_SESSION['xero_token_expiry']  = time() + ($tokenData['expires_in'] ?? 1800);

// Fetch the authenticated user's identity
$ch = curl_init('https://identity.xero.com/connect/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $_SESSION['xero_access_token'],
    ],
]);
$userInfo = json_decode(curl_exec($ch), true);
curl_close($ch);

$_SESSION['xero_user'] = $userInfo;

// Fetch the list of Xero organisations (tenants) the user has access to
$ch = curl_init('https://api.xero.com/connections');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $_SESSION['xero_access_token'],
        'Content-Type: application/json',
    ],
]);
$tenants = json_decode(curl_exec($ch), true);
curl_close($ch);

// Use the first available tenant
$_SESSION['xero_tenant_id'] = $tenants[0]['tenantId'] ?? null;

header('Location: index.php');
exit;
