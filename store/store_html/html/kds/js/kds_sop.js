/**
 * Toptea Store - KDS
 * SOP Page Logic (Dynamic Recipe Engine Version)
 * Engineer: Gemini | Date: 2025-10-31 | Revision: 8.2 (Categorized Steps & Full Info Display)
 */
$(function() {

    const I18N = {
        'zh-CN': {
            btn_mode_quantity: '用量版', btn_mode_sop: 'SOP版', info_enter_sku: '请先输入编码',
            btn_action_complete: '制茶完成', btn_action_report: '缺料申报', nav_prep: '物料制备',
            nav_expiry: '效期追踪', nav_guide: '制杯指引', step_base: '底料', step_mixing: '调杯',
            step_topping: '顶料', tip_waiting: '请输入饮品编码开始查询', cards_waiting: '等待查询...',
            placeholder_sku: '输入产品编码...', btn_logout: '退出', sop_query_failed: '查询失败',
            label_cup: '杯型', label_ice: '冰量', label_sweetness: '甜度'
        },
        'es-ES': {
            btn_mode_quantity: 'Dosis', btn_mode_sop: 'SOP', info_enter_sku: 'Introduzca el código',
            btn_action_complete: 'Completado', btn_action_report: 'Reportar Falta', nav_prep: 'Preparación',
            nav_expiry: 'Seguimiento', nav_guide: 'Guía de Preparación', step_base: 'Base', step_mixing: 'Mezcla',
            step_topping: 'Topping', tip_waiting: 'Introduzca el código del producto para buscar', cards_waiting: 'Esperando búsqueda...',
            placeholder_sku: 'Introduzca el código...', btn_logout: 'Salir', sop_query_failed: 'Error de Búsqueda',
            label_cup: 'Vaso', label_ice: 'Hielo', label_sweetness: 'Azúcar'
        }
    };

    let currentRecipeData = null;
    let currentOptions = null;
    let selectedOptions = { p_code: null, a_code: null, m_code: null, t_code: null };
    let currentLang = localStorage.getItem("kds_lang") || "zh-CN";
    
    const $productInfoArea = $('#product-info-area');
    const $infoDisplayContainer = $('#kds-info-display-container');
    const $optionsContainer = $('#dynamic-options-container');
    const $stepTip = $('#kds-step-tip');
    const $cardsContainer = $('#kds-cards-container');
    const $skuInput = $('#sku-input');
    const $searchForm = $('#sku-search-form');

    function fetchSopData(code) {
        $searchForm.find('button').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        $.ajax({
            url: 'api/sop_handler.php',
            type: 'GET',
            data: { code: code },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;
                    if (data.type === 'base_info') {
                        currentRecipeData = data;
                        currentOptions = data.options;
                        selectedOptions.p_code = data.product.product_code;
                        selectedOptions.a_code = data.options.cups[0]?.cup_code || null;
                        selectedOptions.m_code = data.options.ice_options[0]?.ice_code || null;
                        selectedOptions.t_code = data.options.sweetness_options[0]?.sweetness_code || null;
                        renderOptionSelectors();
                    } else if (data.type === 'adjusted_recipe') {
                        currentRecipeData.recipe = data.recipe;
                        currentRecipeData.product = { ...currentRecipeData.product, ...data.product };
                    }
                    renderAll();
                } else {
                     handleApiError(response.message);
                }
            },
            error: function(jqXHR) {
                const errorMsg = jqXHR.responseJSON?.message || '查询失败，请检查网络或编码是否正确。';
                handleApiError(errorMsg);
            },
            complete: function() {
                $searchForm.find('button').prop('disabled', false).html('<i class="bi bi-search"></i>');
                $skuInput.focus().select();
            }
        });
    }
    
    function handleApiError(message) {
        currentRecipeData = null;
        currentOptions = null;
        renderAll();
        const translations = I18N[currentLang] || I18N['zh-CN'];
        showKdsAlert(`${translations.sop_query_failed}: ${message}`, true);
    }

    function renderProductInfo() {
        if (!currentRecipeData) {
            $productInfoArea.find('.kds-cup-number').html('---').addClass('text-muted');
            $productInfoArea.find('h3').html(I18N[currentLang].info_enter_sku).addClass('text-muted');
            $infoDisplayContainer.html(`<div class="kds-info-display text-muted">--</div>`);
            $('.btn-touch-action').prop('disabled', true);
            return;
        }
        const product = currentRecipeData.product;
        const langKey = currentLang.substring(0, 2);
        const productName = product[`name_${langKey}`] || product.name_zh;

        const cupName = product.cup_name || '--';
        const statusName = product.status_name_zh || '--';
        const iceName = product.ice_name_zh || '--';
        const sweetnessName = product.sweetness_name_zh || '--';

        $productInfoArea.find('.kds-cup-number').html(product.product_code).removeClass('text-muted');
        $productInfoArea.find('h3').html(productName).removeClass('text-muted');
        $infoDisplayContainer.html(`
            <div class="kds-info-display">${cupName} / ${statusName}</div>
            <div class="kds-info-display">${iceName} / ${sweetnessName}</div>
        `);
        $('.btn-touch-action').prop('disabled', false);
    }

    function renderOptionSelectors() {
        $optionsContainer.empty();
        if (!currentOptions) return;
        const translations = I18N[currentLang] || I18N['zh-CN'];
        const createGroup = (label, type, options, valueField, nameField) => {
            if (!options || options.length === 0) return '';
            let buttons = '';
            options.forEach(opt => {
                const isActive = selectedOptions[type] == opt[valueField];
                buttons += `<button class="btn ${isActive ? 'btn-primary' : ''} option-btn" data-type="${type}" data-code="${opt[valueField]}">${opt[nameField]}</button>`;
            });
            return `<div class="mb-3"><label class="form-label small fw-bold text-muted">${label}</label><div class="d-flex flex-wrap gap-2">${buttons}</div></div>`;
        };
        $optionsContainer.append(createGroup(translations.label_cup, 'a_code', currentOptions.cups, 'cup_code', 'cup_name'));
        $optionsContainer.append(createGroup(translations.label_ice, 'm_code', currentOptions.ice_options, 'ice_code', 'name_zh'));
        $optionsContainer.append(createGroup(translations.label_sweetness, 't_code', currentOptions.sweetness_options, 'sweetness_code', 'name_zh'));
    }

    function renderStepCards() {
        // Hide all containers first
        $('.step-cards').hide();
        $('#cards-waiting').show();
        $('.kds-steps').hide();

        if (!currentRecipeData || !currentRecipeData.recipe) {
            return;
        }

        $('.kds-steps').show();
        $('#cards-waiting').hide();
        $('.step-cards').empty(); // Clear all three containers

        currentRecipeData.recipe.forEach((item, index) => {
            const title = item[`material_${currentLang.substring(0,2)}`] || item.material_zh;
            const unit = item[`unit_${currentLang.substring(0,2)}`] || item.unit_zh;
            const cardHtml = `<div class="col-12 col-md-6 col-xl-4"><div class="kds-ingredient-card"><span class="badge bg-success position-absolute top-0 start-0 m-3">${index + 1}</span><div class="bg-secondary rounded mx-auto mb-2" style="width:80px;height:80px; background-image: url(images/placeholder.png); background-size: cover;"></div><h5 class="fw-bold mb-0">${title}</h5><div class="kds-quantity">${item.quantity}</div><div class="kds-unit-measure">${unit}</div></div></div>`;
            
            // Append card to the correct container based on its category
            const targetContainer = `#cards-${item.step_category}`;
            if ($(targetContainer).length) {
                $(targetContainer).append(cardHtml);
            } else {
                console.warn(`Container for step category "${item.step_category}" not found.`);
                $('#cards-base').append(cardHtml); // Fallback to base
            }
        });

        // Show the currently active tab's container
        const activeStep = $('.kds-step-tab.active').data('step') || 'base';
        $(`#cards-${activeStep}`).show();
    }

    function renderAll() {
        renderProductInfo();
        renderStepCards();
    }

    $searchForm.on('submit', function(e) {
        e.preventDefault();
        const code = $skuInput.val().trim().toUpperCase();
        if (code) {
            $optionsContainer.empty();
            fetchSopData(code);
        }
    });

    $optionsContainer.on('click', '.option-btn', function() {
        const type = $(this).data('type');
        const code = $(this).data('code');
        selectedOptions[type] = code;
        renderOptionSelectors();
        const fullCode = [selectedOptions.p_code, selectedOptions.a_code, selectedOptions.m_code, selectedOptions.t_code].filter(Boolean).join('-');
        fetchSopData(fullCode);
    });
    
    $('.kds-step-tab').on('click', function() {
        $('.kds-step-tab').removeClass('active');
        $(this).addClass('active');
        // Hide all card containers, then show the one for the clicked tab
        $('.step-cards').hide();
        const stepToShow = $(this).data('step');
        $(`#cards-${stepToShow}`).show();
    });

    function applyLang(lang) {
      currentLang = lang;
      localStorage.setItem("kds_lang", lang);
      const translations = I18N[currentLang] || I18N['zh-CN'];
      $('[data-i18n-key]').each(function() {
          const key = $(this).data('i18n-key');
          if (translations[key]) {
              if ($(this).is('input[placeholder]')) { $(this).attr('placeholder', translations[key]); } 
              else { $(this).text(translations[key]); }
          }
      });
      renderAll();
      renderOptionSelectors();
    }

    $('.lang-switch').on('click', '.lang-flag', function() { applyLang($(this).data('lang')); });

    function startClock() { function tick() { $('#kds-clock').text(new Date().toLocaleTimeString('zh-CN', { hour12: false })); } tick(); setInterval(tick, 1000); }
    
    startClock();
    applyLang(currentLang);
    $('#cp-year').text(new Date().getFullYear());
    $skuInput.focus();
});