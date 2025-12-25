<?php
/**
 * DreamView OTT - Astro MPD Proxy
 * Strips DRM and serves ClearKey-only MPD
 * Optimized headers to match working curl command
 */

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
        'key' => '525510cfa634bd630af8c95fa93576ca',
        // Desktop Firefox User-Agent (NOT mobile!)
        'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
        // Referer WITH trailing slash
        'referer' => 'https://astrogo.astro.com.my/'
    ]
    // Add more channels here:
    // 'tv1' => [
    //     'mpd' => 'http://linearjitp-playback.astro.com.my/dash-wv/linear/5001/default_ott.mpd',
    //     'kid' => 'YOUR_KID_HERE',
    //     'key' => 'YOUR_KEY_HERE',
    //     'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
    //     'referer' => 'https://astrogo.astro.com.my/'
    // ],
];

// Validate channel exists
if (!isset($channels[$channel])) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=utf-8");
    exit("❌ Channel '{$channel}' not found\n\nAvailable channels: " . implode(', ', array_keys($channels)) . "\n\nUsage: /api/?channel=awani");
}

$config = $channels[$channel];

// Initialize cURL
$ch = curl_init($config['mpd']);

// Configure cURL with exact settings that work
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    // Follow redirects (important!)
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    // Exact headers that work in curl command
    CURLOPT_HTTPHEADER => [
        "User-Agent: " . $config['ua'],
        "Accept: */*",
        "Referer: " . $config['referer'],
        "Origin: https://astrogo.astro.com.my"
    ]
]);

// Execute request
$data = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Check for errors
if ($httpCode !== 200 || !$data) {
    http_response_code($httpCode ?: 500);
    header("Content-Type: text/plain; charset=utf-8");
    
    $errorMsg = "❌ Error fetching MPD from Astro\n\n";
    $errorMsg .= "HTTP Code: {$httpCode}\n";
    $errorMsg .= "URL: {$config['mpd']}\n";
    
    if ($curlError) {
        $errorMsg .= "cURL Error: {$curlError}\n";
    }
    
    // Show partial response if available
    if ($data) {
        $errorMsg .= "\nPartial Response:\n" . substr($data, 0, 500);
    }
    
    exit($errorMsg);
}

// Strip Widevine DRM (case-insensitive)
$data = preg_replace(
    '/<ContentProtection[^>]*schemeIdUri="urn:uuid:edef8ba9-79d6-4ace-a3c8-27dcd51d21ed"[^>]*>[\s\S]*?<\/ContentProtection>/i',
    '<!-- Widevine removed -->',
    $data
);

// Strip PlayReady DRM (if exists)
$data = preg_replace(
    '/<ContentProtection[^>]*schemeIdUri="urn:uuid:9a04f079-9840-4286-ab92-e65be0885f95"[^>]*>[\s\S]*?<\/ContentProtection>/i',
    '<!-- PlayReady removed -->',
    $data
);

// Return modified MPD
header("Content-Type: application/dash+xml; charset=utf-8");
header("Cache-Control: public, max-age=30");
header("X-Channel: {$channel}");
header("X-Proxy: DreamView-Astro-PHP");
header("X-KID: {$config['kid']}");

echo $data;
?>
