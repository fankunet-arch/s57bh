<?php
/**
 * Toptea HQ - cpsys
 * Main Layout File
 * Engineer: Gemini | Date: 2025-10-31 | Revision: 13.2 (Correctly Add Product Status Menu)
 */
$page_title = $page_title ?? 'TopTea HQ';
$page = $_GET['page'] ?? 'dashboard';

// Updated page groups for menu highlighting
$rmsPages = ['rms_product_management'];
$posPages = ['pos_menu_management', 'pos_variants_management', 'pos_category_management', 'pos_invoice_list', 'pos_invoice_detail', 'pos_promotion_management', 'pos_eod_reports', 'pos_member_level_management', 'pos_member_management', 'pos_member_settings', 'pos_point_redemption_rules'];
$dictionaryPages = ['cup_management', 'material_management', 'unit_management', 'ice_option_management', 'sweetness_option_management', 'product_status_management'];
$systemPages = ['user_management', 'store_management', 'kds_user_management', 'pos_print_template_management', 'pos_print_template_variables'];
$stockPages = ['warehouse_stock_management', 'stock_allocation', 'store_stock_view'];
?>
<!DOCTYPE html>
<html lang="zh-CN" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="d-flex">
        <nav class="sidebar vh-100 p-3">
            <div class="sidebar-header mb-4"><h4 class="text-white">TopTea HQ</h4></div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page === 'dashboard') ? 'active' : ''; ?>" href="index.php?page=dashboard">
                        <i class="bi bi-speedometer2 me-2"></i>仪表盘
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (in_array($page, $rmsPages)) ? 'active' : ''; ?>" href="index.php?page=rms_product_management">
                        <i class="bi bi-cup-straw me-2"></i>配方管理 (RMS)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($page === 'expiry_management') ? 'active' : ''; ?>" href="index.php?page=expiry_management">
                        <i class="bi bi-calendar-check me-2"></i>效期管理
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link collapsed <?php echo (in_array($page, $stockPages)) ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#stock-submenu" aria-expanded="<?php echo (in_array($page, $stockPages)) ? 'true' : 'false'; ?>"><i class="bi bi-box-seam me-2"></i>库存管理</a>
                    <div class="collapse <?php echo (in_array($page, $stockPages)) ? 'show' : ''; ?>" id="stock-submenu">
                        <ul class="nav flex-column ps-4">
                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'warehouse_stock_management') ? 'active' : ''; ?>" href="index.php?page=warehouse_stock_management">总仓库存</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'store_stock_view') ? 'active' : ''; ?>" href="index.php?page=store_stock_view">门店库存</a></li>
                            <li class="nav-item"><a class="nav-link <?php echo ($page === 'stock_allocation') ? 'active' : ''; ?>" href="index.php?page=stock_allocation">库存调拨</a></li>
                        </ul>
                    </div>
                </li>
				<?php if (($_SESSION['role_id'] ?? null) === ROLE_SUPER_ADMIN): ?>
                    <li class="nav-item">
                        <a class="nav-link collapsed <?php echo (in_array($page, $posPages)) ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#pos-submenu" aria-expanded="<?php echo (in_array($page, $posPages)) ? 'true' : 'false'; ?>"><i class="bi bi-display me-2"></i>POS 管理</a>
                        <div class="collapse <?php echo (in_array($page, $posPages)) ? 'show' : ''; ?>" id="pos-submenu">
                            <ul class="nav flex-column ps-4">
                                <li class="nav-item"><a class="nav-link <?php echo (in_array($page, ['pos_menu_management', 'pos_variants_management'])) ? 'active' : ''; ?>" href="index.php?page=pos_menu_management">菜单管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_category_management') ? 'active' : ''; ?>" href="index.php?page=pos_category_management">POS分类管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_promotion_management') ? 'active' : ''; ?>" href="index.php?page=pos_promotion_management">营销活动管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo (in_array($page, ['pos_invoice_list', 'pos_invoice_detail'])) ? 'active' : ''; ?>" href="index.php?page=pos_invoice_list">票据查询</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_eod_reports') ? 'active' : ''; ?>" href="index.php?page=pos_eod_reports">日结报告</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_member_level_management') ? 'active' : ''; ?>" href="index.php?page=pos_member_level_management">会员等级管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_member_management') ? 'active' : ''; ?>" href="index.php?page=pos_member_management">会员列表管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_member_settings') ? 'active' : ''; ?>" href="index.php?page=pos_member_settings">会员积分设置</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_point_redemption_rules') ? 'active' : ''; ?>" href="index.php?page=pos_point_redemption_rules">积分兑换规则</a></li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link collapsed <?php echo (in_array($page, $dictionaryPages)) ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#dictionary-submenu" aria-expanded="<?php echo (in_array($page, $dictionaryPages)) ? 'true' : 'false'; ?>"><i class="bi bi-card-checklist me-2"></i>字典管理</a>
                        <div class="collapse <?php echo (in_array($page, $dictionaryPages)) ? 'show' : ''; ?>" id="dictionary-submenu">
                            <ul class="nav flex-column ps-4">
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'cup_management') ? 'active' : ''; ?>" href="index.php?page=cup_management">杯型管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'material_management') ? 'active' : ''; ?>" href="index.php?page=material_management">物料管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'unit_management') ? 'active' : ''; ?>" href="index.php?page=unit_management">单位管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'ice_option_management') ? 'active' : ''; ?>" href="index.php?page=ice_option_management">冰量选项管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'sweetness_option_management') ? 'active' : ''; ?>" href="index.php?page=sweetness_option_management">甜度选项管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'product_status_management') ? 'active' : ''; ?>" href="index.php?page=product_status_management">状态管理</a></li>
                            </ul>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link collapsed <?php echo (in_array($page, $systemPages)) ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#system-submenu" aria-expanded="<?php echo (in_array($page, $systemPages)) ? 'true' : 'false'; ?>"><i class="bi bi-gear me-2"></i>系统设置</a>
                        <div class="collapse <?php echo (in_array($page, $systemPages)) ? 'show' : ''; ?>" id="system-submenu">
                             <ul class="nav flex-column ps-4">
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'user_management') ? 'active' : ''; ?>" href="index.php?page=user_management">用户管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'store_management' || $page === 'kds_user_management') ? 'active' : ''; ?>" href="index.php?page=store_management">门店管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_print_template_management') ? 'active' : ''; ?>" href="index.php?page=pos_print_template_management">打印模板管理</a></li>
                                <li class="nav-item"><a class="nav-link <?php echo ($page === 'pos_print_template_variables') ? 'active' : ''; ?>" href="index.php?page=pos_print_template_variables">模板可用变量</a></li>
                            </ul>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="main-content flex-grow-1 p-4">
            <header class="d-flex justify-content-between align-items-center mb-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="index.php?page=dashboard">后台管理</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></li>
                    </ol>
                </nav>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">
                        <li><a class="dropdown-item" href="index.php?page=profile">个人资料</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                    </ul>
                </div>
            </header>
            <main>
                <?php
                    if (isset($content_view) && file_exists($content_view)) {
                        include $content_view;
                    } else {
                        $error_msg = 'Error: Content view file not found';
                        if (isset($content_view)) {
                             $error_msg .= ' at path: ' . htmlspecialchars($content_view);
                        } else {
                             $error_msg .= ' (path variable is empty).';
                        }
                         echo '<div class="alert alert-danger">' . $error_msg . '</div>';
                    }
                ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    
    <?php if (isset($page_js)): ?>
        <script src="js/<?php echo $page_js; ?>?v=<?php echo time(); ?>"></script>
    <?php endif; ?>
</body>
</html>