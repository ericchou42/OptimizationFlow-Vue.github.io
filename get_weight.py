#!/usr/bin/env python
# -*- coding: utf-8 -*-

import socket
import sys
import logging
import time
import random  # 用於模擬，實際使用時移除
import os
import re
import traceback
from dotenv import load_dotenv

# 載入環境變數
load_dotenv()

# # 設定日誌
# logging.basicConfig(
#     filename='/tmp/weight_log.txt',
#     level=logging.INFO,
#     format='%(asctime)s - %(levelname)s - %(message)s',
#     encoding='utf-8'  # 添加明確的編碼設定
# )

def parse_weight(weight_str):
    """
    解析重量字串，兼容各種可能的格式問題
    
    Args:
        weight_str (str): 原始重量字串
        
    Returns:
        float: 解析後的重量值，如解析失敗則返回None
    """
    if not weight_str:
        logging.error("接收到空的重量數據")
        return None
        
    logging.info(f"正在解析重量字串: '{weight_str}'")
    
    try:
        # 方法1: 如果格式是 "ST,GS,+  5.078kg" 這樣的標準格式
        if "," in weight_str and ("kg" in weight_str or "g" in weight_str):
            parts = weight_str.split(',')
            if len(parts) >= 3:
                weight_part = parts[2].strip()
                # 先移除單位
                for unit in ['kg', 'g']:
                    weight_part = weight_part.replace(unit, '')
                    
                # 移除所有空格並處理加號和減號
                weight_part = weight_part.replace(' ', '').replace('+', '')
                
                # 如果有連續的減號，只保留一個
                if '--' in weight_part:
                    weight_part = '-' + weight_part.replace('--', '')
                    
                # 轉換為浮點數
                weight = float(weight_part)
                logging.info(f"使用標準格式成功解析重量: {weight}")
                return weight
                
        # 方法2: 直接清理字串後轉換
        cleaned_str = weight_str.replace(' ', '').replace('+', '')
        
        # 處理單位
        for unit in ['kg', 'g']:
            cleaned_str = cleaned_str.replace(unit, '')
            
        # 處理連續的減號
        if '--' in cleaned_str:
            cleaned_str = '-' + cleaned_str.replace('--', '')
            
        # 嘗試直接轉換
        try:
            weight = float(cleaned_str)
            logging.info(f"通過清理字串成功解析重量: {weight}")
            return weight
        except ValueError:
            logging.info("方法2失敗，嘗試正則表達式方法")
            
        # 方法3: 使用正則表達式提取數字（最後嘗試的方法）
        # 提取帶符號的浮點數
        pattern = r'(-?\d*\.?\d+)'
        matches = re.findall(pattern, weight_str)
        
        if matches:
            # 如果找到多個數字，取最後一個（通常是重量值）
            weight_value = float(matches[-1])
            logging.info(f"使用正則表達式提取的重量值: {weight_value}")
            return weight_value
        else:
            logging.error(f"無法使用任何方法從字串中提取有效的數字: {weight_str}")
            return None
                
    except Exception as e:
        logging.error(f"解析重量值時發生未知錯誤: {e}")
        logging.error(traceback.format_exc())
        return None

def get_weight_from_device(host, port, retry_count=3):
    """
    從秤重設備獲取重量，增加重試機制
    
    Args:
        host (str): 設備 IP 地址
        port (int): 設備端口
        retry_count (int): 重試次數
        
    Returns:
        float: 獲取的重量值（公斤）
    """
    
    for attempt in range(retry_count):
        try:
            logging.info(f"嘗試連接設備 {host}:{port} (第 {attempt+1}/{retry_count} 次)")
            
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
            
            # 使用增強的解析函數
            weight = parse_weight(weight_str)
            
            if weight is not None:
                logging.info(f"成功獲取重量: {weight} kg")
                return weight
                
            # 如果解析失敗但不是因為連接問題，等待後重試
            time.sleep(1)
            
        except socket.timeout:
            logging.error(f"連接設備超時 (第 {attempt+1} 次嘗試)")
            time.sleep(1)
            
        except socket.error as e:
            logging.error(f"Socket 錯誤 (第 {attempt+1} 次嘗試): {e}")
            time.sleep(1)
            
        except Exception as e:
            logging.error(f"獲取重量時發生未知錯誤 (第 {attempt+1} 次嘗試): {e}")
            logging.error(traceback.format_exc())
            time.sleep(1)
    
    # 所有嘗試都失敗
    logging.error(f"在 {retry_count} 次嘗試後仍無法獲取有效重量")
    raise Exception(f"無法在 {retry_count} 次嘗試後獲取有效重量")

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
        # 初始化 weight 變數，確保它一定會有值
        weight = 0.0
        
        # 設備參數
        host = os.getenv('WEIGHT_DEVICE_IP', "192.168.1.100")
        port = int(os.getenv('WEIGHT_DEVICE_PORT', 100))
        
        # 是否使用模擬模式
        use_simulation = os.getenv('USE_SIMULATION', 'False').lower() in ('true', '1', 'yes')
        
        if use_simulation:
            logging.info("使用模擬模式")
            weight = simulate_weight()
        else:
            try:
                # 嘗試從實際設備獲取重量
                weight = get_weight_from_device(host, port)
                
                # 處理負重量情況
                if weight < 0:
                    logging.warning(f"接收到負重量: {weight}，將轉換為絕對值")
                    weight = abs(weight)
                    
            except Exception as e:
                logging.warning(f"從設備獲取重量失敗: {e}")
                
                # 檢查是否允許在失敗時使用模擬數據
                fallback_to_sim = os.getenv('FALLBACK_TO_SIMULATION', 'True').lower() in ('true', '1', 'yes')
                
                if fallback_to_sim:
                    logging.info("使用模擬數據作為備選")
                    weight = simulate_weight()
                else:
                    # 如果不允許使用模擬數據，則返回0
                    logging.warning("不使用模擬數據，返回 0.000")
                    weight = 0.0
        
        # 打印重量值（PHP 將捕獲這個輸出）
        print(f"{weight:.3f}")
        return 0
        
    except Exception as e:
        logging.error(f"執行錯誤: {e}")
        logging.error(traceback.format_exc())
        print("0.000")  # 輸出默認值
        return 1

if __name__ == "__main__":
    sys.exit(main())