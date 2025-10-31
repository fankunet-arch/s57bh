/**
 * TopTea HQ - JavaScript for POS Member Level Management
 * Engineer: Gemini | Date: 2025-10-28
 */
$(document).ready(function() {
    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');

    // Handle 'Create' button click
    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新会员等级');
        form[0].reset();
        dataIdInput.val('');
    });

    // Handle 'Edit' button click (using event delegation)
    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑会员等级');
        form[0].reset();
        dataIdInput.val(dataId);

        $.ajax({
            url: 'api/pos_member_level_handler.php',
            type: 'GET',
            data: { action: 'get', id: dataId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const level = response.data;
                    $('#level_name_zh').val(level.level_name_zh);
                    $('#level_name_es').val(level.level_name_es);
                    $('#points_threshold').val(level.points_threshold);
                    $('#level_up_promo_id').val(level.level_up_promo_id);
                    $('#sort_order').val(level.sort_order);
                } else {
                    alert('获取数据失败: ' + response.message);
                    dataDrawer.hide();
                }
            },
            error: function() {
                alert('获取数据时发生网络错误。');
                dataDrawer.hide();
            }
        });
    });

    // Handle form submission for both Create and Update
    form.on('submit', function(e) {
        e.preventDefault();
        const formData = {
            id: dataIdInput.val(),
            level_name_zh: $('#level_name_zh').val(),
            level_name_es: $('#level_name_es').val(),
            points_threshold: $('#points_threshold').val(),
            level_up_promo_id: $('#level_up_promo_id').val(),
            sort_order: $('#sort_order').val()
        };

        $.ajax({
            url: 'api/pos_member_level_handler.php',
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

    // Handle 'Delete' button click (using event delegation)
    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除等级 "${dataName}" 吗？\n警告：此操作不可撤销。`)) {
            $.ajax({
                url: 'api/pos_member_level_handler.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ action: 'delete', id: dataId }),
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