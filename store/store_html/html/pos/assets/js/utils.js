import { STATE, I18N } from './state.js';

export function t(key) {
    return (I18N[STATE.lang]?.[key] || I18N['zh'][key]) || key;
}

export function fmtEUR(n) {
    return `€${(Math.round(parseFloat(n) * 100) / 100).toFixed(2)}`;
}

export function toast(msg) {
    const t = new bootstrap.Toast('#sys_toast', { delay: 2500 });
    $('#toast_msg').text(msg);
    t.show();
}