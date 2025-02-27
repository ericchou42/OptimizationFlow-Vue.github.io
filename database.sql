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