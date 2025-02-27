<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'getUsers':
        try {
            $stmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '獲取使用者資料失敗']);
        }
        break;

    case 'getProduct':
        $barcode = $_GET['barcode'] ?? '';
        if (empty($barcode)) {
            http_response_code(400);
            echo json_encode(['error' => '條碼不能為空']);
            break;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE barcode = ?");
            $stmt->execute([$barcode]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                echo json_encode($product);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '找不到產品']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '獲取產品資料失敗']);
        }
        break;

    case 'getWeight':
        // 這裡需要根據您的磅秤設備進行實際的重量讀取
        // 這是一個模擬的例子
        echo json_encode(['weight' => number_format(rand(1, 100) / 10, 2)]);
        break;

    case 'saveRecord':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => '方法不允許']);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_id'], $data['barcode'], $data['weight'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要資料']);
            break;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO weight_records (user_id, barcode, weight) VALUES (?, ?, ?)");
            $stmt->execute([$data['user_id'], $data['barcode'], $data['weight']]);
            echo json_encode(['success' => true, 'message' => '資料儲存成功']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料儲存失敗']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => '無效的操作']);
        break;
}
?>