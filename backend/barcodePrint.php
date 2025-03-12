<?php
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
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()]);
    }
}

// 從第48行開始修改 getCarData() 函數
function getCarData() {
    require_once 'config.php';
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header('Access-Control-Allow-Headers: Content-Type');

    try {
        // 1. 獲取機台看板的基礎數據
        $sql = "SELECT md.機台 as 車台號, md.狀態, md.工單號 
                FROM 機台看板 md 
                ORDER BY md.機台";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $dashboardData = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        // 整理數據結構
        $result = [];
        foreach ($dashboardData as $dashboard) {
            $carId = $dashboard['車台號'];
            $workOrderId = $dashboard['工單號'];
            $productName = isset($workOrderMap[$workOrderId]) ? $workOrderMap[$workOrderId] : "";
            $drawingInfo = isset($drawingMap[$productName]) ? $drawingMap[$productName] : null;
            
            $result[$carId] = [
                'car' => $carId,
                'currentStatus' => $dashboard['狀態'] ?: 'B',
                'currentWorkOrder' => $workOrderId,
                'productName' => $productName,
                'drawingInfo' => $drawingInfo,
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

        echo json_encode(array_values($result));

    } catch (PDOException $e) {
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()]);
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
        // 出錯時返回null
        return ['workOrder' => null, 'status' => null, 'statusName' => null];
    }
}

// 獲取所有工單的函數 (原本已經存在，但調整了SQL查詢)
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
        echo json_encode([
            'success' => false,
            'error' => '更新失敗: ' . $e->getMessage()
        ]);
    }
}
?>