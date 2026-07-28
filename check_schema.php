<?php
$host = 'localhost';
$db   = 'kinder_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$stmt = $pdo->query("DESCRIBE kaj_items");
$cols = $stmt->fetchAll();
foreach ($cols as $c) {
    echo $c['Field'] . " - " . $c['Type'] . "\n";
}
