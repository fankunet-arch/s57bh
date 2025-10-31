<?php
/**
 * TopTea HQ · cpsys
 * API: User management (create/update/delete/get) — soft delete aware
 * Build: 2025-10-23
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

/* ---------- Bootstrap（寻找 core/config.php） ---------- */
$loaded = false;
$try = [
    realpath(__DIR__ . '/../../../core/config.php'), // /html/cpsys/api -> /core/config.php
    realpath(__DIR__ . '/../../core/config.php'),
    realpath(__DIR__ . '/../../config.php'),
];
foreach ($try as $p) { if ($p && file_exists($p)) { require_once $p; $loaded = true; break; } }
if (!$loaded) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'配置文件加载失败']); exit; }

/* ---------- 常量兜底 ---------- */
if (!defined('ROLE_SUPER_ADMIN'))   define('ROLE_SUPER_ADMIN', 1);
if (!defined('ROLE_PRODUCT_MANAGER')) define('ROLE_PRODUCT_MANAGER', 2);
if (!defined('ROLE_STORE_MANAGER')) define('ROLE_STORE_MANAGER', 3);

/* ---------- 会话与权限 ---------- */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== ROLE_SUPER_ADMIN) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'权限不足，仅超级管理员可执行此操作。'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- Helpers ---------- */
function ok($msg, $data=null){ echo json_encode(['status'=>'success','message'=>$msg,'data'=>$data], JSON_UNESCAPED_UNICODE); exit; }
function err($msg, $code=400){ http_response_code($code>=400?$code:200); echo json_encode(['status'=>'error','message'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function pdo(): PDO { if (!isset($GLOBALS['pdo'])) err('数据库连接未初始化',500); return $GLOBALS['pdo']; }
function body(): array {
    $raw = file_get_contents('php://input'); if ($raw==='') return [];
    $d = json_decode($raw,true); return is_array($d)?$d:[];
}

/* ---------- 路由 ---------- */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method==='GET' ? ($_GET['action'] ?? '') : ((body()['action'] ?? $_POST['action'] ?? ''));

try {
    switch ($action) {
        /* ===== 读取单个 ===== */
        case 'get': {
            $id = (int)($_GET['id'] ?? 0);
            if ($id<=0) err('参数 id 无效');
            $st = pdo()->prepare("SELECT id,username,display_name,email,role_id,is_active,last_login_at
                                  FROM cpsys_users WHERE id=? AND deleted_at IS NULL");
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) err('用户不存在');
            ok('ok',$row);
        }

        /* ===== 新增 / 更新（含软删除恢复） ===== */
        case 'save': {
            $payload = body()['data'] ?? [];
            $id           = (int)($payload['id'] ?? 0);
            $username     = trim((string)($payload['username'] ?? ''));
            $display_name = trim((string)($payload['display_name'] ?? ''));
            $email        = strtolower(trim((string)($payload['email'] ?? '')));
            $password     = (string)($payload['password'] ?? '');
            $role_id      = (int)($payload['role_id'] ?? 0);
            $is_active    = (int)($payload['is_active'] ?? 0);

            if ($display_name==='') err('显示名称不能为空');
            if ($role_id<=0)        err('角色不能为空');

            $db = pdo();
            $db->beginTransaction();

            if ($id === 0) {
                if ($username==='') { $db->rollBack(); err('用户名不能为空'); }
                if ($password===''){ $db->rollBack(); err('新建用户必须填写密码'); }

                // 先查是否存在同名（含已软删）
                $st = $db->prepare("SELECT id, deleted_at FROM cpsys_users WHERE username = ? LIMIT 1");
                $st->execute([$username]);
                $exist = $st->fetch(PDO::FETCH_ASSOC);

                $password_hash = hash('sha256', $password);

                if ($exist) {
                    if ($exist['deleted_at'] !== null) {
                        // 恢复软删除的账号（符合你“删除后视为没有，但可恢复”的规则）
                        $st = $db->prepare("UPDATE cpsys_users
                            SET display_name = ?, email = ?, password_hash = ?, role_id = ?, is_active = ?, 
                                deleted_at = NULL, updated_at = NOW()
                            WHERE id = ?");
                        $st->execute([$display_name, $email ?: null, $password_hash, $role_id, $is_active, (int)$exist['id']]);
                        $db->commit();
                        ok('已恢复并更新该用户名的账户',['id'=>(int)$exist['id']]);
                    } else {
                        $db->rollBack(); err('用户名已存在，请更换一个用户名');
                    }
                } else {
                    // 纯新增
                    $st = $db->prepare("INSERT INTO cpsys_users
                        (username,password_hash,email,display_name,is_active,role_id,created_at,updated_at)
                        VALUES (?,?,?,?,?,?,NOW(),NOW())");
                    $st->execute([$username,$password_hash,$email?:null,$display_name,$is_active,$role_id]);
                    $new_id = (int)$db->lastInsertId();
                    $db->commit();
                    ok('新用户已创建',['id'=>$new_id]);
                }
            } else {
                // 更新 — 防止把自己锁死
                if ($id === (int)($_SESSION['user_id'] ?? 0)) {
                    if ($is_active===0) { $db->rollBack(); err('不能禁用自己的账户'); }
                    if ($role_id !== (int)$_SESSION['role_id']) { $db->rollBack(); err('不能修改自己的角色'); }
                }

                if ($password!=='') {
                    $st = $db->prepare("UPDATE cpsys_users
                        SET display_name=?, email=?, password_hash=?, role_id=?, is_active=?, updated_at=NOW()
                        WHERE id=? AND deleted_at IS NULL");
                    $st->execute([$display_name,$email?:null,hash('sha256',$password),$role_id,$is_active,$id]);
                } else {
                    $st = $db->prepare("UPDATE cpsys_users
                        SET display_name=?, email=?, role_id=?, is_active=?, updated_at=NOW()
                        WHERE id=? AND deleted_at IS NULL");
                    $st->execute([$display_name,$email?:null,$role_id,$is_active,$id]);
                }
                $db->commit();
                ok('用户信息已更新');
            }
        }

        /* ===== 软删除 ===== */
        case 'delete': {
            $id = (int)((body()['id'] ?? 0));
            if ($id<=0) err('参数 id 无效');
            if ($id === (int)($_SESSION['user_id'] ?? 0)) err('不能删除当前登录的账户');

            $st = pdo()->prepare("UPDATE cpsys_users 
                                  SET is_active=0, deleted_at=NOW(), updated_at=NOW()
                                  WHERE id=? AND deleted_at IS NULL");
            $st->execute([$id]);
            ok('用户已删除');
        }

        default: err('未知的 action');
    }
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) { $db->rollBack(); }
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'服务器异常：'.$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
