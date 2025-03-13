import serial
import time

# 配置串口參數
port = 'COM4'           # 指定串口號
baudrate = 9600         # 波特率，可根據需要調整
timeout = 1             # 設置讀取的超時時間

# 開啟串口
try:
    ser = serial.Serial(port, baudrate, timeout=timeout)
    print(f"已連接到 {port}")

    # 持續讀取數據
    while True:
        if ser.in_waiting > 0:  # 如果有數據待讀取
            data = ser.readline().decode('utf-8').strip()  # 讀取數據並去除空白字符
            print(f"收到數據: {data}")
        else:
            time.sleep(0.1)  # 如果沒數據，稍微等待一段時間

except serial.SerialException as e:
    print(f"串口打開失敗：{e}")

finally:
    if ser.is_open:
        ser.close()
        print(f"{port} 已關閉")