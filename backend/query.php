<?php
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
                $result[$car]['data'][] = [
                    'workOrder' => $workOrder,
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
?>