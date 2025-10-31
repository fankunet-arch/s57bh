/**
 * Toptea HQ - JavaScript for POS Member Settings Page
 * Engineer: Gemini | Date: 2025-10-28
 */
$(document).ready(function() {
    const form = $('#member-settings-form');
    const feedbackDiv = $('#settings-feedback');
    const eurosPerPointInput = $('#euros_per_point');

    // Function to load settings
    function loadSettings() {
        feedbackDiv.html('<div class="spinner-border spinner-border-sm text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>');
        $.ajax({
            url: 'api/pos_settings_handler.php',
            type: 'GET',
            data: { action: 'load' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Ensure the key exists and has a value, otherwise default
                    const valueFromServer = response.data?.points_euros_per_point;
                    eurosPerPointInput.val(valueFromServer || '1.00'); 
                    feedbackDiv.empty(); // Clear loading indicator
                } else {
                    feedbackDiv.html(`<div class="alert alert-danger">加载设置失败: ${response.message || '未知错误'}</div>`);
                }
            },
            error: function(jqXHR) {
                 const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '加载设置时发生网络错误。';
                 feedbackDiv.html(`<div class="alert alert-danger">${errorMsg}</div>`);
            }
        });
    }

    // Function to save settings
    form.on('submit', function(e) {
        e.preventDefault();
        feedbackDiv.html('<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Saving...</span></div>');
        
        // Disable button during save
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true);

        const settingsData = {
            points_euros_per_point: eurosPerPointInput.val()
            // Add other settings here if needed in the future
        };

        $.ajax({
            url: 'api/pos_settings_handler.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'save', settings: settingsData }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    feedbackDiv.html('<div class="alert alert-success">设置已成功保存！</div>');
                     setTimeout(() => feedbackDiv.empty(), 3000); // Clear feedback after 3s
                } else {
                    feedbackDiv.html(`<div class="alert alert-danger">保存失败: ${response.message || '未知错误'}</div>`);
                }
            },
            error: function(jqXHR) {
                const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : '保存过程中发生网络或服务器错误。';
                feedbackDiv.html(`<div class="alert alert-danger">操作失败: ${errorMsg}</div>`);
            },
            complete: function() {
                 // Re-enable button after request completion
                 submitButton.prop('disabled', false);
                 if (!feedbackDiv.find('.alert-success').length) {
                     setTimeout(() => feedbackDiv.empty(), 5000);
                 }
            }
        });
    });

    // Initial load
    loadSettings();
});

