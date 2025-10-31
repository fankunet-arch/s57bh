/**
 * TopTea HQ - JavaScript for POS Item Variants Management
 * Engineer: Gemini | Date: 2025-10-26
 */
$(document).ready(function() {
    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');

    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新规格');
        form[0].reset();
        dataIdInput.val('');
        $('#is_default').prop('checked', false);
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑规格');
        form[0].reset();
        dataIdInput.val(dataId);

        $.ajax({
            url: 'api/pos_item_variant_handler.php',
            type: 'GET',
            data: { action: 'get', id: dataId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const v = response.data;
                    $('#variant_name_zh').val(v.variant_name_zh);
                    $('#variant_name_es').val(v.variant_name_es);
                    $('#price_eur').val(v.price_eur);
                    $('#product_id').val(v.product_id);
                    $('#sort_order').val(v.sort_order);
                    $('#is_default').prop('checked', v.is_default == 1);
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
            menu_item_id: $('#menu-item-id').val(),
            variant_name_zh: $('#variant_name_zh').val(),
            variant_name_es: $('#variant_name_es').val(),
            price_eur: $('#price_eur').val(),
            product_id: $('#product_id').val(),
            sort_order: $('#sort_order').val(),
            is_default: $('#is_default').is(':checked') ? 1 : 0
        };
        $.ajax({
            url: 'api/pos_item_variant_handler.php',
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
        if (confirm(`您确定要删除规格 "${dataName}" 吗？`)) {
            $.ajax({
                url: 'api/pos_item_variant_handler.php',
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