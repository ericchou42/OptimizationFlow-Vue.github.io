// 整合所有外部JavaScript依賴

// === 簡化版 HTTP 客戶端（替代 Axios）===
const http = {
    async get(url, params = {}) {
      const queryString = Object.keys(params).length ? 
        '?' + new URLSearchParams(params).toString() : '';
      
      try {
        const response = await fetch(url + queryString);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
      } catch (error) {
        console.error('Fetch error:', error);
        throw error;
      }
    },
    
    async post(url, data = {}) {
      try {
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(data)
        });
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
      } catch (error) {
        console.error('Fetch error:', error);
        throw error;
      }
    }
  };
  
  // === 簡化版 Select2 替代品 ===
  class SimpleSelect {
    constructor(element, options = {}) {
      this.element = typeof element === 'string' ? document.querySelector(element) : element;
      this.options = Object.assign({
        placeholder: '請選擇...',
        allowClear: false
      }, options);
      
      if (!this.element) {
        console.error('Select element not found');
        return;
      }
      
      this.init();
    }
    
    init() {
      // 基本初始化
      this.element.classList.add('simple-select');
      
      // 簡單搜尋功能
      this.element.addEventListener('keyup', (e) => {
        const searchText = e.target.value.toLowerCase();
        Array.from(this.element.options).forEach(option => {
          const optionText = option.textContent.toLowerCase();
          option.style.display = optionText.includes(searchText) ? '' : 'none';
        });
      });
    }
    
    // 選擇選項
    select(value) {
      this.element.value = value;
      this.element.dispatchEvent(new Event('change'));
    }
  }
  
  // === Excel 處理功能（簡化版）===
  const Excel = {
    parse(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function(e) {
          try {
            const data = e.target.result;
            // 簡化實現，實際使用時可能需要更複雜的處理
            const rows = data.split('\n').map(row => row.split(','));
            resolve(rows);
          } catch (error) {
            reject(error);
          }
        };
        reader.onerror = reject;
        reader.readAsText(file);
      });
    }
  };
  
  // === 通用工具函數 ===
  const utils = {
    formatDate(date) {
      if (!date) return '';
      const d = new Date(date);
      return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    },
    
    showMessage(message, type, duration = 3000) {
      const msgElement = document.createElement('div');
      msgElement.className = `alert ${type}`;
      msgElement.textContent = message;
      
      document.body.appendChild(msgElement);
      
      setTimeout(() => {
        msgElement.remove();
      }, duration);
    },
    
    toggleFullScreen() {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(e => {
          console.error('無法進入全螢幕模式:', e);
        });
        return true;
      } else {
        if (document.exitFullscreen) {
          document.exitFullscreen();
          return false;
        }
      }
    }
  };
  
  // 提供 Axios 兼容層
  window.axios = http;
  
  // 初始化所有下拉選單
  document.addEventListener('DOMContentLoaded', function() {
    // 初始化所有選擇器
    document.querySelectorAll('select').forEach(select => {
      new SimpleSelect(select);
    });
    
    // 自動聚焦第一個輸入框
    const firstInput = document.querySelector('input');
    if (firstInput) firstInput.focus();
  });