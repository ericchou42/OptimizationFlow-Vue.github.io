import mysql.connector
from mysql.connector import Error
from zebra import Zebra
import socket

def connect_to_database():
    try:
        connection = mysql.connector.connect(
            host="127.0.0.1",
            database="excel_manager",
            user="root",
            password=""
        )
        if connection.is_connected():
            return connection
    except Error as e:
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
        print(f"查詢錯誤: {e}")
        return None

def print_zebra_label(data):
    try:
        # 定義標籤參數
        # XY軸位置
        x_position = 50
        x_position_right = 600
        y_position = 50
        # 條碼高度和模組寬度
        barcode_height = 100
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

        # 設定 Code 128 條碼參數
        zpl_command += f"^BCN,{barcode_height},Y,N,N"
        zpl_command += f"^FD{data['工單號']}-{data['機台']}-01^FS" #工單號-機台-箱數
        
        # 日期
        zpl_command += f"^FO{x_position},{y_position + barcode_height + 80}"
        zpl_command += "^A0N,50,50"
        zpl_command += f"^FDDate:3/11^FS"

        # 工單號
        zpl_command += f"^FO{x_position},{y_position + barcode_height + 160}"
        zpl_command += "^A0N,50,50"
        zpl_command += f"^FDWorkNumber:{data['工單號']}^FS"

        # 品名
        zpl_command += "^CI0,14,15,28" # 使用 Big5 編碼 (台灣繁體中文)
        zpl_command += f"^FO{x_position},{y_position + barcode_height + 240}"
        zpl_command += "^A0N,50,50"
        zpl_command += f"^FDProductName:081-5052-1^FS"


        # 人員
        zpl_command += f"^FO{x_position},{y_position + barcode_height + 320}"
        zpl_command += "^A0N,50,50"
        zpl_command += f"^FDPersonne:Peter^FS"

        # 工序
        zpl_command += f"^FO{300},{y_position + barcode_height + 80}"
        zpl_command += "^A0N,50,50"
        zpl_command += f"^FDProcess:10^FS"

        # 車台
        zpl_command += f"^FO{x_position_right},{y_position + barcode_height + 80}"
        zpl_command += "^A0N,50,50"
        zpl_command += f"^FDChassis: {data['機台']}^FS"


        # 箱數
        zpl_command += f"^FO{x_position_right},{y_position + barcode_height + 160}"
        zpl_command += "^A0N,50,50"
        zpl_command += f"^FDBox:1^FS"

        # 班別
        zpl_command += f"^FO{x_position_right},{y_position + barcode_height + 320}"
        zpl_command += "^A0N,50,50"
        zpl_command += f"^FDShift:night^FS"

        zpl_command += "^XZ"  # 結束ZPL命令
        
        # 使用網路打印可能解決這個問題
        printer_ip = "172.29.123.150"
        printer_port = 9100
        printer_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        printer_socket.connect((printer_ip, printer_port))

        # 將 ZPL 命令編碼為 UTF-8 或 Big5
        encoded_command = zpl_command.encode('utf-8')  # 或 'big5'
        printer_socket.send(encoded_command)
        printer_socket.close()
        
        print("標籤已發送到打印機")
        
    except Exception as e:
        print(f"打印機錯誤: {e}")

def main():
    # 連接到資料庫
    connection = connect_to_database()
    if not connection:
        return
    
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
            print_option = input("\n是否要打印標籤? (y/n): ")
            if print_option.lower() == 'y':
                print_zebra_label(data)
        else:
            print(f"找不到工單號為 '{work_order_number}' 的資料")
            
    except Exception as e:
        print(f"發生錯誤: {e}")
    finally:
        if connection.is_connected():
            connection.close()

if __name__ == "__main__":
    main()