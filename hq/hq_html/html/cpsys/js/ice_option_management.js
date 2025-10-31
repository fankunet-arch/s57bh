/**
 * Toptea HQ - cpsys
 * JavaScript for Ice Option Management Page (Bilingual Template)
 *
 * Engineer: Gemini
 * Date: 2025-10-25
 * Revision: 6.5 (SOP Refactor)
 */
$(document).ready(function() {
    
    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');
    const dataCodeInput = $('#data-code');
    const dataNameZhInput = $('#data-name-zh');
    const dataNameEsInput = $('#data-name-es');
    const dataSopZhInput = $('#data-sop-zh');
    const dataSopEsInput = $('#data-sop-es');

    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新冰量选项');
        form[0].reset();
        dataIdInput.val('');
        $.ajax({
            url: 'api/ice_option_handler.php', type: 'GET', data: { action: 'get_next_code' }, dataType: 'json',
            success: function(response) { if (response.status === 'success') { dataCodeInput.val(response.data.next_code); } }
        });
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑冰量选项');
        form[0].reset();
        dataIdInput.val(dataId);
        $.ajax({
            url: 'api/ice_option_handler.php', type: 'GET', data: { action: 'get', id: dataId }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;
                    dataCodeInput.val(data.ice_code); 
                    dataNameZhInput.val(data.name_zh);
                    dataNameEsInput.val(data.name_es);
                    dataSopZhInput.val(data.sop_zh);
                    dataSopEsInput.val(data.sop_es);
                } else { alert('获取数据失败: ' + response.message); dataDrawer.hide(); }
            },
            error: function() { alert('获取数据时发生网络错误。'); dataDrawer.hide(); }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        const formData = { 
            id: dataIdInput.val(), 
            code: dataCodeInput.val(), 
            name_zh: dataNameZhInput.val(),
            name_es: dataNameEsInput.val(),
            sop_zh: dataSopZhInput.val(),
            sop_es: dataSopEsInput.val()
        };
        $.ajax({
            url: 'api/ice_option_handler.php', type: 'POST', contentType: 'application/json',
            data: JSON.stringify({ action: 'save', data: formData }), dataType: 'json',
            success: function(response) { if (response.status === 'success') { alert(response.message); window.location.reload(); } else { alert('保存失败: ' + (response.message || '未知错误')); } },
            error: function(jqXHR) { if (jqXHR.responseJSON && jqXHR.responseJSON.message) { alert('操作失败: ' + jqXHR.responseJSON.message); } else { alert('保存过程中发生网络或服务器错误。'); } }
        });
    });
    
    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除 "${dataName}" 吗？`)) {
            $.ajax({
                url: 'api/ice_option_handler.php', type: 'POST', contentType: 'application/json',
                data: JSON.stringify({ action: 'delete', id: dataId }), dataType: 'json',
                success: function(response) { if (response.status === 'success') { alert(response.message); window.location.reload(); } else { alert('删除失败: ' + response.message); } },
                error: function() { alert('删除过程中发生网络或服务器错误。'); }
            });
        }
    });
});