/**
 * Toptea HQ - cpsys
 * JavaScript for Store Management Page
 * Engineer: Gemini | Date: 2025-10-26 | Revision: 2.1 (Full Code)
 */
$(document).ready(function() {
    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');

    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新门店');
        form[0].reset();
        dataIdInput.val('');
        $('#is_active').prop('checked', true);
        $('#default_vat_rate').val('10.00');
        $('#invoice_number_offset').val('10000');
        $('#eod_cutoff_hour').val('3'); // Default value for new stores
    });

    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑门店');
        form[0].reset();
        dataIdInput.val(dataId);
        $.ajax({
            url: 'api/store_handler.php', type: 'GET', data: { action: 'get', id: dataId }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const store = response.data;
                    $('#store_code').val(store.store_code);
                    $('#store_name').val(store.store_name);
                    $('#tax_id').val(store.tax_id);
                    $('#billing_system').val(store.billing_system);
                    $('#default_vat_rate').val(store.default_vat_rate);
                    $('#invoice_number_offset').val(store.invoice_number_offset);
                    $('#eod_cutoff_hour').val(store.eod_cutoff_hour); // Load value
                    $('#store_city').val(store.store_city);
                    $('#is_active').prop('checked', store.is_active == 1);
                } else { alert('获取数据失败: ' + response.message); dataDrawer.hide(); }
            },
            error: function() { alert('获取数据时发生网络错误。'); dataDrawer.hide(); }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();
        const formData = {
            id: dataIdInput.val(),
            store_code: $('#store_code').val(),
            store_name: $('#store_name').val(),
            tax_id: $('#tax_id').val(),
            billing_system: $('#billing_system').val(),
            default_vat_rate: $('#default_vat_rate').val(),
            invoice_number_offset: $('#invoice_number_offset').val(),
            eod_cutoff_hour: $('#eod_cutoff_hour').val(), // Save value
            store_city: $('#store_city').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };
        $.ajax({
            url: 'api/store_handler.php', type: 'POST', contentType: 'application/json',
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
        if (confirm(`您确定要删除门店 "${dataName}" 吗？`)) {
            $.ajax({
                url: 'api/store_handler.php',
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