<?php
/**
 * TopTea POS - Calculate Promotions API
 * Revision: 2.0 (Points Redemption Logic)
 * Patched by Gemini · 2025-10-28
 */

require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');
require_once realpath(__DIR__ . '/../../../pos_backend/services/PromotionEngine.php');

header('Content-Type: application/json; charset=utf-8');

function send_json_response($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response('error', 'Invalid request method.');
}

$raw = file_get_contents('php://input');
$json_data = json_decode($raw, true);

if (!$json_data || !isset($json_data['cart'])) {
    http_response_code(400);
    send_json_response('error', 'Cart data is missing.');
}

try {
    $cart = $json_data['cart'];
    $couponCode = null;
    foreach (['coupon_code','coupon','code','promo_code','discount_code'] as $k) {
        if (isset($json_data[$k]) && trim((string)$json_data[$k]) !== '') {
            $couponCode = trim((string)$json_data[$k]);
            break;
        }
    }

    // --- NEW: Get Member & Points Redemption Info ---
    $member_id = isset($json_data['member_id']) ? (int)$json_data['member_id'] : null;
    $points_to_redeem = isset($json_data['points_to_redeem']) ? (int)$json_data['points_to_redeem'] : 0;
    
    // Step 1: Apply standard promotions first
    $engine = new PromotionEngine($pdo);
    $promoResult = $engine->applyPromotions($cart, $couponCode);
    
    $final_total = (float)$promoResult['final_total'];
    $points_discount = 0.0;
    $points_redeemed = 0;

    // Step 2: If member and points are provided, calculate points discount
    if ($member_id && $points_to_redeem > 0 && $final_total > 0) {
        // Fetch member's current points balance
        $stmt_member = $pdo->prepare("SELECT points_balance FROM pos_members WHERE id = ? AND is_active = 1");
        $stmt_member->execute([$member_id]);
        $member = $stmt_member->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            $current_points = (float)$member['points_balance'];
            
            // Rule: 100 points = 1 EUR discount
            $max_possible_discount = $final_total;
            $max_points_for_discount = floor($max_possible_discount * 100);

            // Determine the actual points that can be redeemed
            $points_can_be_used = min($points_to_redeem, $current_points, $max_points_for_discount);

            if ($points_can_be_used > 0) {
                $points_redeemed = $points_can_be_used;
                $points_discount = floor($points_can_be_used) / 100.0; // Use floor to avoid partial points
                
                // Apply the discount to the final total
                $final_total -= $points_discount;
            }
        }
    }
    
    // Step 3: Recalculate total discount amount
    $total_discount_amount = (float)$promoResult['discount_amount'] + $points_discount;

    // Step 4: Prepare the final response payload
    $result = [
        'cart' => $promoResult['cart'],
        'subtotal' => $promoResult['subtotal'],
        'discount_amount' => number_format($total_discount_amount, 2, '.', ''),
        'final_total' => number_format($final_total, 2, '.', ''),
        'points_redemption' => [
            'points_redeemed' => $points_redeemed,
            'discount_amount' => number_format($points_discount, 2, '.', '')
        ]
    ];

    send_json_response('success', 'Promotions and points calculated successfully.', $result);

} catch (Throwable $e) {
    http_response_code(500);
    send_json_response('error', 'Failed to calculate promotions.', [
        'hint' => 'calc',
        'line' => $e->getLine(),
        'message' => $e->getMessage()
    ]);
}