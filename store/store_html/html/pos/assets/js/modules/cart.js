import { STATE } from '../state.js';
import { refreshCartUI } from '../ui.js';
import { calculatePromotionsAPI } from '../api.js';
import { t, toast } from '../utils.js';

// A richer t function for replacements
function t_rich(key, replacements = {}) {
    let text = t(key);
    for (const placeholder in replacements) {
        text = text.replace(`{${placeholder}}`, replacements[placeholder]);
    }
    return text;
}

export function addToCart() {
    const product = $('#customizeOffcanvas').data('product');
    if (!product) return;
    const selectedVariantId = parseInt($('input[name="variant_selector"]:checked').val());
    const variant = product.variants.find(v => v.id === selectedVariantId);
    if (!variant) return;
    const ice = $('input[name="ice"]:checked').val();
    const sugar = $('input[name="sugar"]:checked').val();
    const remark = $('#remark_input').val().trim();
    const addons = [];
    $('#addon_list .addon-chip.active').each(function () {
        addons.push($(this).data('key'));
    });
    let finalPrice = parseFloat(variant.price_eur);
    addons.forEach(key => {
        const addon = STATE.addons.find(a => a.key === key);
        if (addon) finalPrice += parseFloat(addon.price_eur);
    });
    STATE.cart.push({
        id: `item_${Date.now()}`,
        product_id: product.id,
        variant_id: variant.id,
        title: STATE.lang === 'es' ? product.title_es : product.title_zh,
        variant_name: STATE.lang === 'es' ? variant.name_es : variant.name_zh,
        qty: 1,
        unit_price_eur: finalPrice,
        ice,
        sugar,
        addons,
        remark
    });
    calculatePromotions();
    bootstrap.Offcanvas.getInstance('#customizeOffcanvas').hide();
}

export function updateCartItem(id, action) {
    const idx = STATE.cart.findIndex(x => x.id === id);
    if (idx < 0) return;
    if (action === 'inc') STATE.cart[idx].qty++;
    if (action === 'dec') STATE.cart[idx].qty = Math.max(1, STATE.cart[idx].qty - 1);
    if (action === 'del') STATE.cart.splice(idx, 1);
    calculatePromotions();
}

export async function calculatePromotions(isCouponApplication = false) {
    const couponCode = $('#coupon_code_input').val().trim();
    STATE.activeCouponCode = couponCode;
    const pointsToRedeem = parseInt($('#points_to_redeem_input').val()) || 0;

    if (STATE.cart.length === 0) {
        STATE.calculatedCart = { cart: [], subtotal: 0, discount_amount: 0, final_total: 0 };
        refreshCartUI();
        $('#points_to_redeem_input').val('');
        $('#points_feedback').text('');
        return;
    }

    try {
        const oldTotal = STATE.calculatedCart.final_total || STATE.cart.reduce((sum, item) => sum + (item.unit_price_eur * item.qty), 0);
        
        const payload = {
            cart: STATE.cart,
            coupon_code: STATE.activeCouponCode,
            member_id: STATE.activeMember ? STATE.activeMember.id : null,
            points_to_redeem: pointsToRedeem
        };
        const result = await calculatePromotionsAPI(payload);
        STATE.calculatedCart = result;
        
        if (result.points_redemption) {
            const redeemed = result.points_redemption.points_redeemed;
            const discount = result.points_redemption.discount_amount;
            if (redeemed > 0) {
                $('#points_feedback').html(t_rich('points_feedback_applied', { points: `<strong>${redeemed}</strong>`, amount: `<strong>${discount}</strong>` })).removeClass('text-danger').addClass('text-success');
            } else if (pointsToRedeem > 0) {
                 $('#points_feedback').text(t('points_feedback_not_enough')).removeClass('text-success').addClass('text-danger');
            } else {
                 $('#points_feedback').text('');
            }
        }
        
        if (isCouponApplication && couponCode) {
            const newTotal = parseFloat(STATE.calculatedCart.final_total) || 0;
            if (newTotal < oldTotal) {
                toast(t('coupon_applied'));
            } else {
                toast(t('coupon_not_valid'));
            }
        }
    } catch (error) {
        console.error('Error calculating promotions:', error);
        STATE.calculatedCart = { cart: [], subtotal: 0, discount_amount: 0, final_total: 0 };
    } finally {
        refreshCartUI();
    }
}