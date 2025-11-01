<?php
/**
 * Toptea HQ - RMS (Recipe Management System) View
 * Engineer: Gemini | Date: 2025-10-31 | Revision: 3.5 (Add P-A-M-T Code Display)
 */
?>
<div class="row">
    <div class="col-lg-3">
        <div class="card" style="height: calc(100vh - 120px);">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-journal-album me-2"></i>产品列表</span>
                <button class="btn btn-primary btn-sm" id="btn-add-product" title="创建新产品">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div class="list-group list-group-flush overflow-auto" id="product-list-container">
                <?php if (empty($base_products)): ?>
                    <div class="list-group-item text-muted">暂无产品</div>
                <?php else: ?>
                    <?php foreach ($base_products as $product): ?>
                        <a href="#" class="list-group-item list-group-item-action" data-product-id="<?php echo $product['id']; ?>">
                            <strong><?php echo htmlspecialchars($product['product_code']); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($product['name_zh']); ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div id="product-editor-container">
            <div class="card" style="height: calc(100vh - 120px);">
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div class="text-center text-muted">
                        <i class="bi bi-arrow-left-circle-fill fs-1"></i>
                        <h5 class="mt-3">请从左侧选择一个产品进行编辑</h5>
                        <p>或点击 <i class="bi bi-plus-lg"></i> 创建一个新产品。</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="rms-templates" class="d-none">
    <div id="editor-template">
        <form id="product-form">
            <input type="hidden" id="product-id">
            <div class="card" style="height: calc(100vh - 120px);">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">编辑产品: <span id="editor-title"></span></h5>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" id="btn-delete-product">删除产品</button>
                        <button type="submit" class="btn btn-primary btn-sm">保存更改</button>
                    </div>
                </div>
                <div class="card-body overflow-auto">
                    <div class="card mb-4">
                        <div class="card-header">基础信息</div>
                        <div class="card-body">
                             <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">产品编码 (P-Code)</label>
                                    <input type="text" class="form-control" id="product_code" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">中文名</label>
                                    <input type="text" class="form-control" id="name_zh" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">西班牙语名</label>
                                    <input type="text" class="form-control" id="name_es" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">状态</label>
                                    <select class="form-select" id="status_id">
                                        <?php foreach($status_options as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['status_name_zh']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>基础配方 <small class="text-muted">(标准规格用量)</small></span>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-base-recipe-row">
                                <i class="bi bi-plus-circle me-1"></i> 添加原料
                            </button>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <thead><tr><th>步骤</th><th>物料</th><th>用量</th><th>单位</th><th></th></tr></thead>
                                <tbody id="base-recipe-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>动态调整规则 <small class="text-muted">(用于覆盖基础配方)</small></span>
                             <button type="button" class="btn btn-outline-info btn-sm" id="btn-add-adjustment-rule">
                                <i class="bi bi-plus-circle me-1"></i> 添加调整规则
                            </button>
                        </div>
                        <div class="card-body" id="adjustments-body">
                            <p class="text-muted text-center" id="no-adjustments-placeholder">暂无调整规则。</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <table>
        <tbody id="recipe-row-template-container">
            <tr id="recipe-row-template">
                <td>
                    <select class="form-select form-select-sm step-category-select">
                        <option value="base">① 底料</option>
                        <option value="mixing">② 调杯</option>
                        <option value="topping">③ 顶料</option>
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm material-select">
                        <option value="">-- 选择物料 --</option>
                        <?php foreach($material_options as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name_zh']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" class="form-control form-control-sm quantity-input" placeholder="用量"></td>
                <td>
                    <select class="form-select form-select-sm unit-select">
                         <option value="">-- 单位 --</option>
                        <?php foreach($unit_options as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name_zh']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-trash"></i></button></td>
            </tr>
        </tbody>
    </table>

    <div id="adjustment-rule-template" class="card mb-3 adjustment-rule-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0 text-info">当满足以下条件时:</h6>
                
                <div class="input-group input-group-sm ms-auto me-3" style="max-width: 250px;">
                    <span class="input-group-text" title="P-A-M-T 组合编码">
                        <i class="bi bi-upc-scan"></i>
                    </span>
                    <input type="text" class="form-control form-control-sm pamt-code-display" readonly style="background-color: #1a1d20; font-family: monospace; text-align: center;" value="[P-A-M-T]">
                    <button class="btn btn-outline-secondary btn-copy-pamt" type="button" title="复制编码">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
                <button type="button" class="btn-close btn-remove-adjustment-rule" aria-label="删除此规则"></button>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">杯型</label>
                    <select class="form-select form-select-sm cup-condition">
                        <option value="" data-code="">-- 任意 --</option>
                         <?php foreach($cup_options as $c): ?>
                            <option value="<?php echo $c['id']; ?>" data-code="<?php echo $c['cup_code']; ?>"><?php echo htmlspecialchars($c['cup_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">甜度</label>
                    <select class="form-select form-select-sm sweetness-condition">
                        <option value="" data-code="">-- 任意 --</option>
                         <?php foreach($sweetness_options as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-code="<?php echo $s['sweetness_code']; ?>"><?php echo htmlspecialchars($s['name_zh']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">冰量</label>
                    <select class="form-select form-select-sm ice-condition">
                        <option value="" data-code="">-- 任意 --</option>
                         <?php foreach($ice_options as $i): ?>
                            <option value="<?php echo $i['id']; ?>" data-code="<?php echo $i['ice_code']; ?>"><?php echo htmlspecialchars($i['name_zh']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <h6 class="text-info">则覆盖以下原料用量:</h6>
            <table class="table table-sm table-borderless">
                <thead>
                    <tr>
                        <th style="width: 25%;">步骤</th>
                        <th style="width: 35%;">物料</th>
                        <th style="width: 15%;">用量</th>
                        <th style="width: 20%;">单位</th>
                        <th style="width: 5%;"></th>
                    </tr>
                </thead>
                <tbody class="adjustment-recipe-body"></tbody>
            </table>
            <button type="button" class="btn btn-outline-secondary btn-sm btn-add-adjustment-recipe-row">
                <i class="bi bi-plus-circle-dotted"></i> 添加原料覆盖
            </button>
        </div>
    </div>
</div>