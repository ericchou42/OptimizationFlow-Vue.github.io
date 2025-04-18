<?php
try {
    // 連接字串
    $dsn = "sqlsrv:Server=172.29.172.41;Database=BPMPro";
    $user = "RptUser";
    $pass = "LemonCola$2022";
    
    // 建立PDO實例
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 執行查詢
    $sql = "SELECT * FROM BPMPro.dbo.EZ_View_HRGOCToday ORDER BY 部門代碼, 起始日期, 結束日期";
    $stmt = $conn->query($sql);
    
    // 顯示資料
    echo "<table border='1'>";
    $first = true;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // 顯示表頭
        if ($first) {
            echo "<tr>";
            foreach (array_keys($row) as $key) {
                echo "<th>$key</th>";
            }
            echo "</tr>";
            $first = false;
        }
        
        // 顯示資料行
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>$value</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "連接失敗: " . $e->getMessage();
}
$conn = null;
?>