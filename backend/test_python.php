<?php
// test_get_weight.php - 用於測試 get_weight.py 的執行情況

// 啟用錯誤顯示，方便調試
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 設定頁面 content type
header('Content-Type: text/html; charset=utf-8');

echo "<h1>測試 get_weight.py 執行情況</h1>";

// 讀取 .env 檔案
$env_path = __DIR__ . '/../.env';
echo "<h2>環境變數檔案路徑</h2>";
echo "<p>" . $env_path . " (" . (file_exists($env_path) ? "存在" : "不存在") . ")</p>";

// 讀取環境變數
$env = [];
if (file_exists($env_path)) {
    $env_content = file_get_contents($env_path);
    echo "<h2>環境變數檔案內容</h2>";
    echo "<pre>" . htmlspecialchars($env_content) . "</pre>";
    
    $env = parse_ini_file($env_path);
    echo "<h2>解析後的環境變數</h2>";
    echo "<pre>" . print_r($env, true) . "</pre>";
} else {
    echo "<p style='color: red;'>環境變數檔案不存在！</p>";
}

// 取得 Python 和腳本路徑
$python_path = $env['PYTHON_PATH'] ?? 'python';
$weight_script_path = $env['SCRIPT_PATH_WEIGHT'] ?? 'get_weight.py';

echo "<h2>Python 和腳本路徑</h2>";
echo "<p>Python 路徑: " . htmlspecialchars($python_path) . "</p>";
echo "<p>腳本路徑: " . htmlspecialchars($weight_script_path) . "</p>";

// 檢查腳本檔案是否存在
$script_exists = file_exists($weight_script_path);
echo "<p>腳本檔案 " . ($script_exists ? "存在" : "不存在") . "</p>";

if ($script_exists) {
    echo "<h3>腳本檔案內容</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents($weight_script_path)) . "</pre>";
}

// 測試執行 get_weight.py
echo "<h2>執行測試</h2>";

// 測試 1: 使用 exec
echo "<h3>方法 1: 使用 exec</h3>";
$command = '"' . $python_path . '" "' . $weight_script_path . '"';
echo "<p>執行命令: " . htmlspecialchars($command) . "</p>";

$output = [];
$return_var = 0;
exec($command . " 2>&1", $output, $return_var);

echo "<p>返回碼: " . $return_var . "</p>";
echo "<p>輸出結果:</p>";
echo "<pre>" . implode("\n", array_map('htmlspecialchars', $output)) . "</pre>";

// 測試 2: 使用 shell_exec
echo "<h3>方法 2: 使用 shell_exec</h3>";
$shell_output = shell_exec($command . " 2>&1");
echo "<p>輸出結果:</p>";
echo "<pre>" . htmlspecialchars($shell_output) . "</pre>";

// 測試 3: 使用 proc_open 獲取更詳細的資訊
echo "<h3>方法 3: 使用 proc_open (最詳細)</h3>";
$descriptors = [
    0 => ["pipe", "r"],  // stdin
    1 => ["pipe", "w"],  // stdout
    2 => ["pipe", "w"]   // stderr
];

$process = proc_open($command, $descriptors, $pipes);

if (is_resource($process)) {
    // 關閉標準輸入
    fclose($pipes[0]);
    
    // 讀取標準輸出
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    
    // 讀取標準錯誤
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    
    // 獲取程序退出狀態
    $status = proc_close($process);
    
    echo "<p>退出狀態: " . $status . "</p>";
    echo "<p>標準輸出:</p>";
    echo "<pre>" . htmlspecialchars($stdout) . "</pre>";
    echo "<p>標準錯誤:</p>";
    echo "<pre>" . htmlspecialchars($stderr) . "</pre>";
    
    // 解析輸出為 JSON (如果可能)
    if (!empty($stdout)) {
        echo "<h3>嘗試將輸出解析為 JSON</h3>";
        $json_output = @json_decode($stdout, true);
        if ($json_output !== null) {
            echo "<pre>" . htmlspecialchars(json_encode($json_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        } else {
            echo "<p>無法解析為 JSON</p>";
        }
    }
} else {
    echo "<p style='color: red;'>無法啟動程序</p>";
}

// 判斷執行結果
echo "<h2>執行結果分析</h2>";
if ($return_var === 0 && !empty($output) && is_numeric(trim($output[0]))) {
    echo "<p style='color: green;'>測試成功！獲取到重量值: " . htmlspecialchars(trim($output[0])) . "</p>";
} else {
    echo "<p style='color: red;'>測試失敗！未能獲取有效的重量值</p>";
    
    // 分析可能的問題
    if ($return_var !== 0) {
        echo "<p>Python 腳本執行錯誤 (返回碼: " . $return_var . ")</p>";
    }
    
    if (empty($output)) {
        echo "<p>沒有輸出結果</p>";
    } elseif (!is_numeric(trim($output[0]))) {
        echo "<p>輸出不是有效的數字: " . htmlspecialchars(trim($output[0])) . "</p>";
    }
    
    // 常見問題建議
    echo "<h3>可能的問題和解決方案</h3>";
    echo "<ul>";
    echo "<li>Python 路徑不正確: 確認 PYTHON_PATH 環境變數是否正確設置</li>";
    echo "<li>腳本路徑不正確: 確認 SCRIPT_PATH_WEIGHT 環境變數是否正確設置</li>";
    echo "<li>Python 腳本權限問題: 確保 PHP 有執行該腳本的權限</li>";
    echo "<li>Python 腳本依賴問題: 確認所需的 Python 套件是否已安裝</li>";
    echo "<li>設備連接問題: 檢查秤重設備是否正確連接和配置</li>";
    echo "</ul>";
}

// 完善 getWeight 函數的建議
echo "<h2>改進 getWeight 函數的建議</h2>";
echo "<pre>";
echo htmlspecialchars('// 獲取重量
function getWeight() {
    header(\'Content-Type: application/json\');

    try {
        // 呼叫 Python 腳本獲取重量
        $command = \'"' . PYTHON_PATH . '" "' . WEIGHT_SCRIPT_PATH . '"\';
        
        // 記錄執行的命令以便調試
        error_log(\'Executing weight command: \' . $command);
        
        // 執行命令並捕獲標準輸出和錯誤
        $descriptors = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];
        
        $process = proc_open($command, $descriptors, $pipes);
        
        if (!is_resource($process)) {
            error_log(\'Failed to execute command\');
            sendResponse(false, \'無法執行命令\');
            return;
        }
        
        // 關閉標準輸入
        fclose($pipes[0]);
        
        // 讀取標準輸出
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        
        // 讀取標準錯誤
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        
        // 獲取程序退出狀態
        $status = proc_close($process);
        
        // 記錄完整輸出
        error_log(\'Command stdout: \' . $stdout);
        error_log(\'Command stderr: \' . $stderr);
        error_log(\'Command status: \' . $status);
        
        // 檢查執行結果
        if ($status !== 0) {
            error_log(\'Weight error: \' . $stderr);
            sendResponse(false, \'獲取重量失敗: \' . $stderr);
            return;
        }
        
        // 解析 Python 腳本返回的重量
        $weight = trim($stdout);
        
        // 確保返回的是有效數字
        if (!is_numeric($weight)) {
            error_log(\'獲取的重量格式不正確: \' . $weight);
            sendResponse(false, \'獲取的重量格式不正確\');
            return;
        }
        
        sendResponse(true, \'獲取重量成功\', null, $weight);
    } catch (Exception $e) {
        error_log(\'獲取重量錯誤: \' . $e->getMessage());
        sendResponse(false, \'系統錯誤: \' . $e->getMessage());
    }
}');
echo "</pre>";

// 顯示錯誤日誌位置提示
$error_log_path = ini_get('error_log');
echo "<h2>PHP 錯誤日誌位置</h2>";
echo "<p>" . ($error_log_path ? htmlspecialchars($error_log_path) : "未設定，請檢查 php.ini") . "</p>";

?>