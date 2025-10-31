/**
 * Toptea HQ - cpsys
 * JavaScript for Material Management Page
 *
 * Engineer: Gemini
 * Date: 2025-10-26
 * Revision: 7.4 (Expiry Rule Engine)
 */
$(document).ready(function() {
    
    const materialDrawer = new bootstrap.Offcanvas(document.getElementById('material-drawer'));
    const form = $('#material-form');
    const drawerLabel = $('#drawer-label');
    const materialIdInput = $('#material-id');
    const materialCodeInput = $('#material-code');
    const materialTypeInput = $('#material-type');
    const materialNameZhInput = $('#material-name-zh');
    const materialNameEsInput = $('#material-name-es');
    const baseUnitIdInput = $('#base-unit-id');
    const largeUnitIdInput = $('#large-unit-id');
    const conversionRateInput = $('#conversion-rate');
    const expiryRuleTypeInput = $('#expiry-rule-type');
    const expiryDurationInput = $('#expiry-duration');
    const expiryDurationWrapper = $('#expiry-duration-wrapper');
    const expiryDurationText = $('#expiry-duration-text');

    // --- Event listener for expiry rule type change ---
    expiryRuleTypeInput.on('change', function() {
        const selectedType = $(this).val();
        if (selectedType === 'HOURS' || selectedType === 'DAYS') {
            expiryDurationWrapper.show();
            expiryDurationText.text(selectedType === 'HOURS' ? '单位: 小时' : '单位: 天');
        } else {
            expiryDurationWrapper.hide();
            expiryDurationInput.val('');
        }
    });

    $('#create-material-btn').on('click', function() {
        drawerLabel.text('创建新物料');
        form[0].reset();
        materialIdInput.val('');
        materialTypeInput.val('SEMI_FINISHED');
        expiryRuleTypeInput.trigger('change'); // Reset duration visibility
        $.ajax({
            url: 'api/material_handler.php',
            type: 'GET',
            data: { action: 'get_next_code' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    materialCodeInput.val(response.data.next_code);
                }
            }
        });
    });

    $('.table').on('click', '.edit-material-btn', function() {
        const materialId = $(this).data('material-id');
        drawerLabel.text('编辑物料');
        form[0].reset();
        materialIdInput.val(materialId);

        $.ajax({
            url: 'api/material_handler.php',
            type: 'GET',
            data: { action: 'get', id: materialId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;
                    materialCodeInput.val(data.material_code);
                    materialTypeInput.val(data.material_type);
                    materialNameZhInput.val(data.name_zh);
                    materialNameEsInput.val(data.name_es);
                    baseUnitIdInput.val(data.base_unit_id);
                    largeUnitIdInput.val(data.large_unit_id);
                    conversionRateInput.val(data.conversion_rate);
                    expiryRuleTypeInput.val(data.expiry_rule_type);
                    expiryDurationInput.val(data.expiry_duration);
                    expiryRuleTypeInput.trigger('change'); // Trigger change to show/hide duration field
                } else {
                    alert('获取物料数据失败: ' + response.message);
                    materialDrawer.hide();
                }
            },
            error: function() {
                alert('获取物料数据时发生网络错误。');
                materialDrawer.hide();
            }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();

        const materialData = {
            id: materialIdInput.val(),
            material_code: materialCodeInput.val(),
            material_type: materialTypeInput.val(),
            name_zh: materialNameZhInput.val(),
            name_es: materialNameEsInput.val(),
            base_unit_id: baseUnitIdInput.val(),
            large_unit_id: largeUnitIdInput.val(),
            conversion_rate: conversionRateInput.val(),
            expiry_rule_type: expiryRuleTypeInput.val(),
            expiry_duration: expiryDurationInput.val()
        };

        $.ajax({
            url: 'api/material_handler.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'save', data: materialData }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload();
                } else {
                    alert('保存失败: ' + (response.message || '未知错误'));
                }
            },
            error: function(jqXHR) {
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    alert('操作失败: ' + jqXHR.responseJSON.message);
                } else {
                    alert('保存过程中发生网络或服务器错误。');
                }
            }
        });
    });
    
    $('.table').on('click', '.delete-material-btn', function() {
        const materialId = $(this).data('material-id');
        const materialName = $(this).data('material-name');

        if (confirm(`您确定要删除物料 "${materialName}" 吗？`)) {
            $.ajax({
                url: 'api/material_handler.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'delete', id: materialId }),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        alert(response.message);
                        window.location.reload();
                    } else {
                        alert('删除失败: ' + response.message);
                    }
                },
                error: function() {
                    alert('删除过程中发生网络或服务器错误。');
                }
            });
        }
    });
});