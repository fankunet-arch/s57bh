<?php
/**
 * Toptea Store - KDS
 * KDS Data Helper Functions (HELPER LIBRARY)
 * Engineer: Gemini | Date: 2025-10-31 | Revision: 5.2 (Fix best_adjust to select step_category)
 *
 * 此文件现在是一个纯粹的函数库，供 API 处理器 (如 sop_handler.php) 调用。
 * 它不再处理 API 请求或输出 JSON。
 * (已移除导致无限循环的 require_once)
 */

if (function_exists('parse_code')) {
    return; // 防止重复加载
}

/* ───────────────── 2) 引擎工具函数 (Bilingual Enhanced) ───────────────── */
function parse_code(string $raw): ?array {
  $raw = strtoupper(trim($raw));
  if ($raw === '' || !preg_match('/^[A-Z0-9-]+$/', $raw)) return null;
  $seg = array_values(array_filter(explode('-', $raw), fn($s)=>$s!==''));
  if (count($seg) > 4) return null; // P / P-A / P-A-M / P-A-M-T
  return ['p'=>$seg[0]??'', 'a'=>$seg[1]??null, 'm'=>$seg[2]??null, 't'=>$seg[3]??null, 'raw'=>$raw];
}
function id_by_code(PDO $pdo, string $table, string $col, $val): ?int {
  if ($val===null || $val==='') return null;
  $st=$pdo->prepare("SELECT id FROM {$table} WHERE {$col}=? LIMIT 1"); $st->execute([$val]);
  $id=$st->fetchColumn(); return $id? (int)$id : null;
}
function get_product(PDO $pdo, string $p): ?array {
  $st=$pdo->prepare("SELECT id,product_code,is_active,is_deleted_flag, status_id FROM kds_products WHERE product_code=? LIMIT 1"); 
  $st->execute([$p]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null;
}
function base_recipe(PDO $pdo, int $pid): array {
  $sql="SELECT material_id,unit_id,quantity,step_category,sort_order
        FROM kds_product_recipes
        WHERE product_id=?
        ORDER BY sort_order, id";
  $st=$pdo->prepare($sql); $st->execute([$pid]); return $st->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function norm_cat(string $c): string {
  $c = trim(mb_strtolower($c));
  if (in_array($c, ['base','底料','diliao'], true)) return 'base';
  if (in_array($c, ['mix','mixing','调杯','tiao','blend'], true)) return 'mixing';
  if (in_array($c, ['top','topping','顶料','dingliao'], true)) return 'topping';
  return 'mixing';
}
function best_adjust(PDO $pdo, int $pid, int $mid, ?int $cup, ?int $ice, ?int $sweet): ?array {
  $cond=["product_id=?","material_id=?"]; $args=[$pid,$mid]; $score=[];
  if ($cup!==null){ $cond[]="(cup_id IS NULL OR cup_id=?)"; $args[]=$cup; $score[]="(cup_id IS NOT NULL)"; } else { $cond[]="(cup_id IS NULL)"; }
  if ($ice!==null){ $cond[]="(ice_option_id IS NULL OR ice_option_id=?)"; $args[]=$ice; $score[]="(ice_option_id IS NOT NULL)"; } else { $cond[]="(ice_option_id IS NULL)"; }
  if ($sweet!==null){ $cond[]="(sweetness_option_id IS NULL OR sweetness_option_id=?)"; $args[]=$sweet; $score[]="(sweetness_option_id IS NOT NULL)"; } else { $cond[]="(sweetness_option_id IS NULL)"; }
  $scoreExpr=$score? implode(' + ',$score):'0';
  $sql="SELECT material_id,quantity,unit_id,step_category FROM kds_recipe_adjustments
        WHERE ".implode(' AND ',$cond)." ORDER BY {$scoreExpr} DESC, id DESC LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute($args); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null;
}
// --- START: BILINGUAL FETCH FUNCTIONS ---
function m_name(PDO $pdo, int $mid): array {
  $st=$pdo->prepare("SELECT language_code, material_name FROM kds_material_translations WHERE material_id=?");
  $st->execute([$mid]);
  $names = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
  return ['zh' => $names['zh-CN'] ?? ('#'.$mid), 'es' => $names['es-ES'] ?? ('#'.$mid)];
}
function u_name(PDO $pdo, int $uid): array {
  $st=$pdo->prepare("SELECT language_code, unit_name FROM kds_unit_translations WHERE unit_id=?");
  $st->execute([$uid]);
  $names = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
  return ['zh' => $names['zh-CN'] ?? '', 'es' => $names['es-ES'] ?? ''];
}
function get_product_info(PDO $pdo, int $pid, int $status_id): array {
    $st_prod = $pdo->prepare("
        SELECT 
            pt_zh.product_name AS name_zh,
            pt_es.product_name AS name_es
        FROM kds_product_translations pt_zh
        LEFT JOIN kds_product_translations pt_es ON pt_zh.product_id = pt_es.product_id AND pt_es.language_code = 'es-ES'
        WHERE pt_zh.product_id = ? AND pt_zh.language_code = 'zh-CN'
    ");
    $st_prod->execute([$pid]);
    $info = $st_prod->fetch(PDO::FETCH_ASSOC) ?: [];

    $st_status = $pdo->prepare("
        SELECT status_name_zh, status_name_es 
        FROM kds_product_statuses 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $st_status->execute([$status_id]);
    $status_names = $st_status->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $info['status_name_zh'] = $status_names['status_name_zh'] ?? null;
    $info['status_name_es'] = $status_names['status_name_es'] ?? null;
    
    return $info;
}
function get_cup_names(PDO $pdo, ?int $cid): array {
    if ($cid === null) return ['zh' => null, 'es' => null];
    $st = $pdo->prepare("SELECT cup_name, sop_description_zh, sop_description_es FROM kds_cups WHERE id = ?");
    $st->execute([$cid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return ['zh' => $row['sop_description_zh'] ?? $row['cup_name'] ?? null, 'es' => $row['sop_description_es'] ?? $row['cup_name'] ?? null];
}
function get_ice_names(PDO $pdo, ?int $iid): array {
    if ($iid === null) return ['zh' => null, 'es' => null];
    $st = $pdo->prepare("SELECT language_code, sop_description FROM kds_ice_option_translations WHERE ice_option_id = ?");
    $st->execute([$iid]);
    $names = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    return ['zh' => $names['zh-CN'] ?? null, 'es' => $names['es-ES'] ?? $names['zh-CN'] ?? null];
}
function get_sweet_names(PDO $pdo, ?int $sid): array {
    if ($sid === null) return ['zh' => null, 'es' => null];
    $st = $pdo->prepare("SELECT language_code, sop_description FROM kds_sweetness_option_translations WHERE sweetness_option_id = ?");
    $st->execute([$sid]);
    $names = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    return ['zh' => $names['zh-CN'] ?? null, 'es' => $names['es-ES'] ?? $names['zh-CN'] ?? null];
}
// --- END: BILINGUAL FETCH FUNCTIONS ---

// --- NEW: Function to get base recipe (bilingual) ---
function get_base_recipe(PDO $pdo, int $pid): array {
    $st = $pdo->prepare("
        SELECT r.material_id, r.quantity, r.unit_id, r.step_category
        FROM kds_product_recipes r
        WHERE r.product_id = ? ORDER BY r.sort_order ASC, r.id ASC
    ");
    $st->execute([$pid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    
    $recipe = [];
    foreach ($rows as $row) {
        $m_names = m_name($pdo, (int)$row['material_id']);
        $u_names = u_name($pdo, (int)$row['unit_id']);
        $recipe[] = [
            'material_id'   => (int)$row['material_id'],
            'material_zh' => $m_names['zh'],
            'material_es' => $m_names['es'],
            'quantity'      => (float)$row['quantity'],
            'unit_id'       => (int)$row['unit_id'],
            'unit_zh'       => $u_names['zh'],
            'unit_es'       => $u_names['es'],
            'step_category' => norm_cat((string)$row['step_category'])
        ];
    }
    return $recipe;
}

// --- NEW: Function to get available options (bilingual) ---
function get_available_options(PDO $pdo, int $pid, string $p_code): array {
    $options = [ 'cups' => [], 'ice_options' => [], 'sweetness_options' => [] ];
    
    // 1. Get Cups (Linked via pos_menu_items -> pos_item_variants)
    $cup_sql = "
        SELECT DISTINCT c.id, c.cup_code, c.cup_name, c.sop_description_zh, c.sop_description_es
        FROM kds_cups c
        JOIN pos_item_variants piv ON c.id = piv.cup_id
        JOIN pos_menu_items pmi ON piv.menu_item_id = pmi.id
        WHERE pmi.product_code = ? AND c.deleted_at IS NULL
    ";
    $stmt_cups = $pdo->prepare($cup_sql);
    $stmt_cups->execute([$p_code]);
    $options['cups'] = $stmt_cups->fetchAll(PDO::FETCH_ASSOC);

    // 2. Get Ice Options (Linked via kds_product_ice_options)
    $ice_sql = "
        SELECT io.id, io.ice_code, iot_zh.ice_option_name AS name_zh, iot_es.ice_option_name AS name_es
        FROM kds_product_ice_options pio
        JOIN kds_ice_options io ON pio.ice_option_id = io.id
        LEFT JOIN kds_ice_option_translations iot_zh ON io.id = iot_zh.ice_option_id AND iot_zh.language_code = 'zh-CN'
        LEFT JOIN kds_ice_option_translations iot_es ON io.id = iot_es.ice_option_id AND iot_es.language_code = 'es-ES'
        WHERE pio.product_id = ? AND io.deleted_at IS NULL
    ";
    $stmt_ice = $pdo->prepare($ice_sql);
    $stmt_ice->execute([$pid]);
    $options['ice_options'] = $stmt_ice->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Get Sweetness Options (Linked via kds_product_sweetness_options)
    $sweet_sql = "
        SELECT so.id, so.sweetness_code, sot_zh.sweetness_option_name AS name_zh, sot_es.sweetness_option_name AS name_es
        FROM kds_product_sweetness_options pso
        JOIN kds_sweetness_options so ON pso.sweetness_option_id = so.id
        LEFT JOIN kds_sweetness_option_translations sot_zh ON so.id = sot_zh.sweetness_option_id AND sot_zh.language_code = 'zh-CN'
        LEFT JOIN kds_sweetness_option_translations sot_es ON so.id = sot_es.sweetness_option_id AND sot_es.language_code = 'es-ES'
        WHERE pso.product_id = ? AND so.deleted_at IS NULL
    ";
    $stmt_sweet = $pdo->prepare($sweet_sql);
    $stmt_sweet->execute([$pid]);
    $options['sweetness_options'] = $stmt_sweet->fetchAll(PDO::FETCH_ASSOC);

    return $options;
}
}