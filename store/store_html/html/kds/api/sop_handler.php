<?php
/**
 * TopTea · KDS · SOP 查询接口 (V8.1 - 路径修复版)
 * 修复：
 * - [V8.1] 修正了 $__config_path 和 $__helper_path 的 realpath() 逻辑，
 * 使其能正确地从 /html/kds/api/ 向上追溯到 /kds/core/ 和 /kds/helpers/。
 * - [V8] 彻底重构主流程，使其能正确处理“动态调整规则”中“新增”的物料（例如“海盐”），
 * 而不仅仅是“覆盖”基础配方中已有的物料。
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* ---------- util output ---------- */
function out_json(string $s, string $m, $d=null, int $code=200){
  http_response_code($code);
  echo json_encode(['status'=>$s,'message'=>$m,'data'=>$d], JSON_UNESCAPED_UNICODE);
  exit;
}
function ok($d){ out_json('success','OK',$d,200); }

/* ---------- 1) 引导配置 & 核心助手 (KDS Helper) ---------- */

/* --- 路径修复 (V8.1) --- */
// __DIR__ 是 /.../store_html/html/kds/api
// 我们需要找到位于 /.../store_html/kds/ 的 core 和 helpers
$__kds_root    = realpath(__DIR__ . '/../../../kds');
$__config_path = $__kds_root . '/core/config.php';
$__helper_path = $__kds_root . '/helpers/kds_helper.php';
/* --- 修复结束 --- */

if (!$__config_path || !file_exists($__config_path)) {
  out_json('error','配置文件未找到', ['expected_path' => $__config_path], 500);
}
if (!$__helper_path || !file_exists($__helper_path)) {
  out_json('error','核心助手(kds_helper)未找到', ['expected_path' => $__helper_path], 500);
}

require_once $__config_path; // must define $pdo
require_once $__helper_path; // <--- 引入 kds_helper.php

if (!isset($pdo) || !($pdo instanceof PDO)) {
  out_json('error','数据库连接失败。', null, 500);
}
if (!function_exists('best_adjust')) {
  out_json('error','动态配方助手(best_adjust)加载失败。', null, 500);
}

/* ---------- 2) 专门用于“左侧概览”的选项名称获取函数 (保留) ---------- */
// (这些函数获取选项的 "名称", e.g., "中杯", "少冰")
function get_cup_names_by_code(PDO $pdo, ?string $code): array {
  if ($code===null) return ['cup_name_zh'=>null,'cup_name_es'=>null];
  $st=$pdo->prepare("SELECT cup_name FROM kds_cups WHERE cup_code=? AND deleted_at IS NULL LIMIT 1");
  $st->execute([(int)$code]);
  $r=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  // 左侧概览使用 cup_name
  return ['cup_name_zh'=>$r['cup_name']??null, 'cup_name_es'=>$r['cup_name']??null];
}
function get_ice_names_by_code(PDO $pdo, ?string $code): array {
  if ($code===null) return ['ice_name_zh'=>null,'ice_name_es'=>null];
  $sql="SELECT tzh.ice_option_name AS zh, tes.ice_option_name AS es
        FROM kds_ice_options io
        LEFT JOIN kds_ice_option_translations tzh ON tzh.ice_option_id=io.id AND tzh.language_code='zh-CN'
        LEFT JOIN kds_ice_option_translations tes ON tes.ice_option_id=io.id AND tes.language_code='es-ES'
        WHERE io.ice_code=? AND io.deleted_at IS NULL LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute([(int)$code]); $r=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  return ['ice_name_zh'=>$r['zh']??null,'ice_name_es'=>$r['es']??null];
}
function get_sweet_names_by_code(PDO $pdo, ?string $code): array {
  if ($code===null) return ['sweetness_name_zh'=>null,'sweetness_name_es'=>null];
  $sql="SELECT tzh.sweetness_option_name AS zh, tes.sweetness_option_name AS es
        FROM kds_sweetness_options so
        LEFT JOIN kds_sweetness_option_translations tzh ON tzh.sweetness_option_id=so.id AND tzh.language_code='zh-CN'
        LEFT JOIN kds_sweetness_option_translations tes ON tes.sweetness_option_id=so.id AND tes.language_code='es-ES'
        WHERE so.sweetness_code=? AND so.deleted_at IS NULL LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute([(int)$code]); $r=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  return ['sweetness_name_zh'=>$r['zh']??null,'sweetness_name_es'=>$r['es']??null];
}

/* -------------------- 3) 主流程 (V8 逻辑重构) -------------------- */
try{
  $raw = $_GET['code'] ?? '';
  $seg = parse_code($raw); // 使用 kds_helper.php 的函数
  if (!$seg || $seg['p']==='') out_json('error','编码不合法', null, 400);

  // 1. 验证产品
  $prod = get_product($pdo, $seg['p']); // 使用 kds_helper.php 的函数
  if(!$prod || (int)$prod['is_deleted_flag']!==0 || (int)$prod['is_active']!==1){
    out_json('error','找不到该产品或未上架 (P-Code: ' . htmlspecialchars($seg['p']) . ')', null, 404);
  }
  $pid = (int)$prod['id'];

  // 2. 获取产品基础信息 (名称, 状态)
  $prod_info = array_merge(
    ['product_id'=>$pid, 'product_code'=>$prod['product_code']],
    get_product_info($pdo, $pid, (int)$prod['status_id']) // kds_helper
  );

  // 3. (P-Code ONLY) 仅查询基础信息
  if ($seg['a']===null && $seg['m']===null && $seg['t']===null) {
     ok([
       'type'=>'base_info',
       'product'=>$prod_info,
       'recipe'=> get_base_recipe($pdo, $pid), // kds_helper
       'options'=> get_available_options($pdo, $pid, $prod['product_code']) // kds_helper
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

  // 4b. V8 核心逻辑：合并基础配方与动态规则
  
  // 步骤 1: 获取基础配方，并将其放入一个以 Material ID 为键的 map 中
  $base_recipe_structure = base_recipe($pdo, $pid); // kds_helper
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
    get_cup_names_by_code($pdo, $seg['a']),
    get_ice_names_by_code($pdo, $seg['m']),
    get_sweet_names_by_code($pdo, $seg['t'])
  );
  $prod_info = array_merge($prod_info, $names);

  ok([
    'type'=>'adjusted_recipe',
    'product'=>$prod_info,
    'recipe'=>$adjusted_recipe // <--- 返回计算后的配方
  ]);

}catch(Throwable $e){
  error_log('KDS sop_handler error: '.$e->getMessage());
  // A1.png 中显示的“服务器错误”就是这个
  out_json('error','服务器错误', ['debug'=>$e->getMessage()], 500);
}
?>