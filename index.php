<?php
// DreamView OTT - Astro MPD Proxy
// Strips DRM and serves ClearKey-only MPD

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: *");

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get channel parameter
$channel = $_GET['channel'] ?? '';

// Channel configurations
$channels = [
    'awani' => [
        'mpd' => 'http://linearjitp-playback.astro.com.my/dash-wv/linear/5025/default_ott.mpd',
        'kid' => '6f06f3b3cf7cbad0cc8b21e2c94dfb10',
        'ua' => 'Mozilla/5.0 (Linux; Android 15; 23129RA5FL Build/AQ3A.240829.003; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/134.0.6998.39 Mobile Safari/537.36',
        'referer' => 'https://astrogo.astro.com.my'
    ]
    // Add more channels here:
    // 'tv1' => [
    //     'mpd' => 'http://linearjitp-playback.astro.com.my/dash-wv/linear/5001/default_ott.mpd',
    //     'kid' => 'YOUR_KID_HERE',
    //     'ua' => 'Mozilla/5.0...',
    //     'referer' => 'https://astrogo.astro.com.my'
    // ],
];

// Validate channel
if (!isset($channels[$channel])) {
    http_response_code(404);
    header("Content-Type: text/plain");
    exit("❌ Channel '{$channel}' not found\n\nAvailable channels: " . implode(', ', array_keys($channels)));
}

$config = $channels[$channel];

// Fetch MPD from Astro
$ch = curl_init($config['mpd']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        "User-Agent: " . $config['ua'],
        "Accept: */*",
        "Accept-Language: en-US,en;q=0.9,ms;q=0.8",
        "Referer: " . $config['referer'],
        "Origin: https://astrogo.astro.com.my",
        "Connection: keep-alive"
    ]
]);

$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check response
if ($httpCode !== 200 || !$data) {
    http_response_code($httpCode ?: 500);
    header("Content-Type: text/plain");
    exit("❌ Error fetching MPD from Astro: HTTP {$httpCode}");
}

// Strip Widevine DRM
$data = preg_replace(
    '/<ContentProtection[^>]*schemeIdUri="urn:uuid:EDEF8BA9-79D6-4ACE-A3C8-27DCD51D21ED"[^>]*>[\s\S]*?<\/ContentProtection>/i',
    '<!-- Widevine removed -->',
    $data
);

// Strip PlayReady DRM
$data = preg_replace(
    '/<ContentProtection[^>]*schemeIdUri="urn:uuid:9A04F079-9840-4286-AB92-E65BE0885F95"[^>]*>[\s\S]*?<\/ContentProtection>/i',
    '<!-- PlayReady removed -->',
    $data
);

// Return modified MPD
header("Content-Type: application/dash+xml");
header("Cache-Control: public, max-age=30");
header("X-Channel: {$channel}");
header("X-Proxy: DreamView-Astro-PHP");
echo $data;
?>
