<?php
// 禁用輸出緩衝，確保錯誤信息能夠被記錄
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 定義 Python 執行環境和腳本的絕對路徑
define('PYTHON_PATH', 'D:/我的文件/Documents/OptimizationFlow-Vue/.venv/Scripts/python.exe');
define('BARCODE_SCRIPT_PATH', 'D:/我的文件/Documents/OptimizationFlow-Vue/barcode.py');

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
    case 'print_barcode':
        printBarcode();
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
        $sql = "SELECT md.機台 as 車台號, md.狀態, md.工單號, md.箱數 as 箱數, ms.狀態 as 狀態名稱
                FROM 機台看板 md 
                LEFT JOIN 機台狀態 ms ON md.狀態 = ms.代碼
                WHERE md.狀態 = 'D'  /* 僅選取狀態為D的記錄 */
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
            
            // 確保箱數是數字並轉為字符串
            $boxCount = isset($dashboard['箱數']) ? intval($dashboard['箱數']) : 0;
            $boxCountStr = str_pad($boxCount, 2, '0', STR_PAD_LEFT);
            
            $result[$carId] = [
                'car' => $carId,
                'currentStatus' => $dashboard['狀態'] ?: 'B',
                'currentStatusName' => $dashboard['狀態名稱'] ?: '待排程',
                'currentWorkOrder' => $workOrderId,
                'productName' => $productName,
                'drawingInfo' => $drawingInfo,
                'boxCount' => $boxCountStr,
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
        error_log('輸出數據結構: ' . json_encode(array_keys($outputArray[0] ?? [])));
        
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

// 獲取實際工單號和狀態
function getActualWorkOrder($car, $pdo) {
    try {
        // 檢查表是否存在
        $checkTableSql = "SHOW TABLES LIKE 'OProduction_Schedule'";
        $checkTable = $pdo->query($checkTableSql);
        
        if ($checkTable->rowCount() == 0) {
            // 如果表不存在，返回空值
            return ['workOrder' => null, 'status' => null];
        }
        
        // 查詢車台對應的實際工單和狀態
        $sql = "SELECT ops.工單號, ops.狀態, ms.status_name 
                FROM OProduction_Schedule ops 
                LEFT JOIN OMachine_Status ms ON ops.狀態 = ms.id 
                WHERE ops.車台號 = ? 
                ORDER BY ops.更新時間 DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$car]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 如果有結果，返回工單號和狀態，否則返回null
        return $result ? [
            'workOrder' => $result['工單號'],
            'status' => $result['狀態'],
            'statusName' => $result['status_name']
        ] : ['workOrder' => null, 'status' => null, 'statusName' => null];
    } catch (PDOException $e) {
        error_log('獲取實際工單號錯誤: ' . $e->getMessage());
        // 出錯時返回null
        return ['workOrder' => null, 'status' => null, 'statusName' => null];
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
        $sql = "UPDATE 機台看板 
                SET 箱數 = ?
                WHERE 機台 = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['boxCount'] ?? '01',
            $data['car']
        ]);

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

// 條碼列印功能
function printBarcode() {
    // 1. 連接資料庫並設置回應頭
    require_once 'config.php';
    header('Content-Type: application/json');

    // 2. 接收前端傳來的資料
    $data = json_decode(file_get_contents('php://input'), true);

    // 3. 檢查必要參數
    if (!isset($data['car']) || !isset($data['workOrder'])) {
        echo json_encode(['success' => false, 'error' => '缺少必要參數']);
        return;
    }

    try {
        // 4. 準備資料
        $newBoxCount = intval($data['boxCount'] ?? '01');
        $car = $data['car'];
        $workOrder = $data['workOrder'];
        $operator = $data['operator'] ?? '';
        $shift = $data['shift'] ?? '日';
        $productName = $data['productName'] ?? '';
        
        // 檢查目前機台看板的箱數
        $currentBoxQuery = "SELECT 箱數 FROM 機台看板 WHERE 機台 = ?";
        $currentBoxStmt = $pdo->prepare($currentBoxQuery);
        $currentBoxStmt->execute([$car]);
        $currentBoxResult = $currentBoxStmt->fetch(PDO::FETCH_ASSOC);
        $currentBox = intval($currentBoxResult['箱數'] ?? 0);
        
        // 如果新箱數小於等於目前箱數，回傳錯誤
        if ($newBoxCount <= $currentBox) {
            echo json_encode(['success' => false, 'error' => '新箱數必須大於目前箱數']);
            return;
        }
        
        // 如果新箱數大於9，限制為9
        if ($newBoxCount > 9) {
            $newBoxCount = 9;
        }
        
        // 5. 開始事務處理
        $pdo->beginTransaction();
        
        // 6. 更新機台看板資料
        $updateSql = "UPDATE 機台看板 SET 箱數 = ? WHERE 機台 = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$newBoxCount, $car]);
        
        // 7. 循環插入新增的箱數記錄
        $barcodeIds = [];
        
        // 從目前箱數+1開始，到新箱數結束
        for ($i = $currentBox + 1; $i <= $newBoxCount; $i++) {
            $boxNumber = str_pad($i, 2, '0', STR_PAD_LEFT);
            $barcodeId = $workOrder . $car . $boxNumber;
            $barcodeIds[] = [
                'id' => $barcodeId,
                'boxNumber' => $boxNumber
            ];
            
            // 8. 檢查條碼是否已存在
            $checkSql = "SELECT 條碼編號 FROM 生產紀錄表 WHERE 條碼編號 = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$barcodeId]);
            
            if ($checkStmt->rowCount() == 0) {
                // 9. 條碼不存在，新增記錄
                $insertSql = "INSERT INTO 生產紀錄表 (條碼編號, 工單號, 品名, 機台, 箱數, 顧車, 班別) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $pdo->prepare($insertSql);
                $insertStmt->execute([$barcodeId, $workOrder, $productName, $car, $boxNumber, $operator, $shift]);
            }
        }
        
        // 10. 提交資料庫變更
        $pdo->commit();
        
        // 11. 循環執行列印 - 使用絕對路徑
        $printErrors = [];
        foreach ($barcodeIds as $barcode) {
            // 使用預定義的常量設置完整的 Python 路徑和腳本路徑
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
        
        // 12. 檢查是否有列印錯誤
        if (!empty($printErrors)) {
            echo json_encode(['success' => false, 'error' => implode("\n", $printErrors)]);
            return;
        }
        
        echo json_encode(['success' => true, 'message' => '列印成功']);
        
    } catch (PDOException $e) {
        // 13. 發生錯誤時回滾事務
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('列印條碼錯誤: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => '處理失敗: ' . $e->getMessage()]);
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
        
        $barcodeId = $workOrder . $car . $boxNumber;
        
        // 檢查條碼是否存在
        $checkSql = "SELECT * FROM 生產紀錄表 WHERE 條碼編號 = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$barcodeId]);

        if ($checkStmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'error' => '未找到對應的條碼記錄']);
            return;
        }
        
        // 使用絕對路徑執行 Python 腳本
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
            echo json_encode(['success' => false, 'error' => '重印失敗: ' . implode("\n", $output)]);
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
        
        // 檢查是否有對應的條碼記錄
        $sql = "SELECT COUNT(*) as count FROM 生產紀錄表 WHERE 條碼編號 LIKE ? AND 工單號 = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$workOrder . $car . '%', $workOrder]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'exists' => ($result['count'] > 0)
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