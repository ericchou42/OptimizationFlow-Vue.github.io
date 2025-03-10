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
        SELECT * FROM uploaded_data 
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
        # 使用本地打印機連接
        # zebra_printer = Zebra('ZDesigner ZT610-600dpi ZPL')
        
        # 也可使用網絡打印機連接
        printer_ip = "172.29.123.150"
        printer_port = 9100
        printer_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        printer_socket.connect((printer_ip, printer_port))
        zebra_printer = Zebra(printer_socket)

        # 定義標籤參數
        x_position = 100
        y_position = 50
        barcode_height = 100
        module_width = 2
        
        # 準備ZPL命令 - 使用英文標籤避免編碼問題
        zpl_command = "^XA"  # 開始ZPL命令
        
        # 添加工單號條碼
        zpl_command += f"^FO{x_position},{y_position}"
        zpl_command += f"^BY{module_width}"
        zpl_command += f"^BCN,{barcode_height},Y,N,N"
        zpl_command += f"^FD{data['工單號']}^FS"
        
        # 添加工單號文字 - 使用英文標籤
        zpl_command += f"^FO{x_position},{y_position + barcode_height + 10}"
        zpl_command += "^A0N,25,25"
        zpl_command += f"^FDWork Order: {data['工單號']}^FS"
        
        # 添加品名 - 使用英文標籤
        zpl_command += f"^FO{x_position},{y_position + barcode_height + 45}"
        zpl_command += "^A0N,20,20"
        zpl_command += f"^FDProduct: {str(data['品名']).encode('ascii', 'ignore').decode('ascii')}^FS"
        
        # 添加料號 (如果有) - 使用英文標籤
        if data['料號']:
            zpl_command += f"^FO{x_position},{y_position + barcode_height + 75}"
            zpl_command += "^A0N,20,20"
            zpl_command += f"^FDMaterial: {data['料號']}^FS"
        
        # 添加交期 (如果有) - 使用英文標籤
        if data['交期']:
            zpl_command += f"^FO{x_position},{y_position + barcode_height + 105}"
            zpl_command += "^A0N,20,20"
            zpl_command += f"^FDDelivery: {data['交期']}^FS"
        
        # 添加機台(預) (如果有) - 使用英文標籤
        if data['機台(預)']:
            zpl_command += f"^FO{x_position},{y_position + barcode_height + 135}"
            zpl_command += "^A0N,20,20"
            zpl_command += f"^FDMachine: {data['機台(預)']}^FS"
        
        zpl_command += "^XZ"  # 結束ZPL命令
        
        # 發送ZPL命令到打印機
        zebra_printer.output(zpl_command)
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