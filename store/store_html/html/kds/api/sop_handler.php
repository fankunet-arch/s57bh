<?php
/**
 * TopTea · KDS · SOP 查询接口（修正：字段命名 + 选项名称）
 * 仅修复：前端 “undefined” 与名称不显示；不改其它业务。
 * - 返回字段与 /html/kds/js/kds_sop.js 期望严格对齐：
 *   product: { product_id, product_code, name_zh, name_es, status_name_zh, status_name_es,
 *              cup_name_zh?, cup_name_es?, ice_name_zh?, ice_name_es?, sweetness_name_zh?, sweetness_name_es? }
 *   recipe : [ { material_zh, material_es, unit_zh, unit_es, quantity, step_category } ... ]
 *   options: base_info 时返回 { cups:[{cup_code,cup_name}], ice_options:[{ice_code,name_zh,name_es}], sweetness_options:[{sweetness_code,name_zh,name_es}] }
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

/* ---------- load config (path fix) ---------- */
$__html_root   = dirname(__DIR__, 2);                 // /.../store_html/html
$__config_path = realpath($__html_root . '/../kds/core/config.php');
if (!$__config_path || !file_exists($__config_path)) {
  out_json('error','配置文件未找到', ['expected'=>$__html_root . '/../kds/core/config.php'], 500);
}
require_once $__config_path; // must define $pdo
if (!isset($pdo) || !($pdo instanceof PDO)) {
  out_json('error','数据库连接失败。', null, 500);
}

/* ---------- helpers (local,最小可用) ---------- */
function parse_code_local(string $raw): ?array {
  $raw = strtoupper(trim($raw));
  if ($raw === '' || !preg_match('/^[A-Z0-9-]+$/', $raw)) return null;
  $seg = array_values(array_filter(explode('-', $raw), fn($s)=>$s!==''));
  if (count($seg) > 4) return null;
  return ['p'=>$seg[0]??'', 'a'=>$seg[1]??null, 'm'=>$seg[2]??null, 't'=>$seg[3]??null, 'raw'=>$raw];
}

function get_product_row(PDO $pdo, string $p_code): ?array {
  $st=$pdo->prepare("SELECT id, product_code, status_id, is_active, is_deleted_flag
                     FROM kds_products WHERE product_code=? LIMIT 1");
  $st->execute([$p_code]);
  $r=$st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

function get_product_names(PDO $pdo, int $pid): array {
  $st=$pdo->prepare("SELECT language_code, product_name
                     FROM kds_product_translations WHERE product_id=?");
  $st->execute([$pid]);
  $names=['name_zh'=>null,'name_es'=>null];
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){
    if($row['language_code']==='zh-CN'){ $names['name_zh']=$row['product_name']; }
    if($row['language_code']==='es-ES'){ $names['name_es']=$row['product_name']; }
  }
  return $names;
}
function get_status_names(PDO $pdo, int $status_id): array {
  $st=$pdo->prepare("SELECT status_name_zh, status_name_es FROM kds_product_statuses WHERE id=?");
  $st->execute([$status_id]);
  $r=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  return [
    'status_name_zh'=>$r['status_name_zh'] ?? null,
    'status_name_es'=>$r['status_name_es'] ?? null
  ];
}

function get_unit_names(PDO $pdo, int $unit_id): array {
  $st=$pdo->prepare("SELECT language_code, unit_name FROM kds_unit_translations WHERE unit_id=?");
  $st->execute([$unit_id]);
  $names=['unit_zh'=>null,'unit_es'=>null];
  foreach($st->fetchAll(PDO::FETCH_ASSOC) as $row){
    if($row['language_code']==='zh-CN'){ $names['unit_zh']=$row['unit_name']; }
    if($row['language_code']==='es-ES'){ $names['unit_es']=$row['unit_name']; }
  }
  return $names;
}

function get_recipe(PDO $pdo, int $pid): array {
  $sql = "SELECT r.material_id, r.unit_id, r.quantity, r.step_category,
                 mt_zh.material_name AS m_zh, mt_es.material_name AS m_es
          FROM kds_product_recipes r
          LEFT JOIN kds_material_translations mt_zh
                 ON mt_zh.material_id=r.material_id AND mt_zh.language_code='zh-CN'
          LEFT JOIN kds_material_translations mt_es
                 ON mt_es.material_id=r.material_id AND mt_es.language_code='es-ES'
          WHERE r.product_id=?
          ORDER BY FIELD(r.step_category,'base','mixing','topping'), r.sort_order, r.id";
  $st=$pdo->prepare($sql); $st->execute([$pid]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $out=[];
  foreach($rows as $r){
    $u = get_unit_names($pdo, (int)$r['unit_id']);
    $out[] = [
      'material_zh'   => $r['m_zh'] ?? null,
      'material_es'   => $r['m_es'] ?? null,
      'unit_zh'       => $u['unit_zh'],
      'unit_es'       => $u['unit_es'],
      'quantity'      => is_null($r['quantity'])? null : (float)$r['quantity'],
      'step_category' => $r['step_category']
    ];
  }
  return $out;
}

function get_cup_list(PDO $pdo): array {
  $rows=$pdo->query("SELECT cup_code, cup_name, sop_description_es FROM kds_cups WHERE deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $out=[];
  foreach($rows as $r){
    $out[]=[
      'cup_code'=>(int)$r['cup_code'],
      'cup_name'=>$r['cup_name'],        // 作为 zh
      'cup_name_es'=>$r['sop_description_es'] ?? $r['cup_name'], // 作为 es (无独立列时回退)
    ];
  }
  return $out;
}
function get_ice_list(PDO $pdo): array {
  $sql="SELECT io.ice_code,
               tzh.ice_option_name AS name_zh,
               tes.ice_option_name AS name_es
        FROM kds_ice_options io
        LEFT JOIN kds_ice_option_translations tzh ON tzh.ice_option_id=io.id AND tzh.language_code='zh-CN'
        LEFT JOIN kds_ice_option_translations tes ON tes.ice_option_id=io.id AND tes.language_code='es-ES'
        WHERE io.deleted_at IS NULL
        ORDER BY io.ice_code";
  $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $out=[]; foreach($rows as $r){
    $out[]=['ice_code'=>(int)$r['ice_code'],'name_zh'=>$r['name_zh'],'name_es'=>$r['name_es']];
  } return $out;
}
function get_sweet_list(PDO $pdo): array {
  $sql="SELECT so.sweetness_code,
               tzh.sweetness_option_name AS name_zh,
               tes.sweetness_option_name AS name_es
        FROM kds_sweetness_options so
        LEFT JOIN kds_sweetness_option_translations tzh ON tzh.sweetness_option_id=so.id AND tzh.language_code='zh-CN'
        LEFT JOIN kds_sweetness_option_translations tes ON tes.sweetness_option_id=so.id AND tes.language_code='es-ES'
        WHERE so.deleted_at IS NULL
        ORDER BY so.sweetness_code";
  $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $out=[]; foreach($rows as $r){
    $out[]=['sweetness_code'=>(int)$r['sweetness_code'],'name_zh'=>$r['name_zh'],'name_es'=>$r['name_es']];
  } return $out;
}

function get_cup_names_by_code(PDO $pdo, ?string $code): array {
  if ($code===null) return ['cup_name_zh'=>null,'cup_name_es'=>null];
  $st=$pdo->prepare("SELECT cup_name, sop_description_es FROM kds_cups WHERE cup_code=? LIMIT 1");
  $st->execute([(int)$code]);
  $r=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  return ['cup_name_zh'=>$r['cup_name']??null, 'cup_name_es'=>($r['sop_description_es']??$r['cup_name']??null)];
}
function get_ice_names_by_code(PDO $pdo, ?string $code): array {
  if ($code===null) return ['ice_name_zh'=>null,'ice_name_es'=>null];
  $sql="SELECT tzh.ice_option_name AS zh, tes.ice_option_name AS es
        FROM kds_ice_options io
        LEFT JOIN kds_ice_option_translations tzh ON tzh.ice_option_id=io.id AND tzh.language_code='zh-CN'
        LEFT JOIN kds_ice_option_translations tes ON tes.ice_option_id=io.id AND tes.language_code='es-ES'
        WHERE io.ice_code=? LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute([(int)$code]); $r=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  return ['ice_name_zh'=>$r['zh']??null,'ice_name_es'=>$r['es']??null];
}
function get_sweet_names_by_code(PDO $pdo, ?string $code): array {
  if ($code===null) return ['sweetness_name_zh'=>null,'sweetness_name_es'=>null];
  $sql="SELECT tzh.sweetness_option_name AS zh, tes.sweetness_option_name AS es
        FROM kds_sweetness_options so
        LEFT JOIN kds_sweetness_option_translations tzh ON tzh.sweetness_option_id=so.id AND tzh.language_code='zh-CN'
        LEFT JOIN kds_sweetness_option_translations tes ON tes.sweetness_option_id=so.id AND tes.language_code='es-ES'
        WHERE so.sweetness_code=? LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute([(int)$code]); $r=$st->fetch(PDO::FETCH_ASSOC) ?: [];
  return ['sweetness_name_zh'=>$r['zh']??null,'sweetness_name_es'=>$r['es']??null];
}

/* ---------- main ---------- */
try{
  $raw = $_GET['code'] ?? '';
  $seg = parse_code_local($raw);
  if (!$seg || $seg['p']==='') out_json('error','编码不合法', null, 400);

  $prod = get_product_row($pdo, $seg['p']);
  if(!$prod || (int)$prod['is_deleted_flag']!==0 || (int)$prod['is_active']!==1){
    out_json('error','找不到该产品或未上架', null, 404);
  }

  $prod_info = array_merge(
    ['product_id'=>(int)$prod['id'], 'product_code'=>$prod['product_code']],
    get_product_names($pdo, (int)$prod['id']),
    get_status_names($pdo, (int)$prod['status_id'])
  );

  $recipe = get_recipe($pdo, (int)$prod['id']);

  // NO A/M/T -> base_info (含可选项)
  if ($seg['a']===null && $seg['m']===null && $seg['t']===null) {
     ok([
       'type'=>'base_info',
       'product'=>$prod_info,
       'recipe'=>$recipe,
       'options'=>[
         'cups'=>get_cup_list($pdo),
         'ice_options'=>get_ice_list($pdo),
         'sweetness_options'=>get_sweet_list($pdo),
       ]
     ]);
  }

  // 带 A/M/T -> adjusted_recipe (补充已选项名称，供左侧“概览”显示)
  $names = array_merge(
    get_cup_names_by_code($pdo, $seg['a']),
    get_ice_names_by_code($pdo, $seg['m']),
    get_sweet_names_by_code($pdo, $seg['t'])
  );
  $prod_info = array_merge($prod_info, $names);

  ok([
    'type'=>'adjusted_recipe',
    'product'=>$prod_info,
    'recipe'=>$recipe
  ]);

}catch(Throwable $e){
  error_log('KDS sop_handler error: '.$e->getMessage());
  out_json('error','服务器错误', ['debug'=>$e->getMessage()], 500);
}
