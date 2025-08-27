<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getDailyReportData':
        getDailyReportData($pdo);
        break;
    case 'saveUnitWeights':
        saveUnitWeights($pdo);
        break;
    default:
        sendResponse(false, '無效的請求');
        break;
}

function getDailyReportData($pdo) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $dateYmd = date('Ymd', strtotime($date));
    $prevDateYmd = date('Ymd', strtotime($date . ' -1 day'));

    try {
        // Corrected query based on user feedback
        $sql = "SELECT 
                    pr.機台, 
                    pr.工單號, 
                    pr.品名,
                    ud.利潤中心 AS NP碼,  -- Corrected: NP碼 is 利潤中心
                    pr.班別, 
                    pr.箱數, 
                    pr.重量, 
                    pr.數量,
                    pr.單重
                FROM 生產紀錄表 pr
                LEFT JOIN uploaded_data ud ON BINARY pr.工單號 = BINARY ud.工單號
                WHERE pr.條碼編號 LIKE ?
                ORDER BY pr.機台, pr.工單號, pr.班別, pr.箱數";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dateYmd . '%']);
        $dailyRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $reportData = [];

        foreach ($dailyRecords as $record) {
            $key = $record['機台'] . '-' . $record['工單號'];
            if (!isset($reportData[$key])) {
                $reportData[$key] = [
                    '機台' => $record['機台'],
                    '品名' => $record['品名'],
                    '工單號碼' => $record['工單號'],
                    'NP碼' => $record['NP碼'],
                    '日班淨重1' => null, '日班淨重2' => null, '日班淨重3' => null,
                    '夜班淨重1' => null, '夜班淨重2' => null, '夜班淨重3' => null,
                    '單重' => '',
                    '日班數量' => 0,
                    '夜班數量' => 0,
                    '合計' => 0,
                    '前日數量' => 0,
                    '累計' => 0,
                ];
            }

            $boxNum = intval($record['箱數']);
            if ($boxNum >= 1 && $boxNum <= 4) {
                if ($record['班別'] == '日') {
                    $reportData[$key]['日班淨重' . $boxNum] = $record['重量'];
                } elseif ($record['班別'] == '夜' || $record['班別'] == '中') {
                    $reportData[$key]['夜班淨重' . $boxNum] = $record['重量'];
                }
            }

            if ($record['班別'] == '日') {
                $reportData[$key]['日班數量'] += intval($record['數量']);
            } elseif ($record['班別'] == '夜' || $record['班別'] == '中') {
                $reportData[$key]['夜班數量'] += intval($record['數量']);
            }
            $reportData[$key]['合計'] += intval($record['數量']);

            if (empty($reportData[$key]['單重']) && !empty($record['單重']) && floatval($record['單重']) > 0) {
                $reportData[$key]['單重'] = $record['單重'];
            }
        }

        foreach ($reportData as $key => &$row) {
            $workOrder = $row['工單號碼'];
            $prevSql = "SELECT SUM(數量) as total FROM 生產紀錄表 WHERE 工單號 = ? AND 條碼編號 LIKE ?";
            $prevStmt = $pdo->prepare($prevSql);
            $prevStmt->execute([$workOrder, $prevDateYmd . '%']);
            $row['前日數量'] = $prevStmt->fetchColumn() ?? 0;

            $cumulSql = "SELECT SUM(數量) as total FROM 生產紀錄表 WHERE 工單號 = ? AND SUBSTRING(條碼編號, 1, 8) <= ?";
            $cumulStmt = $pdo->prepare($cumulSql);
            $cumulStmt->execute([$workOrder, $dateYmd]);
            $row['累計'] = $cumulStmt->fetchColumn() ?? 0;
        }

        sendResponse(true, '查詢成功', array_values($reportData));

    } catch (PDOException $e) {
        error_log('查詢日報表錯誤: ' . $e->getMessage());
        sendResponse(false, '後端查詢失敗: ' . $e->getMessage());
    }
}

function saveUnitWeights($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    $weightsToSave = $data['weights'] ?? [];
    $date = $data['date'] ?? date('Y-m-d');
    $dateYmd = date('Ymd', strtotime($date));

    if (empty($weightsToSave)) {
        sendResponse(false, '沒有需要儲存的資料');
        return;
    }

    $pdo->beginTransaction();

    try {
        $sql = "UPDATE 生產紀錄表 SET 單重 = ? WHERE 工單號 = ? AND 條碼編號 LIKE ?";
        $stmt = $pdo->prepare($sql);

        foreach ($weightsToSave as $item) {
            if (isset($item['工單號碼']) && isset($item['單重']) && is_numeric($item['單重']) && $item['單重'] > 0) {
                $likePattern = $dateYmd . '%';
                $stmt->execute([$item['單重'], $item['工單號碼'], $likePattern]);
            }
        }

        $pdo->commit();
        sendResponse(true, '單重儲存成功');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('儲存單重錯誤: ' . $e->getMessage());
        sendResponse(false, '儲存失敗: ' . $e->getMessage());
    }
}

function sendResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    // Ensure JSON is properly encoded
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
?>