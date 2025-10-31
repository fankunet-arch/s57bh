<?php
/**
 * TopTea · KDS · SOP 查询接口（纯产品码 / P-A-M-T）
 * 域名与目录：
 *   store.toptea.es → /web/store_html/html
 *   hq.toptea.es    → /web/hq_html/html
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* ───────────────── 0) 通用输出 ───────────────── */
function _out(string $s, string $m, $d=null, int $code=200){ http_response_code($code); echo json_encode(['status'=>$s,'message'=>$m,'data'=>$d], JSON_UNESCAPED_UNICODE); exit; }
function _ok($d, string $m='OK'){ _out('success',$m,$d,200); }

/* ───────────────── 1) 引导数据库（自动搜寻） ───────────────── */
$pdo = $pdo ?? null;
$boot_ok = false;
$tried = [];

function _parents(string $path, int $maxUp = 6): array {
  $p = rtrim($path, '/');
  $list = [$p];
  for ($i=0; $i<$maxUp; $i++) {
    $p = dirname($p);
    if ($p === '' || $p === '/' || $p === '.' || $p === DIRECTORY_SEPARATOR) break;
    $list[] = $p;
  }
  return array_unique($list);
}

function _try_require(array $files, array &$tried, ?PDO &$pdo, bool &$boot_ok): void {
  foreach ($files as $f) {
    if (!$f) continue;
    $tried[] = $f;
    if (file_exists($f)) {
      require_once $f;
      if (!isset($pdo) || !($pdo instanceof PDO)) {
        if (function_exists('get_pdo')) { $pdo = get_pdo(); }
        elseif (function_exists('db'))  { $pdo = db(); }
        elseif (function_exists('get_db')) { $pdo = get_db(); }
      }
      if (isset($pdo) && ($pdo instanceof PDO)) { $boot_ok = true; return; }
    }
  }
}

$docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');      // /web/store_html/html
$here    = rtrim((string)__DIR__, '/');                                // /web/store_html/html/kds/api
$parents = array_unique(array_merge(_parents($here), _parents($docroot)));

$commonSuffixes = [
  'core/config.php',
  'kds/core/config.php',
  'cpsys/core/config.php',
  'pos/core/config.php',
  // 若你有 private/bootstrap，可按需放置
  'private/toptea/config/bootstrap.php',
  'private/config/bootstrap.php',
  'toptea/config/bootstrap.php',
];

$candidateFiles = [];
foreach ($parents as $base) {
  foreach ($commonSuffixes as $suf) {
    $candidateFiles[] = $base . '/' . ltrim($suf, '/');
  }
}

// 额外：相对当前文件的直连尝试（命中概率高）
$candidateFiles = array_merge([
  realpath(__DIR__ . '/../core/config.php'),      // /kds/core/config.php
  realpath(__DIR__ . '/../../core/config.php'),   // /html/core/config.php
  realpath($docroot . '/core/config.php'),
  realpath($docroot . '/kds/core/config.php'),
  realpath($docroot . '/cpsys/core/config.php'),
], $candidateFiles);

_try_require(array_filter($candidateFiles), $tried, $pdo, $boot_ok);

if (!$boot_ok) {
  $msg = '引导失败：未找到数据库配置';
  if ((string)($_GET['debug'] ?? '') === '1') {
    _out('error', $msg, ['docroot'=>$docroot, 'here'=>$here, 'tried'=>$tried], 500);
  }
  _out('error', $msg, null, 500);
}

/* ───────────────── 2) 引擎工具函数 ───────────────── */
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
  $st=$pdo->prepare("SELECT id,product_code,is_active,is_deleted_flag FROM kds_products WHERE product_code=? LIMIT 1");
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
  if (in_array($c, ['mix','mixing','调杯','tiao','blend'], true)) return 'mix';
  if (in_array($c, ['top','topping','顶料','dingliao'], true)) return 'top';
  return 'mix';
}
function best_adjust(PDO $pdo, int $pid, int $mid, ?int $cup, ?int $ice, ?int $sweet): ?array {
  $cond=["product_id=?","material_id=?"]; $args=[$pid,$mid]; $score=[];
  if ($cup!==null){ $cond[]="(cup_id IS NULL OR cup_id=?)"; $args[]=$cup; $score[]="(cup_id IS NOT NULL)"; } else { $cond[]="(cup_id IS NULL)"; }
  if ($ice!==null){ $cond[]="(ice_option_id IS NULL OR ice_option_id=?)"; $args[]=$ice; $score[]="(ice_option_id IS NOT NULL)"; } else { $cond[]="(ice_option_id IS NULL)"; }
  if ($sweet!==null){ $cond[]="(sweetness_option_id IS NULL OR sweetness_option_id=?)"; $args[]=$sweet; $score[]="(sweetness_option_id IS NOT NULL)"; } else { $cond[]="(sweetness_option_id IS NULL)"; }
  $scoreExpr=$score? implode(' + ',$score):'0';
  $sql="SELECT material_id,quantity,unit_id FROM kds_recipe_adjustments
        WHERE ".implode(' AND ',$cond)." ORDER BY {$scoreExpr} DESC, id DESC LIMIT 1";
  $st=$pdo->prepare($sql); $st->execute($args); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null;
}
function m_name(PDO $pdo, int $mid, string $lang='zh-CN'): string {
  $st=$pdo->prepare("SELECT material_name FROM kds_material_translations WHERE material_id=? AND language_code=? LIMIT 1");
  $st->execute([$mid,$lang]); return (string)($st->fetchColumn()?:('#'.$mid));
}
function u_name(PDO $pdo, int $uid, string $lang='zh-CN'): string {
  $st=$pdo->prepare("SELECT unit_name FROM kds_unit_translations WHERE unit_id=? AND language_code=? LIMIT 1");
  $st->execute([$uid,$lang]); return (string)($st->fetchColumn()?:'');
}

/* ───────────────── 3) 主流程 ───────────────── */
try{
  $lang = (string)($_GET['lang'] ?? 'zh-CN');
  $raw  = (string)($_REQUEST['code'] ?? '');
  $code = parse_code($raw);
  if (!$code || $code['p']==='') _out('error','缺少或非法的 code（示例：101 或 101-1-1-11）',null,400);

  $prod = get_product($pdo, $code['p']);
  if (!$prod || (int)$prod['is_deleted_flag']!==0 || (int)$prod['is_active']!==1) _out('error','找不到该产品或未上架',null,404);
  $pid = (int)$prod['id'];

  $cup   = id_by_code($pdo,'kds_cups','cup_code',$code['a']);
  $ice   = id_by_code($pdo,'kds_ice_options','ice_code',$code['m']);
  $sweet = id_by_code($pdo,'kds_sweetness_options','sweetness_code',$code['t']);

  $rows = base_recipe($pdo,$pid);
  if (!$rows) _out('error','该产品尚未配置基础配方',null,404);

  $steps = ['base'=>[], 'mix'=>[], 'top'=>[]];
  foreach ($rows as $r) {
    $mid=(int)$r['material_id']; $uid=(int)$r['unit_id']; $qty=(float)$r['quantity'];
    $cat = norm_cat((string)$r['step_category']);

    if ($cup!==null || $ice!==null || $sweet!==null) {
      if ($adj = best_adjust($pdo, $pid, $mid, $cup, $ice, $sweet)) {
        $qty = (float)$adj['quantity']; $uid = (int)$adj['unit_id'];
      }
    }

    $steps[$cat][] = [
      'material_id'   => $mid,
      'material_name' => m_name($pdo,$mid,$lang),
      'qty'           => $qty,
      'unit_id'       => $uid,
      'unit'          => u_name($pdo,$uid,$lang),
    ];
  }

  _ok([
    'adjusted_recipe' => $steps,
    'meta' => [
      'product_code' => $code['p'],
      'cup_code'     => $code['a'],
      'ice_code'     => $code['m'],
      'sweet_code'   => $code['t'],
      'lang'         => $lang,
    ]
  ]);
}catch(Throwable $e){
  error_log('KDS sop_handler: '.$e->getMessage());
  _out('error','服务器错误',['debug'=>$e->getMessage()],500);
}
