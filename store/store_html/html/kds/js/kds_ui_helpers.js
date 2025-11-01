/**
 * Toptea KDS - UI Helpers
 * 包含自定义对话框 (alert) 逻辑，以兼容套壳环境。
 * Engineer: Gemini | Date: 2025-10-31
 * Revision: 1.2 (Added KDS Clock)
 */

var kdsSimpleAlertModalInstance = null;

/**
 * 启动 KDS 顶部导航栏时钟
 */
function startKdsClock() {
    var clockEl = document.getElementById('kds-clock');
    if (!clockEl) {
        console.warn("KDS Clock element (#kds-clock) not found.");
        return;
    }
    
    function tick() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        clockEl.textContent = h + ':' + m + ':' + s;
    }
    
    try {
        tick(); // 立即执行一次
        setInterval(tick, 1000); // 每秒更新
    } catch (e) {
        console.error("Failed to start KDS Clock:", e);
    }
}


// 延迟初始化，确保 Bootstrap 和 DOM 元素已加载
document.addEventListener('DOMContentLoaded', function() {
    // 【关键修复】将 getElementById 移入事件监听器内部
    var kdsSimpleAlertModalEl = document.getElementById('kdsSimpleAlertModal');
    
    if (kdsSimpleAlertModalEl) {
        kdsSimpleAlertModalInstance = new bootstrap.Modal(kdsSimpleAlertModalEl);
    } else {
        console.error("KDS Alert Modal HTML (kdsSimpleAlertModal) 未在 main.php 中找到。");
    }
    
    // 启动时钟
    startKdsClock();
});


/**
 * 显示一个自定义的 KDS 提示框，替代 alert()。
 * @param {string} message 要显示的消息
 * @param {boolean} isError true 会显示错误图标和红色标题, false 显示成功/信息
 */
function showKdsAlert(message, isError = false) {
    if (!kdsSimpleAlertModalInstance) {
        // 后备方案，以防模态框未正确加载
        console.warn("KDS Alert Modal 未初始化，回退到原生 alert。");
        alert(message); // 在套壳中这会失败，但在PC上至少能看到错误
        return;
    }

    var modalTitleEl = document.getElementById('kdsSimpleAlertTitle');
    var modalBodyEl = document.getElementById('kdsSimpleAlertBody');
    var modalIconEl = document.getElementById('kdsSimpleAlertIcon');

    if (modalTitleEl && modalBodyEl && modalIconEl) {
        if (isError) {
            modalTitleEl.textContent = '操作失败';
            modalTitleEl.style.color = '#dc3545'; // Bootstrap Danger color
            modalIconEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger" style="font-size: 1.5rem;"></i>';
        } else {
            modalTitleEl.textContent = '操作成功';
            modalTitleEl.style.color = '#198754'; // Bootstrap Success color
            modalIconEl.innerHTML = '<i class="bi bi-check-circle-fill text-success" style="font-size: 1.5rem;"></i>';
        }
        
        modalBodyEl.textContent = message;
        kdsSimpleAlertModalInstance.show();
    }
}