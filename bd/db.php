<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");

// ---------------- HELPER ----------------
function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
