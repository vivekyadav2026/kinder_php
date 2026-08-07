<?php
$content = file_get_contents('c:/xampp/htdocs/kinder_php/db.php');
$lines = explode("\n", $content);
$newLines = [];
foreach ($lines as $idx => $line) {
    if ($idx < 296) {
        $newLines[] = $line;
    }
}
$tail = <<<'EOD'
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();

        $ratesConfig = refreshRatesIfNeeded($pdo, $userId);
        $rate24k = $ratesConfig['rate_24k'];
        $rate22k = $ratesConfig['rate_22k'];
        $rateAg = $ratesConfig['rate_ag'];

        $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row && $row['user_type'] === 'admin') {
            $isAdmin = true;
        } elseif ($row && $row['user_type'] === 'read_only') {
            $isReadOnly = true;
        }
    }

    // Global helper functions
    function isSettled($pdo, $bapariId, $userId, $date) {
        $stmt = $pdo->prepare("SELECT MAX(settlement_date) as last_settle FROM ledger_settlements WHERE bapari_id = ? AND user_id = ?");
        $stmt->execute([$bapariId, $userId]);
        $lastSettle = $stmt->fetch()['last_settle'];
        return ($lastSettle && $date <= $lastSettle);
    }
    
    function isKarigorSettled($pdo, $karigorId, $userId, $date) {
        $stmt = $pdo->prepare("SELECT MAX(settlement_date) as last_settle FROM karigor_ledger_settlements WHERE karigor_id = ? AND user_id = ?");
        $stmt->execute([$karigorId, $userId]);
        $lastSettle = $stmt->fetch()['last_settle'];
        return ($lastSettle && $date <= $lastSettle);
    }
?>
EOD;

file_put_contents('c:/xampp/htdocs/kinder_php/db.php', implode("\n", $newLines) . "\n" . $tail);
?>
