import socket
import sys
import logging
import time
import random  # 用於模擬，實際使用時移除
import os
from dotenv import load_dotenv

# 載入環境變數
load_dotenv()

# 設定日誌
# logging.basicConfig(
#     filename='weight_log.txt',
#     level=logging.INFO,
#     format='%(asctime)s - %(levelname)s - %(message)s',
#     encoding='utf-8'  # 添加明確的編碼設定
# )

def get_weight_from_device(host, port):
    """
    從秤重設備獲取重量
    
    Args:
        host (str): 設備 IP 地址
        port (int): 設備端口
        
    Returns:
        float: 獲取的重量值（公斤）
    """
    try:
        logging.info(f"嘗試連接設備 {host}:{port}")
        
        # 創建 socket 連接
        s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        s.settimeout(5)  # 設置超時時間為 5 秒
        s.connect((host, port))
        
        # 接收數據 (不需要發送命令)
        response = s.recv(1024)
        s.close()
        
        # 解析回應並提取重量值
        weight_str = response.decode('utf-8', errors='replace').strip()
        logging.info(f"從設備獲取的原始數據: {weight_str}")
        
        # 解析格式如 "ST,GS,+  5.078kg"
        if "kg" in weight_str:
            # 提取數值部分
            parts = weight_str.split(',')
            if len(parts) >= 3:
                weight_part = parts[2].strip()
                # 去除前面的 '+' 或 '-' 號和單位 'kg'
                weight_value = weight_part.replace('+', '').replace('kg', '').strip()
                weight = float(weight_value)
                logging.info(f"解析後的重量: {weight} kg")
                return weight
            else:
                raise ValueError(f"無法解析重量數據: {weight_str}")
        else:
            raise ValueError(f"回應中沒有找到單位 'kg': {weight_str}")
        
    except socket.timeout:
        logging.error("連接設備超時")
        raise Exception("連接設備超時，請檢查設備是否正常運行")
    except socket.error as e:
        logging.error(f"Socket 錯誤: {e}")
        raise Exception(f"連接設備失敗: {e}")
    except ValueError as e:
        logging.error(f"解析重量值錯誤: {e}")
        raise Exception(f"解析重量值錯誤: {e}")
    except Exception as e:
        logging.error(f"獲取重量時發生未知錯誤: {e}")
        raise

def simulate_weight():
    """
    模擬獲取重量（僅用於測試）
    
    Returns:
        float: 模擬的重量值（2-5公斤之間的隨機值）
    """
    weight = round(random.uniform(2, 5), 3)
    logging.info(f"模擬重量: {weight} kg")
    return weight

def main():
    try:
        # 設備參數
        host = os.getenv('WEIGHT_DEVICE_IP', "192.168.1.100")
        port = int(os.getenv('WEIGHT_DEVICE_PORT', 100))
        
        try:
            # 嘗試從實際設備獲取重量
            weight = get_weight_from_device(host, port)
        except Exception as e:
            logging.warning(f"從設備獲取重量失敗，將使用模擬數據: {e}")
            # 如果實際設備連接失敗，使用模擬數據
            weight = simulate_weight()
        
        # 打印重量值（PHP 將捕獲這個輸出）
        print(weight)
        return 0
        
    except Exception as e:
        logging.error(f"執行錯誤: {e}")
        print("0.000")  # 輸出默認值
        return 1

if __name__ == "__main__":
    sys.exit(main())