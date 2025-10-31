<div class="kds-container">
    <div class="kds-left-sidebar">
      <div class="kds-left-header">
        <form id="sku-search-form" class="d-flex gap-2">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                <input type="text" class="form-control" id="sku-input" placeholder="输入产品编码..." required data-i18n-key="placeholder_sku">
            </div>
            <button class="btn btn-primary" type="submit" style="background-color: var(--brand-500); border-color: var(--brand-500);"><i class="bi bi-search"></i></button>
        </form>
      </div>
      
      <div id="product-info-area">
          <div class="kds-cup-number mb-2 text-muted">---</div>
          <h3 class="fw-bold mb-3 text-muted" data-i18n-key="info_enter_sku">请先输入编码</h3>
          <div id="kds-info-display-container">
            <div class="kds-info-display text-muted">--</div>
          </div>
      </div>

      <div id="dynamic-options-container" class="mt-3"></div>

      <div class="d-grid gap-3 mt-auto">
        <button class="btn btn-complete btn-touch-action" disabled><span data-i18n-key="btn_action_complete">制茶完成</span> <i class="bi bi-check-lg"></i></button>
        <button class="btn btn-report btn-touch-action" disabled data-i18n-key="btn_action_report">缺料申报</button>
      </div>
      <div class="kds-side-cp" id="kds-side-cp">© <span id="cp-year">2025</span> TOPTEA</div>
    </div>
    
    <div class="kds-main-content">
      <div class="kds-top-nav">
        <div class="nav">
          <a class="nav-link" href="prep.php" data-i18n-key="nav_prep">物料制备</a>
          <a class="nav-link" href="expiry.php" data-i18n-key="nav_expiry">效期追踪</a>
          <a class="nav-link active" href="#" data-i18n-key="nav_guide">制杯指引</a>
        </div>
        
        <div class="ms-auto d-flex align-items-center gap-3">
          <span id="kds-clock" class="fw-bold">--:--:--</span>
          <span id="store-name" class="small"><?php echo htmlspecialchars($_SESSION['kds_store_name'] ?? '未知门店'); ?></span>
          
          <div class="lang-switch" role="group" aria-label="切换语言">
            <span class="lang-flag active" data-lang="zh-CN" role="button" tabindex="0"><svg class="flag" viewBox="0 0 30 20"><rect fill="#DE2910" height="20" width="30"></rect><text fill="#FFDE00" font-size="8.5" x="6" y="8">★</text><text fill="#FFDE00" font-size="3.8" x="12.5" y="4.5">★</text><text fill="#FFDE00" font-size="3.8" x="14.5" y="8">★</text><text fill="#FFDE00" font-size="3.8" x="12.5" y="11.5">★</text><text fill="#FFDE00" font-size="3.8" x="9.8" y="9.5">★</text></svg></span>
            <span class="lang-flag" data-lang="es-ES" role="button" tabindex="0"><svg class="flag" viewBox="0 0 30 20"><rect fill="#AA151B" height="20" width="30"></rect><rect y="5" width="30" height="10" fill="#F1BF00"></rect></svg></span>
          </div>
          
          <div class="kds-top-actions d-flex align-items-center gap-3">
            <a href="#" title="设置"><i class="bi bi-gear-fill"></i></a>
            <a href="logout.php" class="btn btn-sm btn-outline-danger kds-logout-btn" title="退出登录">
                <i class="bi bi-box-arrow-right me-1"></i> <span data-i18n-key="btn_logout">退出</span>
            </a>
          </div>
        </div>
      </div>
      
      <div class="kds-sop-area">
        <div class="kds-steps d-flex align-items-center mb-3" role="tablist" aria-label="制作步骤">
          <div class="kds-step-tab active" role="tab" tabindex="0" data-step="base"><span class="step-number">①</span> <span data-i18n-key="step_base">底料</span></div>
          <div class="kds-step-tab" role="tab" tabindex="0" data-step="mixing"><span class="step-number">②</span> <span data-i18n-key="step_mixing">调杯</span></div>
          <div class="kds-step-tab" role="tab" tabindex="0" data-step="topping"><span class="step-number">③</span> <span data-i18n-key="step_topping">顶料</span></div>
          <div class="ms-auto kds-tip"><i class="bi bi-check-circle-fill me-2"></i><span id="kds-step-tip" data-i18n-key="tip_waiting">请输入饮品编码开始查询</span></div>
        </div>

        <div id="kds-cards-container">
            <div id="cards-base" class="row g-4 step-cards"></div>
            <div id="cards-mixing" class="row g-4 step-cards" style="display: none;"></div>
            <div id="cards-topping" class="row g-4 step-cards" style="display: none;"></div>
            <div id="cards-waiting" class="col-12 text-center text-muted pt-5">
                <h4 data-i18n-key="cards_waiting">等待查询...</h4>
            </div>
        </div>

      </div>
    </div>
</div>