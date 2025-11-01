<?php
/**
 * TopTea POS - Main Entry Point
 * Engineer: Gemini | Date: 2025-10-30
 * Revision: 3.5 (Enhance Top Bar User Info Display)
 */

// This MUST be the first include. It checks if the user is logged in.
require_once realpath(__DIR__ . '/../../pos_backend/core/pos_auth_core.php');

$cache_version = time();
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>TopTea · POS 点餐台</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script>window.SHIFT_POLICY = 'force_all';</script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link href="./assets/pos.css?v=<?php echo $cache_version; ?>" rel="stylesheet">
</head>
<body class="lefty-mode">
  <nav class="navbar navbar-expand bg-surface fixed-top shadow-sm border-0">
    <div class="container-fluid gap-2">
      <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="#">
        <span class="brand-dot"></span>TopTea POS<span class="badge bg-brand-soft text-brand fw-semibold ms-2" data-i18n="internal">Internal</span>
      </a>
      <div class="d-flex align-items-center ms-auto gap-3">
        <span id="pos_clock" class="navbar-text fw-bold">--:--:--</span>
        <span class="navbar-text text-muted">|</span>
        <span id="pos_store_name" class="navbar-text fw-bold"><?php echo htmlspecialchars($_SESSION['pos_store_name'] ?? 'Store'); ?></span>
        <span class="navbar-text text-muted">|</span>
        <div class="dropdown">
            <a href="#" class="navbar-text dropdown-toggle text-decoration-none" data-bs-toggle="dropdown">
                <i class="bi bi-person"></i> <?php echo htmlspecialchars($_SESSION['pos_display_name'] ?? 'User'); ?> <span class="badge bg-secondary fw-normal"><?php echo htmlspecialchars($_SESSION['pos_user_role'] ?? 'staff'); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>退出登录</a></li>
            </ul>
        </div>
        
        <div class="dropdown">
            <button class="btn btn-outline-ink btn-sm dropdown-toggle px-2" data-bs-toggle="dropdown" id="lang_toggle"><span class="flag">🇨🇳</span> <span data-i18n="lang_zh">中文</span></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item active" href="#" data-lang="zh"><span class="flag">🇨🇳</span> 中文</a></li>
                <li><a class="dropdown-item" href="#" data-lang="es"><span class="flag">🇪🇸</span> Español</a></li>
            </ul>
        </div>
        <button class="btn btn-brand btn-sm" id="btn_sync" title="同步/刷新"><i class="bi bi-arrow-repeat"></i></button>
      </div>
    </div>
  </nav>

  <main class="container-fluid pos-container"><div class="row g-2"><div class="col-12"><div class="input-group search-box prominent"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" class="form-control" id="search_input" placeholder="搜索饮品或拼音简称…"><button class="btn btn-outline-ink" id="clear_search"><i class="bi bi-x-lg"></i></button></div></div><div class="col-12"><div id="category_scroller" class="nav nav-pills flex-nowrap overflow-x-auto gap-2"></div></div></div><div class="row g-2 mt-2" id="product_grid"></div></main>

  <div class="pos-bottombar border-0"><div class="container-fluid d-flex align-items-center justify-content-between" id="bottom_bar"><button class="btn btn-outline-ink d-flex align-items-center gap-2" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas" id="btn_cart_open"><i class="bi bi-bag-check"></i> <span data-i18n="cart">购物车</span><span class="badge bg-brand-soft text-brand fw-semibold" id="cart_count">0</span></button><div class="text-end me-auto ms-3 small text-muted d-none d-sm-block"><div data-i18n="total_before_discount">合计</div><div class="fs-5 text-ink fw-semibold" id="cart_total">€0.00</div></div><div class="d-flex gap-2 align-items-stretch"><button class="btn btn-outline-ink" data-bs-toggle="offcanvas" data-bs-target="#opsOffcanvas" id="btn_ops"><i class="bi bi-grid"></i> <span data-i18n="more">功能</span></button></div></div></div>

  <div class="offcanvas offcanvas-end offcanvas-sheet" tabindex="-1" id="cartOffcanvas">
    <div class="offcanvas-header border-0"><h5 class="offcanvas-title"><i class="bi bi-bag"></i> <span data-i18n="cart">购物车</span></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body p-0 d-flex flex-column">
      <div id="member_section" class="p-3 border-bottom border-sheet"></div>
      <div id="cart_items" class="list-group list-group-flush flex-grow-1 overflow-y-auto"></div>
      <div class="p-3 border-top border-sheet mt-auto">
        <div class="input-group mb-3"><input type="text" class="form-control" id="coupon_code_input" placeholder="输入优惠码"><button class="btn btn-outline-secondary" type="button" id="apply_coupon_btn">应用</button></div>
        <div id="points_redemption_section" class="mb-3" style="display: none;">
            <div class="input-group">
                <input type="number" class="form-control" id="points_to_redeem_input" placeholder="使用积分">
                <button class="btn btn-outline-secondary" type="button" id="apply_points_btn">应用</button>
            </div>
            <div class="form-text d-flex justify-content-between">
                <span data-i18n="points_rule">100积分 = 1€</span>
                <span id="points_feedback"></span>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center fs-5 mt-2"><span class="fw-semibold" data-i18n="payable">应收</span><span id="cart_payable" class="fw-bold text-brand">€0.00</span></div>
        <div class="d-flex gap-2 mt-3"><button class="btn btn-outline-secondary flex-grow-1" id="btn_hold_current_cart" data-i18n="hold_this">挂起此单</button><button class="btn btn-brand flex-grow-1" id="btn_cart_checkout"><i class="bi bi-credit-card-2-front"></i> <span id="btn_cart_checkout_label" data-i18n="go_checkout">去结账</span></button></div>
      </div>
    </div>
  </div>

  <div class="offcanvas offcanvas-bottom offcanvas-sheet h-75" tabindex="-1" id="opsOffcanvas"><div class="offcanvas-header"><h5 class="offcanvas-title" data-i18n="ops_panel">功能面板</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body"><div class="row g-3">
    <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" id="btn_open_txn_query"><i class="bi bi-clock-history d-block fs-2 mb-2"></i><span data-i18n="txn_query">交易查询</span></button></div>
    <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" id="btn_open_eod"><i class="bi bi-calendar-check d-block fs-2 mb-2"></i><span data-i18n="eod">日结</span></button></div>
    <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" id="btn_open_holds"><i class="bi bi-inboxes d-block fs-2 mb-2"></i><span data-i18n="holds">挂起单</span></button></div>
    <div class="col-6 col-md-3"><button class="btn btn-outline-ink w-100 py-3" data-bs-toggle="offcanvas" data-bs-target="#settingsOffcanvas"><i class="bi bi-gear d-block fs-2 mb-2"></i><span data-i18n="settings">设置</span></button></div>
  </div></div></div>

  <div class="offcanvas offcanvas-bottom offcanvas-sheet h-75" tabindex="-1" id="customizeOffcanvas"><div class="offcanvas-header"><h5 class="offcanvas-title" id="customize_title"></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body"><div class="mb-4"><h6 class="fw-bold" data-i18n="size">规格</h6><div class="d-flex flex-wrap gap-2" id="customize_variants_list"></div></div><div class="mb-4"><h6 class="fw-bold" data-i18n="ice">冰量</h6><div class="d-flex flex-wrap gap-2"><input type="radio" class="btn-check" name="ice" id="ice_100" value="100"><label class="btn btn-pill" for="ice_100">正常冰</label><input type="radio" class="btn-check" name="ice" id="ice_50" value="50" checked><label class="btn btn-pill" for="ice_50">少冰</label><input type="radio" class="btn-check" name="ice" id="ice_0" value="0"><label class="btn btn-pill" for="ice_0">去冰</label></div></div><div class="mb-4"><h6 class="fw-bold" data-i18n="sugar">糖度</h6><div class="d-flex flex-wrap gap-2"><input type="radio" class="btn-check" name="sugar" id="sugar_100" value="100"><label class="btn btn-pill" for="sugar_100">全糖</label><input type="radio" class="btn-check" name="sugar" id="sugar_50" value="50" checked><label class="btn btn-pill" for="sugar_50">半糖</label><input type="radio" class="btn-check" name="sugar" id="sugar_0" value="0"><label class="btn btn-pill" for="sugar_0">无糖</label></div></div><div class="mb-4"><h6 class="fw-bold" data-i18n="addons">加料</h6><div class="d-flex flex-wrap gap-2" id="addon_list"></div></div><div class="mb-3"><label for="remark_input" class="form-label fw-bold" data-i18n="remark">备注（可选）</label><input type="text" class="form-control" id="remark_input"></div></div><div class="offcanvas-footer p-3 border-top border-sheet"><div class="d-flex justify-content-between align-items-center mb-3"><span data-i18n="curr_price">当前价格</span><span class="fs-4 fw-bold text-brand" id="customize_price">€0.00</span></div><button class="btn btn-brand w-100" id="btn_add_to_cart" data-i18n="add_to_cart">加入购物车</button></div></div>

  <div class="modal fade" id="orderSuccessModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-sheet"><div class="modal-body text-center p-4"><i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i><h3 class="mt-3" data-i18n="order_success">下单成功</h3><p class="text-muted" data-i18n="invoice_number">票号</p><h4 class="mb-3" id="success_invoice_number">--</h4><p class="text-muted small" data-i18n="qr_code_info">合规二维码内容 (TicketBAI/Veri*Factu)</p><div class="p-2 bg-light rounded border"><code id="success_qr_content" style="word-break: break-all;">-</code></div><button type="button" class="btn btn-brand w-100 mt-4" data-bs-dismiss="modal" data-i18n="new_order">开始新订单</button></div></div></div></div>

  <div class="modal fade" id="paymentModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"><div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-sheet"><div class="modal-header"><h5 class="modal-title" data-i18n="checkout">结账</h5><button type="button" class="btn-close" data-bs-dismiss="modal" id="btn_cancel_payment"></button></div><div class="modal-body p-4"><div class="row text-center mb-3"><div class="col"><small data-i18n="receivable">应收</small><div class="fs-4 fw-bold text-brand" id="payment_total_display">€0.00</div></div><div class="col"><small data-i18n="paid">已收</small><div class="fs-4 fw-bold" id="payment_paid_display">€0.00</div></div><div class="col"><small data-i18n="remaining">剩余</small><div class="fs-4 fw-bold text-info" id="payment_remaining_display">€0.00</div></div><div class="col"><small data-i18n="change">找零</small><div class="fs-4 fw-bold" id="payment_change_display">€0.00</div></div></div><div id="payment_parts_container" class="mb-3"></div>
  
  <div class="mb-3"><small class="text-muted">快捷现金</small><div class="d-flex flex-wrap gap-2 mt-1"><button class="btn btn-outline-secondary btn-quick-cash" data-value="5">€5</button><button class="btn btn-outline-secondary btn-quick-cash" data-value="10">€10</button><button class="btn btn-outline-secondary btn-quick-cash" data-value="20">€20</button><button class="btn btn-outline-secondary btn-quick-cash" data-value="50">€50</button></div></div>

  <div class="mb-2"><small class="text-muted" data-i18n="payment_methods_label">支付方式</small></div><div id="payment_method_selector" class="d-flex flex-wrap gap-2"><button class="btn btn-outline-primary btn-payment-method" data-pay-method="Cash"><i class="bi bi-cash-coin me-1"></i><span data-i18n="cash_payment">现金</span></button><button class="btn btn-outline-primary btn-payment-method" data-pay-method="Card"><i class="bi bi-credit-card me-1"></i><span data-i18n="card_payment">刷卡</span></button><button class="btn btn-outline-primary btn-payment-method" data-pay-method="Bizum" disabled><i class="bi bi-phone me-1"></i>Bizum</button><button class="btn btn-outline-primary btn-payment-method" data-pay-method="Platform"><i class="bi bi-qr-code me-1"></i><span data-i18n="platform_code">平台码</span></button></div></div><div class="modal-footer d-grid"><button type="button" id="btn_confirm_payment" class="btn btn-primary w-100">确认收款</button></div></div></div></div>
  <div id="payment_templates" class="d-none"><div class="payment-part card card-body mb-2" data-method="Cash"><div class="d-flex align-items-center mb-2"><span class="fw-bold"><i class="bi bi-cash-coin me-2"></i><span data-i18n="cash_payment">现金</span></span><button class="btn-close ms-auto remove-part-btn"></button></div><input type="number" class="form-control form-control-lg text-center payment-part-input" placeholder="0.00"></div><div class="payment-part card card-body mb-2" data-method="Card"><div class="d-flex align-items-center mb-2"><span class="fw-bold"><i class="bi bi-credit-card me-2"></i><span data-i18n="card_payment">刷卡</span></span><button class="btn-close ms-auto remove-part-btn"></button></div><input type="number" class="form-control form-control-lg text-center payment-part-input" placeholder="0.00"></div><div class="payment-part card card-body mb-2" data-method="Platform"><div class="d-flex align-items-center mb-2"><span class="fw-bold"><i class="bi bi-qr-code me-2"></i><span data-i18n="platform_code">平台码</span></span><button class="btn-close ms-auto remove-part-btn"></button></div><div class="row g-2"><div class="col-7"><label class="form-label small" data-i18n="platform_amount">收款金额</label><input type="number" class="form-control form-control-lg text-center payment-part-input" placeholder="0.00"></div><div class="col-5"><label class="form-label small" data-i18n="platform_ref">参考码</label><input type="text" class="form-control form-control-lg text-center payment-part-ref" placeholder="输入码"></div></div></div></div>

  <div class="offcanvas offcanvas-end offcanvas-sheet" tabindex="-1" id="holdOrdersOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title d-flex align-items-center gap-2"><i class="bi bi-inboxes"></i><span data-i18n="holds">挂起单</span></h5>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-sort-down"></i> <span data-i18n="sort_by_time">排序: 最近</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" data-sort="time_desc" data-i18n="sort_by_time">最近</a></li>
                <li><a class="dropdown-item" href="#" data-sort="amount_desc" data-i18n="sort_by_amount">金额</a></li>
            </ul>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="input-group mb-3">
            <input type="text" class="form-control" id="hold_order_note_input" data-i18n-placeholder="hold_placeholder">
            <button class="btn btn-brand" type="button" id="btn_create_new_hold"><i class="bi bi-plus-circle"></i> <span data-i18n="create_hold">新建挂起单</span></button>
        </div>
        <p class="form-text mt-0 mb-3" data-i18n="hold_instruction"></p>
        <hr/>
        <div id="held_orders_list"></div>
    </div>
  </div>

  <div class="offcanvas offcanvas-bottom offcanvas-sheet" tabindex="-1" id="settingsOffcanvas"><div class="offcanvas-header"><h5 class="offcanvas-title" data-i18n="settings">设置</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body"><div class="list-group"><div class="list-group-item"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="setting_peak_mode"><label class="form-check-label" for="setting_peak_mode" data-i18n="peak_mode">高峰模式 (对比增强)</label></div><small class="form-text text-muted" data-i18n="peak_mode_desc">左侧菜单变白，并在前方功能按钮保留返回图示，避免误操。</small></div><div class="list-group-item"><div class="form-check"><input class="form-check-input" type="radio" name="hand_mode" id="setting_lefty_mode" value="lefty-mode"><label class="form-check-label" for="setting_lefty_mode" data-i18n="lefty_mode">左手模式 (点菜按钮靠左)</label></div></div><div class="list-group-item"><div class="form-check"><input class="form-check-input" type="radio" name="hand_mode" id="setting_righty_mode" value="righty-mode"><label class="form-check-label" for="setting_righty_mode" data-i18n="righty_mode">右手模式 (点菜按钮靠右)</label></div></div></div></div></div>

  <div class="offcanvas offcanvas-end offcanvas-sheet" tabindex="-1" id="txnQueryOffcanvas"><div class="offcanvas-header"><h5 class="offcanvas-title" data-i18n="txn_query">交易查询</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div><div class="offcanvas-body p-0" id="txn_list_container"></div></div>

  <div class="modal fade" id="txnDetailModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content modal-sheet"><div class="modal-header"><h5 class="modal-title" id="txn_detail_title">票据详情</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="txn_detail_body"></div><div class="modal-footer" id="txn_detail_footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button></div></div></div></div>

  <div class="modal fade" id="eodSummaryModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content modal-sheet"><div class="modal-header"><h5 class="modal-title" data-i18n="eod_title">今日日结报告</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="eod_summary_body"><div class="text-center p-4"><div class="spinner-border"></div></div></div><div class="modal-footer" id="eod_summary_footer"></div></div></div></div>
  <div class="modal fade" id="eodConfirmModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-sheet"><div class="modal-header"><h5 class="modal-title" data-i18n="eod_confirm_title">确认提交日结</h5></div><div class="modal-body"><p data-i18n="eod_confirm_body">提交后，今日日结数据将被存档且无法修改。请确认所有款项已清点完毕。</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-i18n="eod_confirm_cancel">取消</button><button type="button" class="btn btn-primary" id="btn_confirm_eod_final" data-i18n="eod_confirm_submit">确认提交</button></div></div></div></div>

  <div class="modal fade" id="memberCreateModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content modal-sheet"><div class="modal-header"><h5 class="modal-title" data-i18n="member_create_title">创建新会员</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="form_create_member"><div class="mb-3"><label for="member_phone" class="form-label" data-i18n="member_phone">手机号</label><input type="tel" class="form-control" id="member_phone" required></div><div class="row g-2 mb-3"><div class="col-md"><label for="member_firstname" class="form-label" data-i18n="member_firstname">名字</label><input type="text" class="form-control" id="member_firstname"></div><div class="col-md"><label for="member_lastname" class="form-label" data-i18n="member_lastname">姓氏</label><input type="text" class="form-control" id="member_lastname"></div></div><div class="mb-3"><label for="member_email" class="form-label" data-i18n="member_email">邮箱</label><input type="email" class="form-control" id="member_email"></div><div class="mb-3"><label for="member_birthdate" class="form-label" data-i18n="member_birthdate">生日</label><input type="date" class="form-control" id="member_birthdate"></div><div id="member_create_error" class="alert alert-danger d-none mt-3"></div></form></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-i18n="cancel">取消</button><button type="submit" form="form_create_member" class="btn btn-primary" data-i18n="member_create_submit">创建并关联</button></div></div></div></div>

  <div class="modal fade" id="refundConfirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-sheet">
        <div class="modal-header">
          <h5 class="modal-title" id="refundConfirmModalLabel">确认操作</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="refundConfirmModalBody">
          您确定要执行此操作吗？
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-i18n="cancel">取消</button>
          <button type="button" class="btn btn-primary" id="btn_confirm_refund_action">确认</button>
        </div>
      </div>
    </div>
  </div>
  
  <div class="modal fade" id="startShiftModal" tabindex="-1" aria-labelledby="startShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-sheet">
        <div class="modal-header">
          <h5 class="modal-title" id="startShiftModalLabel" data-i18n="shift_start_title">开始当班 (Start Shift)</h5>
          <div class="dropdown ms-auto">
              <button class="btn btn-outline-secondary btn-sm dropdown-toggle px-2" data-bs-toggle="dropdown" id="lang_toggle_modal"><span class="flag">🇨🇳</span></button>
              <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item active" href="#" data-lang="zh"><span class="flag">🇨🇳</span> 中文</a></li>
                  <li><a class="dropdown-item" href="#" data-lang="es"><span class="flag">🇪🇸</span> Español</a></li>
              </ul>
          </div>
        </div>
        <div class="modal-body">
          <p data-i18n="shift_start_body">在开始销售前，请输入您钱箱中的初始备用金金额。</p>
          <form id="start_shift_form">
            <div class="form-floating">
              <input type="number" class="form-control" id="starting_float" placeholder="初始备用金" step="0.01" min="0" required>
              <label for="starting_float" data-i18n="shift_start_label">初始备用金 (€)</label>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="submit" form="start_shift_form" class="btn btn-primary w-100" data-i18n="shift_start_submit">确认并开始当班</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="endShiftModal" tabindex="-1" aria-labelledby="endShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content modal-sheet">
        <div class="modal-header">
          <h5 class="modal-title" id="endShiftModalLabel">交接班 / 结束当班 (End Shift)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 border-end" id="end_shift_summary_body">
              </div>
            <div class="col-md-6">
              <form id="end_shift_form">
                <div class="mb-3">
                  <label for="counted_cash" class="form-label fs-5">清点现金总额 (€)</label>
                  <input type="number" class="form-control form-control-lg" id="counted_cash" placeholder="0.00" step="0.01" min="0" required>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                  <span class="fs-5">现金差异:</span>
                  <span id="cash_variance_display" class="fs-4">€0.00</span>
                </div>
                <p class="form-text">差异 = 清点现金 - 系统应有现金。负数表示短款。</p>
              </form>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
          <button type="submit" form="end_shift_form" class="btn btn-danger w-50"><i class="bi bi-printer me-2"></i>确认交班并打印</button>
        </div>
      </div>
    </div>
  </div>
  
  <div class="modal fade" id="eodResultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">交接班已完成</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2">
          <span id="eod_started_at"></span> → <span id="eod_ended_at"></span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <tbody>
              <tr><td>期初备用金</td><td class="text-end" id="eod_starting_float">€0.00</td></tr>
              <tr><td>现金销售</td><td class="text-end" id="eod_cash_sales">€0.00</td></tr>
              <tr><td>现金流入</td><td class="text-end" id="eod_cash_in">€0.00</td></tr>
              <tr><td>现金流出</td><td class="text-end" id="eod_cash_out">€0.00</td></tr>
              <tr><td>现金退款</td><td class="text-end" id="eod_cash_refunds">€0.00</td></tr>
              <tr class="table-light"><td>理论应有现金</td><td class="text-end fw-bold" id="eod_expected_cash">€0.00</td></tr>
              <tr><td>清点现金</td><td class="text-end fw-bold" id="eod_counted_cash">€0.00</td></tr>
              <tr class="table-light">
                <td>现金差异</td>
                <td class="text-end fw-bold" id="eod_cash_diff">€0.00</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnViewEodHistory">查看交接班记录</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">知道了</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="eodHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">最近交接班记录</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class_ = "table-responsive">
          <table class="table table-sm" id="eodHistoryTable">
            <thead>
              <tr>
                <th>开始</th><th>结束</th>
                <th class="text-end">期初</th>
                <th class="text-end">现金销</th>
                <th class="text-end">流入</th>
                <th class="text-end">流出</th>
                <th class="text-end">退款</th>
                <th class="text-end">理论现金</th>
                <th class="text-end">清点现金</th>
                <th class="text-end">差异</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="paymentConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">收款确认</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="row text-center mb-3">
          <div class="col-4">
            <div class="text-muted small">应收</div>
            <div class="fs-5 fw-bold" id="pc-due">€0.00</div>
          </div>
          <div class="col-4">
            <div class="text-muted small">实收</div>
            <div class="fs-5 fw-bold" id="pc-paid">€0.00</div>
          </div>
          <div class="col-4">
            <div class="text-muted small">应找零</div>
            <div class="fs-5 fw-bold" id="pc-change">€0.00</div>
          </div>
        </div>

        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between small text-muted">
            <span>收款方式</span><span>入账金额</span>
          </div>
          <div id="pc-methods"><div class="small text-muted">—</div></div>
        </div>

        <div id="pc-warning" class="alert alert-danger py-2 d-none">
          少收 <span id="pc-lack">€0.00</span>，请补齐后再提交。
        </div>
        <div id="pc-note" class="alert alert-info py-2 d-none">
          已包含找零 <span id="pc-note-change">€0.00</span>，系统将按应收金额入账。
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">返回修改</button>
        <button type="button" class="btn btn-primary" id="pc-confirm">确认入账并打印</button>
      </div>

    </div>
  </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3"><div id="sys_toast" class="toast" role="alert"><div class="toast-body" id="toast_msg"></div></div></div>

<script type="module" src="./assets/js/main.js?v=<?php echo $cache_version; ?>"></script>

</body>
</html>