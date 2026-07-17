<?php
require_once 'db.php';
require_once 'config.php';

if (!isset($_GET['code'])) {
    header("Location: login.php");
    exit();
}

$code = $_GET['code'];

// 1. Exchange Authorization Code for Access Token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token'])) {
    die("Google Login Error: Failed to retrieve access token. Check client configuration in config.php. Response: " . htmlspecialchars($response));
}

$accessToken = $tokenData['access_token'];

// 2. Fetch User Profile Info from Google API
$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$userResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userResponse, true);

if (!isset($googleUser['email'])) {
    die("Google Login Error: Failed to retrieve user info. Response: " . htmlspecialchars($userResponse));
}

$email = filter_var($googleUser['email'], FILTER_SANITIZE_EMAIL);
$name = $googleUser['name'] ?? '';
$oauthId = $googleUser['sub'] ?? '';

// 3. User Login/Registration Flow
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $userId = $user['id'];
    $userName = $user['name'];
    
    // Dynamically sync and update the name if it is empty, or has changed on their Google profile
    if (empty($user['name']) || $user['name'] !== $name) {
        $updateStmt = $pdo->prepare("UPDATE users SET name = ?, oauth_provider = 'google', oauth_id = ? WHERE id = ?");
        $updateStmt->execute([$name, $oauthId, $userId]);
        $userName = $name;
    } else if ($user['oauth_provider'] !== 'google') {
        $updateStmt = $pdo->prepare("UPDATE users SET oauth_provider = 'google', oauth_id = ? WHERE id = ?");
        $updateStmt->execute([$oauthId, $userId]);
    }
} else {
    // Register new user via Google
    $insertStmt = $pdo->prepare("INSERT INTO users (email, name, oauth_provider, oauth_id) VALUES (?, ?, 'google', ?)");
    $insertStmt->execute([$email, $name, $oauthId]);
    $userId = $pdo->lastInsertId();
    $userName = $name;
}

// 4. Establish Session
session_unset();
$_SESSION['user_id'] = $userId;
$_SESSION['user_name'] = $userName;
$_SESSION['user_email'] = $email;
session_regenerate_id(true);

header("Location: index.php");
exit();
?>
