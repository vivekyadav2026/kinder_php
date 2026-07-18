<?php
// Rates helper to cache and fetch precious metals prices dynamically from the database users record

function loadRates($pdo, $userId) {
    $defaultRates = [
        'gold_api_key' => '',
        'rate_24k' => 12565.0,
        'rate_22k' => 11510.0,
        'rate_ag' => 179.0,
        'last_updated' => 0
    ];

    if (!$userId) {
        return $defaultRates;
    }

    try {
        $stmt = $pdo->prepare("SELECT gold_api_key, rate_24k, rate_22k, rate_ag, rates_last_updated FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        
        if (!$row) {
            return $defaultRates;
        }

        return [
            'gold_api_key' => $row['gold_api_key'] ?? '',
            'rate_24k' => floatval($row['rate_24k'] ?: 12565.0),
            'rate_22k' => floatval($row['rate_22k'] ?: 11510.0),
            'rate_ag' => floatval($row['rate_ag'] ?: 179.0),
            'last_updated' => intval($row['rates_last_updated'] ?: 0)
        ];
    } catch (Exception $e) {
        return $defaultRates;
    }
}

function saveRates($pdo, $userId, $rates) {
    if (!$userId) return;
    try {
        $stmt = $pdo->prepare("UPDATE users SET gold_api_key = ?, rate_24k = ?, rate_22k = ?, rate_ag = ?, rates_last_updated = ? WHERE id = ?");
        $stmt->execute([
            $rates['gold_api_key'],
            $rates['rate_24k'],
            $rates['rate_22k'],
            $rates['rate_ag'],
            $rates['last_updated'],
            $userId
        ]);
    } catch (Exception $e) {
        // Fail silently
    }
}

function refreshRatesIfNeeded($pdo, $userId) {
    $rates = loadRates($pdo, $userId);
    
    // Check if cached for more than 6 hours
    if ((time() - $rates['last_updated']) < 21600) {
        return $rates;
    }

    $apiKey = trim($rates['gold_api_key']);
    
    // 1. Fetch Gold Price in INR (XAU/INR)
    $goldUrl = 'https://api.gold-api.com/price/XAU/INR';
    $goldData = fetchGoldApiData($goldUrl, $apiKey);
    
    // 2. Fetch Silver Price in INR (XAG/INR)
    $silverUrl = 'https://api.gold-api.com/price/XAG/INR';
    $silverData = fetchGoldApiData($silverUrl, $apiKey);

    $updated = false;
    
    // 1 Ounce = 31.1034768 Grams
    $ouncesToGrams = 31.1034768;

    if ($goldData && isset($goldData['price'])) {
        $pricePerOunce = floatval($goldData['price']);
        $rates['rate_24k'] = round($pricePerOunce / $ouncesToGrams);
        $rates['rate_22k'] = round($rates['rate_24k'] * 0.916);
        $updated = true;
    }

    if ($silverData && isset($silverData['price'])) {
        $pricePerOunce = floatval($silverData['price']);
        $rates['rate_ag'] = round($pricePerOunce / $ouncesToGrams);
        $updated = true;
    }

    if ($updated) {
        $rates['last_updated'] = time();
        saveRates($pdo, $userId, $rates);
    }

    return $rates;
}

function fetchGoldApiData($url, $apiKey) {
    $headers = ["Content-Type: application/json"];
    if (!empty($apiKey)) {
        $headers[] = "Authorization: Bearer " . $apiKey;
    }

    // 1. Try cURL first
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['price'])) {
                return $decoded;
            }
        }
    }
    
    // 2. Fallback: Try file_get_contents with stream context
    if (ini_get('allow_url_fopen')) {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 8,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context = stream_context_create($opts);
        $responseFallback = @file_get_contents($url, false, $context);
        if ($responseFallback) {
            $decoded = json_decode($responseFallback, true);
            if ($decoded && isset($decoded['price'])) {
                return $decoded;
            }
        }
    }
    
    return null;
}
?>
