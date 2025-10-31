/**
 * state.js — POS 全局状态 & UI 模式控制（稳定稳定版）
 * - APK 清缓存默认右手
 * - 左/右手切换：立即生效 + 同步旧键 + 触发旧监听
 * - 高峰模式：按钮/开关均可用
 * - 兼容旧键：POS_HAND_MODE / POS_LEFTY / POS_RIGHTY
 * - 额外桥接：点击包含 hand_mode 的整行也会触发切换（防止“选中了但未触发事件”）
 * Revision: 2.3.1 (Add hold i18n keys)
 */

//////////////////// I18N ////////////////////
export const I18N = {
  zh: {
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
    points_redeem_placeholder: '使用积分', points_apply_btn: '应用', points_rule: '100积分 = 1€',
    points_feedback_applied: '已用 {points} 积分抵扣 €{amount}', points_feedback_not_enough: '积分不足或超出上限',

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

    points_available_rewards: '可用积分兑换',
    points_redeem_button: '兑换',
    points_redeemed_success: '已应用积分兑换！',
    points_insufficient: '积分不足，无法兑换。',
    redemption_incompatible: '积分兑换不能与优惠券同时使用。',
    redemption_applied: '已兑换',
    // --- Add EOD Print ---
    eod_print_report: '打印报告',
    print_failed: '打印失败',
    print_data_fetch_failed: '获取打印数据失败',
    print_template_missing: '找不到对应的打印模板',
    print_preview_title: '打印预览 (模拟)',
    close: '关闭'
  },
  es: {
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

    points_available_rewards: 'Recompensas Disponibles',
    points_redeem_button: 'Canjear',
    points_redeemed_success: '¡Canje de puntos aplicado!',
    points_insufficient: 'Puntos insuficientes para canjear.',
    redemption_incompatible: 'El canje de puntos no se puede usar con un cupón.',
    redemption_applied: 'Canjeado',
     // --- Add EOD Print ---
    eod_print_report: 'Imprimir Informe',
    print_failed: 'Fallo de impresión',
    print_data_fetch_failed: 'Fallo al obtener datos de impresión',
    print_template_missing: 'Plantilla de impresión no encontrada',
    print_preview_title: 'Vista Previa de Impresión (Simulado)',
    close: 'Cerrar'
  }
};

//////////////////// STATE ////////////////////
export const STATE = {
  active_category_key: null,
  cart: [],
  products: [],
  categories: [],
  addons: [],
  redemptionRules: [],
  printTemplates: {}, // --- CORE ADDITION: Store for print templates ---
  activeCouponCode: '',
  activeRedemptionRuleId: null,
  calculatedCart: { cart: [], subtotal: 0, discount_amount: 0, final_total: 0 },
  payment: { total: 0, parts: [] },
  holdSortBy: 'time_desc',
  activeMember: null,
  lang: (typeof localStorage !== 'undefined' && localStorage.getItem('POS_LANG')) || 'zh',

  // 旧字段（兼容）
  lefty_mode:  (typeof localStorage !== 'undefined' && localStorage.getItem('POS_LEFTY')  === '1'),
  righty_mode: (typeof localStorage !== 'undefined' && localStorage.getItem('POS_RIGHTY') === '1'),
  hand_mode:   (typeof localStorage !== 'undefined' && (localStorage.getItem('POS_HAND_MODE') || 'right')),
  store_id: Number((typeof localStorage !== 'undefined' && localStorage.getItem('POS_STORE_ID')) || 1),
  points_redeemed: 0,

  // 新字段
  ui: { selected_category_id: null, search_text: '', hand: 'right', peak: false },
  flags: { loading: false }
};
if (typeof window !== 'undefined') window.STATE = STATE;

//////////////////// LocalStorage helpers ////////////////////
const LS = {
  get(k){ try{ return localStorage.getItem(k); }catch(_){ return null; } },
  set(k,v){ try{ localStorage.setItem(k,v); }catch(_){} }
};

//////////////////// constants ////////////////////
const HAND_KEY = 'tt_pos_hand';
const PEAK_KEY = 'tt_pos_peak';

//////////////////// targets to apply classes ////////////////////
const TARGETS = [
  document.documentElement,
  document.body,
  document.querySelector('#app'),
  document.querySelector('#root'),
  document.querySelector('#posRoot'),
  document.querySelector('#page'),
  document.querySelector('#layout'),
  document.querySelector('#main'),
  document.querySelector('#mainContent'),
  document.querySelector('#pos_main'),
  document.querySelector('.pos-app')
].filter(Boolean);

//////////////////// Hand helpers ////////////////////
function syncLegacyHand(m){
  STATE.hand_mode   = m;
  STATE.lefty_mode  = (m === 'left');
  STATE.righty_mode = (m === 'right');
  LS.set('POS_HAND_MODE', m);
  LS.set('POS_LEFTY',  m === 'left'  ? '1' : '0');
  LS.set('POS_RIGHTY', m === 'right' ? '1' : '0');
}

function applyHand(mode){
  const m = (mode === 'left') ? 'left' : 'right';
  STATE.ui.hand = m;
  syncLegacyHand(m);

  const PAIRS = [
    ['hand-left','hand-right'],
    ['lefty','righty'],
    ['left-mode','right-mode'],
    ['lefty-mode','righty-mode'],
    ['pos-hand-left','pos-hand-right'],
    ['is-left','is-right'],
    ['layout-left','layout-right'],
    ['left-handed','right-handed'],
    ['left','right']
  ];
  const addSet = (el, addLeft) => {
    for (const [l,r] of PAIRS) { el.classList.remove(l, r); }
    for (const [l,r] of PAIRS) { el.classList.add(addLeft ? l : r); }
    el.setAttribute('data-hand', addLeft ? 'left' : 'right');
    el.setAttribute('data-lefty',  addLeft ? '1' : '0');
    el.setAttribute('data-righty', addLeft ? '0' : '1');
    el.style.setProperty('--hand-mode', addLeft ? 'left' : 'right');
    el.style.setProperty('--is-lefty',  addLeft ? '1' : '0');
    el.style.setProperty('--is-righty', addLeft ? '0' : '1');
  };
  for (const el of TARGETS) addSet(el, m === 'left');
  document.documentElement.setAttribute('data-hand', m);

  // 同步 UI 控件状态
  const sw = document.querySelector('#hand_switch,[data-hand-toggle="switch"],#right_hand_switch');
  if (sw && 'checked' in sw) sw.checked = (m === 'right');
  const rRight = document.querySelector('#hand_right,[data-hand="right"],input[name="hand_mode"][value="right"],#btn_right_hand,#hand_right_btn');
  const rLeft  = document.querySelector('#hand_left, [data-hand="left"], input[name="hand_mode"][value="left"],  #btn_left_hand,  #hand_left_btn');
  if (rRight && 'checked' in rRight) rRight.checked = (m === 'right');
  if (rLeft  && 'checked' in rLeft ) rLeft.checked  = (m === 'left');

  // 通知旧监听
  queueMicrotask(() => {
    try {
      const ev = new CustomEvent('pos:handchange', { detail: { mode: m }});
      window.dispatchEvent(ev); document.dispatchEvent(ev);
    } catch(_) {}
    ['#hand_switch', '#right_hand_switch'].forEach(sel => {
      const el = document.querySelector(sel);
      if (el) { try { el.dispatchEvent(new Event('change', { bubbles:true })); } catch(_){} }
    });
    void document.body?.offsetHeight;
  });

  // 二次重申，避免别的脚本“抢回”
  const reassert = () => {
    for (const el of TARGETS) addSet(el, m === 'left');
    document.documentElement.setAttribute('data-hand', m);
    try {
      if (typeof window.onHandModeChange === 'function') window.onHandModeChange(m);
      if (window.UI && typeof window.UI.applyHand === 'function') window.UI.applyHand(m);
    } catch(_) {}
  };
  requestAnimationFrame(reassert);
  setTimeout(reassert, 120);
}

export function setHand(mode, persist = true){
  const m = (mode === 'left') ? 'left' : 'right';
  applyHand(m);
  if (persist) {
    LS.set(HAND_KEY, m);
    LS.set('POS_HAND_MODE', m);
    LS.set('POS_LEFTY',  m === 'left'  ? '1' : '0');
    LS.set('POS_RIGHTY', m === 'right' ? '1' : '0');
  }
}
export function getHand(){ return STATE.ui.hand || STATE.hand_mode || 'right'; }

//////////////////// Peak helpers ////////////////////
function applyPeak(on){
  const flag = !!on;
  STATE.ui.peak = flag;
  for (const el of TARGETS){
    el.classList.toggle('contrast-boost', flag);
    el.setAttribute('data-peak', flag ? '1' : '0');
  }
  document.documentElement.setAttribute('data-peak', flag ? '1' : '0');

  const peakSw = document.querySelector('#setting_peak_mode, #peak_switch, [data-peak-toggle="switch"]');
  if (peakSw && 'checked' in peakSw) peakSw.checked = flag;

  queueMicrotask(() => {
    try {
      const ev = new CustomEvent('pos:peakchange', { detail: { on: flag }});
      window.dispatchEvent(ev); document.dispatchEvent(ev);
    } catch(_) {}
    void document.body?.offsetHeight;
  });
}
export function setPeak(on, persist = true){
  applyPeak(!!on);
  if (persist) LS.set(PEAK_KEY, on ? '1' : '0');
}
export function isPeak(){ return !!STATE.ui.peak; }

//////////////////// Init (默认右手) ////////////////////
const savedHand = (LS.get(HAND_KEY) || LS.get('POS_HAND_MODE') || '').toLowerCase();
applyHand((savedHand === 'left' || savedHand === 'right') ? savedHand : 'right');

const savedPeakNew = LS.get(PEAK_KEY);
const savedPeakOld = LS.get('POS_PEAK_MODE');
const initialPeakState = savedPeakNew !== null ? savedPeakNew === '1' : savedPeakOld === 'true';
applyPeak(initialPeakState);


//////////////////// Re-apply after DOM changes ////////////////////
const mo = new MutationObserver(() => {
  applyHand(getHand());
  applyPeak(isPeak());
});
mo.observe(document.documentElement, { childList: true, subtree: true });

//////////////////// Event bindings ////////////////////
// 1) 直接按钮/开关
document.addEventListener('click', (e) => {
  const handBtn = e.target.closest(
    '[data-hand="right"],[data-hand="left"],' +
    '[data-action="hand.right"],[data-action="hand.left"],[data-action="hand.toggle"],' +
    '[data-hand-toggle],' +
    '#btn_right_hand,#btn_left_hand,#hand_right_btn,#hand_left_btn'
  );
  if (!handBtn) return;
  const tag = (handBtn.tagName || '').toLowerCase();
  if (tag === 'button' || tag === 'a' || handBtn.getAttribute('role') === 'button') e.preventDefault();

  let next = getHand();
  if (handBtn.matches('[data-action="hand.toggle"],[data-hand-toggle]')) {
    next = (getHand() === 'right') ? 'left' : 'right';
  } else if (handBtn.matches('[data-hand="right"],[data-action="hand.right"],#btn_right_hand,#hand_right_btn')) {
    next = 'right';
  } else if (handBtn.matches('[data-hand="left"],[data-action="hand.left"],#btn_left_hand,#hand_left_btn')) {
    next = 'left';
  } else {
    next = (getHand() === 'right') ? 'left' : 'right';
  }
  setHand(next, true);
});

document.addEventListener('change', (e) => {
  const el = e.target;
  if (!(el instanceof Element)) return;

  if (el.matches('#hand_switch, #right_hand_switch, [data-hand-toggle="switch"]') && 'checked' in el) {
    const next = el.checked ? 'right' : 'left';
    setHand(next, true);
    return;
  }
  if (el.matches('input[name="hand_mode"], input[data-hand]')) {
    const val = (el.getAttribute('value') || el.getAttribute('data-hand') || '').toLowerCase();
    if (val === 'left' || val === 'right') setHand(val, true);
  }
});

// 2) **桥接**：点击“整行”也能触发 hand 切换（防止只改了样式没触发 change）
document.addEventListener('click', (e) => {
  const row = e.target.closest('.list-group-item, .form-check, .hand-option-row, [data-hand-row]');
  if (!row) return;
  const input = row.querySelector('input[name="hand_mode"]');
  if (!input) return;
  if (e.target !== input) {
    e.preventDefault();
    if (!input.checked) {
      input.checked = true;
      try { input.dispatchEvent(new Event('change', { bubbles: true })); } catch(_) {}
    } else {
      try { input.dispatchEvent(new Event('change', { bubbles: true })); } catch(_) {}
    }
  }
});

// Peak：按钮/开关
document.addEventListener('click', (e) => {
  const btn = e.target.closest(
    '[data-peak="on"],[data-peak="off"],[data-peak-toggle],' +
    '#btn_peak_on,#btn_peak_off,#btn_peak_toggle'
  );
  if (!btn) return;
  const tag = (btn.tagName || '').toLowerCase();
  if (tag === 'button' || tag === 'a' || btn.getAttribute('role') === 'button') e.preventDefault();

  const next = btn.hasAttribute('data-peak-toggle') || btn.matches('#btn_peak_toggle')
    ? !isPeak()
    : btn.matches('[data-peak="on"],#btn_peak_on');
  setPeak(next, true);
});

document.addEventListener('change', (e) => {
  const el = e.target;
  if (!(el instanceof Element)) return;

  if (el.matches('#setting_peak_mode, #peak_switch, [data-peak-toggle="switch"]') && 'checked' in el) {
    setPeak(!!el.checked, true);
    return;
  }
  if (el.matches('input[name="peak_mode"]')) {
    const v = (el.value || '').toLowerCase();
    setPeak(v === 'on' || v === 'true' || v === '1', true);
  }
});

// cross-tab（对 APK 无影响）
window.addEventListener?.('storage', (ev) => {
  if (ev.key === HAND_KEY && ev.newValue) applyHand(ev.newValue === 'left' ? 'left' : 'right');
  if (ev.key === PEAK_KEY) applyPeak(ev.newValue === '1');
});