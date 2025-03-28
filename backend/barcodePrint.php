<?php
// 禁用輸出緩衝，確保錯誤信息能夠被記錄
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 讀取 .env 檔案
$env_path = __DIR__ . '/../.env';
$env = file_exists($env_path) ? parse_ini_file($env_path) : [];

// 定義 Python 執行環境和腳本的絕對路徑
define('PYTHON_PATH', $env['PYTHON_PATH'] ?? 'python');
define('BARCODE_SCRIPT_PATH', $env['SCRIPT_PATH_BARCODE'] ?? 'barcode.py');

// 檢查請求的動作類型
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_work_orders':
        getWorkOrders();
        break;
    case 'save_work_order':
        saveWorkOrder();
        break;
    case 'get_machine_status':
        getMachineStatus();
        break;
    case 'update_machine':
        updateMachine();
        break;
    case 'print_box_count':
        print_box_count();
        break;
    case 'reprint_barcode':
        reprintBarcode();
        break;
    case 'check_barcode':
        checkBarcode();
        break;
    default:
        getCarData();
        break;
}

// 獲取所有機台狀態的函數
function getMachineStatus() {
    require_once 'config.php';
    header('Content-Type: application/json');

    try {
        $sql = "SELECT 代碼 as id, 狀態 as status_name FROM 機台狀態 ORDER BY 代碼";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($statuses);
    } catch (PDOException $e) {
        error_log('機台狀態查詢失敗: ' . $e->getMessage());
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()]);
    }
}

function getCarData() {
    require_once 'config.php';
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');

    try {
        // 調試信息：記錄函數執行開始
        error_log('getCarData 函數開始執行');

        // 1. 獲取機台看板的基礎數據 - 移除顧車和班別的子查詢
        $sql = "SELECT md.機台 as 車台號, md.狀態, md.工單號, ms.狀態 as 狀態名稱
                FROM 機台看板 md 
                LEFT JOIN 機台狀態 ms ON md.狀態 = ms.代碼
                WHERE md.狀態 = '1'  /* 僅選取狀態為1的記錄 */
                ORDER BY md.機台";
        
        error_log('執行SQL查詢: ' . $sql);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $dashboardData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('查詢返回 ' . count($dashboardData) . ' 筆記錄');

        // 如果沒有找到記錄，返回空數組
        if (empty($dashboardData)) {
            error_log('無機台看板數據，返回空數組');
            echo json_encode([]);
            return;
        }

        // 2. 獲取預排工單信息
        $sql = "SELECT `機台(預)`, 工單號, 架機日期, 品名 
                FROM uploaded_data 
                WHERE `機台(預)` IS NOT NULL AND `機台(預)` <> '' 
                ORDER BY 架機日期 DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $scheduledData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. 獲取工單和品名對應信息
        $sql = "SELECT 工單號, 品名 FROM uploaded_data WHERE 工單號 IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $workOrderData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $workOrderMap = [];
        foreach ($workOrderData as $wo) {
            $workOrderMap[$wo['工單號']] = $wo['品名'];
        }

        // 4. 獲取圖號表信息
        $sql = "SELECT 品名, 圖號, 規格, 材料外徑, 材質, 只_2_5M FROM 圖號表";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $drawingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $drawingMap = [];
        foreach ($drawingData as $drawing) {
            $drawingMap[$drawing['品名']] = [
                '圖號' => $drawing['圖號'],
                '規格' => $drawing['規格'],
                '材料外徑' => $drawing['材料外徑'],
                '材質' => $drawing['材質'],
                '只_2_5M' => $drawing['只_2_5M']
            ];
        }

        // 整理數據結構 - 移除從數據庫獲取雇車和班別
        $result = [];
        foreach ($dashboardData as $dashboard) {
            $carId = $dashboard['車台號'];
            $workOrderId = $dashboard['工單號'];
            $productName = isset($workOrderMap[$workOrderId]) ? $workOrderMap[$workOrderId] : "";
            $drawingInfo = isset($drawingMap[$productName]) ? $drawingMap[$productName] : null;
            
            $result[$carId] = [
                'car' => $carId,
                'currentStatus' => $dashboard['狀態'] ?: 'B',
                'currentStatusName' => $dashboard['狀態名稱'] ?: '待排程',
                'currentWorkOrder' => $workOrderId,
                'productName' => $productName,
                'drawingInfo' => $drawingInfo,
                // 移除從數據庫獲取雇車和班別，前端會設置
                'operator' => '',
                'shift' => '',
                'scheduledOrders' => []
            ];
        }

        // 處理預排工單
        foreach ($scheduledData as $scheduled) {
            $machines = explode(',', $scheduled['機台(預)']);
            foreach ($machines as $machine) {
                $machine = trim($machine);
                if (isset($result[$machine])) {
                    $result[$machine]['scheduledOrders'][] = [
                        'workOrder' => $scheduled['工單號'],
                        'installDate' => $scheduled['架機日期'],
                        'productName' => $scheduled['品名']
                    ];
                }
            }
        }

        // 為每個機台的預排工單按照架機日期降序排序
        foreach ($result as &$carData) {
            usort($carData['scheduledOrders'], function($a, $b) {
                return strtotime($b['installDate']) - strtotime($a['installDate']);
            });
        }

        // 確保輸出為數組格式
        $outputArray = array_values($result);
        
        // 記錄輸出結果
        error_log('輸出數組數量: ' . count($outputArray));
        if (count($outputArray) > 0) {
            error_log('輸出數據結構: ' . json_encode(array_keys($outputArray[0] ?? [])));
        }
        
        // 確保不會有其他輸出，然後輸出JSON
        ob_clean(); // 清除任何已有的輸出緩衝
        echo json_encode($outputArray);

    } catch (PDOException $e) {
        error_log('數據庫錯誤: ' . $e->getMessage());
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()]);
    } catch (Exception $e) {
        error_log('一般錯誤: ' . $e->getMessage());
        echo json_encode(['error' => '處理失敗: ' . $e->getMessage()]);
    }
}

// 獲取所有工單的函數
function getWorkOrders() {
    // 引入數據庫連接配置
    require_once 'config.php';

    // 設置回應類型
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');

    try {
        // 查詢工單資料
        $sql = "SELECT 工單號, 品名 FROM uploaded_data WHERE 工單號 IS NOT NULL AND 工單號 <> '' GROUP BY 工單號, 品名 ORDER BY 工單號";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 返回工單資料
        echo json_encode($workOrders);
        
    } catch (PDOException $e) {
        error_log('獲取工單錯誤: ' . $e->getMessage());
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()]);
    }
}

// 修改保存工單的函數
function saveWorkOrder() {
    require_once 'config.php';
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['car']) || !isset($data['status'])) {
        echo json_encode(['success' => false, 'error' => '缺少必要參數']);
        return;
    }

    try {
        $sql = "UPDATE 機台看板 
                SET 狀態 = ?, 工單號 = ?
                WHERE 機台 = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['status'],
            $data['workOrder'] ?? null,
            $data['car']
        ]);

        echo json_encode([
            'success' => true,
            'message' => '更新成功'
        ]);

    } catch (PDOException $e) {
        error_log('保存工單錯誤: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => '更新失敗: ' . $e->getMessage()
        ]);
    }
}

// 更新機台信息的函數
function updateMachine() {
    require_once 'config.php';
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['car'])) {
        echo json_encode(['success' => false, 'error' => '缺少必要參數']);
        return;
    }

    try {
        // 移除更新箱數的功能，因為現在箱數由生產紀錄表決定
        echo json_encode([
            'success' => true,
            'message' => '更新成功'
        ]);

    } catch (PDOException $e) {
        error_log('更新機台錯誤: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => '更新失敗: ' . $e->getMessage()
        ]);
    }
}

// 依照指定箱數列印條碼功能 - 先更新資料庫再列印
function print_box_count() {
    // 1. 連接資料庫並設置回應頭
    require_once 'config.php';
    header('Content-Type: application/json');

    // 2. 接收前端傳來的資料
    $data = json_decode(file_get_contents('php://input'), true);

    // 3. 檢查必要參數
    if (!isset($data['car']) || !isset($data['workOrder']) || !isset($data['date']) || !isset($data['boxCount'])) {
        echo json_encode(['success' => false, 'error' => '缺少必要參數']);
        return;
    }

    try {
        // 4. 準備資料
        $car = $data['car'];
        $workOrder = $data['workOrder'];
        $operator = $data['operator'] ?? '';
        $shift = $data['shift'] ?? '日';
        $shiftNumber = $data['shiftNumber'] ?? '1';
        $productName = $data['productName'] ?? '';
        $date = $data['date'] ?? date('Ymd');
        $boxCount = intval($data['boxCount']);
        $nextUnit = $data['nextUnit'] ?? '電';
        
        // 驗證箱數範圍
        if ($boxCount < 1) $boxCount = 1;
        if ($boxCount > 3) $boxCount = 3;
        
        // 5. 開始資料庫事務
        $pdo->beginTransaction();
        $barcodeIds = [];
        
        // 6. 先處理所有資料庫操作
        for ($boxNum = 1; $boxNum <= $boxCount; $boxNum++) {
            $boxNumber = str_pad($boxNum, 2, '0', STR_PAD_LEFT);
            
            // 7. 構建條碼編號
            $barcodeId = $date . $workOrder . $car . $shiftNumber . $boxNumber;
            $barcodeIds[] = [
                'id' => $barcodeId,
                'boxNumber' => $boxNumber
            ];
            
            // 8. 檢查條碼是否已存在，如果存在則更新
            $checkSql = "SELECT 條碼編號 FROM 生產紀錄表 WHERE 條碼編號 = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$barcodeId]);
            
            if ($checkStmt->rowCount() == 0) {
                // 條碼不存在，新增記錄
                // 檢查是否需要新增後續單位欄位
                $columnExists = false;
                try {
                    $columnCheck = $pdo->query("SHOW COLUMNS FROM 生產紀錄表 LIKE '後續單位'");
                    $columnExists = ($columnCheck->rowCount() > 0);
                } catch (PDOException $e) {
                    error_log('檢查後續單位欄位錯誤: ' . $e->getMessage());
                }
                
                if ($columnExists) {
                    // 資料表已有後續單位欄位
                    $insertSql = "INSERT INTO 生產紀錄表 (條碼編號, 工單號, 品名, 機台, 箱數, 顧車, 班別, 後續單位) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $pdo->prepare($insertSql);
                    $insertStmt->execute([$barcodeId, $workOrder, $productName, $car, $boxNumber, $operator, $shift, $nextUnit]);
                } else {
                    // 資料表尚未新增後續單位欄位，先執行ALTER TABLE
                    try {
                        $alterSql = "ALTER TABLE 生產紀錄表 ADD COLUMN 後續單位 VARCHAR(50) DEFAULT '電' AFTER 班別";
                        $pdo->exec($alterSql);
                        error_log('已新增後續單位欄位到生產紀錄表');
                        
                        // 然後插入記錄
                        $insertSql = "INSERT INTO 生產紀錄表 (條碼編號, 工單號, 品名, 機台, 箱數, 顧車, 班別, 後續單位) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $insertStmt = $pdo->prepare($insertSql);
                        $insertStmt->execute([$barcodeId, $workOrder, $productName, $car, $boxNumber, $operator, $shift, $nextUnit]);
                    } catch (PDOException $e) {
                        // 如果無法新增欄位，則使用原始SQL
                        error_log('新增後續單位欄位失敗: ' . $e->getMessage());
                        $insertSql = "INSERT INTO 生產紀錄表 (條碼編號, 工單號, 品名, 機台, 箱數, 顧車, 班別) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $insertStmt = $pdo->prepare($insertSql);
                        $insertStmt->execute([$barcodeId, $workOrder, $productName, $car, $boxNumber, $operator, $shift]);
                    }
                }
            } else {
                // 條碼已存在，更新記錄
                // 檢查是否有後續單位欄位
                $columnExists = false;
                try {
                    $columnCheck = $pdo->query("SHOW COLUMNS FROM 生產紀錄表 LIKE '後續單位'");
                    $columnExists = ($columnCheck->rowCount() > 0);
                } catch (PDOException $e) {
                    error_log('檢查後續單位欄位錯誤: ' . $e->getMessage());
                }
                
                if ($columnExists) {
                    // 更新包含後續單位
                    $updateSql = "UPDATE 生產紀錄表 
                                SET 品名 = ?, 顧車 = ?, 班別 = ?, 後續單位 = ? 
                                WHERE 條碼編號 = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([$productName, $operator, $shift, $nextUnit, $barcodeId]);
                } else {
                    // 不包含後續單位
                    $updateSql = "UPDATE 生產紀錄表 
                                SET 品名 = ?, 顧車 = ?, 班別 = ? 
                                WHERE 條碼編號 = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([$productName, $operator, $shift, $barcodeId]);
                }
            }
        }
        
        // 9. 提交資料庫事務 - 無論後續列印是否成功，資料庫變更都會保存
        $pdo->commit();
        
        // 10. 開始執行列印
        $printErrors = [];
        
        foreach ($barcodeIds as $barcode) {
            // 執行列印命令
            $command = '"' . PYTHON_PATH . '" "' . BARCODE_SCRIPT_PATH . '" ' . 
                      escapeshellarg($barcode['id']) . " " . 
                      escapeshellarg($workOrder) . " " . 
                      escapeshellarg($productName) . " " . 
                      escapeshellarg($operator) . " " . 
                      escapeshellarg($car) . " " . 
                      escapeshellarg($barcode['boxNumber']) . " " . 
                      escapeshellarg($shift);
            
            // 記錄執行的命令以便調試
            error_log('Executing command: ' . $command);
            
            // 執行命令
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                $printErrors[] = "箱號 {$barcode['boxNumber']} 列印失敗: " . implode("\n", $output);
                error_log('Print error: ' . implode("\n", $output));
            }
        }
        
        // 11. 檢查是否有列印錯誤
        if (!empty($printErrors)) {
            // 即使有列印錯誤，資料庫更新也已經完成
            echo json_encode([
                'success' => false, 
                'error' => implode("\n", $printErrors),
                'dataUpdated' => true, // 指示資料庫已更新
                'message' => "資料庫更新成功，但列印失敗。"
            ]);
            return;
        }
        
        // 全部成功
        echo json_encode([
            'success' => true, 
            'message' => "批量列印成功，共 {$boxCount} 張標籤"
        ]);
        
    } catch (PDOException $e) {
        // 資料庫操作錯誤時回滾事務
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('資料庫錯誤: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => '資料庫處理失敗: ' . $e->getMessage()]);
    } catch (Exception $e) {
        // 其他錯誤，如果資料庫事務已提交，不需要回滾
        error_log('處理錯誤: ' . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => '處理失敗: ' . $e->getMessage(),
            'dataUpdated' => !$pdo->inTransaction() // 如果不在事務中，表示資料已更新
        ]);
    }
}

// 條碼重印功能
function reprintBarcode() {
    require_once 'config.php';
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['car']) || !isset($data['workOrder']) || !isset($data['boxNumber'])) {
        echo json_encode(['success' => false, 'error' => '缺少必要參數']);
        return;
    }

    try {
        $car = $data['car'];
        $workOrder = $data['workOrder'];
        $boxNumber = $data['boxNumber'];
        $productName = $data['productName'] ?? '';
        $operator = $data['operator'] ?? '';
        $shift = $data['shift'] ?? '日';
        $date = $data['date'] ?? date('Ymd');
        $shiftNumber = $data['shiftNumber'] ?? '1';
        $nextUnit = $data['nextUnit'] ?? '電';
        
        // 構建條碼編號
        $barcodeId = $date . $workOrder . $car . $shiftNumber . $boxNumber;
        
        // 檢查新格式條碼是否存在
        $checkNewSql = "SELECT * FROM 生產紀錄表 WHERE 條碼編號 = ?";
        $checkNewStmt = $pdo->prepare($checkNewSql);
        $checkNewStmt->execute([$barcodeId]);
        
        // 如果新格式條碼不存在，檢查舊格式
        if ($checkNewStmt->rowCount() == 0) {
            // 檢查舊格式條碼
            $oldBarcodeId = $workOrder . $car . $boxNumber;
            $checkOldSql = "SELECT * FROM 生產紀錄表 WHERE 條碼編號 = ?";
            $checkOldStmt = $pdo->prepare($checkOldSql);
            $checkOldStmt->execute([$oldBarcodeId]);
            
            if ($checkOldStmt->rowCount() == 0) {
                // 兩種格式都不存在，嘗試創建新記錄
                $columnExists = false;
                try {
                    $columnCheck = $pdo->query("SHOW COLUMNS FROM 生產紀錄表 LIKE '後續單位'");
                    $columnExists = ($columnCheck->rowCount() > 0);
                } catch (PDOException $e) {
                    error_log('檢查後續單位欄位錯誤: ' . $e->getMessage());
                }
                
                if ($columnExists) {
                    $insertSql = "INSERT INTO 生產紀錄表 (條碼編號, 工單號, 品名, 機台, 箱數, 顧車, 班別, 後續單位) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $pdo->prepare($insertSql);
                    $insertStmt->execute([$barcodeId, $workOrder, $productName, $car, $boxNumber, $operator, $shift, $nextUnit]);
                } else {
                    $insertSql = "INSERT INTO 生產紀錄表 (條碼編號, 工單號, 品名, 機台, 箱數, 顧車, 班別) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $pdo->prepare($insertSql);
                    $insertStmt->execute([$barcodeId, $workOrder, $productName, $car, $boxNumber, $operator, $shift]);
                }
            } else {
                // 使用舊格式條碼
                $barcodeId = $oldBarcodeId;
                
                // 更新舊記錄
                $columnExists = false;
                try {
                    $columnCheck = $pdo->query("SHOW COLUMNS FROM 生產紀錄表 LIKE '後續單位'");
                    $columnExists = ($columnCheck->rowCount() > 0);
                } catch (PDOException $e) {
                    error_log('檢查後續單位欄位錯誤: ' . $e->getMessage());
                }
                
                if ($columnExists) {
                    $updateSql = "UPDATE 生產紀錄表 
                                SET 品名 = ?, 顧車 = ?, 班別 = ?, 後續單位 = ? 
                                WHERE 條碼編號 = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([$productName, $operator, $shift, $nextUnit, $barcodeId]);
                } else {
                    $updateSql = "UPDATE 生產紀錄表 
                                SET 品名 = ?, 顧車 = ?, 班別 = ? 
                                WHERE 條碼編號 = ?";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([$productName, $operator, $shift, $barcodeId]);
                }
            }
        } else {
            // 更新記錄信息
            $columnExists = false;
            try {
                $columnCheck = $pdo->query("SHOW COLUMNS FROM 生產紀錄表 LIKE '後續單位'");
                $columnExists = ($columnCheck->rowCount() > 0);
            } catch (PDOException $e) {
                error_log('檢查後續單位欄位錯誤: ' . $e->getMessage());
            }
            
            if ($columnExists) {
                $updateSql = "UPDATE 生產紀錄表 
                            SET 品名 = ?, 顧車 = ?, 班別 = ?, 後續單位 = ? 
                            WHERE 條碼編號 = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$productName, $operator, $shift, $nextUnit, $barcodeId]);
            } else {
                $updateSql = "UPDATE 生產紀錄表 
                            SET 品名 = ?, 顧車 = ?, 班別 = ? 
                            WHERE 條碼編號 = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$productName, $operator, $shift, $barcodeId]);
            }
        }
        
        // 無論如何，使用當前的條碼ID列印
        $command = '"' . PYTHON_PATH . '" "' . BARCODE_SCRIPT_PATH . '" ' . 
                  escapeshellarg($barcodeId) . " " . 
                  escapeshellarg($workOrder) . " " . 
                  escapeshellarg($productName) . " " . 
                  escapeshellarg($operator) . " " . 
                  escapeshellarg($car) . " " . 
                  escapeshellarg($boxNumber) . " " . 
                  escapeshellarg($shift);
        
        // 記錄執行的命令以便調試
        error_log('Executing reprint command: ' . $command);
        
        exec($command, $output, $returnVar);
                
        if ($returnVar !== 0) {
            error_log('Reprint error: ' . implode("\n", $output));
            echo json_encode([
                'success' => false, 
                'error' => '重印失敗: ' . implode("\n", $output),
                'dataUpdated' => true
            ]);
            return;
        }
        
        echo json_encode(['success' => true, 'message' => '重印成功']);
        
    } catch (PDOException $e) {
        error_log('重印條碼錯誤: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => '處理失敗: ' . $e->getMessage()]);
    }
}

// 檢查條碼是否已存在
function checkBarcode() {
    require_once 'config.php';
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['car']) || !isset($data['workOrder'])) {
        echo json_encode(['success' => false, 'error' => '缺少必要參數']);
        return;
    }

    try {
        $car = $data['car'];
        $workOrder = $data['workOrder'];
        $date = $data['date'] ?? date('Ymd');
        $shift = $data['shift'] ?? '1'; // 班別數字
        
        // 檢查舊格式條碼
        $oldPrefix = $workOrder . $car;
        $oldSql = "SELECT 箱數 FROM 生產紀錄表 WHERE 條碼編號 LIKE ? ORDER BY 箱數 ASC";
        $oldStmt = $pdo->prepare($oldSql);
        $oldStmt->execute([$oldPrefix . '%']);
        $oldBoxes = $oldStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 檢查新格式條碼
        $newPrefix = $date . $workOrder . $car . $shift;
        $newSql = "SELECT 箱數 FROM 生產紀錄表 WHERE 條碼編號 LIKE ? ORDER BY 箱數 ASC";
        $newStmt = $pdo->prepare($newSql);
        $newStmt->execute([$newPrefix . '%']);
        $newBoxes = $newStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 合併兩種格式找到的箱數
        $existingBoxes = array_merge($oldBoxes, $newBoxes);
        
        // 去重
        $existingBoxes = array_unique($existingBoxes);
        
        // 將箱數轉換為整數
        $boxNumbers = array_map('intval', $existingBoxes);
        
        // 排序
        sort($boxNumbers);
        
        echo json_encode([
            'success' => true,
            'exists' => !empty($boxNumbers),
            'existingBoxes' => $boxNumbers
        ]);
        
    } catch (PDOException $e) {
        error_log('檢查條碼錯誤: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => '檢查失敗: ' . $e->getMessage()
        ]);
    }
}
?>