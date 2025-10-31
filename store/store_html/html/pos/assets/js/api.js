import { STATE } from './state.js';
import { toast } from './utils.js';

async function apiCall(url, options = {}) {
    try {
        // CORE FIX: Add credentials to all API calls to send session cookies
        const fetchOptions = {
            ...options,
            credentials: 'same-origin'
        };

        const response = await fetch(url, fetchOptions);
        if (!response.ok) {
            let errorData;
            try {
                errorData = await response.json();
            } catch (e) {
                throw new Error(`Server returned error: ${response.status}`);
            }
            throw new Error(errorData.message || 'Unknown server error');
        }
        return response.json();
    } catch (error) {
        console.error(`API call to ${url} failed:`, error);
        toast(`Network Error: ${error.message}`);
        throw error;
    }
}

export async function fetchInitialData() {
    // Fetch main POS data (products, categories, etc.)
    const result = await apiCall('./api/pos_data_loader.php');
    if (result.status === 'success') {
        STATE.products = result.data.products;
        STATE.categories = result.data.categories;
        STATE.addons = result.data.addons;
        STATE.redemptionRules = result.data.redemption_rules || [];
        if (!STATE.active_category_key && STATE.categories.length > 0) {
            STATE.active_category_key = STATE.categories[0].key;
        }
    }
    // --- CORE ADDITION: Fetch print templates after main data ---
    await fetchPrintTemplates();
}

/**
 * Version: 2.2.0
 * Fetches all available print templates from the backend.
 */
export async function fetchPrintTemplates() {
    try {
        const result = await apiCall('./api/pos_print_handler.php?action=get_templates');
        if (result.status === 'success') {
            STATE.printTemplates = result.data || {};
            console.log('Print templates loaded:', STATE.printTemplates);
        } else {
             console.error('Failed to load print templates:', result.message);
             STATE.printTemplates = {}; // Ensure it's an empty object on failure
        }
    } catch (error) {
        console.error('Network error fetching print templates:', error);
        STATE.printTemplates = {}; // Ensure it's an empty object on network error
    }
}

/**
 * Version: 2.2.0
 * Fetches the specific data required for printing an EOD report.
 */
export async function fetchEodPrintData(reportId) {
    const result = await apiCall(`./api/pos_print_handler.php?action=get_eod_print_data&report_id=${reportId}`);
    if (result.status === 'success') {
        return result.data;
    } else {
        throw new Error(result.message || 'Failed to fetch EOD print data');
    }
}


export async function calculatePromotionsAPI(payload) {
    const result = await apiCall('api/calculate_promotions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    return result.data;
}

export async function submitOrderAPI(paymentPayload) {
     const payload = {
        cart: STATE.cart,
        coupon_code: STATE.activeCouponCode,
        redemption_rule_id: STATE.activeRedemptionRuleId,
        member_id: STATE.activeMember ? STATE.activeMember.id : null,
        payment: paymentPayload,
        points_redeemed: STATE.calculatedCart.points_redemption ? STATE.calculatedCart.points_redemption.points_redeemed : 0,
        points_discount: STATE.calculatedCart.points_redemption ? STATE.calculatedCart.points_redemption.discount_amount : 0
    };
    return await apiCall('api/submit_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
}