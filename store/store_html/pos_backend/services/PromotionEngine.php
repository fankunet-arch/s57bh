<?php
/**
 * TopTea POS - Promotion Engine Service
 * Calculates discounts based on active promotion rules.
 * Engineer: Gemini | Date: 2025-10-27 | Revision: 1.5 (Coupon Code Logic)
 */

class PromotionEngine
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function applyPromotions(array $cart, ?string $couponCode = null): array
    {
        $applicablePromos = $this->getApplicablePromotions($couponCode);
        
        $cartWithProductIds = [];
        foreach ($cart as $item) {
            $stmt = $this->pdo->prepare("SELECT mi.id FROM pos_item_variants piv JOIN pos_menu_items mi ON piv.menu_item_id = mi.id WHERE piv.id = ?");
            $stmt->execute([$item['variant_id']]); 
            $menu_item = $stmt->fetch();
            $item['product_id'] = $menu_item['id'] ?? null;
            $cartWithProductIds[] = $item;
        }

        $calculatedCart = $this->initializeCartForPromotions($cartWithProductIds);

        foreach ($applicablePromos as $promo) {
            $conditions = json_decode($promo['promo_conditions'], true);
            $actions = json_decode($promo['promo_actions'], true);

            if ($this->isBogoBuyOneGetOne($promo, $conditions, $actions)) {
                $calculatedCart = $this->applyBogo($calculatedCart, $promo, $conditions, $actions);
            } elseif ($this->isPercentageDiscount($promo, $conditions, $actions)) {
                $calculatedCart = $this->applyPercentageDiscount($calculatedCart, $promo, $conditions, $actions);
            }
        }

        $subtotal = 0;
        foreach ($cart as $originalItem) {
            $subtotal += $originalItem['unit_price_eur'] * $originalItem['qty'];
        }
        
        $finalTotal = 0;
        foreach ($calculatedCart as $item) {
            $pricePerUnit = $item['final_price'] ?? $item['unit_price_eur'];
            $finalTotal += $pricePerUnit * $item['qty'];
        }
        
        $discountAmount = $subtotal - $finalTotal;

        return [
            'cart' => $calculatedCart,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'final_total' => number_format($finalTotal, 2, '.', ''),
        ];
    }

    private function getApplicablePromotions(?string $couponCode = null): array
    {
        $now = date('Y-m-d H:i:s');
        $params = [':now_start' => $now, ':now_end' => $now];
        
        $sql = "
            SELECT * FROM pos_promotions 
            WHERE promo_is_active = 1
              AND (promo_start_date IS NULL OR promo_start_date <= :now_start)
              AND (promo_end_date IS NULL OR promo_end_date >= :now_end)
              AND (
                promo_trigger_type = 'AUTO_APPLY'";
        
        if (!empty($couponCode)) {
            $sql .= " OR (promo_trigger_type = 'COUPON_CODE' AND promo_code = :promo_code)";
            $params[':promo_code'] = $couponCode;
        }
        
        $sql .= ") ORDER BY promo_priority ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function initializeCartForPromotions(array $cart): array
    {
        foreach ($cart as &$item) {
            $item['original_price'] = $item['unit_price_eur'];
            $item['final_price'] = $item['unit_price_eur'];
            $item['discount_applied'] = null;
        }
        return $cart;
    }
    
    private function isBogoBuyOneGetOne(?array $promo, ?array $conditions, ?array $actions): bool
    {
        return !empty($conditions) && !empty($actions) && ($conditions[0]['type'] ?? '') === 'ITEM_QUANTITY' && ($actions[0]['type'] ?? '') === 'SET_PRICE_ZERO';
    }

    private function applyBogo(array $cart, array $promo, array $conditions, array $actions): array
    {
        $condition = $conditions[0]; $action = $actions[0];
        $targetItemIds = $condition['item_ids'] ?? []; $minQuantity = (int)($condition['min_quantity'] ?? 9999); $numToDiscount = (int)($action['quantity'] ?? 0);
        if (empty($targetItemIds) || $numToDiscount === 0) return $cart;
        $eligibleItems = [];
        foreach ($cart as $index => $item) { if (in_array($item['product_id'], $targetItemIds) && $item['final_price'] > 0) { for ($i = 0; $i < $item['qty']; $i++) { $eligibleItems[] = ['cart_index' => $index, 'price' => $item['final_price']]; } } }
        if (count($eligibleItems) < $minQuantity) return $cart;
        usort($eligibleItems, fn($a, $b) => $a['price'] <=> $b['price']);
        $itemsToDiscount = array_slice($eligibleItems, 0, $numToDiscount); $discountsPerCartIndex = [];
        foreach ($itemsToDiscount as $item) { $cartIndex = $item['cart_index']; $discountsPerCartIndex[$cartIndex] = ($discountsPerCartIndex[$cartIndex] ?? 0) + 1; }
        $newCart = [];
        foreach ($cart as $index => $item) {
            if (isset($discountsPerCartIndex[$index])) {
                $numDiscounts = $discountsPerCartIndex[$index]; $paidQty = $item['qty'] - $numDiscounts;
                if ($paidQty > 0) { $paidItem = $item; $paidItem['qty'] = $paidQty; $newCart[] = $paidItem; }
                $freeItem = $item; $freeItem['qty'] = $numDiscounts; $freeItem['final_price'] = 0.00; $freeItem['discount_applied'] = $promo['promo_name']; $freeItem['id'] = $item['id'] . '_free'; $newCart[] = $freeItem;
            } else { $newCart[] = $item; }
        }
        return $newCart;
    }

    private function isPercentageDiscount(?array $promo, ?array $conditions, ?array $actions): bool
    {
        return !empty($actions) && ($actions[0]['type'] ?? '') === 'PERCENTAGE_DISCOUNT';
    }

    private function applyPercentageDiscount(array $cart, array $promo, ?array $conditions, array $actions): array
    {
        $action = $actions[0]; $targetItemIds = $action['item_ids'] ?? []; $discountPercentage = (float)($action['percentage'] ?? 0);
        if (empty($targetItemIds) || $discountPercentage <= 0 || $discountPercentage > 100) { return $cart; }
        $discountMultiplier = 1 - ($discountPercentage / 100);
        foreach ($cart as &$item) { if (in_array($item['product_id'], $targetItemIds) && $item['final_price'] === $item['original_price']) { $item['final_price'] = round($item['original_price'] * $discountMultiplier, 2); $item['discount_applied'] = $promo['promo_name']; } }
        unset($item);
        return $cart;
    }
}