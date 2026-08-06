<?php
require 'db.php';
$stmt = $pdo->query("SELECT * FROM karigor_kaj_receives");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo count($rows) . " rows found.\n";
foreach ($rows as $row) {
    echo "ID: {$row['id']}, Karigor: {$row['karigor_id']}, ProfitLess: {$row['total_profit_less']}\n";
}
?>
