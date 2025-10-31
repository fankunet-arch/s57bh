/**
 * Toptea Store - KDS
 * Login Page Logic (Bilingual)
 * Engineer: Gemini | Date: 2025-10-23
 */
$(function() {
    const I18N = {
        'zh-CN': {
            title_sub: '制茶助手',
            label_store_code: '门店码',
            label_username: '用户名',
            label_password: '密码',
            btn_login: '登 录',
            error_invalid_credentials: '无效的用户名、密码或门店码。',
        },
        'es-ES': {
            title_sub: 'Asistente de Té',
            label_store_code: 'Código de Tienda',
            label_username: 'Usuario',
            label_password: 'Contraseña',
            btn_login: 'Iniciar Sesión',
            error_invalid_credentials: 'Usuario, contraseña o código de tienda no válido.',
        }
    };

    function applyLang(lang) {
      localStorage.setItem("kds_lang", lang);
      $('.lang-flag').removeClass('active').filter(`[data-lang="${lang}"]`).addClass('active');
      const translations = I18N[lang] || I18N['zh-CN'];
      $('[data-i18n-key]').each(function() {
          const key = $(this).data('i18n-key');
          if (translations[key]) {
              $(this).text(translations[key]);
          }
      });
    }

    $('.lang-flag').on('click', function() {
        applyLang($(this).data('lang'));
    });

    // Initialize with saved or default language
    const savedLang = localStorage.getItem("kds_lang") || "zh-CN";
    applyLang(savedLang);
});