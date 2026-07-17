<?php
// Rates helper to cache and fetch precious metals prices dynamically

function getRatesConfigPath() {
    return __DIR__ . '/settings_rates.json';
}

function loadRates() {
    $path = getRatesConfigPath();
    $defaultRates = [
        'gold_api_key' => '',
        'rate_24k' => 12565.0,
        'rate_22k' => 11510.0,
        'rate_ag' => 179.0,
        'last_updated' => 0
    ];

    if (!file_exists($path)) {
        return $defaultRates;
    }

    $json = @file_get_contents($path);
    $data = json_decode($json, true);

    return is_array($data) ? array_merge($defaultRates, $data) : $defaultRates;
}

function saveRates($rates) {
    $path = getRatesConfigPath();
    @file_put_contents($path, json_encode($rates, JSON_PRETTY_PRINT));
}

function refreshRatesIfNeeded() {
    $rates = loadRates();
    
    // Check if key exists and if cached for more than 6 hours
    if (empty($rates['gold_api_key']) || (time() - $rates['last_updated']) < 21600) {
        return $rates;
    }

    $apiKey = trim($rates['gold_api_key']);
    
    // 1. Fetch 24K Gold Price in INR
    $goldUrl = 'https://www.goldapi.io/api/XAU/INR';
    $goldData = fetchGoldApiData($goldUrl, $apiKey);
    
    // 2. Fetch Silver Price in INR
    $silverUrl = 'https://www.goldapi.io/api/XAG/INR';
    $silverData = fetchGoldApiData($silverUrl, $apiKey);

    $updated = false;
    
    if ($goldData && isset($goldData['price_gram_24k'])) {
        $rates['rate_24k'] = round($goldData['price_gram_24k']);
        // Calculate 22K as 91.6% of 24K Gold price
        $rates['rate_22k'] = round($rates['rate_24k'] * 0.916);
        $updated = true;
    }

    if ($silverData && isset($silverData['price_gram'])) {
        $rates['rate_ag'] = round($silverData['price_gram']);
        $updated = true;
    }

    if ($updated) {
        $rates['last_updated'] = time();
        saveRates($rates);
    }

    return $rates;
}

function fetchGoldApiData($url, $apiKey) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => [
            "x-access-token: " . $apiKey,
            "Content-Type: application/json"
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        return json_decode($response, true);
    }
    return null;
}
?>
