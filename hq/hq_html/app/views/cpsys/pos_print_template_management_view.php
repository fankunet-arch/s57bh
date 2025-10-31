<?php
/**
 * TopTea HQ - POS Print Template Management View
 * Version: 3.0.0
 * Engineer: Gemini | Date: 2025-10-30
 * Update: Added CUP_STICKER and EXPIRY_LABEL types, and physical_size selector.
 */

// Helper function to get a readable name for template types
function get_template_type_name($type) {
    $map = [
        'EOD_REPORT' => '日结报告 (Z-Out)',
        'RECEIPT' => '顾客小票',
        'KITCHEN_ORDER' => '厨房出品单',
        'SHIFT_REPORT' => '交接班报告',
        'CUP_STICKER' => '杯贴标签',
        'EXPIRY_LABEL' => '效期标签'
    ];
    return $map[$type] ?? $type;
}

// (Plan II-2) 物理尺寸列表
$physical_sizes = [
    '80mm' => '80mm (连续纸卷)',
    '50x30' => '50 × 30 mm (标签)',
    '40x30' => '40 × 30 mm (标签)',
    '58x40' => '58 × 40 mm (标签)',
    '30x40' => '30 × 40 mm (标签)',
    '25x40' => '25 × 40 mm (标签)',
    '60x40' => '60 × 40 mm (标签)',
    '40x60' => '40 × 60 mm (标签)',
    '50x40' => '50 × 40 mm (标签)',
    '50x70' => '50 × 70 mm (标签)'
];
?>

<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-primary" id="create-btn" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">
        <i class="bi bi-plus-circle me-2"></i>创建新模板
    </button>
</div>

<div class="card">
    <div class="card-header">打印模板列表</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>模板名称</th>
                        <th>模板类型</th>
                        <th>物理尺寸</th>
                        <th>状态</th>
                        <th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr><td colspan="5" class="text-center">暂无打印模板。</td></tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($template['template_name']); ?></strong></td>
                                <td>
                                    <?php 
                                        $type_name = get_template_type_name($template['template_type']);
                                        $badge_class = 'text-bg-info';
                                        if ($template['template_type'] === 'CUP_STICKER') $badge_class = 'text-bg-warning';
                                        if ($template['template_type'] === 'EXPIRY_LABEL') $badge_class = 'text-bg-primary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($type_name); ?></span>
                                </td>
                                <td>
                                    <span class="badge text-bg-secondary"><?php echo htmlspecialchars($template['physical_size'] ?? '未设置'); ?></span>
                                </td>
                                <td>
                                    <?php if ($template['is_active']): ?>
                                        <span class="badge text-bg-success">已启用</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">已禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $template['id']; ?>" data-bs-toggle="offcanvas" data-bs-target="#data-drawer">编辑</button>
                                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?php echo $template['id']; ?>" data-name="<?php echo htmlspecialchars($template['template_name']); ?>">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4" role="alert">
  <h4 class="alert-heading">关于打印模板 (高级功能)</h4>
  <p>此页面用于管理POS终端的打印模板。模板内容使用JSON格式定义，它精确控制小票上打印的每一行内容、格式和变量。目前仅支持通过直接编辑JSON来修改模板。</p>
  <hr>
  <p class="mb-0"><b>功能规划：</b>未来的版本将引入图形化编辑器，届时可拖拽组件轻松设计模板。</p>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="data-drawer" aria-labelledby="drawer-label" style="width: 600px;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="drawer-label">创建/编辑模板</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="data-form">
            <input type="hidden" id="data-id" name="id">
            <div class="mb-3">
                <label for="template_name" class="form-label">模板名称 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="template_name" name="template_name" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="template_type" class="form-label">模板类型 <span class="text-danger">*</span></label>
                    <select class="form-select" id="template_type" name="template_type" required>
                        <option value="" selected disabled>-- 请选择 --</option>
                        <option value="RECEIPT">顾客小票</option>
                        <option value="KITCHEN_ORDER">厨房出品单</option>
                        <option value="CUP_STICKER">杯贴标签</option>
                        <option value="EXPIRY_LABEL">效期标签</option>
                        <option value="EOD_REPORT">日结报告 (Z-Out)</option>
                        <option value="SHIFT_REPORT">交接班报告</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="physical_size" class="form-label">物理尺寸 <span class="text-danger">*</span></label>
                    <select class="form-select" id="physical_size" name="physical_size" required>
                        <option value="" selected disabled>-- 请选择尺寸 --</option>
                        <?php foreach ($physical_sizes as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

             <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">启用此模板</label>
            </div>
            <div class="mb-3">
                <label for="template_content" class="form-label">模板内容 (JSON) <span class="text-danger">*</span></label>
                <textarea class="form-control" id="template_content" name="template_content" rows="20" style="font-family: monospace;"></textarea>
                <div class="form-text">请在此处输入或修改模板的JSON定义。</div>
            </div>
            <div class="d-flex justify-content-end mt-4">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="offcanvas">取消</button>
                <button type="submit" class="btn btn-primary">保存模板</button>
            </div>
        </form>
    </div>
</div>
