<?php
// 禁用輸出緩衝，確保錯誤信息能夠被記錄
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 讀取 .env 檔案
$env_path = __DIR__ . '/../.env';
$env = file_exists($env_path) ? parse_ini_file($env_path) : [];

// 輸出環境變數
echo "<h1>環境變數測試</h1>";
echo "<p>ENV 檔案路徑: {$env_path}</p>";
echo "<p>檔案存在: " . (file_exists($env_path) ? '是' : '否') . "</p>";

if (file_exists($env_path)) {
    echo "<h2>ENV 內容:</h2>";
    echo "<pre>";
    print_r($env);
    echo "</pre>";

    // 測試關鍵變數
    echo "<h2>關鍵變數:</h2>";
    echo "<p>PYTHON_PATH: " . ($env['PYTHON_PATH'] ?? '未設定') . "</p>";
    echo "<p>SCRIPT_PATH_BARCODE: " . ($env['SCRIPT_PATH_BARCODE'] ?? '未設定') . "</p>";

    // 檢查檔案是否存在
    if (isset($env['PYTHON_PATH'])) {
        echo "<p>PYTHON_PATH 檔案存在: " . (file_exists($env['PYTHON_PATH']) ? '是' : '否') . "</p>";
    }

    if (isset($env['SCRIPT_PATH_BARCODE'])) {
        echo "<p>SCRIPT_PATH_BARCODE 檔案存在: " . (file_exists($env['SCRIPT_PATH_BARCODE']) ? '是' : '否') . "</p>";
    }
}
?>
