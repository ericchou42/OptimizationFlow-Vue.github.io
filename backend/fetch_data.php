<?php
require 'config.php';

header('Content-Type: application/json');
$stmt = $pdo->query("SELECT * FROM uploaded_data ORDER BY created_at DESC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
