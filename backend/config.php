<?php
// 確保沒有輸出到響應
error_reporting(E_ALL);
ini_set('display_errors', 0); // 關閉錯誤顯示，改為捕獲

$host = "localhost";
$dbname = "excel_manager";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // 確保錯誤以JSON格式返回
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "資料庫連線失敗", "error" => $e->getMessage()]);
    exit;
}
?>