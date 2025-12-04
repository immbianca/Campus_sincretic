<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "campus_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Eroare conexiune DB: " . $e->getMessage());
}
?>
