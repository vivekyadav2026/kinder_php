<?php
header('Content-Type: text/plain');

$url = 'https://api.gold-api.com/price/XAU/INR';
$apiKey = 'f6ff005a7e9202099c8011409b6401700787b1d2968460d06e078b0daff035ca';

echo "=== TESTING API CONNECTION ===\n";
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
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_VERBOSE => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL Error No: $curlErrno\n";
echo "cURL Error Message: $curlError\n";
echo "Response Body:\n";
echo $response ? $response : "[EMPTY RESPONSE]\n";
echo "\n";

echo "=== ATTEMPTING WITHOUT KEY ===\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);
$responseNoKey = curl_exec($ch);
$httpCodeNoKey = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErrorNoKey = curl_error($ch);
curl_close($ch);

echo "HTTP Code (No Key): $httpCodeNoKey\n";
echo "cURL Error: $curlErrorNoKey\n";
echo "Response Body:\n";
echo $responseNoKey ? $responseNoKey : "[EMPTY RESPONSE]\n";
?>
