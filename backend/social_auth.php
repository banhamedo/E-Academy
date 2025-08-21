<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
while (ob_get_level()) { ob_end_clean(); }
require __DIR__ . '/config.php'; // For get_pdo()
// Keep only JSON Content-Type; CORS centralized in .htaccess
set_cors_headers();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Define your Google API credentials
// IMPORTANT: Replace with your actual credentials from Google Cloud Console
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
// This must match one of the authorized redirect URIs in your Google Cloud Console project
define('GOOGLE_REDIRECT_URI', 'http://localhost/eaacademy/purple-green-academy-39-main/backend/social_auth.php?platform=google');

// Define your Facebook API credentials
// IMPORTANT: Replace with your actual credentials from Facebook Developers
define('FACEBOOK_APP_ID', 'YOUR_FACEBOOK_APP_ID');
define('FACEBOOK_APP_SECRET', 'YOUR_FACEBOOK_APP_SECRET');
// This must match one of the authorized redirect URIs in your Facebook App Dashboard
define('FACEBOOK_REDIRECT_URI', 'http://localhost/eaacademy/purple-green-academy-39-main/backend/social_auth.php?platform=facebook');

$pdo = get_pdo();

try {
    $platform = $_GET['platform'] ?? null;
    $code = $_GET['code'] ?? null; // OAuth authorization code

    if (!$platform) {
        echo json_encode(['success' => false, 'error' => 'No platform specified']);
        exit;
    }

    if ($platform === 'google') {
        if (!$code) {
            // Step 1: Redirect to Google for authorization
            $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'scope' => 'email profile',
                'response_type' => 'code',
                'client_id' => GOOGLE_CLIENT_ID,
                'redirect_uri' => GOOGLE_REDIRECT_URI,
                'access_type' => 'offline',
                'prompt' => 'consent' // Forces consent screen to always show
            ]);
            header('Location: ' . $authUrl);
            exit;
        } else {
            // Step 2: Exchange authorization code for access token
            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $tokenParams = [
                'code' => $code,
                'client_id' => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'redirect_uri' => GOOGLE_REDIRECT_URI,
                'grant_type' => 'authorization_code'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tokenUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $tokenData = json_decode($response, true);

            if (isset($tokenData['access_token'])) {
                // Step 3: Fetch user profile information
                $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $userInfo = json_decode(curl_exec($ch), true);
                curl_close($ch);

                if (isset($userInfo['email'])) {
                    // Authenticate/Register user in your database
                    // For example: check if user exists, if not, create account
                    // Log in user and set session/localStorage as needed
                    // For this example, we'll just return success and user info
                    
                    // You would typically redirect the user back to your frontend here
                    // e.g., header('Location: http://localhost:5173/auth?social_login_success=true&email=' . urlencode($userInfo['email']));
                    echo json_encode(['success' => true, 'platform' => 'google', 'user' => $userInfo]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to retrieve Google user info', 'details' => $userInfo]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to get Google access token', 'details' => $tokenData]);
            }
        }
    } elseif ($platform === 'facebook') {
        if (!$code) {
            // Step 1: Redirect to Facebook for authorization
            $authUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
                'client_id' => FACEBOOK_APP_ID,
                'redirect_uri' => FACEBOOK_REDIRECT_URI,
                'scope' => 'email public_profile'
            ]);
            header('Location: ' . $authUrl);
            exit;
        } else {
            // Step 2: Exchange authorization code for access token
            $tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
                'client_id' => FACEBOOK_APP_ID,
                'redirect_uri' => FACEBOOK_REDIRECT_URI,
                'client_secret' => FACEBOOK_APP_SECRET,
                'code' => $code
            ]);

            $response = file_get_contents($tokenUrl);
            $tokenData = json_decode($response, true);

            if (isset($tokenData['access_token'])) {
                // Step 3: Fetch user profile information
                $userInfoUrl = 'https://graph.facebook.com/v19.0/me?fields=id,name,email&access_token=' . $tokenData['access_token'];
                $userInfo = json_decode(file_get_contents($userInfoUrl), true);

                if (isset($userInfo['email'])) {
                    // Authenticate/Register user in your database
                    // For example: check if user exists, if not, create account
                    // Log in user and set session/localStorage as needed
                    // For this example, we'll just return success and user info

                    // You would typically redirect the user back to your frontend here
                    // e.g., header('Location: http://localhost:5173/auth?social_login_success=true&email=' . urlencode($userInfo['email']));
                    echo json_encode(['success' => true, 'platform' => 'facebook', 'user' => $userInfo]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to retrieve Facebook user info', 'details' => $userInfo]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to get Facebook access token', 'details' => $tokenData]);
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Unsupported platform']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
