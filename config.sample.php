<?php
// ============================================================================
//  SETUP INSTRUCTIONS:
//  1. Rename this file to "config.php" (inside the common/ folder).
//  2. Below, fill DB_HOST, DB_USER, DB_PASS and DB_NAME with your
//     hosting's real database details.
//  3. NEVER upload config.php to GitHub (it is in .gitignore).
// ============================================================================

// Session Start securely
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Database Configuration - set your hosting details here
define('DB_HOST', '127.0.0.1');   // MySQL hostname from your hosting panel
define('DB_USER', 'root');        // MySQL username
define('DB_PASS', 'root');        // MySQL password
define('DB_NAME', 'sm_yt_links'); // MySQL database name

// Google Analytics 4 (optional): put your Measurement ID here, e.g. 'G-XXXXXXX'
// Leave it empty ('') and no analytics code is added to the page.
define('GA_MEASUREMENT_ID', '');

// Create Connection
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log("Database Connection Failed: " . $conn->connect_error);
    die("Site is temporarily unavailable.");
}
$conn->set_charset("utf8mb4");

// --- Helper Functions ---

function redirect($url) {
    header("Location: $url");
    exit;
}

// CSRF Functions
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize Input
function sanitize($input) {
    global $conn;
    return strip_tags($conn->real_escape_string(trim($input)));
}

function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function get_youtube_id($url) {
    $pattern = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i';
    if (preg_match($pattern, $url, $match)) {
        return $match[1];
    }
    return false;
}

// Image Upload Function (hardened: real-image check + random filename)
function upload_image($file, $target_dir = '../upload/') {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Upload error or no file selected.'];
    }

    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['status' => false, 'message' => 'File size exceeds 5MB limit.'];
    }

    $img_info = @getimagesize($file['tmp_name']);
    if (!$img_info) {
        return ['status' => false, 'message' => 'Invalid image file.'];
    }

    $allowed_types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!array_key_exists($img_info[2], $allowed_types)) {
        return ['status' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WEBP.'];
    }

    $ext = $allowed_types[$img_info[2]];
    $new_filename = 'sm_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $destination = __DIR__ . '/../upload/' . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['status' => true, 'filename' => $new_filename];
    } else {
        return ['status' => false, 'message' => 'Failed to move uploaded file. Please use an Image URL instead.'];
    }
}

// Channel Logo Auto-Fetch (YouTube channel pages only, SSRF-safe)
function fetch_channel_logo($url) {
    $parsed = parse_url($url);
    $valid_hosts = ['www.youtube.com', 'youtube.com', 'm.youtube.com'];
    if (!isset($parsed['host']) || !in_array(strtolower($parsed['host']), $valid_hosts)) {
        return ['status' => false, 'message' => 'Invalid YouTube channel URL.'];
    }

    // Local SSL Bypass for file_get_contents
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
            'timeout' => 10,
            'follow_location' => 1,
            'max_redirects' => 2
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    $html = '';
    // Local SSL Bypass for cURL
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $html = @curl_exec($ch);
        curl_close($ch);
    }

    if (empty($html) && ini_get('allow_url_fopen')) {
        $html = @file_get_contents($url, false, $context);
    }

    if (empty($html)) return ['status' => false, 'message' => 'Logo fetch failed. Please check the network connection.'];

    if (!preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $html, $matches)) {
        return ['status' => false, 'message' => 'Logo could not be fetched automatically. Please use manual upload or an Image URL below.'];
    }

    $img_url = $matches[1];
    $img_parsed = parse_url($img_url);
    $allowed_img_hosts = ['yt3.googleusercontent.com', 'yt3.ggpht.com', 'lh3.googleusercontent.com'];

    if (!isset($img_parsed['scheme']) || $img_parsed['scheme'] !== 'https' || !isset($img_parsed['host']) || !in_array(strtolower($img_parsed['host']), $allowed_img_hosts)) {
        return ['status' => false, 'message' => 'Invalid logo source.'];
    }

    $img_data = '';
    if (function_exists('curl_version')) {
        $ch = curl_init($img_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $img_data = @curl_exec($ch);
        curl_close($ch);
    } else {
        $img_data = @file_get_contents($img_url, false, $context);
    }

    if (empty($img_data) || strlen($img_data) > 5 * 1024 * 1024) return ['status' => false, 'message' => 'Failed to download logo or size too large.'];

    $tmp_file = sys_get_temp_dir() . '/' . uniqid('logo_');
    if (!@file_put_contents($tmp_file, $img_data)) return ['status' => false, 'message' => 'Failed to save temp file.'];

    $img_info = @getimagesize($tmp_file);
    if (!$img_info) {
        @unlink($tmp_file);
        return ['status' => false, 'message' => 'Downloaded file is not a valid image.'];
    }

    $allowed_types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!array_key_exists($img_info[2], $allowed_types)) {
        @unlink($tmp_file);
        return ['status' => false, 'message' => 'Invalid logo image type.'];
    }

    $ext = $allowed_types[$img_info[2]];
    $new_filename = 'sm_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;

    $destination = __DIR__ . '/../upload/' . $new_filename;

    if (rename($tmp_file, $destination)) {
        return ['status' => true, 'filename' => $new_filename];
    } else {
        @unlink($tmp_file);
        return ['status' => false, 'message' => 'Failed to save fetched logo.'];
    }
}

function get_channel_settings() {
    global $conn;
    $result = $conn->query("SELECT * FROM channel_settings LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return [];
}

$settings = get_channel_settings();
?>
