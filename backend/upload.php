<?php
header('Content-Type: application/json');
require_once 'config.php';

// 啟用錯誤記錄
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

try {
    // 解析 JSON 輸入
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rows']) || !is_array($input['rows'])) {
        throw new Exception("無效的資料格式");
    }
    $rows = $input['rows'];
    if (empty($rows)) {
        throw new Exception("沒有資料可以處理");
    }

    // 取得 Excel 中所有工單號列表（假設工單號為唯一識別）
    $excelWorkOrders = [];
    foreach ($rows as $row) {
        $workOrder = trim($row['工單號'] ?? '');
        if ($workOrder !== '') {
            $excelWorkOrders[$workOrder] = $row;
        }
    }

    // 取得資料庫中現有的工單記錄，建立以工單號為 key 的陣列
    $stmt = $pdo->query("SELECT * FROM uploaded_data");

    $dbRecords = [];
    while ($record = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = trim($record['工單號'] ?? '');
        if ($key !== '') {
            $dbRecords[$key] = $record;
        }
    }

    // 初始化結果統計陣列
    $inserted = [];
    $reactivated = [];
    $closed = [];

    // 開始交易
    $pdo->beginTransaction();

    // 1. 處理 Excel 中的資料：如果不存在則新增，若存在且狀態為 0則更新為 1（重新啟用）
    foreach ($excelWorkOrders as $workOrder => $row) {
        if (!isset($dbRecords[$workOrder])) {
            // 不存在則插入新記錄，狀態預設為 1
            $values = [
                $row['工單號'] ?? '',
                $row['料號'] ?? '',
                $row['品名'] ?? '',
                $row['交期'] ?? '',
                $row['工單數'] ?? null,
                $row['實際入庫'] ?? null,
                $row['產速'] ?? null,
                $row['台數'] ?? null,
                $row['日產量'] ?? null,
                $row['架機說明'] ?? '',
                $row['架機日期'] ?? '',
                $row['機台(預)'] ?? '',
                $row['利潤中心'] ?? '',
                $row['實際完成'] ?? '',
                $row['落後百分比'] ?? null,
                $row['車製回覆完成日'] ?? '',
                1  // 狀態設定為 1
            ];
            $insertStmt = $pdo->prepare("
                INSERT INTO uploaded_data (
                    工單號, 料號, 品名, 交期, 工單數, 實際入庫,
                    產速, 台數, 日產量, 架機說明, 架機日期,
                    `機台(預)`, 利潤中心, 實際完成, 落後百分比,
                    車製回覆完成日, 狀態
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?
                )
            ");
            $insertStmt->execute($values);
            $inserted[] = $workOrder;
        } else {
            // 已存在：如果狀態為 0，則更新狀態為 1並更新其他欄位資料
            if ((int)$dbRecords[$workOrder]['狀態'] === 0) {
                $updateStmt = $pdo->prepare("
                    UPDATE uploaded_data SET
                        料號 = ?,
                        品名 = ?,
                        交期 = ?,
                        工單數 = ?,
                        實際入庫 = ?,
                        產速 = ?,
                        台數 = ?,
                        日產量 = ?,
                        架機說明 = ?,
                        架機日期 = ?,
                        `機台(預)` = ?,
                        利潤中心 = ?,
                        實際完成 = ?,
                        落後百分比 = ?,
                        車製回覆完成日 = ?,
                        狀態 = 1
                    WHERE 工單號 = ?
                ");
                $updateStmt->execute([
                    $row['料號'] ?? '',
                    $row['品名'] ?? '',
                    $row['交期'] ?? '',
                    $row['工單數'] ?? null,
                    $row['實際入庫'] ?? null,
                    $row['產速'] ?? null,
                    $row['台數'] ?? null,
                    $row['日產量'] ?? null,
                    $row['架機說明'] ?? '',
                    $row['架機日期'] ?? '',
                    $row['機台(預)'] ?? '',
                    $row['利潤中心'] ?? '',
                    $row['實際完成'] ?? '',
                    $row['落後百分比'] ?? null,
                    $row['車製回覆完成日'] ?? '',
                    $workOrder
                ]);
                $reactivated[] = $workOrder;
            }
            // 若狀態已為1則不處理
        }
    }

    // 2. 處理資料庫中存在，但 Excel 中不存在的工單：更新狀態為 0（結案）
    foreach ($dbRecords as $workOrder => $record) {
        if (!isset($excelWorkOrders[$workOrder])) {
            if ((int)$record['狀態'] !== 0) {
                $closeStmt = $pdo->prepare("UPDATE uploaded_data SET 狀態 = 0 WHERE 工單號 = ?");
                $closeStmt->execute([$workOrder]);
                $closed[] = $workOrder;
            }
        }
    }

    // 提交交易
    $pdo->commit();

    // 回傳結果摘要
    echo json_encode([
        'success'     => true,
        'inserted'    => $inserted,
        'reactivated' => $reactivated,
        'closed'      => $closed
    ]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Upload Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>