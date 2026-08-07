<?php
$host = 'localhost';
$db   = 'kinder_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$pdo = new PDO($dsn, $user, $pass);
$stmt = $pdo->query("SELECT id, email, name, is_admin FROM users");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
