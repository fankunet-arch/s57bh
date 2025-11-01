<?php
/**
 * TopTea · KDS · SOP 查询接口 (V5 - 严格RMS关联校验版)
 * 修复：
 * - [安全] 严格校验 A, M, T 码。如果提供了 A/M/T 码，该码不仅要在字典表存在，
 * 还必须通过关联表 (pos_item_variants, kds_product_ice_options, kds_product_sweetness_options)
 * 明确关联到该 P-Code (product_id)，否则拒绝查询并返回404。
 * - 完整引入 kds_helper.php 的动态配方调整逻辑 (best_adjust)。
 * - 保持为前端返回 A/M/T 选项名称（用于左侧概览）的逻辑。
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
$__html_root   = dirname(__DIR__, 2); // /.../store_html/html
$__config_path = realpath($__html_root . '/../kds/core/config.php');
$__helper_path = realpath($__html_root . '/../kds/helpers/kds_helper.php'); // <--- 引入核心助手

if (!$__config_path || !file_exists($__config_path)) {
  out_json('error','配置文件未找到', null, 500);
}
if (!$__helper_path || !file_exists($__helper_path)) {
  out_json('error','核心助手(kds_helper)未找到', null, 500);
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

/* -------------------- 3) 主流程 -------------------- */
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
  
  // 4a. 将 A,M,T 码转换为 数据库 ID，并进行严格的“存在性”和“关联性”校验
  
  $cup_id = null;
  if ($seg['a'] !== null) {
      // 4a.1 检查杯型编码是否存在
      $cup_id = id_by_code($pdo, 'kds_cups', 'cup_code', $seg['a']);
      if ($cup_id === null) {
          out_json('error', '杯型编码 (A-code) 无效: ' . htmlspecialchars($seg['a']), null, 404);
      }
      // 4a.2 [严格校验] 检查杯型是否已关联到该产品 (通过 pos_item_variants)
      $stmt = $pdo->prepare("SELECT 1 FROM pos_item_variants WHERE product_id = ? AND cup_id = ? AND deleted_at IS NULL LIMIT 1");
      $stmt->execute([$pid, $cup_id]);
      if ($stmt->fetchColumn() === false) {
          out_json('error', '无效的产品组合：该产品 (P=' . $prod['product_code'] . ') 未配置此杯型 (A=' . $seg['a'] . ')。', null, 404);
      }
  }
  
  $ice_id = null;
  if ($seg['m'] !== null) {
      // 4a.3 检查冰量编码是否存在
      $ice_id = id_by_code($pdo, 'kds_ice_options', 'ice_code', $seg['m']);
      if ($ice_id === null) {
          out_json('error', '冰量编码 (M-code) 无效: ' . htmlspecialchars($seg['m']), null, 404);
      }
      // 4a.4 [严格校验] 检查冰量是否已关联到该产品 (通过 kds_product_ice_options)
      $stmt = $pdo->prepare("SELECT 1 FROM kds_product_ice_options WHERE product_id = ? AND ice_option_id = ? LIMIT 1");
      $stmt->execute([$pid, $ice_id]);
      if ($stmt->fetchColumn() === false) {
          out_json('error', '无效的产品组合：该产品 (P=' . $prod['product_code'] . ') 未配置此冰量 (M=' . $seg['m'] . ')。', null, 404);
      }
  }

  $sweet_id = null;
  if ($seg['t'] !== null) {
      // 4a.5 检查甜度编码是否存在
      $sweet_id = id_by_code($pdo, 'kds_sweetness_options', 'sweetness_code', $seg['t']);
      if ($sweet_id === null) {
          out_json('error', '甜度编码 (T-code) 无效: ' . htmlspecialchars($seg['t']), null, 404);
      }
      // 4a.6 [严格校验] 检查甜度是否已关联到该产品 (通过 kds_product_sweetness_options)
      $stmt = $pdo->prepare("SELECT 1 FROM kds_product_sweetness_options WHERE product_id = ? AND sweetness_option_id = ? LIMIT 1");
      $stmt->execute([$pid, $sweet_id]);
      if ($stmt->fetchColumn() === false) {
          out_json('error', '无效的产品组合：该产品 (P=' . $prod['product_code'] . ') 未配置此甜度 (T=' . $seg['t'] . ')。', null, 404);
      }
  }

  // 4b. 获取基础配方结构
  $base_recipe_structure = base_recipe($pdo, $pid); // kds_helper
  if (empty($base_recipe_structure)) {
      out_json('error', '该产品尚未配置基础配方。', null, 404);
  }
  
  $adjusted_recipe = [];
  
  // 4c. 循环基础配方，应用调整规则
  foreach ($base_recipe_structure as $r) {
      $mid = (int)$r['material_id'];
      $qty = (float)$r['quantity'];
      $uid = (int)$r['unit_id'];
      $cat = norm_cat((string)$r['step_category']); // kds_helper

      // 寻找最佳调整
      $adj = best_adjust($pdo, $pid, $mid, $cup_id, $ice_id, $sweet_id); // kds_helper
      
      if ($adj) {
          $qty = (float)$adj['quantity'];
          $uid = (int)$adj['unit_id'];
      }
      
      // 获取物料和单位的双语名称
      $m_names = m_name($pdo, $mid); // kds_helper
      $u_names = u_name($pdo, $uid); // kds_helper

      $adjusted_recipe[] = [
          'material_zh'   => $m_names['zh'],
          'material_es'   => $m_names['es'],
          'unit_zh'       => $u_names['zh'],
          'unit_es'       => $u_names['es'],
          'quantity'      => $qty,
          'step_category' => $cat
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