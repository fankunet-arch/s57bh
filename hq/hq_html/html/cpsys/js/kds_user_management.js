/**
 * Toptea HQ - cpsys
 * JavaScript for KDS User Management Page
 * Engineer: Gemini | Date: 2025-10-24
 */
$(document).ready(function() {
    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');
    const usernameInput = $('#username');

    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新 KDS 账户');
        form[0].reset();
        dataIdInput.val('');
        usernameInput.prop('readonly', false);
        $('#is_active').prop('checked', true);
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑 KDS 账户');
        form[0].reset();
        dataIdInput.val(dataId);
        usernameInput.prop('readonly', true);
        $.ajax({
            url: 'api/kds_user_handler.php', type: 'GET', data: { action: 'get', id: dataId }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const user = response.data;
                    usernameInput.val(user.username);
                    $('#display_name').val(user.display_name);
                    $('#is_active').prop('checked', user.is_active == 1);
                } else { alert('获取数据失败: ' + response.message); dataDrawer.hide(); }
            },
            error: function() { alert('获取数据时发生网络错误。'); dataDrawer.hide(); }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        if ($('#password').val() !== $('#password_confirm').val()) {
            alert('两次输入的密码不匹配！');
            return;
        }
        const formData = {
            id: dataIdInput.val(),
            store_id: $('#store-id').val(), // Important: pass the store context
            username: usernameInput.val(),
            display_name: $('#display_name').val(),
            password: $('#password').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };
        $.ajax({
            url: 'api/kds_user_handler.php', type: 'POST', contentType: 'application/json',
            data: JSON.stringify({ action: 'save', data: formData }), dataType: 'json',
            success: function(response) { if (response.status === 'success') { alert(response.message); window.location.reload(); } else { alert('保存失败: ' + (response.message || '未知错误')); } },
            error: function(jqXHR) { if (jqXHR.responseJSON && jqXHR.responseJSON.message) { alert('操作失败: ' + jqXHR.responseJSON.message); } else { alert('保存过程中发生网络或服务器错误。'); } }
        });
    });
    
    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除 KDS 用户 "${dataName}" 吗？`)) {
            $.ajax({
                url: 'api/kds_user_handler.php', type: 'POST', contentType: 'application/json',
                data: JSON.stringify({ action: 'delete', id: dataId }), dataType: 'json',
                success: function(response) { if (response.status === 'success') { alert(response.message); window.location.reload(); } else { alert('删除失败: ' + response.message); } },
                error: function() { alert('删除过程中发生网络或服务器错误。'); }
            });
        }
    });
});