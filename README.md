# OptimizationFlow-Vue - 智慧化生產流程管理系統

## 專案簡介

OptimizationFlow-Vue 是一套專為製造業設計的輕量級生產流程管理系統。系統前端採用 Vue.js 和 HTML/CSS/JavaScript，後端使用 PHP 和 Python 腳本，並整合了磅秤和條碼列印機等硬體設備，旨在實現從工單導入、生產、秤重、檢驗到標籤列印的全流程數位化管理，提升生產效率和數據準確性。

## 核心功能

- **使用者權限管理**: 內建登入系統，區分不同使用者。
- **工單管理**: 支援從 Excel 檔案匯入工單資料，方便快速建立生產任務。
- **即時機台監控**: 提供機台看板，即時顯示各機台的運行狀態、目前工單等資訊。
- **生產記錄與追蹤**:
    - 掃描條碼記錄生產資訊。
    - 整合電子磅秤自動獲取重量，計算數量。
    - 產生唯一的產品條碼，用於後續流程追蹤。
- **條碼與標籤列印**:
    - 自動為每批次產品生成並列印條碼標籤。
    - 支援標籤內容的客製化。
- **數據查詢與報表**: 提供生產紀錄查詢功能，方便追溯與分析。
- **硬體整合**:
    - 透過 Python 腳本與序列埠磅秤通訊。
    - 支援 Zebra 條碼印表機進行標籤列印。

## 技術棧

- **前端**: HTML, CSS, JavaScript, Vue.js, jQuery, Axios, Select2, html5-qrcode.js, xlsx.js
- **後端**: PHP, Python
- **資料庫**: MySQL / MariaDB
- **硬體通訊**: Pyserial (Python)
- **標籤列印**: Zebra (Python)

## 系統架構

```
OptimizationFlow-Vue/
│
├── backend/              # PHP API 後端
│   ├── config.php        # 資料庫與環境變數設定
│   ├── weight.php        # 秤重與生產記錄 API
│   ├── barcodePrint.php  # 條碼列印 API
│   └── ...               # 其他 PHP 檔案
│
├── py_scripts/           # Python 腳本 (建議將 .py 檔案移入此目錄)
│   ├── get_weight.py     # 讀取磅秤重量
│   ├── Label.py          # 列印生產標籤
│   └── barcode.py        # 列印初始條碼
│
├── *.html                # 前端 UI 頁面 (index.html, weight.html, query.html 等)
├── css/                  # CSS 樣式
├── js/                   # JavaScript 函式庫與腳本
│
├── database.sql          # 資料庫結構與初始數據
├── requirements.txt      # Python 套件依賴清單
├── .env.example          # 環境變數範例檔
└── README.md             # 專案說明
```

## 安裝與部署

**1. 環境準備**
   - 一個網頁伺服器 (例如 Apache, Nginx) 且支援 PHP。
   - MySQL 或 MariaDB 資料庫。
   - Python 3.x 環境。

**2. 後端設定 (PHP)**
   - 將專案檔案部署到網頁伺服器的根目錄。
   - 安裝 PHP 的 `mysqli` 擴充功能。
   - 參考 `backend/config.php`，設定資料庫連線資訊。建議使用環境變數來管理敏感資訊。

**3. 資料庫設定**
   - 在 MySQL 中建立一個名為 `excel_manager` 的資料庫。
   - 將 `database.sql` 檔案匯入到 `excel_manager` 資料庫中，以建立所需的資料表和初始資料。

**4. Python 腳本設定**
   - 建議為 Python 腳本建立一個虛擬環境。
     ```bash
     # 進入專案目錄
     cd /path/to/OptimizationFlow-Vue

     # 建立虛擬環境
     python -m venv .venv

     # 啟用虛擬環境 (Windows)
     .venv\Scripts\activate

     # 啟用虛擬環境 (Linux / macOS)
     source .venv/bin/activate

     # 安裝所需的 Python 套件
     pip install -r requirements.txt
     ```
   - 確保 PHP 後端有權限執行 Python 腳本，並在 `backend/config.php` 或相關檔案中設定正確的 Python 直譯器路徑和腳本路徑。

**5. 硬體設定**
   - 根據 `get_weight.py`, `Label.py` 等腳本中的設定，配置磅秤的序列埠位址和印表機的 IP 位址。建議將這些設定也移至環境變數中管理。

## 模組說明

- **`index.html`**: 使用者登入頁面。
- **`import.html`**: 匯入 Excel 工單資料的頁面。
- **`vehicle.html`**: 機台看板，顯示所有機台的即時狀態。
- **`weight.html` / `weight2.html`**: 主要生產作業頁面，用於秤重、記錄生產資訊及列印標籤。
- **`query.html`**: 生產紀錄查詢頁面。
- **`barcodePrint.html`**: 初始條碼列印頁面。
- **`daily_report.html`**: 每日報表頁面。

## 資料流程

1. **條碼列印流程**:
   - 前端 → `barcodePrint.php` → 資料庫寫入 → `barcode.py` → 印表機
2. **標籤列印流程**:
   - 前端 → `weight.php` → 資料庫寫入 → `Label.py` → 印表機
3. **秤重流程**:
   - 前端 → `weight.php` → `get_weight.py` → 磅秤設備 → 回傳數據

## 安全考量

- **環境變數**: 請務必使用 `.env` 檔案來管理資料庫密碼、設備 IP 等敏感資訊，並將 `.env` 檔案加入到 `.gitignore` 中，避免上傳到版本控制系統。
- **使用者輸入**: 所有來自前端的輸入都應在後端進行驗證和清理，以防止 SQL 注入等攻擊。
- **檔案權限**: 確保日誌檔案和上傳目錄有正確的檔案權限，防止未經授權的存取。
