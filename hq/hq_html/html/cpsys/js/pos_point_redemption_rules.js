/**
 * Toptea HQ - JavaScript for POS Point Redemption Rules Page
 * Engineer: Gemini | Date: 2025-10-28
 */
$(document).ready(function() {
    const ruleDrawer = new bootstrap.Offcanvas(document.getElementById('rule-drawer'));
    const form = $('#rule-form');
    const drawerLabel = $('#drawer-label');
    const ruleIdInput = $('#rule-id');
    const rewardTypeSelect = $('#reward_type');
    const decimalGroup = $('#reward-value-decimal-group');
    const promoGroup = $('#reward-promo-id-group');

    // Toggle reward value inputs based on reward type
    rewardTypeSelect.on('change', function() {
        const type = $(this).val();
        if (type === 'DISCOUNT_AMOUNT') {
            decimalGroup.show();
            promoGroup.hide();
            $('#reward_value_decimal').prop('required', true);
            $('#reward_promo_id').prop('required', false).val(''); // Clear promo selection
        } else if (type === 'SPECIFIC_PROMOTION') {
            decimalGroup.hide();
            promoGroup.show();
            $('#reward_value_decimal').prop('required', false).val(''); // Clear decimal value
            $('#reward_promo_id').prop('required', true);
        } else {
            decimalGroup.hide();
            promoGroup.hide();
            $('#reward_value_decimal').prop('required', false).val('');
            $('#reward_promo_id').prop('required', false).val('');
        }
    });

    // Handle 'Create' button click
    $('#create-rule-btn').on('click', function() {
        drawerLabel.text('创建新兑换规则');
        form[0].reset();
        ruleIdInput.val('');
        $('#is_active').prop('checked', true);
        rewardTypeSelect.val('DISCOUNT_AMOUNT').trigger('change'); // Reset to default type
    });

    // Handle 'Edit' button click
    $('.table').on('click', '.edit-rule-btn', function() {
        const ruleId = $(this).data('id');
        drawerLabel.text('编辑兑换规则');
        form[0].reset();
        ruleIdInput.val(ruleId);

        $.ajax({
            url: 'api/pos_point_redemption_handler.php',
            type: 'GET',
            data: { action: 'get', id: ruleId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const rule = response.data;
                    $('#rule_name_zh').val(rule.rule_name_zh);
                    $('#rule_name_es').val(rule.rule_name_es);
                    $('#points_required').val(rule.points_required);
                    rewardTypeSelect.val(rule.reward_type).trigger('change'); // Trigger change to show correct fields
                    if (rule.reward_type === 'DISCOUNT_AMOUNT') {
                        $('#reward_value_decimal').val(rule.reward_value_decimal);
                    } else if (rule.reward_type === 'SPECIFIC_PROMOTION') {
                        $('#reward_promo_id').val(rule.reward_promo_id);
                    }
                    $('#is_active').prop('checked', rule.is_active == 1);
                } else {
                    alert('获取规则数据失败: ' + response.message);
                    ruleDrawer.hide();
                }
            },
            error: function() {
                alert('获取规则数据时发生网络错误。');
                ruleDrawer.hide();
            }
        });
    });

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();
        const formData = {
            id: ruleIdInput.val(),
            rule_name_zh: $('#rule_name_zh').val(),
            rule_name_es: $('#rule_name_es').val(),
            points_required: $('#points_required').val(),
            reward_type: rewardTypeSelect.val(),
            reward_value_decimal: $('#reward_value_decimal').val(),
            reward_promo_id: $('#reward_promo_id').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        // Clear irrelevant reward value based on type before sending
        if (formData.reward_type === 'DISCOUNT_AMOUNT') {
            formData.reward_promo_id = null;
        } else if (formData.reward_type === 'SPECIFIC_PROMOTION') {
            formData.reward_value_decimal = null;
        }

        $.ajax({
            url: 'api/pos_point_redemption_handler.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'save', data: formData }),
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
                const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '保存过程中发生网络或服务器错误。';
                alert('操作失败: ' + errorMsg);
            }
        });
    });

    // Handle 'Delete' button click
    $('.table').on('click', '.delete-rule-btn', function() {
        const ruleId = $(this).data('id');
        const ruleName = $(this).data('name');
        if (confirm(`您确定要删除规则 "${ruleName}" 吗？此操作为软删除。`)) {
            $.ajax({
                url: 'api/pos_point_redemption_handler.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'delete', id: ruleId }),
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

    // Initial trigger for reward type fields visibility on page load (if editing)
    // rewardTypeSelect.trigger('change'); // Not needed if create resets correctly
});
