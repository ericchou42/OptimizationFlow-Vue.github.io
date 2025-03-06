-- 登入紀錄
CREATE DATABASE excel_manager;
USE excel_manager;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL
    -- created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- 這會建立一個使用者名稱為 'admin'，密碼為 'password' 的帳號

    -- id INT AUTO_INCREMENT PRIMARY KEY,

CREATE DATABASE excel_manager;
USE excel_manager;

CREATE TABLE uploaded_data (
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

-- 新增 OProduction_Schedule 表單，用於儲存車台的實際工單號
CREATE TABLE IF NOT EXISTS OProduction_Schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        車台號 VARCHAR(10) NOT NULL,
        工單號 VARCHAR(50) NOT NULL,
        車台預排 VARCHAR(50),
        建立時間 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        更新時間 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- 途程號 VARCHAR(20),
    -- 工作中心 VARCHAR(50),
    -- 工作中心說明 TEXT,
    -- 在製數 INT,
    -- 途程實際完工量 INT NOT NULL,
    -- 途程實際完工工時 DECIMAL(10,2),
    -- 途程標準完工量 INT,
    -- 途程標準完工工時 DECIMAL(10,2),
    -- 途程完工量差異 INT,
    -- 途程產量差異 INT,
    -- 標準入庫 INT,
    -- 入庫日 VARCHAR(50),
    -- 入庫天數 INT,
    -- 標準應完成數 INT,
    -- 應完成百分比 DECIMAL(5,2),

USE excel_manager;
CREATE TABLE OMachine_Status (
    id VARCHAR(5) PRIMARY KEY,
    status_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 插入預設狀態
INSERT INTO OMachine_Status (id, status_name) VALUES 
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

-- 在 OProduction_Schedule 表中添加狀態欄位
ALTER TABLE OProduction_Schedule ADD COLUMN 狀態 VARCHAR(5) DEFAULT 'C' REFERENCES OMachine_Status(id);

USE excel_manager;
CREATE TABLE products (
    barcode VARCHAR(50) PRIMARY KEY,
    product_name VARCHAR(200) NOT NULL,
    specifications VARCHAR(500)
);

USE excel_manager;
CREATE TABLE weight_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    barcode VARCHAR(50),
    weight DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (barcode) REFERENCES products(barcode)
);