import mysql.connector
from mysql.connector import Error
from zebra import Zebra
import socket
import sys
import datetime
import logging
import os
from dotenv import load_dotenv

# 載入環境變數
load_dotenv()

# 設定日誌
logging.basicConfig(
    filename='/tmp/label_log.txt',
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    encoding='utf-8'  # 添加明確的編碼設定
)

def connect_to_database():
    try:
        connection = mysql.connector.connect(
            host=os.getenv('DB_HOST', "127.0.0.1"),
            database=os.getenv('DB_NAME', "excel_manager"),
            user=os.getenv('DB_USER', "root"),
            password=os.getenv('DB_PASSWORD', "sfc")
        )
        if connection.is_connected():
            return connection
    except Error as e:
        logging.error(f"資料庫連接錯誤: {e}")
        print(f"資料庫連接錯誤: {e}")
    return None

def get_work_order_data(connection, work_order_number):
    try:
        cursor = connection.cursor(dictionary=True)
        query = """
        SELECT * FROM `機台看板` 
        WHERE 工單號 = %s
        """
        cursor.execute(query, (work_order_number,))
        result = cursor.fetchone()
        cursor.close()
        return result
    except Error as e:
        logging.error(f"查詢錯誤: {e}")
        print(f"查詢錯誤: {e}")
        return None

# 新增文字換行函數
def wrap_text(text, max_length):
    """將文字按最大長度分割為多行"""
    if len(text) <= max_length:
        return [text]
    
    lines = []
    while len(text) > 0:
        if len(text) <= max_length:
            lines.append(text)
            break
        
        # 對於中文等無空格的文字，直接在最大長度處截斷
        lines.append(text[:max_length])
        text = text[max_length:]
    
    return lines

def print_zebra_label(data):
    try:
        logging.info(f"開始打印標籤，資料: {data}")
        
        # 獲取當前日期
        current_date = datetime.datetime.now().strftime("%Y/%m/%d")
        
        # 定義標籤參數
        # XY軸位置
        x_position = 170
        x_position_right = 560
        y_position = 30

        # 條碼模組寬度(1-10)，數字越大條碼越寬
        module_width = 6
        
        # 準備ZPL命令 - 使用英文標籤避免編碼問題
        zpl_command = "^XA"  # 開始ZPL命令

        # 設定中文碼頁
        zpl_command += "^CI28" # 使用 Big5 編碼 (台灣繁體中文)
        
        # 設定條碼起始位置
        zpl_command += f"^FO{x_position},{y_position}" 

        # 添加工單號條碼尺寸
        zpl_command += f"^BY{module_width}"

        # 生
        zpl_command += f"^FO{30},{100}"
        zpl_command += "^A@N,80,80,E:ARIAL.TTF"
        zpl_command += f"^FD生^FS"

        # 產
        zpl_command += f"^FO{30},{200}"
        zpl_command += "^A@N,80,80,E:ARIAL.TTF"
        zpl_command += f"^FD產^FS"

        # 部
        zpl_command += f"^FO{30},{300}"
        zpl_command += "^A@N,80,80,E:ARIAL.TTF"
        zpl_command += f"^FD部^FS"

        # |
        zpl_command += f"^FO{60},{400}"
        zpl_command += "^A@N,30,30,E:ARIAL.TTF"
        zpl_command += f"^FD|^FS"

        # MM4
        zpl_command += f"^FO{30},{450}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FDMM4^FS"

        # HSF
        zpl_command += f"^FO{30},{y_position + 530}"
        zpl_command += "^A@N,30,30,E:ARIAL.TTF"
        zpl_command += f"^FD[HSF]^FS"

        # 日期
        zpl_command += f"^FO{x_position},{y_position}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD日期:{current_date}^FS"

        # 品名自動換行處理 - 限制最多兩行(100和200位置)
        full_text = f"品名:{data['品名']}"
        max_length = 25  # 每行最大字符數，可以根據需要調整
        wrapped_lines = wrap_text(full_text, max_length)
        
        # 如果超過兩行，只取前兩行
        if len(wrapped_lines) > 2:
            wrapped_lines = wrapped_lines[:2]
        
        # 打印每一行，100和200兩行位置
        line_positions = [100, 200]
        for i, line in enumerate(wrapped_lines):
            if i < len(line_positions):  # 確保不超過預定的行數
                zpl_command += f"^FO{x_position},{y_position + line_positions[i]}"
                zpl_command += "^A@N,60,60,E:ARIAL.TTF"
                zpl_command += f"^FD{line}^FS"

        # 品名
        zpl_command += f"^FO{x_position},{y_position + 100}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD品名:{data['品名']}^FS"

        # 料號
        zpl_command += f"^FO{x_position},{y_position + 300}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD料號:{data['料號']}^FS"

        # 淨重
        zpl_command += f"^FO{x_position_right},{y_position + 400}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD淨重:{data['重量']}^FS"

        # 數量
        zpl_command += f"^FO{x_position},{y_position + 500}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD數量:{data['數量']}^FS"

        # 班別
        zpl_command += f"^FO{x_position_right},{y_position + 600}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD班別:{data['班別']}^FS"

        # 機台
        zpl_command += f"^FO{x_position},{y_position + 400}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD機台:{data['機台']}^FS"

        # 後續單位
        zpl_command += f"^FO{x_position_right},{y_position + 500}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD後續單位:{data['後續單位']}^FS"

        # 磅貨
        zpl_command += f"^FO{x_position},{y_position + 600}"
        zpl_command += "^A@N,60,60,E:ARIAL.TTF"
        zpl_command += f"^FD磅貨:{data['檢驗人']}^FS"

        # 異常品
        if '異常' in data and data['異常'] == 1:
            zpl_command += f"^FO{750},{y_position}"
            zpl_command += "^A@N,60,60,E:ARIAL.TTF"
            zpl_command += "^FR" # 反轉顏色
            # zpl_command += "^FB400,1,0,C" # 文字區塊，居中對齊
            zpl_command += f"^FD異常^FS"
        
        # 結束ZPL命令
        zpl_command += "^XZ"  
        
        # 使用網路打印可能解決這個問題
        printer_ip = os.getenv('LABEL_PRINTER_IP', "172.29.123.150")
        printer_port = int(os.getenv('LABEL_PRINTER_PORT', 9100))
        
        logging.info(f"嘗試連接打印機 {printer_ip}:{printer_port}")
        printer_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        printer_socket.connect((printer_ip, printer_port))

        # 將 ZPL 命令編碼為 UTF-8 或 Big5
        encoded_command = zpl_command.encode('utf-8')  # 或 'big5'
        printer_socket.send(encoded_command)
        printer_socket.close()
        
        logging.info("標籤已發送到打印機")
        print("標籤已發送到打印機")
        return True
        
    except Exception as e:
        logging.error(f"打印機錯誤: {e}")
        print(f"打印機錯誤: {e}")
        return False

def main():
    logging.info("Label.py 執行開始")
    logging.info(f"命令行參數: {sys.argv}")
    
    # 檢查命令行參數
    if len(sys.argv) >= 11:  # 增加一個參數
        # 從命令行直接使用參數
        work_order = sys.argv[1]
        product_name = sys.argv[2]
        part_number = sys.argv[3]
        operator = sys.argv[4]
        machine = sys.argv[5]
        weight = sys.argv[6]
        quantity = sys.argv[7]
        inspector = sys.argv[8]
        next_unit = sys.argv[9]  # 新增後續單位參數
        shift = sys.argv[10] if len(sys.argv) > 10 else '日'  # 班別參數位置調整
        abnormal = int(sys.argv[11]) if len(sys.argv) > 11 else 0  # 新增異常參數，預設為0
        
        # 構建數據字典
        data = {
            '工單號': work_order,
            '品名': product_name,
            '料號': part_number,
            '顧車': operator,
            '機台': machine,
            '重量': weight,
            '數量': quantity,
            '檢驗人': inspector,
            '後續單位': next_unit,  # 新增後續單位
            '班別': shift,
            '異常': abnormal  # 添加異常狀態
        }
        
        # 直接打印標籤
        success = print_zebra_label(data)
        sys.exit(0 if success else 1)
    else:
        # 連接到資料庫
        connection = connect_to_database()
        if not connection:
            sys.exit(1)
        
        try:
            # 讓用戶輸入工單號
            work_order_number = input("請輸入工單號: ")
            
            # 獲取工單數據
            data = get_work_order_data(connection, work_order_number)
            
            if data:
                print("\n工單資料:")
                print("-" * 50)
                for key, value in data.items():
                    if value is not None:  # 只顯示有值的欄位
                        print(f"{key}: {value}")
                print("-" * 50)
                
                # 詢問是否要打印標籤
                success = print_zebra_label(data)
                sys.exit(0 if success else 1)
            else:
                print(f"找不到工單號為 '{work_order_number}' 的資料")
                sys.exit(1)
                
        except Exception as e:
            logging.error(f"發生錯誤: {e}")
            print(f"發生錯誤: {e}")
            sys.exit(1)
        finally:
            if connection.is_connected():
                connection.close()

if __name__ == "__main__":
    main()