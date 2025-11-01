// TopTea POS · shift.js
// v1.7.0 — 无班次禁止一切操作；开班弹窗不可关闭；交接班完成后强制回到“未开班”状态

import { toast } from '../utils.js';

let startShiftModal = null;
let endShiftModal   = null;
let HAS_ACTIVE_SHIFT = false;

const POLICY = (window.SHIFT_POLICY || 'force_all'); // 默认为最严格

/** 创建“不可关闭”的开班弹窗（static + keyboard:false，且拦截 hide 事件） */
function getNonClosableStartModal() {
  const el = document.getElementById('startShiftModal');
  if (!el) return null;

  // 删除关闭按钮/取消按钮（如果存在）
  el.querySelector('.btn-close')?.classList.add('d-none');
  el.querySelector('[data-role="btn-cancel-start"]')?.classList.add('d-none');

  // 用 static + keyboard:false 禁用点击遮罩/ESC 关闭
  const m = bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static', keyboard: false });

  // 拦截尝试关闭（只要还未开班，就不允许关闭）
  el.addEventListener('hide.bs.modal', (ev) => {
    if (POLICY !== 'optional' && !HAS_ACTIVE_SHIFT) {
      ev.preventDefault();
    }
  });

  return m;
}

/** 初始化（在 main.js 的启动流程中调用） */
export function initializeShiftModals() {
  const startEl = document.getElementById('startShiftModal');
  const endEl   = document.getElementById('endShiftModal');

  if (startEl && !startShiftModal) {
    startShiftModal = getNonClosableStartModal();
  }
  if (endEl && !endShiftModal) {
    endShiftModal = bootstrap.Modal.getOrCreateInstance(endEl);
  }

  const startForm = document.getElementById('start_shift_form');
  if (startForm) startForm.addEventListener('submit', handleStartShift);

  const endForm = document.getElementById('end_shift_form');
  if (endForm) endForm.addEventListener('submit', handleEndShift);
}

/** 启动时或状态变化时检查班次状态 */
export async function checkShiftStatus() {
  try {
    const resp = await fetch('./api/pos_shift_handler.php?action=status', { credentials: 'same-origin' });
    const result = await resp.json();
    if (result.status !== 'success') {
      console.warn('checkShiftStatus error:', result.message);
      return;
    }
    HAS_ACTIVE_SHIFT = !!(result.data && result.data.has_active_shift);

    if (!HAS_ACTIVE_SHIFT) {
      // 未开班：弹出不可关闭的开班弹窗
      if (!startShiftModal) startShiftModal = getNonClosableStartModal();
      startShiftModal?.show();
    } else {
      // 已开班：确保开班弹窗被关闭（如果还开着）
      try { startShiftModal?.hide(); } catch(_) {}
    }
  } catch (err) {
    console.warn('checkShiftStatus network error:', err);
  }
}

/** 开始当班 */
export async function handleStartShift(e) {
  e.preventDefault();
  const input = document.getElementById('starting_float');
  const val = parseFloat(input?.value);
  if (isNaN(val) || val < 0) {
    toast('请输入有效的初始备用金');
    return;
  }

  try {
    const resp = await fetch('./api/pos_shift_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ action: 'start', starting_float: val })
    });
    const result = await resp.json();

    if (resp.ok && result.status === 'success') {
      HAS_ACTIVE_SHIFT = true;
      try { startShiftModal?.hide(); } catch(_) {}
      await checkShiftStatus(); // 刷新状态
      toast('已开始当班');
    } else {
      toast(`开始当班失败：${result.message || `HTTP ${resp.status}`}`);
    }
  } catch (err) {
    toast(`网络错误：${err.message}`);
  }
}

/** 结束当班 / 交接班 */
export async function handleEndShift(e) {
  e.preventDefault();
  const input = document.getElementById('counted_cash');
  const val = parseFloat(input?.value);
  if (isNaN(val) || val < 0) {
    toast('请填写正确的清点现金金额');
    return;
  }

  try {
    const resp = await fetch('./api/pos_shift_handler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ action: 'end', counted_cash: val })
    });
    const result = await resp.json();

    if (result.status === 'success') {
      // 关闭交接班弹窗（带兜底）
      const endEl = document.getElementById('endShiftModal');
      await new Promise((resolve) => {
        let done = false;
        const finish = () => { if (!done) { done = true; resolve(); } };
        if (endEl) endEl.addEventListener('hidden.bs.modal', finish, { once: true });
        setTimeout(finish, 900);
        try { endShiftModal?.hide(); } catch(_) { /* no-op */ }
      });

      // 广播“交接班完成”，由 eod.js 弹出“交接班完成”提示
      const payload = result.data || {};
      document.dispatchEvent(new CustomEvent('pos:eod-finished', {
        detail: { eod: payload.eod, eod_id: payload.eod_id }
      }));

      // 交接班后立即进入“未开班”状态并强制弹开班弹窗
      HAS_ACTIVE_SHIFT = false;
      await checkShiftStatus();
    } else {
      toast(`结束当班失败：${result.message || 'Unknown error'}`);
    }
  } catch (err) {
    toast(`网络错误：${err.message}`);
  }
}

/** 可选：回调注册 */
export function onEodFinished(callback) {
  document.addEventListener('pos:eod-finished', (e) => callback(e.detail));
}
