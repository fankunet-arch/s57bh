/**
 * Toptea HQ - cpsys
 * JavaScript for Warehouse Stock Management Page
 *
 * Engineer: Gemini
 * Date: 2025-10-26
 * Revision: 7.9 (Final Review & Polish)
 */
$(document).ready(function() {

    const addStockModal = new bootstrap.Modal(document.getElementById('add-stock-modal'));
    const form = $('#add-stock-form');
    const materialIdInput = $('#material-id-input');
    const materialNameDisplay = $('#material-name-display');
    const quantityInput = $('#quantity-input');
    const unitSelect = $('#unit-id-select');

    $('.add-stock-btn').on('click', function() {
        const materialId = $(this).data('material-id');
        const materialName = $(this).data('material-name');

        form[0].reset();
        materialIdInput.val(materialId);
        materialNameDisplay.val(materialName);
        unitSelect.empty().append('<option value="" selected disabled>加载中...</option>');

        $.ajax({
            url: 'api/material_handler.php',
            type: 'GET',
            data: { action: 'get', id: materialId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const material = response.data;
                    unitSelect.empty();
                    if (material.base_unit_id && material.base_unit_name) {
                        unitSelect.append(`<option value="${material.base_unit_id}">${material.base_unit_name}</option>`);
                    }
                    if (material.large_unit_id && material.large_unit_name) {
                        unitSelect.append(`<option value="${material.large_unit_id}">${material.large_unit_name}</option>`);
                    }
                } else {
                     alert('获取物料单位信息失败: ' + response.message);
                     addStockModal.hide();
                }
            },
            error: function() {
                alert('获取物料单位信息时发生网络错误。');
                addStockModal.hide();
            }
        });
    });

    form.on('submit', function(e) {
        e.preventDefault();

        const stockData = {
            material_id: materialIdInput.val(),
            quantity: quantityInput.val(),
            unit_id: unitSelect.val()
        };

        $.ajax({
            url: 'api/stock_handler.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'add_warehouse_stock', data: stockData }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload();
                } else {
                    alert('入库失败: ' + (response.message || '未知错误'));
                }
            },
            error: function(jqXHR) {
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    alert('操作失败: ' + jqXHR.responseJSON.message);
                } else {
                    alert('入库过程中发生网络或服务器错误。');
                }
            }
        });
    });
});