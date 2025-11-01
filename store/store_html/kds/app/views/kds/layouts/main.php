<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover" />
    <title><?php echo $page_title ?? 'TopTea KDS'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="css/kds_style.css?v=<?php echo time(); ?>">
</head>
<body>
    
    <?php
        // 这是注入实际页面内容的地方 (例如 sop_view.php 或 prep_view.php)
        if (isset($content_view) && file_exists($content_view)) {
            include $content_view;
        } else {
            echo '<div class="alert alert-danger m-5">Error: Content view file not found.</div>';
        }
    ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="js/kds_state.js?v=<?php echo time(); ?>"></script>
    <script src="js/kds_print_bridge.js?v=<?php echo time(); ?>"></script>
    
    <script src="js/kds_ui_helpers.js?v=<?php echo time(); ?>"></script>
    <?php if (isset($page_js)): ?>
        <script src="js/<?php echo $page_js; ?>?v=<?php echo time(); ?>"></script>
    <?php endif; ?>

    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel" data-i18n-key="modal_title_confirm">请确认操作</h5>
                </div>
                <div class="modal-body" id="confirmationModalBody">
                    您确定要执行此操作吗？
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-i18n-key="modal_btn_cancel">取消</button>
                    <button type="button" class="btn btn-primary" id="confirm-action-btn" data-i18n-key="modal_btn_confirm">确认</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="printPreviewModal" tabindex="-1" aria-labelledby="printPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background-color: #343a40; color: white;">
                <div class="modal-header">
                    <h5 class="modal-title" id="printPreviewModalLabel">打印预览 (模拟)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="printPreviewBody" style="font-family: monospace; white-space: pre; font-size: 0.9rem; background: #fdfdfd; color: #000; border-radius: 4px; padding: 15px;">
                    ...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="kdsSimpleAlertModal" tabindex="-1" aria-labelledby="kdsSimpleAlertModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="d-flex align-items-center">
                        <div id="kdsSimpleAlertIcon" style="flex-shrink: 0; margin-right: 1rem;">
                            </div>
                        <div style="flex-grow: 1;">
                            <h5 class="modal-title" id="kdsSimpleAlertTitle" style="font-weight: 700;">操作成功</h5>
                            <p id="kdsSimpleAlertBody" style="margin: 0; padding-top: 5px;"></p>
                        </div>
                    </div>
                    <div class="text-end" style="margin-top: 1.5rem;">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">确定</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            // 设置 KDS 语言
            var savedLang = localStorage.getItem("kds_lang") || "zh-CN";
            KDS_STATE.lang = savedLang;
            
            var kds_store_id = <?php echo (int)($_SESSION['kds_store_id'] ?? 0); ?>;
            
            if (kds_store_id === 0) {
                 console.error("KDS 启动失败: KDS store_id 未在会话中找到。");
                 if (window.showKdsAlert) {
                    showKdsAlert("KDS 启动失败: 无效的 KDS 会话，请重新登录。", true);
                 } else {
                    alert("KDS 启动失败: 无效的 KDS 会话，请重新登录。");
                 }
                 return;
            }

            // 异步获取打印模板并存入 KDS_STATE
            $.ajax({
                url: '../pos/api/pos_print_handler.php?action=get_templates&kds_store_id=' + kds_store_id,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.data) {
                        KDS_STATE.templates = response.data;
                        console.log("KDS 打印模板加载成功:", KDS_STATE.templates);
                    } else {
                        console.error("KDS 打印模板加载失败:", response.message);
                        if (window.showKdsAlert) {
                           showKdsAlert("打印模板加载失败: " + response.message, true);
                        } else {
                           alert("打印模板加载失败: " + response.message);
                        }
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("KDS 打印模板网络请求失败:", textStatus, errorThrown);
                    if (window.showKdsAlert) {
                        showKdsAlert("打印模板网络请求失败。", true);
                    } else {
                        alert("打印模板网络请求失败。");
                    }
                }
            });
        });
    </script>
</body>
</html>