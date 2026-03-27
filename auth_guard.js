// auth_guard.js - Auto Lock System (ไม่มี Banner)
(function() {
    'use strict';
    
    let isLoggedIn = false;
    
    async function checkAuthStatus() {
        try {
            const response = await fetch('auth_status.php');
            const data = await response.json();
            isLoggedIn = data.logged_in;
            return isLoggedIn;
        } catch (error) {
            console.error('Auth check failed:', error);
            return false;
        }
    }
    
    function lockButtons() {
        const protectedButtons = document.querySelectorAll('[data-require-auth]');
        const protectedLinks = document.querySelectorAll('a[data-require-auth]');
        const protectedForms = document.querySelectorAll('form[data-require-auth]');
        
        protectedButtons.forEach(btn => {
            if (!isLoggedIn) {
                btn.disabled = true;
                btn.classList.add('auth-locked');
                btn.setAttribute('title', '🔒 กรุณาปลดล็อคระบบก่อน');
                
                if (!btn.querySelector('.lock-icon')) {
                    const icon = document.createElement('span');
                    icon.className = 'lock-icon';
                    icon.textContent = '🔒 ';
                    btn.prepend(icon);
                }
            } else {
                btn.disabled = false;
                btn.classList.remove('auth-locked');
                btn.removeAttribute('title');
                
                const icon = btn.querySelector('.lock-icon');
                if (icon) icon.remove();
            }
        });
        
        protectedLinks.forEach(link => {
            if (!isLoggedIn) {
                link.classList.add('auth-locked');
                link.style.pointerEvents = 'none';
                link.style.opacity = '0.5';
                link.setAttribute('title', '🔒 กรุณาปลดล็อคระบบก่อน');
                link.addEventListener('click', preventAction);
            } else {
                link.classList.remove('auth-locked');
                link.style.pointerEvents = '';
                link.style.opacity = '';
                link.removeAttribute('title');
                link.removeEventListener('click', preventAction);
            }
        });
        
        protectedForms.forEach(form => {
            if (!isLoggedIn) {
                const inputs = form.querySelectorAll('input, textarea, select, button');
                inputs.forEach(input => {
                    input.disabled = true;
                });
                
                form.addEventListener('submit', preventAction);
                
                if (!form.querySelector('.auth-warning-banner')) {
                    const banner = document.createElement('div');
                    banner.className = 'auth-warning-banner';
                    banner.innerHTML = '🔒 ระบบถูกล็อก กรุณา<a href="login.php" style="color:#0984e3;margin:0 5px">ปลดล็อค</a>ก่อนแก้ไขข้อมูล';
                    form.prepend(banner);
                }
            } else {
                const inputs = form.querySelectorAll('input, textarea, select, button');
                inputs.forEach(input => {
                    input.disabled = false;
                });
                
                form.removeEventListener('submit', preventAction);
                
                const banner = form.querySelector('.auth-warning-banner');
                if (banner) banner.remove();
            }
        });
    }
    
    function preventAction(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (confirm('ต้องการปลดล็อคระบบเพื่อแก้ไขข้อมูล?')) {
            window.location.href = 'login.php?return=' + encodeURIComponent(window.location.href);
        }
        
        return false;
    }
    
    function autoDetectProtectedElements() {
        const keywords = ['เพิ่ม', 'แก้ไข', 'ลบ', 'บันทึก', 'delete', 'edit', 'add', 'save', 'update', 'create'];
        const buttons = document.querySelectorAll('button, a.btn, input[type="submit"]');
        
        buttons.forEach(btn => {
            const text = btn.textContent.toLowerCase();
            const hasKeyword = keywords.some(keyword => text.includes(keyword.toLowerCase()));
            
            if (hasKeyword && !btn.hasAttribute('data-require-auth')) {
                btn.setAttribute('data-require-auth', 'true');
            }
        });
        
        const forms = document.querySelectorAll('form[method="POST"], form[method="post"]');
        forms.forEach(form => {
            if (!form.action.includes('login.php') && !form.hasAttribute('data-public')) {
                form.setAttribute('data-require-auth', 'true');
            }
        });
    }
    
    function interceptAjax() {
        const originalFetch = window.fetch;
        window.fetch = async function(...args) {
            const url = args[0];
            const options = args[1] || {};
            
            if (options.method && ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
                if (!isLoggedIn) {
                    if (confirm('ต้องการปลดล็อคระบบเพื่อทำรายการนี้?')) {
                        window.location.href = 'login.php?return=' + encodeURIComponent(window.location.href);
                    }
                    throw new Error('Unauthorized: Please login first');
                }
            }
            
            return originalFetch.apply(this, args);
        };
    }
    
    // ❌ ลบฟังก์ชัน showLockBanner() ออกแล้ว
    
    function injectStyles() {
        if (document.getElementById('auth-guard-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'auth-guard-styles';
        style.textContent = `
            .auth-locked {
                opacity: 0.6 !important;
                cursor: not-allowed !important;
                background: #cccccc !important;
                color: #666666 !important;
            }
            
            .auth-locked:hover {
                transform: none !important;
                box-shadow: none !important;
            }
            
            .auth-warning-banner {
                background: #fff3cd;
                border: 2px solid #ffc107;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 20px;
                text-align: center;
                font-weight: 600;
                color: #856404;
            }
        `;
        document.head.appendChild(style);
    }
    
    async function init() {
        await checkAuthStatus();
        injectStyles();
        autoDetectProtectedElements();
        lockButtons();
        // ❌ ลบบรรทัด showLockBanner() ออก
        interceptAjax();
        
        setInterval(async () => {
            await checkAuthStatus();
            lockButtons();
            // ❌ ลบบรรทัด showLockBanner() ออก
        }, 30000);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    window.AuthGuard = {
        checkStatus: checkAuthStatus,
        isLoggedIn: () => isLoggedIn,
        refresh: lockButtons
    };
})();