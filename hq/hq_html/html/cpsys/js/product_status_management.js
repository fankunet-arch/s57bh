/**
 * Toptea HQ - cpsys
 * JavaScript for Product Status Management Page
 * Engineer: Gemini | Date: 2025-10-31
 */
$(document).ready(function() {
    
    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');

    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新状态');
        form[0].reset();
        dataIdInput.val('');
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑状态');
        form[0].reset();
        dataIdInput.val(dataId);

        $.ajax({
            url: 'api/product_status_handler.php',
            type: 'GET',
            data: { action: 'get', id: dataId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const status = response.data;
                    $('#status_code').val(status.status_code);
                    $('#status_name_zh').val(status.status_name_zh);
                    $('#status_name_es').val(status.status_name_es);
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
            status_code: $('#status_code').val(),
            status_name_zh: $('#status_name_zh').val(),
            status_name_es: $('#status_name_es').val(),
        };

        $.ajax({
            url: 'api/product_status_handler.php',
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

        if (confirm(`您确定要删除状态 "${dataName}" 吗？如果该状态正在被产品使用，删除将会失败。`)) {
            $.ajax({
                url: 'api/product_status_handler.php',
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
                error: function(jqXHR) {
                     if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        alert('删除失败: ' + jqXHR.responseJSON.message);
                    } else {
                        alert('删除过程中发生网络或服务器错误。');
                    }
                }
            });
        }
    });
});