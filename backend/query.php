<?php
// 檢查請求的動作類型
$action = $_GET['action'] ?? '';

// 根據動作執行不同的功能
switch ($action) {
    case 'get_work_orders':
        getWorkOrders();
        break;
    case 'save_work_order':
        saveWorkOrder();
        break;
    default:
        getCarData();
        break;
}

// 獲取車台資料的函數
function getCarData() {
    // 引入數據庫連接配置
    require_once 'config.php';

    // 設置回應類型
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');

    try {
        // 生成車台陣列
        $allCars = [];
        $carPrefixes = ['A', 'B', 'C', 'F'];
        $carCounts = [22, 15, 17, 16];
        
        for ($i = 0; $i < count($carPrefixes); $i++) {
            for ($j = 1; $j <= $carCounts[$i]; $j++) {
                $carId = $carPrefixes[$i] . sprintf("%02d", $j);
                $allCars[] = $carId;
            }
        }
        
        // 初始化結果陣列
        $result = [];
        
        // 查詢所有數據
        $sql = "SELECT 工單號, `機台(預)`, 架機日期 FROM uploaded_data WHERE `機台(預)` IS NOT NULL AND `機台(預)` <> '' ORDER BY 工單號";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $allData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 初始化每台車的數據
        foreach ($allCars as $car) {
            $result[$car] = [
                'car' => $car,
                'data' => []
            ];
        }
        
        // 處理每一筆數據
        foreach ($allData as $row) {
            $machineStr = $row['機台(預)'];
            $workOrder = $row['工單號'];
            $installDate = str_replace(['~', '～'], '', $row['架機日期']); // 使用 str_replace 直接移除波浪符號
            
            // 將架機日期標準化，移除特殊符號後取得日期
            if (!empty($installDate)) {
                // 處理可能的日期格式，例如將2/18轉為2024-02-18
                if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $installDate, $matches)) {
                    $installDate = '2024-' . sprintf("%02d", $matches[1]) . '-' . sprintf("%02d", $matches[2]);
                }
            } else {
                $installDate = '9999-12-31'; // 給無日期的資料一個遠期日期用於排序
            }
            
            // 檢查機台(預)是否包含指定車台
            foreach ($allCars as $car) {
                if (strpos($machineStr, $car) !== false) {
                    // 查詢實際工單
                    $actualWorkOrder = getActualWorkOrder($car, $pdo);
                    
                    $result[$car]['data'][] = [
                        'workOrder' => $workOrder,
                        'actualWorkOrder' => $actualWorkOrder,
                        'machineHistory' => $machineStr,
                        'installDate' => $installDate,
                        'displayDate' => $row['架機日期']
                    ];
                }
            }
        }
        
        // 排序每個車台的數據（按照架機日期）
        foreach ($result as &$carData) {
            usort($carData['data'], function($a, $b) {
                return strcmp($a['installDate'], $b['installDate']);
            });
        }
        
        echo json_encode(array_values($result));
        
    } catch (PDOException $e) {
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()]);
    }
}

// 獲取實際工單號
function getActualWorkOrder($car, $pdo) {
    try {
        // 檢查表是否存在
        $checkTableSql = "SHOW TABLES LIKE 'OProduction_Schedule'";
        $checkTable = $pdo->query($checkTableSql);
        
        if ($checkTable->rowCount() == 0) {
            // 如果表不存在，返回空值
            return null;
        }
        
        // 查詢車台對應的實際工單
        $sql = "SELECT 工單號 FROM OProduction_Schedule WHERE 車台號 = ? ORDER BY 更新時間 DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$car]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 如果有結果，返回工單號，否則返回null
        return $result ? $result['工單號'] : null;
    } catch (PDOException $e) {
        // 出錯時返回null
        return null;
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
        $sql = "SELECT 工單號, 品名 FROM uploaded_data WHERE 工單號 IS NOT NULL AND 工單號 <> '' ORDER BY 工單號";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $workOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 返回工單資料
        echo json_encode($workOrders);
        
    } catch (PDOException $e) {
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()]);
    }
}

// 儲存工單資料的函數
function saveWorkOrder() {
    // 引入數據庫連接配置
    require_once 'config.php';

    // 設置回應類型
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');

    // 獲取POST數據
    $data = json_decode(file_get_contents('php://input'), true);

    // 驗證請求數據
    if (!isset($data['car']) || !isset($data['workOrder'])) {
        echo json_encode(['success' => false, 'error' => '缺少必要參數']);
        exit;
    }

    try {
        // 獲取參數
        $car = $data['car'];
        $workOrder = $data['workOrder'];
        $preWorkOrder = $data['preWorkOrder'] ?? '';
        
        // 檢查工單是否存在
        $checkSql = "SELECT COUNT(*) AS count FROM uploaded_data WHERE 工單號 = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$workOrder]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            echo json_encode(['success' => false, 'error' => '工單號不存在']);
            exit;
        }
        
        // 檢查OProduction_Schedule表是否存在，不存在則創建
        $pdo->exec("CREATE TABLE IF NOT EXISTS OProduction_Schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            車台號 VARCHAR(10) NOT NULL,
            工單號 VARCHAR(50) NOT NULL,
            預排工單號 VARCHAR(50),
            建立時間 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            更新時間 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // 檢查該車台是否已有安排
        $checkCarSql = "SELECT id FROM OProduction_Schedule WHERE 車台號 = ?";
        $checkCarStmt = $pdo->prepare($checkCarSql);
        $checkCarStmt->execute([$car]);
        $existingRecord = $checkCarStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            // 更新現有記錄
            $updateSql = "UPDATE OProduction_Schedule SET 工單號 = ?, 預排工單號 = ? WHERE 車台號 = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$workOrder, $preWorkOrder, $car]);
        } else {
            // 建立新記錄
            $insertSql = "INSERT INTO OProduction_Schedule (車台號, 工單號, 預排工單號) VALUES (?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([$car, $workOrder, $preWorkOrder]);
        }
        
        echo json_encode(['success' => true, 'message' => '工單資料儲存成功']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => '數據庫錯誤: ' . $e->getMessage()]);
    }
}
?>
