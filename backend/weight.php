<?php
// 禁用輸出緩衝，確保錯誤信息能夠被記錄
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 定義預設值，確保即使環境變數載入失敗也能正常工作
$default_python_path = 'python';
$default_label_script_path = 'Label.py';
$default_weight_script_path = 'get_weight.py';

// 安全地讀取 .env 文件
function safe_parse_env_file($filepath) {
    $env_vars = [];
    
    // 檢查文件是否存在
    if (!file_exists($filepath)) {
        error_log("環境變數檔案不存在: " . $filepath);
        return $env_vars;
    }
    
    try {
        // 嘗試使用原生函數解析
        if (function_exists('parse_ini_file')) {
            $parsed = @parse_ini_file($filepath);
            if ($parsed !== false) {
                return $parsed;
            }
        }
        
        // 如果原生解析失敗，嘗試手動解析
        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // 跳過註釋行
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // 尋找鍵值對
            $parts = explode('=', $line, 2);
            if (count($parts) == 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                // 移除引號
                if (preg_match('/^("(.*)"|\'(.*)\')$/', $value, $matches)) {
                    $value = isset($matches[3]) ? $matches[3] : $matches[2];
                }
                
                if (!empty($key)) {
                    $env_vars[$key] = $value;
                }
            }
        }
    } catch (Exception $e) {
        error_log("解析環境變數檔案時發生錯誤: " . $e->getMessage());
    }
    
    return $env_vars;
}

// 讀取環境變數
$env_path = dirname(__DIR__) . '/.env';
$env = safe_parse_env_file($env_path);

// 設定變數，優先使用環境變數，若無則使用預設值
$python_path = isset($env['PYTHON_PATH']) && !empty($env['PYTHON_PATH']) 
    ? $env['PYTHON_PATH'] : $default_python_path;
    
$label_script_path = isset($env['SCRIPT_PATH_LABEL']) && !empty($env['SCRIPT_PATH_LABEL']) 
    ? $env['SCRIPT_PATH_LABEL'] : $default_label_script_path;
    
$weight_script_path = isset($env['SCRIPT_PATH_WEIGHT']) && !empty($env['SCRIPT_PATH_WEIGHT']) 
    ? $env['SCRIPT_PATH_WEIGHT'] : $default_weight_script_path;

// 定義常數
define('PYTHON_PATH', $python_path);
define('LABEL_SCRIPT_PATH', $label_script_path);
define('WEIGHT_SCRIPT_PATH', $weight_script_path);

// 檢查請求的動作類型
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getProductInfo':
        getProductInfo();
        break;
    case 'getWeight':
        getWeight();
        break;
    case 'saveData':
        saveData();
        break;
    default:
        sendResponse(false, '無效的請求');
        break;
}
// 獲取產品資訊
function getProductInfo() {
    require_once 'config.php';
    header('Content-Type: application/json');

    $barcode = $_GET['barcode'] ?? '';

    if (empty($barcode)) {
        sendResponse(false, '條碼編號不能為空');
        return;
    }

    try {
        // 使用原有的查詢獲取條碼資訊
        $sql = "SELECT pr.條碼編號, pr.工單號, pr.品名, pr.機台, pr.箱數, pr.班別, pr.顧車, 
                pr.重量, pr.單重, pr.數量, pr.檢驗人, 
                ud.料號, ud.交期, ud.工單數, ud.實際入庫,
                ud.產速, ud.台數, ud.日產量, ud.架機說明, ud.架機日期,
                ud.`機台(預)`, ud.利潤中心, ud.實際完成, ud.落後百分比
                FROM 生產紀錄表 pr
                LEFT JOIN uploaded_data ud ON BINARY pr.工單號 = BINARY ud.工單號
                WHERE pr.條碼編號 = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$barcode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // 如果目前的紀錄沒有單重，就去尋找最適合的歷史單重
            if (empty($result['單重']) || floatval($result['單重']) <= 0) {
                $productName = $result['品名'];
                $machine = $result['機台'];

                // 優先查詢相同機台、相同品名的最新一筆單重
                $prevSql = "SELECT 單重 
                           FROM 生產紀錄表 
                           WHERE 機台 = ? AND 品名 = ? AND 檢驗狀態 = 1 AND 單重 > 0 
                           ORDER BY 檢驗時間 DESC 
                           LIMIT 1";
                $prevStmt = $pdo->prepare($prevSql);
                $prevStmt->execute([$machine, $productName]);
                $prevResult = $prevStmt->fetch(PDO::FETCH_ASSOC);

                // 如果找不到，則放寬條件，查詢相同品名的最新一筆單重 (不限機台)
                if (!$prevResult || floatval($prevResult['單重']) <= 0) {
                    $prevSql = "SELECT 單重 
                               FROM 生產紀錄表 
                               WHERE 品名 = ? AND 檢驗狀態 = 1 AND 單重 > 0 
                               ORDER BY 檢驗時間 DESC 
                               LIMIT 1";
                    $prevStmt = $pdo->prepare($prevSql);
                    $prevStmt->execute([$productName]);
                    $prevResult = $prevStmt->fetch(PDO::FETCH_ASSOC);
                }
                
                if ($prevResult && floatval($prevResult['單重']) > 0) {
                    $result['前一筆單重'] = floatval($prevResult['單重']);
                }
            }
            
            // Get cumulative quantity
            $cumulSql = "SELECT SUM(數量) as 累計數量 FROM 生產紀錄表 WHERE 工單號 = ?";
            $cumulStmt = $pdo->prepare($cumulSql);
            $cumulStmt->execute([$result['工單號']]);
            $cumulResult = $cumulStmt->fetch(PDO::FETCH_ASSOC);
            $result['累計數量'] = $cumulResult['累計數量'] ?? 0;

            sendResponse(true, '查詢成功', $result);
        } else {
            // 原有的處理邏輯...
            sendResponse(false, '查無此條碼及相關工單號');
        }
    } catch (PDOException $e) {
        error_log('查詢產品資訊錯誤: ' . $e->getMessage());
        sendResponse(false, '查詢失敗: ' . $e->getMessage());
    }
}

// 獲取重量
function getWeight() {
    header('Content-Type: application/json');

    try {
        // 呼叫 Python 腳本獲取重量
        $command = '"' . PYTHON_PATH . '" "' . WEIGHT_SCRIPT_PATH . '"';
        
        // 記錄執行的命令以便調試
        error_log('Executing weight command: ' . $command);
        
        // 執行命令
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            error_log('Weight error: ' . implode("\n", $output));
            sendResponse(false, '獲取重量失敗，請手動輸入');
            return;
        }
        
        // 解析 Python 腳本返回的重量
        $weight = trim($output[0] ?? '0');
        
        // 確保返回的是有效數字
        if (!is_numeric($weight)) {
            sendResponse(false, '獲取的重量格式不正確');
            return;
        }
        
        sendResponse(true, '獲取重量成功', null, $weight);
    } catch (Exception $e) {
        error_log('獲取重量錯誤: ' . $e->getMessage());
        sendResponse(false, '系統錯誤: ' . $e->getMessage());
    }
}

// 儲存資料
function saveData() {
    require_once 'config.php';
    header('Content-Type: application/json');

    // 獲取傳入的 POST 資料
    $postData = json_decode(file_get_contents('php://input'), true);
    
    $barcode = $postData['barcode'] ?? '';
    $inspector = $postData['inspector'] ?? '';
    $weight = $postData['weight'] ?? 0;
    $unitWeight = $postData['unitWeight'] ?? 0;
    $quantity = $postData['quantity'] ?? 0;
    $status = $postData['status'] ?? '合格'; // 新增狀態參數，預設為合格
    
    // 驗證資料
    if (empty($barcode) || empty($inspector) || $weight <= 0 || $unitWeight <= 0 || $quantity <= 0) {
        sendResponse(false, '請填寫所有必要資訊');
        return;
    }

    try {
        // 開始事務
        $pdo->beginTransaction();
        
        // 判斷異常狀態 - 確保是整數值
        $isAbnormal = ($status === '異常') ? 1 : 0;
        
        // 記錄異常狀態以便調試
        error_log('異常狀態值: ' . $isAbnormal . ', 原始狀態: ' . $status);
        
        // 更新生產紀錄表，增加異常欄位
        $updateSql = "UPDATE 生產紀錄表 
                     SET 檢驗人 = ?, 重量 = ?, 單重 = ?, 數量 = ?, 檢驗時間 = NOW(), 檢驗狀態 = 1, 異常 = ?
                     WHERE 條碼編號 = ?";
        
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$inspector, $weight, $unitWeight, $quantity, $isAbnormal, $barcode]);
        
        if ($updateStmt->rowCount() === 0) {
            $pdo->rollBack();
            sendResponse(false, '找不到對應的條碼記錄');
            return;
        }
        
        // 查詢更新後的記錄，為打印標籤做準備
        $selectSql = "SELECT pr.條碼編號, pr.工單號, pr.品名, pr.機台, pr.箱數, pr.班別, pr.顧車,
                        pr.重量, pr.單重, pr.數量, pr.檢驗人, pr.異常, 
                        ud.料號, ud.交期,
                        '電' AS 後續單位
                      FROM 生產紀錄表 pr
                      LEFT JOIN uploaded_data ud ON BINARY pr.工單號 = BINARY ud.工單號
                      WHERE pr.條碼編號 = ?";
        
        $selectStmt = $pdo->prepare($selectSql);
        $selectStmt->execute([$barcode]);
        $record = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        // 提交事務
        $pdo->commit();
        
        // 再次確認異常狀態值
        $abnormalValue = $record['異常'] ?? $isAbnormal;
        error_log('從資料庫獲取的異常狀態: ' . $abnormalValue);
        
        // 呼叫 Python 腳本打印標籤
        $labelCommand = '"' . PYTHON_PATH . '" "' . LABEL_SCRIPT_PATH . '" ' . 
                         escapeshellarg($record['工單號']) . " " . 
                         escapeshellarg($record['品名']) . " " . 
                         escapeshellarg($record['料號'] ?? '') . " " . 
                         escapeshellarg($record['顧車']) . " " . 
                         escapeshellarg($record['機台']) . " " . 
                         escapeshellarg($record['重量']) . " " . 
                         escapeshellarg($record['數量']) . " " . 
                         escapeshellarg($record['檢驗人']) . " " . 
                         escapeshellarg($record['後續單位'] ?? '電') . " " .
                         escapeshellarg($record['班別'] ?? '日') . " " .
                         escapeshellarg($abnormalValue); // 使用確認過的異常狀態值
        
        // 記錄執行的命令以便調試
        error_log('Executing label command: ' . $labelCommand);
        
        // 執行命令
        exec($labelCommand, $labelOutput, $labelReturnVar);
        
        if ($labelReturnVar !== 0) {
            error_log('Label error: ' . implode("\n", $labelOutput));
            sendResponse(true, '資料已儲存，但標籤列印失敗', $record);
            return;
        }
        
        sendResponse(true, '資料儲存成功，標籤已列印', $record);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('儲存資料錯誤: ' . $e->getMessage());
        sendResponse(false, '儲存失敗: ' . $e->getMessage());
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('系統錯誤: ' . $e->getMessage());
        sendResponse(false, '系統錯誤: ' . $e->getMessage());
    }
}

// 通用響應函數
function sendResponse($success, $message, $data = null, $weight = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($weight !== null) {
        $response['weight'] = $weight;
    }
    
    echo json_encode($response);
    exit;
}
?>