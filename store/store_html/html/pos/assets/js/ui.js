import { STATE } from './state.js';
import { t, fmtEUR } from './utils.js';

export function applyI18N() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.dataset.i18n;
        if (key) el.textContent = t(key);
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.dataset.i18nPlaceholder;
        if (key) el.placeholder = t(key);
    });
    $('#search_input').attr('placeholder', t('placeholder_search'));
    $('#lang_toggle').html(`<span class="flag">${STATE.lang === 'zh' ? '🇨🇳' : '🇪🇸'}</span> ${t('lang_' + STATE.lang)}`);
    // Also update points placeholder on lang change
    $('#points_to_redeem_input').attr('placeholder', t('points_redeem_placeholder'));
}

export function renderCategories() {
    const $wrap = $('#category_scroller').empty();
    STATE.categories.forEach(cat => {
        const active = STATE.active_category_key === cat.key ? 'active' : '';
        const label = STATE.lang === 'es' ? cat.label_es : cat.label_zh;
        $wrap.append($(`<button class="nav-link ${active}" data-cat="${cat.key}">${label}</button>`));
    });
}

export function renderProducts() {
    const $grid = $('#product_grid').empty();
    const q = ($('#search_input').val() || '').trim().toLowerCase();
    const filtered = STATE.products.filter(p => {
        const name = (STATE.lang === 'es' ? p.title_es : p.title_zh).toLowerCase();
        return p.category_key === STATE.active_category_key && (!q || name.includes(q) || p.id.toString().includes(q));
    });
    if (!filtered.length) {
        $grid.append(`<div class="col-12"><div class="alert alert-sheet">${t('no_products_in_category')}</div></div>`);
        return;
    }
    filtered.forEach(p => {
        const name = STATE.lang === 'es' ? (p.title_es || p.title_zh) : p.title_zh;
        const defaultVariant = p.variants.find(v => v.is_default) || p.variants[0];
        const priceDisplay = defaultVariant ? fmtEUR(defaultVariant.price_eur) : 'N/A';
        $grid.append(`<div class="col-6 col-sm-4 col-md-3 col-lg-2"><div class="product-card h-100 d-flex flex-column" data-id="${p.id}"><div class="flex-grow-1"><div class="product-title">${name}</div><div class="small text-muted">${p.id}</div></div><div class="d-flex justify-content-between align-items-center mt-2"><div class="product-price">${priceDisplay}</div><button class="btn btn-sm btn-brand">${t('choose_variant')}</button></div></div></div>`);
    });
}

export function renderAddons() {
    const $list = $('#addon_list').empty();
    STATE.addons.forEach(a => {
        const label = STATE.lang === 'es' ? a.label_es : a.label_zh;
        $list.append(`<div class="addon-chip" data-key="${a.key}" data-price="${a.price_eur}">${label} +${fmtEUR(a.price_eur)}</div>`);
    });
}

export function openCustomize(productId) {
    const product = STATE.products.find(p => p.id === productId);
    if (!product || !product.variants?.length) return;
    $('#customize_title').text(STATE.lang === 'es' ? product.title_es : product.title_zh);
    const $variantList = $('#customize_variants_list').empty();
    const defaultVariant = product.variants.find(v => v.is_default) || product.variants[0];
    product.variants.forEach(variant => {
        const variantName = STATE.lang === 'es' ? variant.name_es : variant.name_zh;
        const checked = variant.id === defaultVariant.id ? 'checked' : '';
        $variantList.append(`<input type="radio" class="btn-check" name="variant_selector" id="variant_${variant.id}" value="${variant.id}" ${checked}><label class="btn btn-pill" for="variant_${variant.id}">${variantName}</label>`);
    });
    $('#ice_50, #sugar_50').prop('checked', true);
    $('#remark_input').val('');
    $('#addon_list .addon-chip').removeClass('active');
    $('#customizeOffcanvas').data('product', product);
    updateCustomizePrice();
    new bootstrap.Offcanvas('#customizeOffcanvas').show();
}

export function updateCustomizePrice() {
    const product = $('#customizeOffcanvas').data('product');
    if (!product) return;
    const selectedVariantId = parseInt($('input[name="variant_selector"]:checked').val());
    const selectedVariant = product.variants.find(v => v.id === selectedVariantId);
    if (!selectedVariant) return;
    let price = parseFloat(selectedVariant.price_eur);
    $('#addon_list .addon-chip.active').each(function () {
        price += parseFloat($(this).data('price')) || 0;
    });
    $('#customize_price').text(fmtEUR(price));
}

export function refreshCartUI() {
    const totalQty = STATE.cart.reduce((sum, item) => sum + item.qty, 0);
    $('#cart_count').text(totalQty);
    const $list = $('#cart_items').empty();
    const cartToRender = STATE.calculatedCart.cart.length > 0 ? STATE.calculatedCart.cart : STATE.cart;
    if (cartToRender.length === 0) {
        $list.html(`<div class="text-center p-5 text-muted">${t('tip_empty_cart')}</div>`);
    } else {
        cartToRender.forEach(it => {
            const finalPrice = it.final_price ?? it.unit_price_eur;
            const originalPrice = it.original_price ?? it.unit_price_eur;
            const line_total = finalPrice * it.qty;
            const isDiscounted = finalPrice < originalPrice;
            const priceHtml = isDiscounted ? `<div class="fw-semibold">${fmtEUR(line_total)} <del class="text-muted small ms-1">${fmtEUR(originalPrice * it.qty)}</del></div>` : `<div class="fw-semibold">${fmtEUR(line_total)}</div>`;
            const discountInfoHtml = it.discount_applied ? `<div class="small text-brand">${t('promo_applied')}: ${it.discount_applied}</div>` : '';
            const custom_details = [`I${it.ice}`, `S${it.sugar}`, ...it.addons].join('·');
            const remark_html = it.remark ? `<div class="small text-muted">${t('remark').split('（')[0]}：${it.remark}</div>` : '';
            $list.append(`<div class="list-group-item"><div class="d-flex justify-content-between align-items-start"><div><div class="fw-semibold">${it.title} <span class="text-muted">(${it.variant_name})</span></div><div class="small text-muted">${custom_details}</div>${remark_html}${discountInfoHtml}</div><div class="text-end">${priceHtml}<div class="qty-stepper mt-1"><button class="btn btn-outline-ink btn-sm" data-act="dec" data-id="${it.id}"><i class="bi bi-dash-lg"></i></button><span>${it.qty}</span><button class="btn btn-outline-ink btn-sm" data-act="inc" data-id="${it.id}"><i class="bi bi-plus-lg"></i></button><button class="btn btn-outline-ink btn-sm ms-2" data-act="del" data-id="${it.id}"><i class="bi bi-trash3"></i></button></div></div></div></div>`);
        });
    }
    const subtotal = STATE.calculatedCart.subtotal > 0 ? STATE.calculatedCart.subtotal : STATE.cart.reduce((sum, item) => sum + (item.unit_price_eur * item.qty), 0);
    const finalTotal = STATE.calculatedCart.final_total > 0 ? STATE.calculatedCart.final_total : subtotal;
    $('#cart_total').text(fmtEUR(subtotal));
    $('#cart_payable').text(fmtEUR(finalTotal));
    $('#btn_cart_checkout_label').text(`${t('go_checkout')} · ${fmtEUR(finalTotal)}`);
}

export function updateMemberUI() {
    const $container = $('#member_section');
    const $pointsSection = $('#points_redemption_section');

    if (STATE.activeMember) {
        const member = STATE.activeMember;
        const name = [member.first_name, member.last_name].filter(Boolean).join(' ') || member.phone_number;
        const level = (STATE.lang === 'es' ? member.level_name_es : member.level_name_zh) || '-';
        const html = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-0">${name}</h6>
                    <small class="text-muted">${member.phone_number}</small>
                </div>
                <button class="btn btn-sm btn-outline-danger" id="btn_unlink_member">${t('member_unlink')}</button>
            </div>
            <div class="d-flex justify-content-between text-muted small mt-2">
                <span>${t('member_points')}: <strong>${parseFloat(member.points_balance).toFixed(2)}</strong></span>
                <span>${t('member_level')}: <strong>${level}</strong></span>
            </div>`;
        $container.html(html);
        $pointsSection.slideDown(); // Show points section
    } else {
        const html = `
            <div class="input-group">
                <input type="tel" class="form-control" id="member_search_phone" placeholder="${t('member_search_placeholder')}">
                <button class="btn btn-outline-secondary" type="button" id="btn_find_member">${t('member_find')}</button>
            </div>
            <div id="member_search_result" class="mt-2"></div>`;
        $container.html(html);
        $pointsSection.slideUp(); // Hide points section
        $('#points_to_redeem_input').val(''); // Clear input on unlink
        $('#points_feedback').text('');
    }
    applyI18N();
}