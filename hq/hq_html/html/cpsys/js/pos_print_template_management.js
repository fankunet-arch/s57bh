/**
 * TopTea HQ - JavaScript for POS Print Template Management
 * Version: 3.0.0
 * Engineer: Gemini | Date: 2025-10-30
 * Update: Added physical_size field.
 */
$(document).ready(function() {
    const dataDrawer = new bootstrap.Offcanvas(document.getElementById('data-drawer'));
    const form = $('#data-form');
    const drawerLabel = $('#drawer-label');
    const dataIdInput = $('#data-id');

    // Handle 'Create' button click
    $('#create-btn').on('click', function() {
        drawerLabel.text('创建新模板');
        form[0].reset();
        dataIdInput.val('');
        $('#is_active').prop('checked', true);
        $('#physical_size').val('80mm'); // 默认 80mm
        // Pre-fill with a basic structure for convenience
        $('#template_content').val('[\n    {\n        "type": "text",\n        "value": "Your Text Here",\n        "align": "center"\n    }\n]');
    });

    // Handle 'Edit' button click
    $('.table').on('click', '.edit-btn', function() {
        const dataId = $(this).data('id');
        drawerLabel.text('编辑模板');
        form[0].reset();
        dataIdInput.val(dataId);

        $.ajax({
            url: 'api/print_template_handler.php',
            type: 'GET',
            data: { action: 'get', id: dataId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const tpl = response.data;
                    $('#template_name').val(tpl.template_name);
                    $('#template_type').val(tpl.template_type);
                    $('#physical_size').val(tpl.physical_size); // Set physical size
                    $('#is_active').prop('checked', tpl.is_active == 1);
                    // Format JSON for better readability
                    try {
                        const formattedJson = JSON.stringify(JSON.parse(tpl.template_content), null, 4);
                        $('#template_content').val(formattedJson);
                    } catch (e) {
                        $('#template_content').val(tpl.template_content); // Fallback to raw text
                    }
                } else {
                    alert('获取模板数据失败: ' + response.message);
                    dataDrawer.hide();
                }
            },
            error: function() {
                alert('获取模板数据时发生网络错误。');
                dataDrawer.hide();
            }
        });
    });

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();
        
        const contentVal = $('#template_content').val();
        try {
            JSON.parse(contentVal);
        } catch(e) {
            alert('模板内容不是有效的JSON格式，请检查！');
            return;
        }

        const formData = {
            id: dataIdInput.val(),
            template_name: $('#template_name').val(),
            template_type: $('#template_type').val(),
            physical_size: $('#physical_size').val(), // Get physical size
            template_content: contentVal,
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };

        // 验证
        if (!formData.template_type) {
            alert('请选择模板类型！');
            return;
        }
        if (!formData.physical_size) {
            alert('请选择物理尺寸！');
            return;
        }

        $.ajax({
            url: 'api/print_template_handler.php',
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

    // Handle 'Delete' button click
    $('.table').on('click', '.delete-btn', function() {
        const dataId = $(this).data('id');
        const dataName = $(this).data('name');
        if (confirm(`您确定要删除模板 "${dataName}" 吗？此操作不可撤销。`)) {
            $.ajax({
                url: 'api/print_template_handler.php',
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
