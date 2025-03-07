-- 建立資料庫
CREATE DATABASE IF NOT EXISTS excel_manager;
USE excel_manager;

-- 登入紀錄
CREATE TABLE NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL
    -- created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 建立一個使用者名稱為 'admin'，密碼為 'password' 的帳號
INSERT INTO users (username, password) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- 上傳資料
CREATE TABLE IF NOT EXISTS uploaded_data (
    工單號 VARCHAR(50) NOT NULL PRIMARY KEY,
    料號 VARCHAR(50) NOT NULL,
    品名 TEXT NOT NULL,
    交期 VARCHAR(50) NOT NULL,
    工單數 INT,
    實際入庫 INT,
    產速 DECIMAL(10,2),
    台數 INT,
    日產量 INT,
    架機說明 TEXT,
    架機日期 VARCHAR(50),
    `機台(預)` VARCHAR(50),
    利潤中心 VARCHAR(50),
    實際完成 VARCHAR(50),
    落後百分比 DECIMAL(5,2),
    車製回覆完成日 VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS 機台狀態 (
    代碼 VARCHAR(5) PRIMARY KEY,
    狀態 VARCHAR(50) NOT NULL
);

-- 插入預設狀態
INSERT INTO 機台狀態 (代碼, 狀態) VALUES 
('1', '正常運轉'),
('A', '架機(改車)'),
('B', '待排程'),
('C', '待確認'),
('D', '生產(繼續車)日期未到'),
('E', '待訂單'),
('0', '零件維修'),
('F', '委外維修'),
('G', '待機(繼續車)'),
('H', '待訂');

CREATE TABLE IF NOT EXISTS 機台看板 (
    機台 VARCHAR(10) NOT NULL PRIMARY KEY,
    狀態 VARCHAR(5) DEFAULT 'B',
    工單號 VARCHAR(50)
);

-- 插入預設狀態
TRUNCATE TABLE 機台看板;
INSERT INTO 機台看板 (機台) VALUES 
('A01'),('A02'),('A03'),('A04'),('A05'),('A06'),('A07'),('A08'),('A09'),('A10'),
('A11'),('A12'),('A13'),('A14'),('A15'),('A16'),('A17'),('A18'),('A19'),('A20'),
('A21'),('A22'),('B01'),('B02'),('B03'),('B04'),('B05'),('B06'),('B07'),('B08'),
('B09'),('B10'),('B11'),('B12'),('B13'),('B14'),('B15'),('C01'),('C02'),('C03'),
('C04'),('C05'),('C06'),('C07'),('C08'),('C09'),('C10'),('C11'),('C12'),('C13'),
('C14'),('C15'),('C16'),('C17'),('F01'),('F02'),('F03'),('F04'),('F05'),('F06'),
('F07'),('F08'),('F09'),('F10'),('F11'),('F12'),('F13'),('F14'),('F15'),('F16');

