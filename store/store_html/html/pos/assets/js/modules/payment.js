import { STATE } from '../state.js';
import { t, fmtEUR, toast } from '../utils.js';
import { submitOrderAPI } from '../api.js';
import { calculatePromotions } from './cart.js';
import { unlinkMember } from './member.js';

let paymentConfirmModal = null;

/**
 * 入口：打开结账弹窗
 * 核心修改：不再默认添加现金行，支付区域初始为空。
 */
export function openPaymentModal() {
    if (STATE.cart.length === 0) {
        toast(t('tip_empty_cart'));
        return;
    }
    const finalTotal = parseFloat(STATE.calculatedCart.final_total) || 0;
    STATE.payment = { total: finalTotal, parts: [] };

    // 清空支付区域
    $('#payment_parts_container').empty();
    
    updatePaymentState(); // 更新一次UI确保金额显示正确
    
    bootstrap.Offcanvas.getInstance('#cartOffcanvas')?.hide();
    new bootstrap.Modal('#paymentModal').show();
}

/**
 * UI更新：根据输入框金额刷新“应收/已收/剩余/找零”
 */
export function updatePaymentState() {
    let totalPaid = 0;
    $('#payment_parts_container .payment-part-input').each(function () {
        totalPaid += parseFloat($(this).val()) || 0;
    });
    const totalReceivable = STATE.payment.total;
    const remaining = totalReceivable - totalPaid;
    const change = remaining < 0 ? -remaining : 0;
    
    $('#payment_total_display').text(fmtEUR(totalReceivable));
    $('#payment_paid_display').text(fmtEUR(totalPaid));
    $('#payment_remaining_display').text(fmtEUR(remaining > 0 ? remaining : 0));
    $('#payment_change_display').text(fmtEUR(change));
    
    // 只有在实收金额大于等于应收金额时，才启用确认按钮
    $('#btn_confirm_payment').prop('disabled', totalPaid < totalReceivable);
}

/**
 * 添加新的支付方式行
 * 核心修改：添加行时不再自动填充金额。
 */
export function addPaymentPart(method) {
    const $newPart = $(`#payment_templates .payment-part[data-method="${method}"]`).clone();
    
    // 清空默认值
    $newPart.find('.payment-part-input').val('');

    $('#payment_parts_container').append($newPart);
    $newPart.find('.payment-part-input').focus();
    updatePaymentState();
}

/**
 * 新功能：处理快捷现金按钮点击
 */
export function handleQuickCash(value) {
    let $cashInputs = $('#payment_parts_container .payment-part[data-method="Cash"] .payment-part-input');
    
    if ($cashInputs.length === 0) {
        // 如果没有现金输入框，则创建一个
        addPaymentPart('Cash');
        $cashInputs = $('#payment_parts_container .payment-part[data-method="Cash"] .payment-part-input');
    }
    
    // 将金额填入最后一个现金输入框并触发更新
    const $lastCashInput = $cashInputs.last();
    $lastCashInput.val(parseFloat(value).toFixed(2));
    $lastCashInput.trigger('input'); 
}

/**
 * 核心功能：打开最终收款确认弹窗
 */
export function initiatePaymentConfirmation(event) {
    event.preventDefault();

    // 1. 先关闭当前的结账弹窗，解决叠加问题
    const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
    if (paymentModal) {
        paymentModal.hide();
    }

    const paymentParts = [];
    let totalPaid = 0;
    let cashTendered = 0;

    // 2. 收集所有支付信息
    $('#payment_parts_container .payment-part').each(function () {
        const $part = $(this);
        const method = $part.data('method');
        const amount = parseFloat($part.find('.payment-part-input').val()) || 0;
        if (amount > 0) {
            const partData = { method: method, amount: amount };
            if (method === 'Platform') {
                partData.reference = $part.find('.payment-part-ref').val().trim();
            }
            paymentParts.push(partData);
            totalPaid += amount;
            if (method === 'Cash') {
                cashTendered += amount;
            }
        }
    });

    const finalTotal = STATE.payment.total;
    const change = Math.max(0, totalPaid - finalTotal);
    const lack = Math.max(0, finalTotal - totalPaid);

    // 3. 准备并显示最终确认弹窗
    if (!paymentConfirmModal) {
        paymentConfirmModal = new bootstrap.Modal(document.getElementById('paymentConfirmModal'));
    }

    $('#pc-due').text(fmtEUR(finalTotal));
    $('#pc-paid').text(fmtEUR(totalPaid));
    $('#pc-change').text(fmtEUR(change));

    const $methodsContainer = $('#pc-methods').empty();
    if (paymentParts.length > 0) {
        paymentParts.forEach(p => {
            let bookedAmount = p.amount;
            // 核心逻辑：如果是现金且有找零，入账金额需要减去相应部分的找零
            if (p.method === 'Cash' && change > 0 && cashTendered > 0) {
                const cashPortion = p.amount / cashTendered;
                const changeToDeduct = change * cashPortion;
                bookedAmount = Math.max(0, p.amount - changeToDeduct);
            }
            $methodsContainer.append(`<div class="d-flex justify-content-between py-1 border-bottom small"><span>${p.method} ${p.reference ? `(${p.reference})` : ''}</span><span class="fw-semibold">${fmtEUR(bookedAmount)}</span></div>`);
        });
    } else {
        $methodsContainer.html('<div class="small text-muted">—</div>');
    }

    if (lack > 0) {
        $('#pc-warning').removeClass('d-none').find('#pc-lack').text(fmtEUR(lack));
        $('#pc-note').addClass('d-none');
        $('#pc-confirm').prop('disabled', true);
    } else {
        $('#pc-warning').addClass('d-none');
        if (change > 0) {
            $('#pc-note').removeClass('d-none').find('#pc-note-change').text(fmtEUR(change));
        } else {
            $('#pc-note').addClass('d-none');
        }
        $('#pc-confirm').prop('disabled', false);
    }
    
    // 4. 绑定最终提交事件
    $('#pc-confirm').off('click').on('click', function() {
        paymentConfirmModal.hide();
        submitOrder(); 
    });

    // 监听返回修改按钮，重新打开结账窗口
    $('#paymentConfirmModal [data-bs-dismiss="modal"]').off('click').on('click', function() {
        paymentModal.show();
    });

    paymentConfirmModal.show();
}

/**
 * 最终提交订单到后端
 */
export async function submitOrder() {
    const checkoutBtn = $('#btn_confirm_payment');
    checkoutBtn.prop('disabled', true).html(`<span class="spinner-border spinner-border-sm me-2"></span>${t('submitting_order')}`);

    const paymentParts = [];
    let totalPaid = 0;
    $('#payment_parts_container .payment-part').each(function () {
        const $part = $(this);
        const method = $part.data('method');
        const amount = parseFloat($part.find('.payment-part-input').val()) || 0;
        if (amount > 0) {
            const partData = { method: method, amount: amount };
            if (method === 'Platform') {
                partData.reference = $part.find('.payment-part-ref').val().trim();
            }
            paymentParts.push(partData);
            totalPaid += amount;
        }
    });

    const paymentPayload = {
        total: STATE.payment.total,
        paid: totalPaid,
        change: totalPaid - STATE.payment.total > 0 ? totalPaid - STATE.payment.total : 0,
        summary: paymentParts
    };

    try {
        const result = await submitOrderAPI(paymentPayload);
        if (result.status === 'success') {
            bootstrap.Modal.getInstance('#paymentModal')?.hide();
            
            if (result.data.invoice_number === 'NO_INVOICE') {
                $('#orderSuccessModal [data-i18n="order_success"]').text(t('payment_success'));
                $('#orderSuccessModal [data-i18n="invoice_number"]').hide();
                $('#success_invoice_number').hide();
                $('#orderSuccessModal [data-i18n="qr_code_info"]').hide();
                $('#success_qr_content').closest('div').hide();
            } else {
                $('#orderSuccessModal [data-i18n="order_success"]').text(t('order_success'));
                $('#orderSuccessModal [data-i18n="invoice_number"]').show();
                $('#success_invoice_number').show().text(result.data.invoice_number);
                $('#orderSuccessModal [data-i18n="qr_code_info"]').show();
                $('#success_qr_content').closest('div').show().find('code').text(result.data.qr_content);
            }
            
            new bootstrap.Modal('#orderSuccessModal').show();

            // Reset state after successful order
            STATE.cart = [];
            STATE.activeCouponCode = '';
            $('#coupon_code_input').val('');
            unlinkMember();
            calculatePromotions();
        } else {
            throw new Error(result.message || 'Unknown server error.');
        }
    } catch (error) {
        console.error('Failed to submit order:', error);
        toast((t('order_submit_failed') || '订单提交失败') + ': ' + error.message);
    } finally {
        checkoutBtn.prop('disabled', false).html(t('confirm_payment'));
    }
}