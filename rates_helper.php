<?php
/**
 * Production-Ready Precious Metals Rates Redesign
 * Handles caching, secure API integration, error logging, and Indian retail jewellery adjustments.
 */

// Strict type enforcement for safer execution in PHP 8
declare(strict_types=1);

interface PreciousMetalsPriceProvider {
    /**
     * Fetch spot price from API.
     * @return array{price: float, unit: string, symbol: string, currency: string}
     * @throws Exception
     */
    public function fetchPrice(string $symbol, string $currency, string $apiKey): array;
}

class GoldApiProvider implements PreciousMetalsPriceProvider {
    private const BASE_URL = 'https://api.gold-api.com/price/';

    public function fetchPrice(string $symbol, string $currency, string $apiKey): array {
        if (empty(trim($apiKey))) {
            $errorMsg = "API Key is missing or empty.";
            error_log("[GoldApiProvider Error] " . $errorMsg);
            throw new Exception($errorMsg);
        }

        $url = self::BASE_URL . rawurlencode($symbol) . '/' . rawurlencode($currency);
        
        $ch = curl_init();
        if ($ch === false) {
            $errorMsg = "Failed to initialize cURL.";
            error_log("[GoldApiProvider Error] " . $errorMsg);
            throw new Exception($errorMsg);
        }

        $headers = [
            "Authorization: Bearer " . trim($apiKey),
            "Content-Type: application/json",
            "User-Agent: DasgoldLedger/2.0 (Production Precious Metals Helper)"
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
            // Strict SSL Verification for Production Security
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $errorMsg = "cURL request failed: " . $curlError;
            error_log("[GoldApiProvider Error] " . $errorMsg);
            throw new Exception($errorMsg);
        }

        if ($httpCode !== 200) {
            $errorMsg = "HTTP error code {$httpCode} returned from Gold API. Response: " . substr($response, 0, 200);
            error_log("[GoldApiProvider Error] " . $errorMsg);
            if ($httpCode === 401 || $httpCode === 403) {
                throw new Exception("Unauthorized or Invalid Gold API Key.");
            }
            throw new Exception("Gold API request returned HTTP status " . $httpCode);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = "Failed to parse JSON response: " . json_last_error_msg();
            error_log("[GoldApiProvider Error] " . $errorMsg);
            throw new Exception($errorMsg);
        }

        if (!isset($data['price'])) {
            $errorMsg = "Invalid API response structure: 'price' field missing.";
            error_log("[GoldApiProvider Error] " . $errorMsg);
            throw new Exception($errorMsg);
        }

        // Gold API returns prices in Troy Ounces for XAU/XAG.
        // We validate and return structure.
        return [
            'price' => floatval($data['price']),
            'unit' => 'troy_ounce', // Standard unit for XAU/XAG spot markets
            'symbol' => $symbol,
            'currency' => $currency,
            'raw_response' => $data
        ];
    }
}

class IndianJewelleryRateCalculator {
    private const OUNCES_TO_GRAMS = 31.1034768;

    // Standard Indian Taxes & Duties Configurations
    private float $importDutyPercent = 6.0;   // Custom import duty on bullion
    private float $gstPercent = 3.0;          // Goods and Services Tax (GST) on gold
    private float $bullionPremium = 150.0;    // Average local bullion premium/handling charges per gram
    private float $retailMarginPercent = 1.0; // Local jeweller margin

    public function __construct(
        float $importDutyPercent = 6.0,
        float $gstPercent = 3.0,
        float $bullionPremium = 150.0,
        float $retailMarginPercent = 1.0
    ) {
        $this->importDutyPercent = $importDutyPercent;
        $this->gstPercent = $gstPercent;
        $this->bullionPremium = $bullionPremium;
        $this->retailMarginPercent = $retailMarginPercent;
    }

    /**
     * Calculates the Indian Retail Rate per gram based on Troy Ounce spot price.
     */
    public function calculateRetailRatePerGram(float $spotPricePerOunce): float {
        // 1. Convert Troy Ounce spot price to Spot price per gram
        $spotPricePerGram = $spotPricePerOunce / self::OUNCES_TO_GRAMS;

        // 2. Add Customs/Import Duty
        $withDuty = $spotPricePerGram * (1 + ($this->importDutyPercent / 100));

        // 3. Add Local Bullion Premium per gram
        $withPremium = $withDuty + $this->bullionPremium;

        // 4. Add GST
        $withGst = $withPremium * (1 + ($this->gstPercent / 100));

        // 5. Add Retail Margin
        $finalRetailRate = $withGst * (1 + ($this->retailMarginPercent / 100));

        return round($finalRetailRate, 2);
    }
}

class RatesManager {
    private PDO $pdo;
    private PreciousMetalsPriceProvider $provider;
    private IndianJewelleryRateCalculator $calculator;
    private int $cacheLifetime; // in seconds
    private bool $debugMode;
    private array $debugLogs = [];

    public function __construct(
        PDO $pdo,
        PreciousMetalsPriceProvider $provider,
        IndianJewelleryRateCalculator $calculator,
        int $cacheLifetime = 300, // Configurable cache (default 5 minutes)
        bool $debugMode = false
    ) {
        $this->pdo = $pdo;
        $this->provider = $provider;
        $this->calculator = $calculator;
        $this->cacheLifetime = $cacheLifetime;
        $this->debugMode = $debugMode;
    }

    private function logDebug(string $message, mixed $context = null): void {
        $logEntry = "[" . date('Y-m-d H:i:s') . "] " . $message;
        if ($context !== null) {
            $logEntry .= " Context: " . json_encode($context);
        }
        $this->debugLogs[] = $logEntry;
        if ($this->debugMode) {
            echo htmlspecialchars($logEntry) . "\n";
        }
    }

    public function getDebugLogs(): array {
        return $this->debugLogs;
    }

    /**
     * Loads the cached rates record for a user.
     */
    public function loadCachedRates(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT gold_api_key, rate_24k, rate_22k, rate_ag, rates_last_updated FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception("User not found in database.");
        }

        return [
            'gold_api_key' => $row['gold_api_key'] ?? '',
            'rate_24k' => floatval($row['rate_24k'] ?? 12565.0),
            'rate_22k' => floatval($row['rate_22k'] ?? 11510.0),
            'rate_ag' => floatval($row['rate_ag'] ?? 179.0),
            'last_updated' => intval($row['rates_last_updated'] ?? 0)
        ];
    }

    /**
     * Updates and saves rates in the database.
     */
    public function saveRates(int $userId, array $rates): void {
        $stmt = $this->pdo->prepare("UPDATE users SET rate_24k = ?, rate_22k = ?, rate_ag = ?, rates_last_updated = ? WHERE id = ?");
        $stmt->execute([
            $rates['rate_24k'],
            $rates['rate_22k'],
            $rates['rate_ag'],
            $rates['last_updated'],
            $userId
        ]);
        $this->logDebug("Rates successfully saved to database for User ID: {$userId}");
    }

    /**
     * Refreshes metal rates if cache has expired.
     */
    public function refreshRates(int $userId): array {
        $rates = $this->loadCachedRates($userId);
        $apiKey = trim($rates['gold_api_key']);

        $this->logDebug("Starting rates check for User ID: {$userId}", [
            'api_key_configured' => !empty($apiKey),
            'api_key_masked' => !empty($apiKey) ? substr($apiKey, 0, 4) . '...' . substr($apiKey, -4) : 'NONE',
            'last_updated_time' => date('Y-m-d H:i:s', $rates['last_updated']),
            'cache_remaining_seconds' => max(0, $this->cacheLifetime - (time() - $rates['last_updated']))
        ]);

        // Check cache lifetime
        if ((time() - $rates['last_updated']) < $this->cacheLifetime) {
            $this->logDebug("Returning cached rates (Cache valid for " . ($this->cacheLifetime - (time() - $rates['last_updated'])) . "s)");
            return $rates;
        }

        if (empty($apiKey)) {
            // Fallback to system/admin API key if user hasn't configured one
            $adminStmt = $this->pdo->query("SELECT gold_api_key FROM users WHERE is_admin = 1 AND gold_api_key IS NOT NULL AND gold_api_key != '' LIMIT 1");
            $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
            if ($adminRow && !empty(trim($adminRow['gold_api_key'] ?? ''))) {
                $apiKey = trim($adminRow['gold_api_key']);
            }
        }

        if (empty($apiKey)) {
            $errorMsg = "Missing API key in settings. Please configure a valid gold-api.com API key.";
            $this->logDebug($errorMsg);
            error_log("[RatesManager Error] User {$userId}: " . $errorMsg);
            
            // Backoff on failure so we don't spam checks on every page load
            $rates['last_updated'] = time();
            $this->saveRates($userId, $rates);
            
            // Return cached rates instead of crashing, but raise a warning
            throw new Exception($errorMsg);
        }

        try {
            // 1. Fetch Gold price (spot per Troy Ounce)
            $this->logDebug("Fetching Gold price (XAU/INR)...");
            $goldResult = $this->provider->fetchPrice('XAU', 'INR', $apiKey);
            $this->logDebug("Gold Raw price: " . $goldResult['price'] . " " . $goldResult['currency']);

            // 2. Fetch Silver price (spot per Troy Ounce)
            $this->logDebug("Fetching Silver price (XAG/INR)...");
            $silverResult = $this->provider->fetchPrice('XAG', 'INR', $apiKey);
            $this->logDebug("Silver Raw price: " . $silverResult['price'] . " " . $silverResult['currency']);

            // 3. Calculate Indian Retail Prices
            $rate24k = $this->calculator->calculateRetailRatePerGram($goldResult['price']);
            $rate22k = round($rate24k * 0.916, 2); // Calculate 22K (91.6% purity) based on 24K retail rate
            $rateAg = $this->calculator->calculateRetailRatePerGram($silverResult['price']);

            $this->logDebug("Calculated Rates", [
                'Gold 24K (/g)' => $rate24k,
                'Gold 22K (/g)' => $rate22k,
                'Silver (/g)' => $rateAg
            ]);

            // Save updated rates back to database
            $rates['rate_24k'] = $rate24k;
            $rates['rate_22k'] = $rate22k;
            $rates['rate_ag'] = $rateAg;
            $rates['last_updated'] = time();

            $this->saveRates($userId, $rates);
            return $rates;

        } catch (Exception $e) {
            $errorMsg = "Precious metals rates update failed: " . $e->getMessage();
            $this->logDebug($errorMsg);
            error_log("[RatesManager Exception] User {$userId}: " . $errorMsg);
            
            // Backoff on failure so we don't block page loads with slow cURL timeouts
            $rates['last_updated'] = time();
            $this->saveRates($userId, $rates);
            
            // Return existing database values (last successful rates) instead of hardcoded defaults
            $this->logDebug("Using fallback last successful rates from database.");
            return $rates;
        }
    }
}

// Backward compatibility helper functions to support existing code structure
function loadRates($pdo, $userId) {
    $manager = new RatesManager($pdo, new GoldApiProvider(), new IndianJewelleryRateCalculator());
    return $manager->loadCachedRates((int)$userId);
}

function saveRates($pdo, $userId, $rates) {
    $manager = new RatesManager($pdo, new GoldApiProvider(), new IndianJewelleryRateCalculator());
    $manager->saveRates((int)$userId, $rates);
}

function refreshRatesIfNeeded($pdo, $userId, bool $debugMode = false): array {
    $manager = new RatesManager($pdo, new GoldApiProvider(), new IndianJewelleryRateCalculator(), 300, $debugMode);
    try {
        return $manager->refreshRates((int)$userId);
    } catch (Exception $e) {
        // Return cached rates as fallback to avoid crashing existing pages
        return $manager->loadCachedRates((int)$userId);
    }
}
?>
