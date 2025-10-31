// store_html/html/pos/assets/js/modules/member.js
// 会员模块（稳定内联提示：输入框下方 + DOM 重绘自动恢复 + 多制式请求回退）

import { STATE } from '../state.js';
import { updateMemberUI } from '../ui.js';

/* ----------------- 基础工具 ----------------- */
const $ = (sel, root=document) => root.querySelector(sel);
const getEl = id => document.getElementById(id);
const hasBootstrap = () => typeof window.bootstrap !== 'undefined';
const toStr = v => (v === undefined || v === null) ? '' : String(v);

// 输入净化：把任何值转成仅含数字或首位“+”
function sanitizePhoneInput(v){
  if (v && typeof v === 'object' && ('target' in v || 'preventDefault' in v)) v = '';
  const s = toStr(v);
  let t = s.replace(/[^\d+]/g, '');
  if (t.includes('+')) t = '+' + t.replace(/\+/g,'').replace(/[^\d]/g,'');
  return t;
}

/* ----------------- 鲁棒定位：手机号输入框 & 查找按钮 ----------------- */
const PHONE_INPUT_SELECTORS = [
  '#member_search_phone',           // ← 你页面实际使用
  '#member_search_input',
  '#member-phone',
  '#member_input',
  'input[name="member_phone"]',
  '#cartOffcanvas input[type="tel"]',
  '#cartOffcanvas .member-search input',
  '#cartOffcanvas header ~ div input.form-control',
  '#cartOffcanvas input.form-control',
  'input[type="tel"]'
];
function getPhoneInput(){
  for (const sel of PHONE_INPUT_SELECTORS){
    const el = $(sel);
    if (el) return el;
  }
  return null;
}

const SEARCH_BTN_SELECTORS = [
  '#btn_find_member',
  '#member_search_btn',
  '[data-action="member.search"]',
  '#cartOffcanvas .member-search button',
  '#cartOffcanvas header ~ div button',
];
function isSearchButton(el){
  if (!el) return false;
  for (const sel of SEARCH_BTN_SELECTORS){ if (el.matches?.(sel)) return true; }
  const txt = toStr(el.textContent).trim().toLowerCase();
  return ['查找','搜索','search','buscar'].some(k => txt.includes(k));
}

/* ----------------- 稳定的提示条挂载 + 自动恢复 ----------------- */
let lastHint = null;         // 记录最后一次提示内容，DOM 被重绘时可恢复
let hintObserver = null;

function getAnchor(){
  const input = getPhoneInput();
  if (!input) return null;
  // 尽量插在 .input-group 后，结构更稳
  return input.closest('.input-group') || input;
}

function ensureInlineHost(){
  const anchor = getAnchor();
  if (!anchor) return null;

  let mount = getEl('member_hint_inline_mount');
  if (!mount || !mount.isConnected){
    mount = document.createElement('div');
    mount.id = 'member_hint_inline_mount';
    anchor.insertAdjacentElement('afterend', mount);
  }
  Object.assign(mount.style, { marginTop:'8px' });

  // 建立 DOM 观察，若提示被重绘清掉则自动恢复
  const root = anchor.parentElement || document.body;
  if (!hintObserver){
    hintObserver = new MutationObserver(() => {
      const m = getEl('member_hint_inline_mount');
      if (!m || !m.isConnected){
        // 重新挂载并恢复
        const a = getAnchor();
        if (!a) return;
        const newMount = document.createElement('div');
        newMount.id = 'member_hint_inline_mount';
        a.insertAdjacentElement('afterend', newMount);
        Object.assign(newMount.style, { marginTop:'8px' });
        if (lastHint) renderInlineHint(lastHint, true);
      }
    });
    hintObserver.observe(root, { childList: true, subtree: true });
  }

  return mount;
}

function clearInline(){
  const mount = getEl('member_hint_inline_mount');
  if (mount) mount.innerHTML = '';
}

// 渲染提示（type: info / warn / ok / danger；右侧可带按钮）
function renderInlineHint(opts, isRestore=false){
  lastHint = { ...opts }; // 记录状态以便恢复
  const mount = ensureInlineHost();
  if (!mount) return;

  const { text, type='info', actionText=null, onAction=null, extraRight=null } = opts;

  mount.innerHTML = '';

  const palette = {
    info:   { bg:'#e9f2ff', border:'#0b5ed733', fg:'#0b5ed7' },
    warn:   { bg:'#fff6e5', border:'#b76e0033', fg:'#b76e00' },
    ok:     { bg:'#e9fbf1', border:'#197a4233',  fg:'#197a42' },
    danger: { bg:'#ffebee', border:'#b0002033', fg:'#b00020' }
  };
  const c = palette[type] || palette.info;

  const row = document.createElement('div');
  Object.assign(row.style, {
    display:'flex', alignItems:'center', gap:'10px',
    borderRadius:'10px', padding:'10px 12px',
    background:c.bg, border:`1px solid ${c.border}`, color:c.fg
  });

  const msg = document.createElement('div');
  msg.style.flex = '1';
  msg.style.minWidth = '0';
  msg.style.fontSize = '14px';
  msg.textContent = toStr(text);
  row.appendChild(msg);

  if (actionText && typeof onAction === 'function'){
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = actionText;
    btn.className = 'btn btn-sm btn-danger';
    btn.style.whiteSpace = 'nowrap';
    btn.addEventListener('click', e => { e.preventDefault(); onAction(); });
    row.appendChild(btn);
  }

  if (extraRight){
    const wrap = document.createElement('div');
    wrap.appendChild(extraRight);
    row.appendChild(wrap);
  }

  mount.appendChild(row);
  // 恢复渲染时不需要动画
  if (!isRestore) row.style.transition = 'opacity .12s ease-out';
}

/* ----------------- Bootstrap 辅助 ----------------- */
const modalOf = (id, opts={backdrop:'static', keyboard:false}) => {
  const el = getEl(id);
  if (!el || !hasBootstrap()) return null;
  return bootstrap.Modal.getOrCreateInstance(el, opts);
};
const offcanvasOf = id => {
  const el = getEl(id);
  if (!el || !hasBootstrap()) return null;
  return bootstrap.Offcanvas.getOrCreateInstance(el);
};
function detachToBody(id){
  const el = getEl(id);
  if (el && el.parentElement !== document.body) document.body.appendChild(el);
}
function forceVisibleModal(id){
  const modal = getEl(id); if (!modal) return;
  modal.classList.remove('fade');
  Object.assign(modal.style, { position:'fixed', inset:'0', display:'block', visibility:'visible', opacity:'1', zIndex:'1060' });
  const dlg = modal.querySelector('.modal-dialog');
  const content = modal.querySelector('.modal-content');
  if (dlg){
    Object.assign(dlg.style, {
      position:'fixed', left:'50%', top:'50%', transform:'translate(-50%,-50%)',
      width:'min(520px, calc(100vw - 32px))', maxWidth:'min(520px, calc(100vw - 32px))',
      display:'block', opacity:'1', zIndex:'1062'
    });
    dlg.classList.add('modal-dialog-centered');
  }
  if (content){
    Object.assign(content.style, { display:'block', width:'100%', visibility:'visible', opacity:'1', zIndex:'1063' });
  }
  document.querySelectorAll('.offcanvas-backdrop').forEach(n=>n.remove());
  document.body.classList.remove('offcanvas-backdrop');
}

/* ----------------- 打开创建会员 ----------------- */
export async function openMemberCreateModal(){
  const el = getEl('opsOffcanvas');
  const ops = offcanvasOf('opsOffcanvas');
  if (ops && el && el.classList.contains('show')) {
    await new Promise(r=>{ el.addEventListener('hidden.bs.offcanvas', r, {once:true}); ops.hide(); });
  }
  detachToBody('memberCreateModal');
  const m = modalOf('memberCreateModal'); if (m) m.show();
  forceVisibleModal('memberCreateModal');
}
export const showCreateMemberModal = openMemberCreateModal;

/* ----------------- API（JSON → x-www-form-urlencoded → GET 回退） ----------------- */
async function callMemberAPI(url, payload){
  // 1) JSON
  try{
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    if (r.ok){ const j = await r.json().catch(()=>null); if (j && (j.status==='success' || j.ok)) return j.data || j.member || null; }
  }catch(_e){}

  // 2) x-www-form-urlencoded（很多传统 PHP 会要求这个）
  try{
    const body = new URLSearchParams(payload).toString();
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
    if (r.ok){ const j = await r.json().catch(()=>null); if (j && (j.status==='success' || j.ok)) return j.data || j.member || null; }
  }catch(_e){}

  // 3) GET
  try{
    const qs = new URLSearchParams(payload).toString();
    const r = await fetch(`${url}?${qs}`, { method:'GET' });
    if (r.ok){ const j = await r.json().catch(()=>null); if (j && (j.status==='success' || j.ok)) return j.data || j.member || null; }
  }catch(_e){}

  return null;
}

/* ----------------- 关联/提示 ----------------- */
function linkMember(member){
  STATE.activeMember = member;
  updateMemberUI(member);

  const name = member?.name || member?.full_name || member?.display_name || (member?.phone ?? '');
  const vouchers =
    member?.coupon_count ??
    (Array.isArray(member?.vouchers) ? member.vouchers.length : undefined) ??
    (Array.isArray(member?.coupons) ? member.coupons.length : undefined) ??
    (Array.isArray(member?.available_coupons) ? member.available_coupons.length : 0);

  const right = document.createElement('button');
  right.className = 'btn btn-sm btn-outline-secondary';
  right.textContent = '解除关联';
  right.addEventListener('click', e => { e.preventDefault(); unlinkMember(); });

  renderInlineHint({
    text: `${toStr(name)} · 共 ${vouchers ?? 0} 张可用券`,
    type: 'ok',
    extraRight: right
  });
}

/* ----------------- 导出：查找会员 ----------------- */
export async function findMember(phone){
  try{
    const inputEl = getPhoneInput();
    const raw = phone ?? (inputEl ? inputEl.value : '');
    const input = sanitizePhoneInput(raw);

    if (!input){
      renderInlineHint({
        text: '请输入手机号',
        type: 'warn',
        actionText: '添加用户',
        onAction: async () => { await openMemberCreateModal(); $('#member_phone') && ($('#member_phone').value = ''); }
      });
      return null;
    }

    const payload = { action:'find', phone: input };
    const endpoints = ['api/pos_member_handler.php', 'api/member_handler.php'];
    let found = null;
    for (const url of endpoints){
      found = await callMemberAPI(url, payload);
      if (found) break;
    }

    if (found){
      linkMember(found);
      return found;
    } else {
      renderInlineHint({
        text: `未找到会员：${input}`,
        type: 'info',
        actionText: '添加用户',
        onAction: async () => { await openMemberCreateModal(); $('#member_phone') && ($('#member_phone').value = input); }
      });
      STATE.activeMember = null;
      updateMemberUI(null);
      return null;
    }
  }catch(err){
    console.error('[member.findMember] error:', err);
    renderInlineHint({ text:'查询失败，请稍后重试', type:'danger' });
    return null;
  }
}

/* ----------------- 导出：创建会员 ----------------- */
export async function createMember(payload){
  try{
    let name, phone, email;
    if (payload){
      name  = toStr(payload.name).trim();
      phone = sanitizePhoneInput(payload.phone);
      email = toStr(payload.email).trim();
    }else{
      name  = toStr($('#member_name')?.value).trim();
      phone = sanitizePhoneInput($('#member_phone')?.value);
      email = toStr($('#member_email')?.value).trim();
    }
    if (!name || !phone){
      renderInlineHint({ text:'姓名与手机号为必填', type:'warn' });
      return null;
    }

    const endpoints = ['api/pos_member_handler.php', 'api/member_handler.php'];
    const req = { action:'create', name, phone, email };
    let created = null;
    for (const url of endpoints){
      created = await callMemberAPI(url, req);
      if (created) break;
    }
    if (!created){
      created = { id:'LOCAL-'+Date.now(), name, phone, email, points:0, _local:true };
    }

    linkMember(created);
    const md = modalOf('memberCreateModal'); md && md.hide();
    return created;
  }catch(err){
    console.error('[member.createMember] error:', err);
    renderInlineHint({ text:'创建失败，请稍后重试', type:'danger' });
    return null;
  }
}

/* ----------------- 导出：解绑会员 ----------------- */
export function unlinkMember(){
  try{
    STATE.activeMember = null;
    updateMemberUI(null);
    renderInlineHint({
      text: '已解除会员关联',
      type: 'info',
      actionText: '添加用户',
      onAction: async () => { await openMemberCreateModal(); }
    });
  }catch(err){
    console.error('[member.unlinkMember] error:', err);
  }
}

/* ----------------- 事件绑定（鲁棒） ----------------- */
// 点击“查找”
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('button, a');
  if (btn && isSearchButton(btn)){ e.preventDefault(); findMember(); }
});
// 输入框回车
document.addEventListener('keydown', (e)=>{
  const input = getPhoneInput();
  if (e.key === 'Enter' && input && e.target === input){
    e.preventDefault(); findMember();
  }
});
// 手动打开“创建会员”
document.addEventListener('click', (e)=>{
  const t = e.target.closest('#btn_open_member_create, [data-action="member.create"], #btn_member_create');
  if (t){ e.preventDefault(); openMemberCreateModal(); }
});
// 提交创建（兼容两个 id）
document.addEventListener('click', (e)=>{
  const t = e.target.closest('#btn_member_submit, #btn_member_create[data-role="submit"]');
  if (t){ e.preventDefault(); createMember(); }
});
// Offcanvas 重新展示时，恢复提示
document.addEventListener('shown.bs.offcanvas', ()=>{
  if (lastHint) renderInlineHint(lastHint, true);
});
// ESC 关闭弹窗
document.addEventListener('keydown', (e)=>{
  if (e.key === 'Escape'){
    const el = getEl('memberCreateModal');
    if (el && el.classList.contains('show')){
      hasBootstrap() && bootstrap.Modal.getInstance(el)?.hide();
    }
  }
});
