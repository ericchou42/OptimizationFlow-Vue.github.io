<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

$env_path = __DIR__ . '/../.env';
$env = file_exists($env_path) ? parse_ini_file($env_path) : [];

define('PYTHON_PATH', $env['PYTHON_PATH'] ?? 'python');
define('BARCODE_SCRIPT_PATH', $env['SCRIPT_PATH_BARCODE'] ?? 'barcode.py');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_machine_status':
        getMachineStatus();
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

function getMachineStatus() {
    require_once 'config.php';
    header('Content-Type: application/json; charset=utf-8');
    try {
        $sql = "SELECT 代碼 as id, 狀態 as status_name FROM 機台狀態 ORDER BY 代碼";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($statuses, JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log('機台狀態查詢失敗: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function getCarData() {
    require_once 'config.php';
    header('Content-Type: application/json; charset=utf-8');

    try {
        $sql_machines = "SELECT md.機台 as 車台號, md.狀態, md.工單號, ms.狀態 as 狀態名稱
                         FROM 機台看板 md 
                         LEFT JOIN 機台狀態 ms ON md.狀態 = ms.代碼
                         WHERE md.狀態 = '1'
                         ORDER BY md.機台";
        $stmt_machines = $pdo->prepare($sql_machines);
        $stmt_machines->execute();
        $dashboardData = $stmt_machines->fetchAll(PDO::FETCH_ASSOC);

        $workOrderIds = array_column($dashboardData, '工單號');
        $workOrderIds = array_values(array_filter(array_unique($workOrderIds)));

        $workOrderMap = [];
        $cumulativeQuantities = [];

        if (!empty($workOrderIds)) {
            $placeholders = implode(',', array_fill(0, count($workOrderIds), '?'));

            $sql_workorders = "SELECT 工單號, 品名, 工單數, 日產量 FROM uploaded_data WHERE 工單號 IN ($placeholders)";
            $stmt_workorders = $pdo->prepare($sql_workorders);
            $stmt_workorders->execute($workOrderIds);
            $workOrderData = $stmt_workorders->fetchAll(PDO::FETCH_ASSOC);
            foreach ($workOrderData as $wo) {
                $workOrderMap[$wo['工單號']] = $wo;
            }

            $sql_cumul = "SELECT 工單號, SUM(數量) as total_qty FROM 生產紀錄表 WHERE 工單號 IN ($placeholders) GROUP BY 工單號";
            $stmt_cumul = $pdo->prepare($sql_cumul);
            $stmt_cumul->execute($workOrderIds);
            $cumulativeQuantities = $stmt_cumul->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        $result = [];
        foreach ($dashboardData as $dashboard) {
            $carId = $dashboard['車台號'];
            $workOrderId = $dashboard['工單號'];

            $workOrderInfo = $workOrderMap[$workOrderId] ?? null;
            $productName = $workOrderInfo['品名'] ?? '';
            $totalRequired = (int)($workOrderInfo['工單數'] ?? 0);
            $dailyQuota = (int)($workOrderInfo['日產量'] ?? 0);
            $cumulativeQty = (int)($cumulativeQuantities[$workOrderId] ?? 0);

            $isFinishingSoon = false;

            if ($totalRequired > 0 && $dailyQuota > 0) {
                $remainingQty = $totalRequired - $cumulativeQty;
                if ($remainingQty > 0) {
                    $remainingDays = $remainingQty / $dailyQuota;
                    if ($remainingDays <= 1) {
                        $isFinishingSoon = true;
                    }
                } else {
                    $isFinishingSoon = true; // Already finished
                }
            }
            
            $result[] = [
                'car' => $carId,
                'currentWorkOrder' => $workOrderId,
                'productName' => $productName,
                'operator' => '',
                'shift' => '',
                'workOrderCount' => $totalRequired,
                'cumulativeCount' => $cumulativeQty,
                'dailyQuota' => $dailyQuota,
                'isFinishingSoon' => $isFinishingSoon
            ];
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log('數據庫錯誤: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function print_box_count() {
    require_once 'config.php';
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['car']) || !isset($data['workOrder']) || !isset($data['date']) || !isset($data['boxCount'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要參數'], JSON_UNESCAPED_UNICODE);
        return;
    }
    try {
        $pdo->beginTransaction();
        $car = $data['car'];
        $workOrder = $data['workOrder'];
        $operator = $data['operator'] ?? '';
        $shift = $data['shift'] ?? '日';
        $shiftNumber = $data['shiftNumber'] ?? '1';
        $productName = $data['productName'] ?? '';
        $date = $data['date'] ?? date('Ymd');
        $boxCount = intval($data['boxCount']);
        $nextUnit = $data['nextUnit'] ?? '電';
        if ($boxCount < 1) $boxCount = 1;
        if ($boxCount > 9) $boxCount = 9;
        
        $barcodeIds = [];
        for ($boxNum = 1; $boxNum <= $boxCount; $boxNum++) {
            $boxNumber = str_pad($boxNum, 2, '0', STR_PAD_LEFT);
            $barcodeId = $date . $workOrder . $car . $shiftNumber . $boxNumber;
            $barcodeIds[] = ['id' => $barcodeId, 'boxNumber' => $boxNumber];
            
            $checkSql = "SELECT 條碼編號 FROM 生產紀錄表 WHERE 條碼編號 = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$barcodeId]);
            
            if ($checkStmt->rowCount() == 0) {
                $insertSql = "INSERT INTO 生產紀錄表 (條碼編號, 工單號, 品名, 機台, 箱數, 顧車, 班別, 後續單位) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $pdo->prepare($insertSql);
                $insertStmt->execute([$barcodeId, $workOrder, $productName, $car, $boxNumber, $operator, $shift, $nextUnit]);
            } else {
                $updateSql = "UPDATE 生產紀錄表 SET 品名 = ?, 顧車 = ?, 班別 = ?, 後續單位 = ? WHERE 條碼編號 = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$productName, $operator, $shift, $nextUnit, $barcodeId]);
            }
        }
        
        $pdo->commit();
        
        $printErrors = [];
        foreach ($barcodeIds as $barcode) {
            $command = '"' . PYTHON_PATH . '" "' . BARCODE_SCRIPT_PATH . '" ' . escapeshellarg($barcode['id']) . " " . escapeshellarg($workOrder) . " " . escapeshellarg($productName) . " " . escapeshellarg($operator) . " " . escapeshellarg($car) . " " . escapeshellarg($barcode['boxNumber']) . " " . escapeshellarg($shift);
            exec($command, $output, $returnVar);
            if ($returnVar !== 0) {
                $printErrors[] = "箱號 {" . $barcode['boxNumber'] . "} 列印失敗: " . implode("\n", $output);
                error_log('Print error: ' . implode("\n", $output));
            }
        }
        
        if (!empty($printErrors)) {
            echo json_encode(['success' => false, 'error' => implode("\n", $printErrors), 'dataUpdated' => true, 'message' => "資料庫更新成功，但列印失敗。"], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        echo json_encode(['success' => true, 'message' => "批量列印成功，共 {" . $boxCount . "} 張標籤"], JSON_UNESCAPED_UNICODE);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('資料庫錯誤: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '資料庫處理失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function reprintBarcode() {
    require_once 'config.php';
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['car']) || !isset($data['workOrder']) || !isset($data['boxNumber'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要參數'], JSON_UNESCAPED_UNICODE);
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
        $barcodeId = $date . $workOrder . $car . $shiftNumber . $boxNumber;
        
        $command = '"' . PYTHON_PATH . '" "' . BARCODE_SCRIPT_PATH . '" ' . escapeshellarg($barcodeId) . " " . escapeshellarg($workOrder) . " " . escapeshellarg($productName) . " " . escapeshellarg($operator) . " " . escapeshellarg($car) . " " . escapeshellarg($boxNumber) . " " . escapeshellarg($shift);
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            error_log('Reprint error: ' . implode("\n", $output));
            echo json_encode(['success' => false, 'error' => '重印失敗: ' . implode("\n", $output), 'dataUpdated' => true], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        echo json_encode(['success' => true, 'message' => '重印成功'], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log('重印條碼錯誤: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '處理失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function checkBarcode() {
    require_once 'config.php';
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['car']) || !isset($data['workOrder'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '缺少必要參數'], JSON_UNESCAPED_UNICODE);
        return;
    }
    try {
        $car = $data['car'];
        $workOrder = $data['workOrder'];
        $date = $data['date'] ?? date('Ymd');
        $shift = $data['shift'] ?? '1';
        
        $newPrefix = $date . $workOrder . $car . $shift;
        $newSql = "SELECT 箱數 FROM 生產紀錄表 WHERE 條碼編號 LIKE ? ORDER BY 箱數 ASC";
        $newStmt = $pdo->prepare($newSql);
        $newStmt->execute([$newPrefix . '%']);
        $newBoxes = $newStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $boxNumbers = array_map('intval', $newBoxes);
        sort($boxNumbers);
        
        echo json_encode(['success' => true, 'exists' => !empty($boxNumbers), 'existingBoxes' => $boxNumbers], JSON_UNESCAPED_UNICODE);
        
    } catch (PDOException $e) {
        error_log('檢查條碼錯誤: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '檢查失敗: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
?>