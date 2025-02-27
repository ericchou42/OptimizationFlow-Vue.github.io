<?php
header('Content-Type: application/json');
require_once 'config.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

try {
    // Validate input data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['rows']) || !is_array($input['rows'])) {
        throw new Exception("無效的資料格式");
    }

    $rows = $input['rows'];
    if (empty($rows)) {
        throw new Exception("沒有資料可以處理");
    }

    // Start transaction
    $pdo->beginTransaction();
    
    // Initialize counter
    $inserted = 0;

    // Prepare the insert statement
    $stmt = $pdo->prepare("
        INSERT INTO uploaded_data (
            工單號, 料號, 品名, 交期, 工單數, 實際入庫,
            產速, 台數, 日產量, 架機說明, 架機日期,
            `機台(預)`, 利潤中心, 實際完成, 落後百分比,
            車製回覆完成日
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?
        )
    ");

    // Process each row
    foreach ($rows as $row) {
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
            $row['車製回覆完成日'] ?? ''
        ];

        $stmt->execute($values);
        $inserted++;
    }

    // Commit transaction
    $pdo->commit();

    // Return success message
    echo json_encode([
        'success' => true,
        'inserted' => $inserted
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Upload Error: " . $e->getMessage());
    
    // Rollback transaction if active
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Return error message
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>