from zebra import Zebra
import socket

# # 使用 WiFi 連接打印機
printer_ip = "172.29.123.150"  # 替換為您打印機的 IP 地址
printer_port = 9100  # Zebra 打印機默認端口為 9100

# try:
#     # 建立 TCP Socket 連接
#     printer_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
#     printer_socket.connect((printer_ip, printer_port))
    
#     # 創建 Zebra 對象，使用網絡打印機
#     zebra_printer = Zebra(printer_socket)

# # ... rest of the code remains the same ...

#     # 發送 ZPL 指令到 Zebra 打印機
#     zebra_printer.output(zpl_command)
#     print("ZPL 指令已成功發送到打印機。")
    
# except Exception as e:
#     print(f"連接打印機時發生錯誤: {str(e)}")
# finally:
#     # 關閉 socket 連接
#     if 'printer_socket' in locals():
#         printer_socket.close()


# 創建 Zebra 對象，並指定打印機隊列名稱（如 'ZDesigner ZT610-600dpi ZPL'）
zebra_printer = Zebra('ZDesigner ZT610-600dpi ZPL')

# # 使用藍芽連接打印機
# # 請將 COM4 替換為您的藍芽串口號
# zebra_printer = Zebra('COM4')  # Windows 系統使用 COM 端口


# 定義條碼位置變數
x_position = 150  # 條碼的X軸起始位置(點)
y_position = 350  # 條碼的Y軸起始位置(點)

# 定義文字區域變數
text_width = 800  # 文字區域寬度(點)
text_lines = 1    # 文字行數
text_spacing = 0  # 行距
text_align = "C"  # 文字對齊方式: C=置中, L=靠左, R=靠右

# 定義條碼變數
barcode_height = 200  # 條碼高度(點)
module_width = 10      # 條碼模組寬度(1-10)，數字越大條碼越寬
barcode_text = "FD321866"  # 條碼內容

# 定義文字變數
additional_text = "這是額外的文字"  # 要顯示的文字
text_y_offset = 50  # 文字與條碼的垂直間距(點)

# 組合 ZPL 指令 - Code 128 條碼置中
zpl_command = (
    f"^XA"                # 開始 ZPL 指令
    f"^FO{x_position},{y_position}"  # 設定條碼起始位置
    f"^FB{text_width},{text_lines},{text_spacing},{text_align}"  # 設定文字區域格式
    f"^BY{module_width}"              # 修改條碼模組寬度
    f"^BCN,{barcode_height},Y,N,N"  # 設定 Code 128 條碼參數
    f"^FD{barcode_text}^FS"         # 設定條碼內容
    # 新增文字
    f"^FO{x_position},{y_position + barcode_height + text_y_offset}"  # 設定文字位置（在條碼下方）
    f"^FB{text_width},1,0,C"        # 設定文字區域格式
    f"^A0N,30,30"                   # 設定字體（字型,高度,寬度）
    f"^FD{additional_text}^FS"      # 設定文字內容
    f"^XZ"              # 結束 ZPL 指令
)

# 發送 ZPL 指令到 Zebra 打印機
zebra_printer.output(zpl_command)
print("ZPL 指令已成功發送到打印機。")
