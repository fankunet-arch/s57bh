<?php
/**
 * TopTea POS - Data Loader API (Self-Contained)
 * Provides initial data required for the POS frontend to boot up.
 * Engineer: Gemini | Date: 2025-10-28 | Revision: 4.3 (Fix product_sku alias AND add resilience for missing redemption table)
 * Revision: 5.0 (Gemini | 2025-11-01 | Fix RMS JOIN logic)
 */

require_once realpath(__DIR__ . '/../../../pos_backend/core/config.php');

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Fetch all active POS categories
    $categories_sql = "SELECT category_code AS `key`, name_zh AS label_zh, name_es AS label_es FROM pos_categories WHERE deleted_at IS NULL ORDER BY sort_order ASC";
    $categories = $pdo->query($categories_sql)->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch all active menu items and their variants
    // --- 关键修复：将 pv.product_id 修正为 mi.product_code，并使用 LEFT JOIN 保证健壮性 ---
    $menu_sql = "
        SELECT 
            mi.id,
            mi.name_zh,
            mi.name_es,
            mi.image_url,
            pc.category_code,
            pv.id as variant_id,
            pv.variant_name_zh,
            pv.variant_name_es,
            pv.price_eur,
            pv.is_default,
            kp.product_code AS product_sku
        FROM pos_menu_items mi
        JOIN pos_item_variants pv ON mi.id = pv.menu_item_id
        JOIN pos_categories pc ON mi.pos_category_id = pc.id
        LEFT JOIN kds_products kp ON mi.product_code = kp.product_code
        WHERE mi.deleted_at IS NULL 
          AND mi.is_active = 1
          AND pv.deleted_at IS NULL
          AND pc.deleted_at IS NULL
        ORDER BY pc.sort_order, mi.sort_order, mi.id, pv.sort_order
    ";
    
    $results = $pdo->query($menu_sql)->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach ($results as $row) {
        $itemId = $row['id'];
        if (!isset($products[$itemId])) {
            $products[$itemId] = [
                'id' => $itemId, 
                'title_zh' => $row['name_zh'],
                'title_es' => $row['name_es'],
                'image_url' => $row['image_url'],
                'category_key' => $row['category_code'],
                'variants' => []
            ];
        }
        
        $products[$itemId]['variants'][] = [
            'id' => $row['variant_id'],
            'recipe_sku' => $row['product_sku'], // product_sku 可能为 NULL (如果 LEFT JOIN 失败)
            'name_zh' => $row['variant_name_zh'],
            'name_es' => $row['variant_name_es'],
            'price_eur' => (float)$row['price_eur'],
            'is_default' => (bool)$row['is_default']
        ];
    }

    $addons = [
        ['key' => 'boba', 'label_zh' => '珍珠', 'label_es' => 'Boba', 'price_eur' => 0.6],
        ['key' => 'coconut', 'label_zh' => '椰果', 'label_es' => 'Coco', 'price_eur' => 0.5],
        ['key' => 'pudding', 'label_zh' => '布丁', 'label_es' => 'Pudin', 'price_eur' => 0.7],
    ];

    // --- 健壮性修复：添加 try-catch 以防 pos_point_redemption_rules 表不存在 ---
    $redemption_rules = [];
    try {
        $rules_sql = "
            SELECT id, rule_name_zh, rule_name_es, points_required, reward_type, reward_value_decimal, reward_promo_id
            FROM pos_point_redemption_rules
            WHERE is_active = 1 AND deleted_at IS NULL
            ORDER BY points_required ASC
        ";
        $redemption_rules = $pdo->query($rules_sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // 记录错误，但不要让API崩溃
        error_log("POS Data Loader Warning: Could not load point redemption rules (Table might be missing). Error: " . $e->getMessage());
        $redemption_rules = []; // 默认返回空数组
    }
    // -------------------------------------------------------------------------------------------------

    $data_payload = [
        'products' => array_values($products),
        'addons' => $addons,
        'categories' => $categories,
        'redemption_rules' => $redemption_rules
    ];

    echo json_encode(['status' => 'success', 'data' => $data_payload]);

} catch (PDOException $e) {
    // 此处将捕获主查询（商品查询）中的任何错误
    error_log("POS Data Loader CRITICAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '从数据库加载POS数据失败。', 'debug' => $e->getMessage()]);
}
?>