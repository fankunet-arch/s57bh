/**
 * Toptea HQ - cpsys
 * JavaScript for User Profile Page
 * Engineer: Gemini | Date: 2025-10-23
 */
$(document).ready(function() {
    $('#profile-form').on('submit', function(e) {
        e.preventDefault();

        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();

        if (newPassword !== confirmPassword) {
            alert('新密码和确认密码不匹配！');
            return;
        }

        const formData = {
            display_name: $('#display_name').val(),
            email: $('#email').val(),
            current_password: $('#current_password').val(),
            new_password: newPassword
        };

        $.ajax({
            url: 'api/profile_handler.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    window.location.reload(); // Reload to see changes reflected in header
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
});