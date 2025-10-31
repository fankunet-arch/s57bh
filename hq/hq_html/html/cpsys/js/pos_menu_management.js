/**
 * TopTea HQ - JavaScript for POS Menu Management
 * Engineer: Gemini | Date: 2025-10-26
 */
$(document).ready(function() {
    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');

    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新商品');
        form[0].reset();
        dataIdInput.val('');
        $('#is_active').prop('checked', true);
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑商品');
        form[0].reset();
        dataIdInput.val(dataId);

        $.ajax({
            url: 'api/pos_menu_item_handler.php',
            type: 'GET',
            data: { action: 'get', id: dataId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const item = response.data;
                    $('#name_zh').val(item.name_zh);
                    $('#name_es').val(item.name_es);
                    $('#pos_category_id').val(item.pos_category_id);
                    $('#description_zh').val(item.description_zh);
                    $('#description_es').val(item.description_es);
                    $('#sort_order').val(item.sort_order);
                    $('#is_active').prop('checked', item.is_active == 1);
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

    form.on('submit', function(e) {
        e.preventDefault();
        const formData = {
            id: dataIdInput.val(),
            name_zh: $('#name_zh').val(),
            name_es: $('#name_es').val(),
            pos_category_id: $('#pos_category_id').val(),
            description_zh: $('#description_zh').val(),
            description_es: $('#description_es').val(),
            sort_order: $('#sort_order').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };
        $.ajax({
            url: 'api/pos_menu_item_handler.php',
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
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    alert('操作失败: ' + jqXHR.responseJSON.message);
                } else {
                    alert('保存过程中发生网络或服务器错误。');
                }
            }
        });
    });

    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除商品 "${dataName}" 吗？\n警告：此操作将同时删除其下所有规格和定价！`)) {
            $.ajax({
                url: 'api/pos_menu_item_handler.php',
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