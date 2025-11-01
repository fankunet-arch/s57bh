import { STATE, I18N } from './state.js';
import { applyI18N, renderCategories, renderProducts, renderAddons, openCustomize, updateCustomizePrice, refreshCartUI, updateMemberUI } from './ui.js';
import { fetchInitialData, fetchPrintTemplates, fetchEodPrintData } from './api.js';
import { t, toast } from './utils.js';
import { addToCart, updateCartItem, calculatePromotions } from './modules/cart.js';
import { openPaymentModal, addPaymentPart, updatePaymentState, initiatePaymentConfirmation, handleQuickCash } from './modules/payment.js';
import { openHoldOrdersPanel, createHoldOrder, restoreHeldOrder, refreshHeldOrdersList } from './modules/hold.js';
import { openEodModal, openEodConfirmationModal, submitEodReportFinal, handlePrintEodReport } from './modules/eod.js';
import { openTxnQueryPanel, showTxnDetails, initializeRefundModal } from './modules/transactions.js';
import { handleSettingChange } from './modules/settings.js';
import { findMember, unlinkMember, showCreateMemberModal, createMember } from './modules/member.js';
import { initializePrintSimulator, printReceipt } from './modules/print.js';
import { checkShiftStatus, initializeShiftModals, handleStartShift } from './modules/shift.js'; 

console.log("Modules imported successfully in main.js");

// Add new I18N keys
const I18N_NS = (typeof I18N === 'object' && I18N) ? I18N : (window.I18N = window.I18N || {});
I18N_NS.zh = I18N_NS.zh || {};
I18N_NS.es = I18N_NS.es || {};

Object.assign(I18N_NS.zh, {
  payment_success: '支付成功',
  payment_methods_label: '支付方式',
  internal:'Internal', lang_zh:'中文', lang_es:'Español', cart:'购物车', total_before_discount:'合计', more:'功能',
  customizing:'正在定制', size: '规格', addons:'加料', remark:'备注（可选）', ice: '冰量', sugar: '糖度',
  curr_price:'当前价格', add_to_cart:'加入购物车', placeholder_search:'搜索饮品或拼音简称…',
  go_checkout:'去结账', payable: '应收', tip_empty_cart: '购物车为空', choose_variant: '选规格', no_products_in_category: '该分类下暂无商品',
  order_success: '下单成功', invoice_number: '票号', qr_code_info: '合规二维码内容 (TicketBAI/Veri*Factu)', new_order: '开始新订单',
  submitting_order: '正在提交...', promo_applied: '已应用活动', coupon_applied: '优惠码已应用', coupon_not_valid: '优惠码无效或不适用',
  checkout: '结账', cash_payment: '现金', card_payment: '刷卡', amount_tendered: '实收金额', change: '找零', confirm_payment: '确认收款', cancel: '取消',
  receivable:'应收', paid:'已收', remaining:'剩余', done:'完成', cash_input:'收现金', card_amount:'刷卡金额', add_payment_method: '添加其它方式',
  platform_code: '平台码', platform_amount: '收款金额', platform_ref: '参考码',
  ops_panel:'功能面板', txn_query:'交易查询', eod:'日结', holds:'挂起单', member:'会员', create_hold:'新建挂起单', no_held_orders:'暂无挂起单', restore:'恢复',
  hold_this: '挂起此单', sort_by_time: '排序: 最近', sort_by_amount: '排序: 金额',
  hold_placeholder: '输入桌号或备注 (必填)',
  hold_instruction: '挂起的订单将保留当前购物车内容，稍后可恢复。',
  settings: '设置', peak_mode: '高峰模式 (对比增强)', peak_mode_desc: '左侧菜单变白，并在前方功能按钮保留返回图示，避免误操。',
  lefty_mode: '左手模式 (点菜按钮靠左)', righty_mode: '右手模式 (点菜按钮靠右)',
  no_transactions: '暂无交易记录', issued: '已开具', cancelled: '已作废',
  eod_title: '今日日结报告', eod_date: '报告日期', eod_txn_count: '交易笔数', eod_gross_sales: '总销售额',
  eod_discounts: '折扣总额', eod_net_sales: '净销售额', eod_tax: '税额', eod_payments: '收款方式汇总',
  eod_cash: '现金收款', eod_card: '刷卡收款', eod_platform: '平台收款', eod_counted_cash: '清点现金金额',
  eod_cash_discrepancy: '现金差异', eod_notes: '备注 (可选)', eod_submit: '确认并提交日结',
  eod_submitted_already: '今日已日结', eod_submitted_desc: '今日报告已存档，以下为存档数据。',
  eod_success_submit: '日结已完成并存档！', eod_confirm_title: '确认提交日结',
  eod_confirm_body: '提交后，今日日结数据将被存档且无法修改。请确认所有款项已清点完毕。',
  eod_confirm_cancel: '取消', eod_confirm_submit: '确认提交',
  eod_confirm_headnote: '提交后无法再结报', eod_confirm_text: '提交后将不可修改。',

  member_search_placeholder: '输入会员手机号查找', member_find: '查找', member_not_found: '未找到会员',
  member_create: '创建新会员', member_name: '会员姓名', member_points: '积分', member_level: '等级',
  member_unlink: '解除关联', member_create_title: '创建新会员', member_phone: '手机号',
  member_firstname: '名字', member_lastname: '姓氏', member_email: '邮箱', member_birthdate: '生日',
  member_create_submit: '创建并关联', member_create_success: '新会员已创建并关联到订单！',
  points_redeem_placeholder: '使用积分',
  points_apply_btn: '应用',
  points_rule: '100积分 = 1€',
  points_feedback_applied: '已用 {points} 积分抵扣 €{amount}',
  points_feedback_not_enough: '积分不足或超出上限',

  unclosed_eod_title: '操作提醒',
  unclosed_eod_header: '上一营业日未日结',
  unclosed_eod_message: '系统检测到日期为 {date} 的营业日没有日结报告。',
  unclosed_eod_instruction: '为保证数据准确，请先完成该日期的日结，再开始新的营业日。',
  unclosed_eod_button: '立即完成上一日日结',
  unclosed_eod_force_button: '强制开启新一日 (需授权)',

  start_date: '起始日期',
  end_date: '截止日期',
  query: '查询',
  validation_date_range_too_large: '查询范围不能超过一个月。',
  validation_end_date_in_future: '截止日期不能是未来日期。',
  validation_end_date_before_start: '截止日期不能早于起始日期。',
  validation_select_dates: '请选择起始和截止日期',

  points_available_rewards: '可用积分兑换',
  points_redeem_button: '兑换',
  points_redeemed_success: '已应用积分兑换！',
  points_insufficient: '积分不足，无法兑换。',
  redemption_incompatible: '积分兑换不能与优惠券同时使用。',
  redemption_applied: '已兑换',
  loading: '加载中',
  time: '时间',
  cashier: '收银员',
  status: '状态',
  item_list: '商品列表',
  item: '商品',
  qty: '数量',
  unit_price: '单价',
  total_price: '总价',
  no_items: '无商品',
  subtotal: '税前',
  vat: '税额',
  total: '总计',
  invoice_details: '票据详情',
  cancel_invoice: '作废此单',
  correct_invoice: '开具更正票据',
  confirm_cancel_invoice_title: '确认作废票据',
  confirm_cancel_invoice_body: '您确定要作废票据 {invoiceNumber} 吗？此操作不可逆。',
  confirm_cancel_invoice_confirm: '确认作废',
  confirm_correct_invoice_title: '确认开具更正票据',
  confirm_correct_invoice_body: '为票据 {invoiceNumber} 开具更正票据？请在 HQ 后台完成后续操作。',
  confirm_correct_invoice_confirm: '确认开具',
  shift_handover: '交接班',
  shift_start_title: '开始当班',
  shift_start_body: '在开始销售前，请输入您钱箱中的初始备用金金额。',
  shift_start_label: '初始备用金 (€)',
  shift_start_submit: '确认并开始当班',
  shift_start_success: '开班成功！',
  shift_start_fail: '开班失败',
  shift_end_title: '交接班',
  shift_end_summary: '当班小结',
  shift_end_sales_total: '净销售额',
  shift_end_cash_expected: '系统应有现金',
  shift_end_cash_counted: '清点现金总额',
  shift_end_cash_variance: '现金差异',
  shift_end_variance_desc: '差异 = 清点 - 应有。负数表示短款。',
  shift_end_submit: '确认交班并打印',
  shift_end_success: '交班成功，系统将自动退出。',
  shift_end_fail: '交班失败'
});
Object.assign(I18N_NS.es, {
   payment_success: 'Pago completado',
   payment_methods_label: 'Métodos de Pago',
   internal:'Interno', lang_zh:'Chino', lang_es:'Español', cart:'Carrito', total_before_discount:'Total', more:'Más',
  customizing:'Personalizando', size: 'Tamaño', addons:'Extras', remark:'Observaciones (opc.)', ice: 'Hielo', sugar: 'Azúcar',
  curr_price:'Precio actual', add_to_cart:'Añadir al carrito', placeholder_search:'Buscar bebida o abreviatura…',
  go_checkout:'Ir a cobrar', payable: 'A cobrar', tip_empty_cart: 'Carrito vacío', choose_variant: 'Elegir', no_products_in_category: 'No hay productos en esta categoría',
  order_success: 'Pedido completado', invoice_number: 'Nº de ticket', qr_code_info: 'Contenido QR (TicketBAI/Veri*Factu)', new_order: 'Nuevo pedido',
  submitting_order: 'Procesando...', promo_applied: 'Promoción aplicada', coupon_applied: 'Cupón aplicado', coupon_not_valid: 'Cupón no válido o no aplicable',
  checkout: 'Cobrar', cash_payment: 'Efectivo', card_payment: 'Tarjeta', amount_tendered: 'Importe recibido', change: 'Cambio', confirm_payment: 'Confirmar pago', cancel: 'Cancelar',
  receivable:'A cobrar', paid:'Cobrado', remaining:'Pendiente', done:'Hecho', cash_input:'Importe efectivo', card_amount:'Importe tarjeta', add_payment_method: 'Añadir otro método',
  platform_code: 'Cód. Plataforma', platform_amount: 'Importe', platform_ref: 'Referencia',
  ops_panel:'Panel de funciones', txn_query:'Consulta', eod:'Cierre', holds:'En espera', member:'Socio', create_hold:'Crear espera', no_held_orders:'Sin pedidos en espera', restore:'Restaurar',
  hold_placeholder: 'Introduzca nota (obligatorio)',
  hold_instruction: 'Los pedidos en espera guardarán el carrito actual para restaurarlo más tarde.',
  sort_by_time: 'Ordenar: Reciente', sort_by_amount: 'Ordenar: Importe',
  settings: 'Ajustes', peak_mode: 'Modo Pico (Contraste alto)', peak_mode_desc: 'Mejora legibilidad.',
  lefty_mode: 'Modo Zurdo', righty_mode: 'Modo Diestro',
  no_transactions: 'Sin transacciones', issued: 'Emitido', cancelled: 'Anulado',
  eod_title: 'Informe de Cierre Diario', eod_date: 'Fecha', eod_txn_count: 'Transacciones', eod_gross_sales: 'Ventas brutas',
  eod_discounts: 'Descuentos', eod_net_sales: 'Ventas netas', eod_tax: 'Impuestos',
  eod_payments: 'Resumen de cobros', eod_cash: 'Efectivo', eod_card: 'Tarjeta', eod_platform: 'Plataforma',
  eod_counted_cash: 'Efectivo contado', eod_cash_discrepancy: 'Diferencia de caja', eod_notes: 'Notas (opc.)',
  eod_submit: 'Confirmar y Enviar', eod_submitted_already: 'Cierre ya enviado', eod_submitted_desc: 'Archivado.',
  eod_success_submit: '¡Cierre archivado!', eod_confirm_title: 'Confirmar Cierre', eod_confirm_body: 'Será definitivo.',
  eod_confirm_cancel: 'Cancelar', eod_confirm_submit: 'Confirmar',
  eod_confirm_headnote: 'Después del envío no se podrá volver a cerrar', eod_confirm_text: 'Será definitivo.',
  member_search_placeholder: 'Buscar socio por teléfono', member_find: 'Buscar', member_not_found: 'Socio no encontrado',
  member_create: 'Crear nuevo socio', member_name: 'Nombre', member_points: 'Puntos', member_level: 'Nivel',
  member_unlink: 'Desvincular', member_create_title: 'Crear Nuevo Socio', member_phone: 'Teléfono',
  member_firstname: 'Nombre', member_lastname: 'Apellidos', member_email: 'Email', member_birthdate: 'Fecha nac.',
  member_create_submit: 'Crear y Vincular', member_create_success: '¡Nuevo socio creado y vinculado al pedido!',
  points_redeem_placeholder: 'Usar puntos', points_apply_btn: 'Aplicar', points_rule: '100 puntos = 1€',
  points_feedback_applied: '{points} puntos aplicados, descuento de €{amount}',
  points_feedback_not_enough: 'Puntos insuficientes o excede el límite',
  unclosed_eod_title: 'Aviso de Operación',
  unclosed_eod_header: 'Día Anterior No Cerrado',
  unclosed_eod_message: 'El sistema detectó que el día hábil con fecha {date} no tiene informe de cierre.',
  unclosed_eod_instruction: 'Para garantizar la precisión de los datos, complete primero el cierre de ese día antes de comenzar un nuevo día hábil.',
  unclosed_eod_button: 'Completar Cierre Anterior Ahora',
  unclosed_eod_force_button: 'Forzar Inicio Nuevo Día (Requiere Autorización)',
  start_date: 'Fecha de inicio',
  end_date: 'Fecha de finalización',
  query: 'Consultar',
  validation_date_range_too_large: 'El rango de fechas no puede exceder un mes.',
  validation_end_date_in_future: 'La fecha de finalización no puede ser futura.',
  validation_end_date_before_start: 'La fecha de finalización no puede ser anterior a la de inicio.',
  shift_start_title: 'Iniciar Turno',
  shift_start_body: 'Antes de comenzar, ingrese el fondo de caja inicial.',
  shift_start_label: 'Fondo de Caja (€)',
  shift_start_submit: 'Confirmar e Iniciar Turno',
  shift_start_success: '¡Turno iniciado!',
  shift_start_fail: 'Error al iniciar turno',
  // ... rest of the es translations ...
});

/**
 * Starts a clock to update the time in the navbar every second.
 */
function startClock() {
    const clockEl = document.getElementById('pos_clock');
    if (!clockEl) return;

    function tick() {
        clockEl.textContent = new Date().toLocaleTimeString('es-ES', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        });
    }
    tick(); 
    setInterval(tick, 1000);
}


function showUnclosedEodOverlay(unclosedDate) {
    const existingOverlay = document.getElementById('eod-block-overlay');
    if (existingOverlay) existingOverlay.remove();
    const overlay = document.createElement('div');
    overlay.id = 'eod-block-overlay';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.zIndex = '1060';
    overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.65)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.padding = '1rem';
    overlay.style.backdropFilter = 'blur(3px)';

    overlay.innerHTML = `
        <div class="eod-block-content" style="background-color: var(--surface-1, #fff); color: var(--ink, #111); border-radius: 0.8rem; box-shadow: 0 8px 30px rgba(0,0,0,0.2); width: 100%; max-width: 500px; overflow: hidden;">
            <div class="eod-block-header" style="background-color: #ffc107; color: #000; padding: 0.8rem 1rem; font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.3rem;"></i>
                <span>${t('unclosed_eod_title')}</span>
            </div>
            <div class="eod-block-body" style="padding: 1.5rem; text-align: center;">
                <h4 style="margin-bottom: 0.75rem; font-weight: 600;">${t('unclosed_eod_header')}</h4>
                <p style="margin-bottom: 0.5rem;">${t('unclosed_eod_message').replace('{date}', `<strong>${unclosedDate}</strong>`)}</p>
                <p class="text-muted small" style="margin-bottom: 0.5rem; color: #6c757d;">${t('unclosed_eod_instruction')}</p>
            </div>
            <div class="eod-block-footer" style="padding: 0.8rem 1rem; background-color: var(--surface-2, #f1f1f1); border-top: 1px solid var(--border, #ccc); display: flex; justify-content: space-between; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" disabled>${t('unclosed_eod_force_button')}</button>
                <button type="button" class="btn btn-primary" id="btn_eod_now_overlay">${t('unclosed_eod_button')}</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const btnEodNow = document.getElementById('btn_eod_now_overlay');
    if (btnEodNow) {
        btnEodNow.addEventListener('click', () => {
            overlay.remove();
            openEodModal();
        });
    }
}


function bindEvents() {
  console.log("Binding events..."); 

  const $document = $(document);

  // --- Language & Sync (using delegation) ---
  $document.on('click', '.dropdown-menu [data-lang]', function(e) { 
      e.preventDefault();
      const newLang = $(this).data('lang');
      
      $('.dropdown-menu [data-lang]').removeClass('active');
      $(`.dropdown-menu [data-lang="${newLang}"]`).addClass('active');
      
      STATE.lang = newLang;
      localStorage.setItem('POS_LANG', STATE.lang);
      
      applyI18N();
      renderCategories();
      renderProducts();
      refreshCartUI();
      renderAddons();
      updateMemberUI();

      const langText = t(`lang_${newLang}`);
      const flag = newLang === 'zh' ? '🇨🇳' : '🇪🇸';
      $('#lang_toggle').html(`<span class="flag">${flag}</span> ${langText}`);
      $('#lang_toggle_modal').html(`<span class="flag">${flag}</span>`);
   });

  $document.on('click', '#btn_sync', function() {
      $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
      initApplication().finally(() => $(this).prop('disabled', false).html('<i class="bi bi-arrow-repeat"></i>'));
  });

  // --- Product & Customization ---
  $document.on('click', '#category_scroller .nav-link', function() { STATE.active_category_key = $(this).data('cat'); renderCategories(); renderProducts(); });
  $document.on('input', '#search_input', renderProducts);
  $document.on('click', '#clear_search', () => { $('#search_input').val('').trigger('input'); });
  $document.on('click', '.product-card', function() { openCustomize($(this).data('id')); });
  $document.on('change', 'input[name="variant_selector"]', updateCustomizePrice);
  $document.on('click', '#addon_list .addon-chip', function() { $(this).toggleClass('active'); updateCustomizePrice(); });
  $document.on('change', 'input[name="ice"], input[name="sugar"]', updateCustomizePrice);
  $document.on('click', '#btn_add_to_cart', addToCart);

  // --- Cart ---
  $('#cartOffcanvas').on('show.bs.offcanvas', () => { calculatePromotions(); updateMemberUI(); });
  $document.on('click', '#cart_items [data-act]', function() { updateCartItem($(this).data('id'), $(this).data('act')); });
  $document.on('click', '#apply_coupon_btn', () => calculatePromotions(true));
  $document.on('click', '#apply_points_btn', () => calculatePromotions());

  // --- Payment ---
  $document.on('click', '#btn_cart_checkout', openPaymentModal);
  $document.on('click', '#btn_confirm_payment', initiatePaymentConfirmation);
  $document.on('click', '[data-pay-method]', function() { addPaymentPart($(this).data('pay-method')); });
  $document.on('click', '.remove-part-btn', function() { $(this).closest('.payment-part').remove(); updatePaymentState(); });
  $document.on('input', '.payment-part-input', updatePaymentState);
  // NEW: Event listener for quick cash buttons
  $document.on('click', '.btn-quick-cash', function() { handleQuickCash($(this).data('value')); });


  // --- Ops Panel & Modals ---
  $document.on('click', '#btn_open_eod', openEodModal);
  $document.on('click', '#btn_open_holds', openHoldOrdersPanel);
  $document.on('click', '#btn_open_txn_query', openTxnQueryPanel);
  $document.on('click', '#btn_open_shift_end', () => { new bootstrap.Modal(document.getElementById('endShiftModal')).show(); });
  
  // --- Hold ---
  $document.on('click', '#btn_hold_current_cart', function() { if (STATE.cart.length === 0) { toast(t('tip_empty_cart')); return; } bootstrap.Offcanvas.getInstance('#cartOffcanvas')?.hide(); setTimeout(() => $('#hold_order_note_input').focus(), 400); });
  $document.on('click', '#btn_create_new_hold', createHoldOrder);
  $document.on('click', '.restore-hold-btn', function(e) { e.preventDefault(); restoreHeldOrder($(this).data('id')); });
  $document.on('click', '#holdOrdersOffcanvas .dropdown-item', function(e) { e.preventDefault(); STATE.holdSortBy = $(this).data('sort'); const sortKey = STATE.holdSortBy === 'time_desc' ? 'sort_by_time' : 'sort_by_amount'; $('#holdOrdersOffcanvas .dropdown-toggle').html(`<i class="bi bi-sort-down"></i> ${t(sortKey)}`); refreshHeldOrdersList(); });

  // --- EOD ---
  $document.on('click', '#btn_submit_eod_start', openEodConfirmationModal);
  $document.on('click', '#btn_confirm_eod_final', submitEodReportFinal);
  $document.on('click', '#btn_print_eod_report', handlePrintEodReport);

  // --- Txn Query & Refund/Cancel ---
  $document.on('click', '.txn-item', function(e) { e.preventDefault(); showTxnDetails($(this).data('id')); });
  $document.on('click', '.btn-cancel-invoice', function() { const id = $(this).data('id'); const num = $(this).data('number'); requestRefundActionConfirmation('cancel', id, num); });
  $document.on('click', '.btn-correct-invoice', function() { const id = $(this).data('id'); const num = $(this).data('number'); requestRefundActionConfirmation('correct', id, num); });

  // --- Member ---
  $document.on('click', '#btn_find_member', findMember);
  $document.on('click', '#btn_unlink_member', unlinkMember);
  $document.on('click', '#member_section .btn-create-member, #btn_show_create_member', function(e) { e.preventDefault(); showCreateMemberModal($('#member_search_phone').val()); });
  $document.on('submit', '#form_create_member', function(e) {
      e.preventDefault();
      createMember({ phone_number: $('#member_phone').val(), first_name: $('#member_firstname').val(), last_name: $('#member_lastname').val(), email: $('#member_email').val(), birthdate: $('#member_birthdate').val() });
  });

  // --- CORE FIX: Robust Shift Management Event Binding ---
  // The event is delegated from the document to the form's submit event.
  // This is robust and does not depend on the button's location in the DOM.
  $document.on('submit', '#start_shift_form', handleStartShift);

  // --- Settings ---
  $('#settingsOffcanvas input').on('change', handleSettingChange);

  console.log("Event bindings complete."); 
}

async function initApplication() {
    console.log("initApplication started.");
    try {
        console.log("Checking EOD status...");
        const eodStatusResponse = await fetch('./api/check_eod_status.php', { credentials: 'same-origin' });
        const eodStatusResult = await eodStatusResponse.json();
        console.log("EOD status result:", eodStatusResult);

        if (eodStatusResult.status === 'success' && eodStatusResult.data.previous_day_unclosed) {
            STATE.unclosedEodDate = eodStatusResult.data.unclosed_date;
            showUnclosedEodOverlay(eodStatusResult.data.unclosed_date);
            console.log("Previous EOD unclosed. Blocking UI.");
            return; 
        }
        STATE.unclosedEodDate = null;
        console.log("EOD check passed or not required.");

        console.log("Fetching initial data...");
        await fetchInitialData();
        console.log("Initial data fetched (or attempted). STATE after fetch:", JSON.parse(JSON.stringify(STATE)));

        // --- CORE FIX: Removed the fatal error check for empty products/categories ---
        // This check caused the app to crash if the store was new.
        // The rendering functions (renderProducts) already handle empty arrays gracefully.
        
        console.log("Essential data check skipped (as per fix), allowing empty stores.");
        
        const opsBody = document.querySelector('#opsOffcanvas .offcanvas-body');
        if (opsBody) {
             opsBody.innerHTML = `<div class="row g-3">
                <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" id="btn_open_shift_end"><i class="bi bi-person-check d-block fs-2 mb-2"></i><span data-i18n="shift_handover">交接班</span></button></div>
                <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" id="btn_open_txn_query"><i class="bi bi-clock-history d-block fs-2 mb-2"></i><span data-i18n="txn_query">交易查询</span></button></div>
                <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" id="btn_open_eod"><i class="bi bi-calendar-check d-block fs-2 mb-2"></i><span data-i18n="eod">日结</span></button></div>
                <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" id="btn_open_holds"><i class="bi bi-inboxes d-block fs-2 mb-2"></i><span data-i18n="holds">挂起单</span></button></div>
                <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" data-bs-toggle="offcanvas" data-bs-target="#settingsOffcanvas"><i class="bi bi-gear d-block fs-2 mb-2"></i><span data-i18n="settings">设置</span></button></div>
              </div>`;
        }

        console.log("Applying I18N...");
        applyI18N();
        console.log("Updating Member UI...");
        updateMemberUI();
        console.log("Rendering Categories...");
        renderCategories();
        console.log("Rendering Products...");
        renderProducts();
        console.log("Rendering Addons...");
        renderAddons();
        console.log("Refreshing Cart UI...");
        refreshCartUI();
        console.log("Initializing Print Simulator...");
        initializePrintSimulator();
        console.log("Initializing Refund Modal...");
        const refundModalEl = document.getElementById('refundConfirmModal');
        if (refundModalEl) {
             const modalInstance = new bootstrap.Modal(refundModalEl);
             initializeRefundModal(modalInstance);
             console.log("Refund modal initialized.");
        } else {
             console.error("Refund confirmation modal element not found!");
        }

        console.log("POS Initialized Successfully.");

        await checkShiftStatus();

    } catch (error) {
        console.error("Fatal Error during initialization:", error);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger m-5';
        errorDiv.innerHTML = `<strong>Fatal Error:</strong> Could not initialize POS. ${error.message}. Please try refreshing. Check console for details.`;
        document.body.innerHTML = '';
        document.body.appendChild(errorDiv);
        document.body.style.backgroundColor = '#f8d7da';
    } finally {
        console.log("initApplication finished.");
    }
}

// --- Main Execution ---
document.addEventListener('DOMContentLoaded', () => {
    initializeShiftModals();
    bindEvents();
    initApplication();
    startClock();
});