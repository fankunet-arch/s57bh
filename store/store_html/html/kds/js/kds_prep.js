/**
 * Toptea KDS - kds_prep.js
 * JavaScript for Material Preparation Page
 * Engineer: Gemini | Date: 2025-10-31
 * Revision: 9.8 (FIX: Replace all alert() with showKdsAlert())
 */

let confirmationModal = null;
let actionToConfirm = null;

// 全局函数：设置并显示物料制备的确认模态框
function setupAndShowPrepModal(buttonElement) {
    const I18N = { 'zh-CN': { confirm_action: '您确定要记录“{materialName}”吗？\n此操作将开始计算该物品的效期。' }, 'es-ES': { confirm_action: '¿Confirma que desea registrar "{materialName}"?\nEsta acción comenzará el seguimiento de la caducidad del artículo.' } };
    const currentLang = localStorage.getItem("kds_lang") || "zh-CN";
    const translations = I18N[currentLang] || I18N['zh-CN'];
    
    const materialName = buttonElement.dataset.materialName;
    const confirmationMessage = translations.confirm_action.replace('{materialName}', materialName);

    // 设置当用户点击“确认”时，应该执行的回调函数
    actionToConfirm = function() {
        performPrepAction(buttonElement);
    };

    // 显示模态框
    const modalBody = document.getElementById('confirmationModalBody');
    if (modalBody) {
        modalBody.textContent = confirmationMessage;
    }
    if (confirmationModal) {
        confirmationModal.show();
    }
}

// 全局函数：执行实际的API调用
async function performPrepAction(buttonElement) {
    const I18N = { 'zh-CN': { action_success: '操作成功！', action_failed: '操作失败' }, 'es-ES': { action_success: '¡Operación exitosa!', action_failed: 'La operación falló' } };
    const currentLang = localStorage.getItem("kds_lang") || "zh-CN";
    const translations = I18N[currentLang] || I18N['zh-CN'];
    const API_URL_RECORD = 'api/record_expiry_item.php';
    const materialId = buttonElement.dataset.materialId;

    const originalText = buttonElement.innerHTML;
    buttonElement.disabled = true;
    buttonElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>`;

    try {
        const response = await fetch(API_URL_RECORD, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ material_id: materialId }) });
        const result = await response.json();
        
        if (response.ok && result.status === 'success') {
            
            // 提示成功 (使用自定义 Alert)
            showKdsAlert(translations.action_success, false); 
            
            // 检查后端是否返回了打印数据
            if (result.data && result.data.print_data) {
                // 调用打印桥接
                if (window.KDS_PRINT_BRIDGE && typeof window.KDS_PRINT_BRIDGE.executePrint === 'function') {
                    const template = KDS_STATE.templates['EXPIRY_LABEL'];
                    if (template) {
                        window.KDS_PRINT_BRIDGE.executePrint(template, result.data.print_data);
                    } else {
                        console.error("未在 KDS_STATE.templates 中找到 'EXPIRY_LABEL' 模板!");
                        showKdsAlert("操作成功，但打印失败：未配置效期标签模板。", true);
                    }
                } else {
                    showKdsAlert('打印桥接 KDS_PRINT_BRIDGE 未找到。', true);
                }
            } else {
                 console.warn("API 成功了，但没有返回 print_data，跳过打印。");
            }

        } else {
            throw new Error(result.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Failed to record expiry:', error);
        // (使用自定义 Alert)
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

    const I18N_PAGE = { 'zh-CN': { prep_title: '物料制备与开封', btn_back_kds: '返回KDS', section_packaged: '开封物料', section_preps: '门店现制', btn_open: '开封', btn_prep: '制备完成', no_packaged: '暂无可开封物料', no_preps: '暂无现制物料', loading_failed: '加载物料失败！', placeholder_search: '搜索物料...', no_results: '无匹配结果' }, 'es-ES': { prep_title: 'Preparación y Apertura', btn_back_kds: 'Volver a KDS', section_packaged: 'Abrir Empaquetados', section_preps: 'Preparar en Tienda', btn_open: 'Abrir', btn_prep: 'Completado', no_packaged: 'No hay artículos para abrir', no_preps: 'No hay artículos para preparar', loading_failed: '¡Fallo al cargar materiales!', placeholder_search: 'Buscar material...', no_results: 'No hay resultados' } };
    const currentLang = localStorage.getItem("kds_lang") || "zh-CN";
    const translations = I18N_PAGE[currentLang] || I18N_PAGE['zh-CN'];
    
    document.querySelectorAll('[data-i18n-key]').forEach(el => {
        const key = el.getAttribute('data-i18n-key');
        if (translations[key]) {
             if (el.tagName === 'INPUT' && el.type === 'search') {
                el.placeholder = translations[key];
            } else {
                el.textContent = translations[key];
            }
        }
    });

    const API_URL_GET = 'api/get_preppable_materials.php';
    const packagedGoodsList = document.getElementById('packaged-goods-list');
    const inStorePrepsList = document.getElementById('in-store-preps-list');
    const searchInput = document.getElementById('material-search-input');
    
    function createMaterialItem(material, type) {
        const itemDiv = document.createElement('div');
        itemDiv.className = 'material-item';
        const langKey = currentLang === 'es-ES' ? 'es' : 'zh';
        const materialName = material[`name_${langKey}`] || material.name_zh;
        
        const buttonClass = (type === 'packaged') ? 'btn-open' : 'btn-prep';
        const buttonIcon = (type === 'packaged') ? 'bi-box-arrow-up' : 'bi-check-circle';
        const buttonText = (type === 'packaged') ? translations.btn_open : translations.btn_prep;

        const buttonHTML = `
            <button 
                class="btn-action ${buttonClass}" 
                data-material-id="${material.id}" 
                data-material-name="${materialName}"
                onclick="setupAndShowPrepModal(this)">
                <i class="bi ${buttonIcon}"></i> ${buttonText}
            </button>
        `;
        
        itemDiv.innerHTML = `<span class="material-name">${materialName}</span> ${buttonHTML}`;
        return itemDiv;
    }

    function filterMaterials() { const searchTerm = searchInput.value.toLowerCase(); const activeList = document.querySelector('.tab-pane.active .material-list'); if (!activeList) return; const items = activeList.querySelectorAll('.material-item'); let visibleCount = 0; items.forEach(item => { const nameElement = item.querySelector('.material-name'); const name = nameElement ? nameElement.textContent.toLowerCase() : ''; if (name.includes(searchTerm)) { item.style.display = ''; visibleCount++; } else { item.style.display = 'none'; } }); let noResultsMsg = activeList.querySelector('.no-results-message'); if (visibleCount === 0 && items.length > 0) { if (!noResultsMsg) { noResultsMsg = document.createElement('p'); noResultsMsg.className = 'text-muted no-results-message'; activeList.appendChild(noResultsMsg); } noResultsMsg.textContent = translations.no_results || 'No results found'; noResultsMsg.style.display = ''; } else if (noResultsMsg) { noResultsMsg.style.display = 'none'; } }
    async function fetchAndRenderMaterials() { try { const response = await fetch(API_URL_GET); if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`); const result = await response.json(); if (result.status === 'success' && result.data) { packagedGoodsList.innerHTML = ''; inStorePrepsList.innerHTML = ''; if (result.data.packaged_goods.length > 0) { result.data.packaged_goods.forEach(material => { packagedGoodsList.appendChild(createMaterialItem(material, 'packaged')); }); } else { packagedGoodsList.innerHTML = `<p class="text-muted">${translations.no_packaged}</p>`; } if (result.data.in_store_preps.length > 0) { result.data.in_store_preps.forEach(material => { inStorePrepsList.appendChild(createMaterialItem(material, 'in_store')); }); } else { inStorePrepsList.innerHTML = `<p class="text-muted">${translations.no_preps}</p>`; } filterMaterials(); } else { throw new Error(result.message || 'Invalid data from API'); } } catch (error) { console.error('Failed to fetch materials:', error); const errorHtml = `<p class="text-danger fw-bold">${translations.loading_failed}</p>`; packagedGoodsList.innerHTML = errorHtml; inStorePrepsList.innerHTML = errorHtml; } }
    
    if (searchInput) { searchInput.addEventListener('input', filterMaterials); }
    const triggerTabList = [].slice.call(document.querySelectorAll('#v-pills-tab button'));
    triggerTabList.forEach(function (triggerEl) {
        triggerEl.addEventListener('shown.bs.tab', function (event) {
            filterMaterials();
        });
    });

    fetchAndRenderMaterials();
});