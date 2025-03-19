# 系統架構說明

## 整體系統架構

```
OptimizationFlow-Vue/
│
├── .env                  # 環境變數設定檔（從 .env.example 複製得到）
├── .env.example          # 環境變數範例檔案
├── backend/              # PHP API 後端
│   ├── config.php        # 資料庫連線（已修改使用環境變數）
│   ├── weight.php        # 秤重相關 API（已修改使用環境變數）
│   ├── barcodePrint.php  # 條碼列印 API（已修改使用環境變數）
│   └── ...               # 其他 PHP 檔案
│
├── Label.py              # 標籤列印 Python 腳本（已修改使用環境變數）
├── barcode.py            # 條碼列印 Python 腳本（已修改使用環境變數）
├── get_weight.py         # 秤重 Python 腳本（已修改使用環境變數）
│
├── *.html                # 前端頁面
├── database.sql          # 資料庫結構和初始數據
├── requirements.txt      # Python 套件依賴清單（已更新）
└── README.md             # 專案說明
```

## 環境變數功能

環境變數整合後，系統變得更加靈活且安全。主要功能包括：

1. **資料庫連線設定**：
   - 主機、資料庫名稱、使用者名稱、密碼可依環境而不同

2. **硬體裝置連線設定**：
   - 秤重設備 IP 和埠號
   - 標籤印表機 IP 和埠號
   - 條碼印表機 IP 和埠號

3. **Python 執行環境設定**：
   - Python 執行檔路徑
   - 各 Python 腳本路徑

## 資料流程

1. **條碼列印流程**：
   - 前端 → `barcodePrint.php` → 資料庫寫入 → `barcode.py` → 印表機

2. **標籤列印流程**：
   - 前端 → `weight.php` → 資料庫寫入 → `Label.py` → 印表機

3. **秤重流程**：
   - 前端 → `weight.php` → `get_weight.py` → 重量設備 → 回傳數據

## 記錄檔

系統運行時會產生以下記錄檔：

1. `label_log.txt` - 標籤列印記錄
2. `weight_log.txt` - 秤重記錄
3. `barcode_log.txt` - 條碼列印記錄

請定期檢查這些記錄檔，以確保系統正常運作並排解可能的問題。

## 安全考量

1. **避免在版本控制中包含 `.env` 檔案**
   - 已將 `.env` 加入 `.gitignore`
   - 只提供 `.env.example` 作為範本

2. **環境變數保護敏感資訊**
   - 資料庫憑據
   - 設備 IP 位址和連結資訊

3. **記錄檔安全**
   - 記錄檔可能包含敏感資訊，請確保適當的存取權限
   - 建議設置日誌輪轉以避免日誌檔過大