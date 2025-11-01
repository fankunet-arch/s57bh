<?php
/**
 * TopTea · KDS · SOP 查询接口 (V10 - 最终自包含修复版)
 *
 * 1. 本文件完全自包含，不再 require_once 'kds_helper.php'，以绕过原助手文件中的致命 Parse Error。
 * 2. 移植了 V8.1 的完整动态配方逻辑 (best_adjust) 和所有依赖函数。
 * 3. 使用 V9.x 的路径逻辑加载 config.php (经 "可通讯文件" 验证有效)。
 * 4. 修复了 get_available_options 中隐藏的 SQL bug (pmi.product_code)。
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* ========= 通用输出 ========= */
function out_json(string $s, string $m, $d=null, int $code=200){
  http_response_code($code);
  echo json_encode(['status'=>$s,'message'=>$m,'data'=>$d], JSON_UNESCAPED_UNICODE);
  exit;
}
function ok($d){ out_json('success','OK',$d,200); }

/* ========= 1) 引导配置 (路径已验证) ========= */
try {
    $__html_root   = dirname(__DIR__, 2); // /.../store_html/html
    $__config_path = realpath($__html_root . '/../kds/core/config.php');
    
    if (!$__config_path || !file_exists($__config_path)) {
      out_json('error','[V10] 配置文件未找到', ['expected'=>$__html_root . '/../kds/core/config.php'], 500);
    }
    
    require_once $__config_path; // $pdo
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
      out_json('error','[V10] 数据库连接失败。', null, 500);
    }
} catch (Throwable $e) {
    out_json('error', '[V10] 引导失败: ' . $e->getMessage(), null, 500);
}


/* ========= 2) 移植 KDS Helper 核心函数 (自包含) ========= */

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
    if ($cid === null) return ['cup_name_zh' => null, 'cup_name_es' => null];
    $st = $pdo->prepare("SELECT cup_name FROM kds_cups WHERE id = ?");
    $st->execute([$cid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    // V10: 简化，SOP 左侧概览仅需 cup_name
    return ['cup_name_zh' => $row['cup_name'] ?? null, 'cup_name_es' => $row['cup_name'] ?? null];
}
function get_ice_names(PDO $pdo, ?int $iid): array {
    if ($iid === null) return ['ice_name_zh' => null, 'ice_name_es' => null];
    $st = $pdo->prepare("SELECT language_code, ice_option_name FROM kds_ice_option_translations WHERE ice_option_id = ?");
    $st->execute([$iid]);
    $names = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    return ['ice_name_zh' => $names['zh-CN'] ?? null, 'ice_name_es' => $names['es-ES'] ?? $names['zh-CN'] ?? null];
}
function get_sweet_names(PDO $pdo, ?int $sid): array {
    if ($sid === null) return ['sweetness_name_zh' => null, 'sweetness_name_es' => null];
    $st = $pdo->prepare("SELECT language_code, sweetness_option_name FROM kds_sweetness_option_translations WHERE sweetness_option_id = ?");
    $st->execute([$sid]);
    $names = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    return ['sweetness_name_zh' => $names['zh-CN'] ?? null, 'sweetness_name_es' => $names['es-ES'] ?? $names['zh-CN'] ?? null];
}
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
function get_available_options(PDO $pdo, int $pid, string $p_code): array {
    $options = [ 'cups' => [], 'ice_options' => [], 'sweetness_options' => [] ];
    
    // 1. Get Cups (BUG FIX V10: 链接 `piv.product_id` 而不是 `pmi.product_code`)
    $cup_sql = "
        SELECT DISTINCT c.id, c.cup_code, c.cup_name, c.sop_description_zh, c.sop_description_es
        FROM kds_cups c
        JOIN pos_item_variants piv ON c.id = piv.cup_id
        WHERE piv.product_id = ? AND c.deleted_at IS NULL AND piv.deleted_at IS NULL
    ";
    $stmt_cups = $pdo->prepare($cup_sql);
    $stmt_cups->execute([$pid]);
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

/* -------------------- 3) 主流程 (V10 移植) -------------------- */
try{
  $raw = $_GET['code'] ?? '';
  $seg = parse_code($raw); // 使用内部函数
  if (!$seg || $seg['p']==='') out_json('error','编码不合法', null, 400);

  // 1. 验证产品
  $prod = get_product($pdo, $seg['p']); // 使用内部函数
  if(!$prod || (int)$prod['is_deleted_flag']!==0 || (int)$prod['is_active']!==1){
    out_json('error','找不到该产品或未上架 (P-Code: ' . htmlspecialchars($seg['p']) . ')', null, 404);
  }
  $pid = (int)$prod['id'];

  // 2. 获取产品基础信息 (名称, 状态)
  $prod_info = array_merge(
    ['product_id'=>$pid, 'product_code'=>$prod['product_code']],
    get_product_info($pdo, $pid, (int)$prod['status_id']) // 内部函数
  );

  // 3. (P-Code ONLY) 仅查询基础信息
  if ($seg['a']===null && $seg['m']===null && $seg['t']===null) {
     ok([
       'type'=>'base_info',
       'product'=>$prod_info,
       'recipe'=> get_base_recipe($pdo, $pid), // 内部函数
       'options'=> get_available_options($pdo, $pid, $prod['product_code']) // 内部函数 (已修复)
     ]);
  }

  // 4. (P-A-M-T) 动态计算配方
  
  // 4a. 将 A,M,T 码转换为 数据库 ID
  $cup_id = id_by_code($pdo, 'kds_cups', 'cup_code', $seg['a']);
  if ($seg['a'] !== null && $cup_id === null) {
      out_json('error', '杯型编码 (A-code) 无效: ' . htmlspecialchars($seg['a']), null, 404);
  }
  
  $ice_id = id_by_code($pdo, 'kds_ice_options', 'ice_code', $seg['m']);
  if ($seg['m'] !== null && $ice_id === null) {
      out_json('error', '冰量编码 (M-code) 无效: ' . htmlspecialchars($seg['m']), null, 404);
  }

  $sweet_id = id_by_code($pdo, 'kds_sweetness_options', 'sweetness_code', $seg['t']);
  if ($seg['t'] !== null && $sweet_id === null) {
      out_json('error', '甜度编码 (T-code) 无效: ' . htmlspecialchars($seg['t']), null, 404);
  }

  // 4b. V10 核心逻辑：合并基础配方与动态规则
  
  // 步骤 1: 获取基础配方，并将其放入一个以 Material ID 为键的 map 中
  $base_recipe_structure = base_recipe($pdo, $pid); // 内部函数
  $final_recipe_map = [];
  $base_material_ids = [];
  foreach ($base_recipe_structure as $r) {
      $final_recipe_map[(int)$r['material_id']] = $r;
      $base_material_ids[] = (int)$r['material_id'];
  }
  
  // 步骤 2: 遍历 map，应用“覆盖”规则
  foreach ($final_recipe_map as $mid => &$item) {
      $adj = best_adjust($pdo, $pid, $mid, $cup_id, $ice_id, $sweet_id);
      if ($adj) {
          $item['quantity'] = (float)$adj['quantity'];
          $item['unit_id'] = (int)$adj['unit_id'];
          if (!empty($adj['step_category'])) {
              $item['step_category'] = $adj['step_category'];
          }
      }
  }
  unset($item); // 解除引用

  // 步骤 3: 查找所有可能“新增”的物料
  $stmt_new = $pdo->prepare("SELECT DISTINCT material_id FROM kds_recipe_adjustments WHERE product_id = ?");
  $stmt_new->execute([$pid]);
  $all_adj_material_ids = $stmt_new->fetchAll(PDO::FETCH_COLUMN);
  
  $new_material_ids = array_diff($all_adj_material_ids, $base_material_ids);

  // 步骤 4: 遍历“新增”物料，检查它们的条件是否满足
  foreach ($new_material_ids as $mid) {
      $adj = best_adjust($pdo, $pid, (int)$mid, $cup_id, $ice_id, $sweet_id);
      
      // 如果 best_adjust 返回了规则，意味着这个“新增”物料在此条件下应当被添加
      if ($adj) {
          $final_recipe_map[(int)$mid] = [
              'material_id'   => (int)$mid,
              'quantity'      => (float)$adj['quantity'],
              'unit_id'       => (int)$adj['unit_id'],
              'step_category' => $adj['step_category'] ?? 'base' // 默认为 base
          ];
      }
  }

  // 步骤 5: 转换最终 map 为双语数组
  $adjusted_recipe = [];
  foreach ($final_recipe_map as $item) {
      $m_names = m_name($pdo, (int)$item['material_id']);
      $u_names = u_name($pdo, (int)$item['unit_id']);
      
      $adjusted_recipe[] = [
          'material_zh'   => $m_names['zh'],
          'material_es'   => $m_names['es'],
          'unit_zh'       => $u_names['zh'],
          'unit_es'       => $u_names['es'],
          'quantity'      => (float)$item['quantity'],
          'step_category' => norm_cat((string)$item['step_category'])
      ];
  }
  
  // 4d. 补充左侧概览所需的选项名称
  $names = array_merge(
    get_cup_names($pdo, $cup_id), // V10: 使用 ID 重新获取名称
    get_ice_names($pdo, $ice_id),
    get_sweet_names($pdo, $sweet_id)
  );
  // V10: 修复概览名称（使用 xxx_name_zh 而不是 cup_name_zh）
  $prod_info['cup_name_zh'] = $names['cup_name_zh'] ?? null;
  $prod_info['cup_name_es'] = $names['cup_name_es'] ?? null;
  $prod_info['ice_name_zh'] = $names['ice_name_zh'] ?? null;
  $prod_info['ice_name_es'] = $names['ice_name_es'] ?? null;
  $prod_info['sweetness_name_zh'] = $names['sweetness_name_zh'] ?? null;
  $prod_info['sweetness_name_es'] = $names['sweetness_name_es'] ?? null;

  ok([
    'type'=>'adjusted_recipe',
    'product'=>$prod_info,
    'recipe'=>$adjusted_recipe // <--- 返回计算后的配方
  ]);

}catch(Throwable $e){
  error_log('KDS sop_handler error (V10): '.$e->getMessage());
  $error_message = "[V10] " . $e->getMessage() . " in " . basename($e->getFile()) . " on line " . $e->getLine();
  out_json('error', $error_message, ['debug'=>$e->getMessage()], 500);
}
?>