/**
 * Toptea KDS - kds_expiry.js
 * JavaScript for Expiry Tracking Page
 * Engineer: Gemini | Date: 2025-10-31
 * Revision: 10.0 (Change "Discard" to "Scrap")
 */

let confirmationModal = null;
let actionToConfirm = null;

// 全局函数：设置并显示效期操作的确认模态框
function setupAndShowExpiryModal(buttonElement) {
    const I18N = { 'zh-CN': { confirm_used: '您确定要将“{materialName}”标记为【已用完】吗？', confirm_discard: '您确定要将“{materialName}”标记为【报废】吗？此操作通常用于已过期的物品。' }, 'es-ES': { confirm_used: '¿Confirmar que "{materialName}" ha sido 【Usado】?', confirm_discard: '¿Confirmar que desea 【Desechar】 "{materialName}"? Esta acción es para artículos caducados.' } };
    const currentLang = localStorage.getItem("kds_lang") || "zh-CN";
    const translations = I18N[currentLang] || I18N['zh-CN'];

    const action = buttonElement.dataset.action;
    const materialName = buttonElement.dataset.materialName;
    const confirmMessageKey = action === 'USED' ? 'confirm_used' : 'confirm_discard';
    const confirmationMessage = translations[confirmMessageKey].replace('{materialName}', materialName);
    
    actionToConfirm = function() {
        performExpiryAction(buttonElement);
    };

    const modalBody = document.getElementById('confirmationModalBody');
    if (modalBody) {
        modalBody.textContent = confirmationMessage;
    }
    if (confirmationModal) {
        confirmationModal.show();
    }
}

// 全局函数：执行实际的API调用
async function performExpiryAction(buttonElement) {
    const I18N = { 'zh-CN': { action_failed: '操作失败', no_items: '当前没有追踪中的物品。' }, 'es-ES': { action_failed: 'La operación falló', no_items: 'No hay artículos en seguimiento.' } };
    const currentLang = localStorage.getItem("kds_lang") || "zh-CN";
    const translations = I18N[currentLang] || I18N['zh-CN'];
    const API_UPDATE_URL = 'api/update_expiry_status.php';

    const action = buttonElement.dataset.action;
    const itemId = buttonElement.dataset.id;
    
    const originalText = buttonElement.innerHTML;
    buttonElement.disabled = true;
    buttonElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>`;
    try {
        const response = await fetch(API_UPDATE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ item_id: itemId, status: action }) });
        const result = await response.json();
        if (response.ok && result.status === 'success') {
            const row = buttonElement.closest('tr');
            if (row) row.remove();
            if (document.getElementById('expiry-list-body').rows.length === 0) {
                 document.getElementById('expiry-list-body').innerHTML = `<tr><td colspan="5" class="text-center">${translations.no_items}</td></tr>`;
            }
        } else {
            throw new Error(result.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Failed to update status:', error);
        showKdsAlert(`${translations.action_failed}: ${error.message}`, true);
    } finally {
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalText;
    }
}


document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('confirmationModal');
    if (modalElement) {
        confirmationModal = new bootstrap.Modal(modalElement);
        document.getElementById('confirm-action-btn').addEventListener('click', function () {
            if (typeof actionToConfirm === 'function') {
                actionToConfirm();
            }
            confirmationModal.hide();
            actionToConfirm = null;
        });
        modalElement.addEventListener('hidden.bs.modal', function () {
            actionToConfirm = null;
        });
    }

    const I18N_PAGE = { 'zh-CN': { expiry_title: '效期追踪', btn_back_kds: '返回KDS', th_material: '物料', th_opened_at: '开封/制作时间', th_expires_at: '过期时间', th_time_left: '剩余时间', th_actions: '操作', btn_used: '已用完', btn_discard: '报废', no_items: '当前没有追踪中的物品。', loading_failed: '加载效期物品失败！', time_left_format: '{d}天 {h}小时 {m}分钟', status_expired: '已过期' }, 'es-ES': { expiry_title: 'Seguimiento de Caducidad', btn_back_kds: 'Volver a KDS', th_material: 'Material', th_opened_at: 'Abierto/Preparado', th_expires_at: 'Caduca', th_time_left: 'Tiempo Restante', th_actions: 'Acciones', btn_used: 'Usado', btn_discard: 'Desechar', no_items: 'No hay artículos en seguimiento.', loading_failed: '¡Fallo al cargar artículos!', time_left_format: '{d}d {h}h {m}m', status_expired: 'Caducado' } };
    const currentLang = localStorage.getItem("kds_lang") || "zh-CN";
    const translations = I18N_PAGE[currentLang] || I18N_PAGE['zh-CN'];
    
    document.querySelectorAll('[data-i18n-key]').forEach(el => {
        const key = el.getAttribute('data-i18n-key');
        if (translations[key]) el.textContent = translations[key];
    });

    const API_GET_URL = 'api/get_kds_expiry_items.php';
    const listBody = document.getElementById('expiry-list-body');

    function createRow(item) {
        const tr = document.createElement('tr');
        const langKey = currentLang === 'es-ES' ? 'es' : 'zh';
        const materialName = item[`name_${langKey}`] || item.name_zh;
        const timeLeft = calculateTimeLeft(item.expires_at);

        tr.className = `status-${timeLeft.class}`;

        tr.innerHTML = `
            <td><strong>${materialName}</strong></td>
            <td>${formatTime(item.opened_at)}</td>
            <td>${formatTime(item.expires_at)}</td>
            <td>${timeLeft.text}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-success btn-action" 
                        data-action="USED" data-id="${item.id}" data-material-name="${materialName}"
                        onclick="setupAndShowExpiryModal(this)">
                    ${translations.btn_used}
                </button>
                <button class="btn btn-sm btn-outline-danger btn-action" 
                        data-action="DISCARDED" data-id="${item.id}" data-material-name="${materialName}"
                        onclick="setupAndShowExpiryModal(this)">
                    ${translations.btn_discard}
                </button>
            </td>`;
        
        return tr;
    }
    
    function formatTime(isoString) { const date = new Date(isoString); return date.toLocaleString('zh-CN', { hour12: false, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).replace(/\//g, '-'); }
    function calculateTimeLeft(expiresAt) { const now = new Date(); const expiry = new Date(expiresAt); let diff = expiry - now; if (diff <= 0) { return { text: `<span class="text-danger fw-bold">${translations.status_expired}</span>`, class: 'danger' }; } const hoursLeft = diff / (1000 * 60 * 60); let statusClass = 'normal'; if (hoursLeft <= 2) statusClass = 'warning'; if (hoursLeft <= 0) statusClass = 'danger'; const d = Math.floor(diff / (1000 * 60 * 60 * 24)); diff -= d * (1000 * 60 * 60 * 24); const h = Math.floor(diff / (1000 * 60 * 60)); diff -= h * (1000 * 60 * 60); const m = Math.floor(diff / (1000 * 60)); const text = translations.time_left_format.replace('{d}', d).replace('{h}', h).replace('{m}', m); return { text, class: statusClass }; }
    async function fetchAndRenderItems() { try { const response = await fetch(API_GET_URL); if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`); const result = await response.json(); if (result.status === 'success' && result.data) { listBody.innerHTML = ''; if (result.data.length > 0) { result.data.forEach(item => listBody.appendChild(createRow(item))); } else { listBody.innerHTML = `<tr><td colspan="5" class="text-center">${translations.no_items}</td></tr>`; } } else { throw new Error(result.message || 'Invalid data from API'); } } catch (error) { console.error('Failed to fetch expiry items:', error); listBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger fw-bold">${translations.loading_failed}</td></tr>`; } }

    fetchAndRenderItems();
    setInterval(fetchAndRenderItems, 60000);
});