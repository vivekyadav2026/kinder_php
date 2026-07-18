<?php
header('Content-Type: text/plain');

$url = 'https://api.gold-api.com/price/XAU/INR';
$apiKey = 'f6ff005a7e9202099c8011409b6401700787b1d2968460d06e078b0daff035ca';

echo "=== TESTING API CONNECTION (cURL) ===\n";
echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json"
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL Error: $curlError\n";
echo "Response Body:\n";
echo $response ? $response : "[EMPTY RESPONSE]\n";
echo "\n";

echo "=== TESTING FALLBACK (file_get_contents) ===\n";
$opts = [
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer " . $apiKey . "\r\nContent-Type: application/json\r\n",
        'timeout' => 15,
        'ignore_errors' => true
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
];
$context = stream_context_create($opts);
$responseFallback = @file_get_contents($url, false, $context);
echo "allow_url_fopen status: " . (ini_get('allow_url_fopen') ? 'ENABLED' : 'DISABLED') . "\n";
echo "Response Body:\n";
echo $responseFallback ? $responseFallback : "[EMPTY RESPONSE]\n";
?>
