/**
 * Toptea HQ - cpsys
 * JavaScript for Unit Management Page (Bilingual Template)
 *
 * Engineer: Gemini
 * Date: 2025-10-23
 */
$(document).ready(function() {
    
    const unitDrawer = new bootstrap.Offcanvas(document.getElementById('unit-drawer'));
    const form = $('#unit-form');
    const drawerLabel = $('#drawer-label');
    const unitIdInput = $('#unit-id');
    const unitCodeInput = $('#unit-code');
    const unitNameZhInput = $('#unit-name-zh');
    const unitNameEsInput = $('#unit-name-es');

    $('#create-unit-btn').on('click', function() {
        drawerLabel.text('创建新单位');
        form[0].reset();
        unitIdInput.val('');
        $.ajax({
            url: 'api/unit_handler.php', type: 'GET', data: { action: 'get_next_code' }, dataType: 'json',
            success: function(response) { if (response.status === 'success') { unitCodeInput.val(response.data.next_code); } }
        });
    });

    $('.table').on('click', '.edit-unit-btn', function() {
        const unitId = $(this).data('unit-id');
        drawerLabel.text('编辑单位');
        form[0].reset();
        unitIdInput.val(unitId);
        $.ajax({
            url: 'api/unit_handler.php', type: 'GET', data: { action: 'get', id: unitId }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    unitCodeInput.val(response.data.unit_code);
                    unitNameZhInput.val(response.data.name_zh);
                    unitNameEsInput.val(response.data.name_es);
                } else { alert('获取单位数据失败: ' + response.message); unitDrawer.hide(); }
            },
            error: function() { alert('获取单位数据时发生网络错误。'); unitDrawer.hide(); }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        const unitData = { id: unitIdInput.val(), unit_code: unitCodeInput.val(), name_zh: unitNameZhInput.val(), name_es: unitNameEsInput.val() };
        $.ajax({
            url: 'api/unit_handler.php', type: 'POST', contentType: 'application/json',
            data: JSON.stringify({ action: 'save', data: unitData }), dataType: 'json',
            success: function(response) { if (response.status === 'success') { alert(response.message); window.location.reload(); } else { alert('保存失败: ' + (response.message || '未知错误')); } },
            error: function(jqXHR) { if (jqXHR.responseJSON && jqXHR.responseJSON.message) { alert('操作失败: ' + jqXHR.responseJSON.message); } else { alert('保存过程中发生网络或服务器错误。'); } }
        });
    });
    
    $('.table').on('click', '.delete-unit-btn', function() {
        const unitId = $(this).data('unit-id');
        const unitName = $(this).data('unit-name');
        if (confirm(`您确定要删除单位 "${unitName}" 吗？`)) {
            $.ajax({
                url: 'api/unit_handler.php', type: 'POST', contentType: 'application/json',
                data: JSON.stringify({ action: 'delete', id: unitId }), dataType: 'json',
                success: function(response) { if (response.status === 'success') { alert(response.message); window.location.reload(); } else { alert('删除失败: ' + response.message); } },
                error: function() { alert('删除过程中发生网络或服务器错误。'); }
            });
        }
    });
});