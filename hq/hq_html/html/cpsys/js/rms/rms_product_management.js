/**
 * Toptea HQ - RMS (Recipe Management System) JavaScript
 * Engineer: Gemini | Date: 2025-10-31
 * Revision: 5.1 (Fixed dynamic button events)
 */
$(document).ready(function() {

    const productListContainer = $('#product-list-container');
    const editorContainer = $('#product-editor-container');
    const templatesContainer = $('#rms-templates');

    // --- DELEGATED EVENT HANDLERS ---

    productListContainer.on('click', '.list-group-item-action', function(e) {
        e.preventDefault();
        productListContainer.find('.list-group-item-action').removeClass('active');
        $(this).addClass('active');
        loadProductEditor($(this).data('productId'));
    });

    $('#btn-add-product').on('click', function() {
        productListContainer.find('.list-group-item-action').removeClass('active');
        renderProductEditor(null); // Render empty editor first
        // Then, fetch and populate the next available product code
        $.ajax({
            url: 'api/rms/product_handler.php', type: 'GET', data: { action: 'get_next_product_code' }, dataType: 'json',
            success: response => {
                if (response.status === 'success') {
                    $('#product_code').val(response.data.next_code);
                }
            }
        });
    });

    editorContainer.on('submit', '#product-form', function(e) { e.preventDefault(); saveProduct(); });

    editorContainer.on('click', '#btn-delete-product', function() {
        const productId = $('#product-id').val();
        if (!productId) { alert('这是一个新产品，尚未保存，无法删除。'); return; }
        if (confirm('您确定要删除这个产品及其所有配方吗？此操作不可撤销。')) {
            deleteProduct(productId);
        }
    });

    editorContainer.on('click', '#btn-add-base-recipe-row', () => addRecipeRow('#base-recipe-body'));
    editorContainer.on('click', '#btn-add-adjustment-rule', () => addAdjustmentRule());
    editorContainer.on('click', '.btn-remove-adjustment-rule', function() {
        $(this).closest('.adjustment-rule-card').remove();
        if ($('.adjustment-rule-card').length === 0) { $('#no-adjustments-placeholder').show(); }
    });
    editorContainer.on('click', '.btn-add-adjustment-recipe-row', function() {
        const targetBody = $(this).closest('.card-body').find('.adjustment-recipe-body');
        addRecipeRow(targetBody);
    });
    editorContainer.on('click', '.btn-remove-row', function() { $(this).closest('tr').remove(); });

    // --- CORE FUNCTIONS ---

    function loadProductEditor(productId) {
        editorContainer.html('<div class="card-body text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        $.ajax({
            url: 'api/rms/product_handler.php', type: 'GET', data: { action: 'get_product_details', id: productId }, dataType: 'json',
            success: response => {
                if (response.status === 'success') { renderProductEditor(response.data); } 
                else { editorContainer.html(`<div class="alert alert-danger">${response.message}</div>`); }
            },
            error: () => editorContainer.html('<div class="alert alert-danger">加载产品数据时发生网络错误。</div>')
        });
    }

    function renderProductEditor(data) {
        editorContainer.html(templatesContainer.find('#editor-template').html());

        if (data) {
            $('#editor-title').text(`${data.product_code} - ${data.name_zh}`);
            $('#product-id').val(data.id);
            $('#product_code').val(data.product_code);
            $('#name_zh').val(data.name_zh);
            $('#name_es').val(data.name_es);
            $('#status_id').val(data.status_id);

            const baseRecipeBody = $('#base-recipe-body');
            if(data.base_recipes && data.base_recipes.length > 0){
                data.base_recipes.forEach(recipe => addRecipeRow(baseRecipeBody, recipe));
            } else {
                baseRecipeBody.html('<tr><td colspan="4" class="text-center text-muted">暂无基础配方步骤。</td></tr>');
            }
            
            if(data.adjustments && data.adjustments.length > 0) {
                $('#no-adjustments-placeholder').hide();
                data.adjustments.forEach(ruleGroup => addAdjustmentRule(ruleGroup));
            }
        } else {
            $('#editor-title').text('新产品');
            $('#btn-delete-product').hide();
        }
    }

    function addRecipeRow(targetBody, data = null) {
        if ($(targetBody).find('td[colspan]').length) $(targetBody).empty();
        // **FIXED**: Use .clone() to copy the entire <tr> element with its structure and events.
        const $newRow = templatesContainer.find('#recipe-row-template').clone().removeAttr('id');
        if (data) {
            $newRow.find('.material-select').val(data.material_id);
            $newRow.find('.quantity-input').val(data.quantity);
            $newRow.find('.unit-select').val(data.unit_id);
        }
        $(targetBody).append($newRow);
    }

    function addAdjustmentRule(ruleGroup = null) {
        $('#no-adjustments-placeholder').hide();
        // **FIXED**: Use .clone() to copy the entire card element correctly.
        const $newRule = templatesContainer.find('#adjustment-rule-template').clone().removeAttr('id');
        
        if (ruleGroup) {
            $newRule.find('.cup-condition').val(ruleGroup.cup_id);
            $newRule.find('.sweetness-condition').val(ruleGroup.sweetness_option_id);
            $newRule.find('.ice-condition').val(ruleGroup.ice_option_id);
            
            const recipeBody = $newRule.find('.adjustment-recipe-body');
            if(ruleGroup.overrides && ruleGroup.overrides.length > 0){
                 ruleGroup.overrides.forEach(override => addRecipeRow(recipeBody, override));
            }
        }
        $('#adjustments-body').append($newRule);
    }

    function saveProduct() {
        const productData = {
            id: $('#product-id').val() || null,
            product_code: $('#product_code').val(),
            name_zh: $('#name_zh').val(),
            name_es: $('#name_es').val(),
            status_id: $('#status_id').val(),
            base_recipes: [],
            adjustments: []
        };

        $('#base-recipe-body tr').each(function() {
            const row = $(this);
            if (row.find('td[colspan]').length) return;
            productData.base_recipes.push({
                material_id: row.find('.material-select').val(),
                quantity: row.find('.quantity-input').val(),
                unit_id: row.find('.unit-select').val()
            });
        });

        $('.adjustment-rule-card').each(function() {
            const card = $(this);
            const cup_id = card.find('.cup-condition').val() || null;
            const sweetness_option_id = card.find('.sweetness-condition').val() || null;
            const ice_option_id = card.find('.ice-condition').val() || null;
            card.find('.adjustment-recipe-body tr').each(function() {
                const row = $(this);
                if (row.find('td[colspan]').length) return;
                 productData.adjustments.push({
                    cup_id: cup_id,
                    sweetness_option_id: sweetness_option_id,
                    ice_option_id: ice_option_id,
                    material_id: row.find('.material-select').val(),
                    quantity: row.find('.quantity-input').val(),
                    unit_id: row.find('.unit-select').val()
                });
            });
        });

        $.ajax({
            url: 'api/rms/product_handler.php', type: 'POST', contentType: 'application/json', data: JSON.stringify({ action: 'save_product', product: productData }), dataType: 'json',
            success: response => {
                if (response.status === 'success') { alert(response.message); window.location.reload(); } 
                else { alert('保存失败: ' + response.message); }
            },
            error: (jqXHR) => {
                const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '保存过程中发生网络或服务器错误。';
                alert(`操作失败: ${errorMsg}`);
            }
        });
    }

    function deleteProduct(productId) {
        $.ajax({
            url: 'api/rms/product_handler.php', type: 'POST', contentType: 'application/json', data: JSON.stringify({ action: 'delete_product', id: productId }), dataType: 'json',
            success: response => {
                if (response.status === 'success') { alert(response.message); window.location.reload(); } 
                else { alert('删除失败: ' + response.message); }
            },
            error: () => alert('删除过程中发生网络错误。')
        });
    }
});