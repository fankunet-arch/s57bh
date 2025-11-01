/* TopTea · KDS — SOP 兜底绑定器（与主脚本同风格渲染）
 * 仅当页面还加载本文件时，提供与 kds_sop.js 一致的 UI 行为与渲染。
 */
(function () {
  if (window.__KDS_SOP_FALLBACK__) return;
  window.__KDS_SOP_FALLBACK__ = true;

  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
  function lg(){const l=(document.documentElement.getAttribute('lang')||'').toLowerCase();return l.startsWith('es')?'es':'zh';}
  function pick(zh,es){return lg()==='es'?(es||zh):(zh||es);}

  const $input = $("#sku-input, #kds_code_input").first();
  const $form  = $("#sku-search-form");

  const $tip   = $("#kds-step-tip, .sop-tip, [data-role='sop-tip']").first()
                 .text(lg()==='es'?'Haz bien cada paso: mejoran la textura y la calidad.':'每步动作做到位，口感品质才会好。');

  const $codeTargets = $("#kds_code_big, #kds_product_code, .kds-sku-big, [data-role='product-code']");
  const $nameTargets = $("#kds_product_title, #kds_product_name, .kds-name-big, [data-role='product-name']");

  const $line1 = $("#kds_line1, .kds-line1, #kds_overview_line1").first();
  const $line2 = $("#kds_line2, .kds-line2, #kds_overview_line2").first();

  const $wrapBase = $("#cards-base"), $wrapMix = $("#cards-mixing"), $wrapTop = $("#cards-topping");
  const $waiting = $("#cards-waiting");

  const $tabBase = $("#tab-base, .kds-step-tab[data-step='base']").first();
  const $tabMix  = $("#tab-mixing, .kds-step-tab[data-step='mixing']").first();
  const $tabTop  = $("#tab-topping, .kds-step-tab[data-step='topping']").first();

  function normalizeCat(cat){
    const s=String(cat||'').toLowerCase();
    if(s.startsWith('mix')||s.includes('调')) return 'mixing';
    if(s.startsWith('top')||s.includes('顶')) return 'topping';
    return 'base';
  }
  function card(i,n,q,u){
    return `
    <div class="col-xxl-6 col-xl-6 col-lg-12 col-md-12">
      <div class="kds-ingredient-card">
        <div class="step-number" style="position:absolute;left:16px;top:16px;background:#16a34a;color:#fff;width:28px;height:28px;border-radius:999px;display:flex;align-items:center;justify-content:center;font-weight:900;">${i}</div>
        <div class="kds-card-thumb" style="width:140px;height:140px;background:#6b7280;border-radius:.8rem;margin:56px auto 8px auto;"></div>
        <div class="text-center" style="font-size:1.6rem;font-weight:900;letter-spacing:.6px;">${esc(n)}</div>
        <div class="kds-quantity text-center">${esc(q)}</div>
        <div class="kds-unit-measure text-center">${esc(u)}</div>
      </div>
    </div>`;
  }
  function showTab(step){
    $(".kds-step-tab").removeClass("active");
    $(`.kds-step-tab[data-step='${step}']`).addClass("active");
    $wrapBase.addClass("d-none"); $wrapMix.addClass("d-none"); $wrapTop.addClass("d-none");
    if(step==='base') $wrapBase.removeClass("d-none");
    if(step==='mixing') $wrapMix.removeClass("d-none");
    if(step==='topping') $wrapTop.removeClass("d-none");
  }
  function bindTabs(){
    if($tabBase.length&&!$tabBase.data('step')) $tabBase.attr('data-step','base').addClass('kds-step-tab');
    if($tabMix.length &&!$tabMix.data('step'))  $tabMix.attr('data-step','mixing').addClass('kds-step-tab');
    if($tabTop.length &&!$tabTop.data('step'))  $tabTop.attr('data-step','topping').addClass('kds-step-tab');
    $(document).off('click.kdsStep','.kds-step-tab').on('click.kdsStep','.kds-step-tab',function(e){
      e.preventDefault(); showTab($(this).data('step'));
    });
  }

  function render(data){
    const p=data.product||{}, arr=data.recipe||[];
    if($codeTargets.length && p.product_code) $codeTargets.text(p.product_code);
    if($nameTargets.length) $nameTargets.text(pick(p.name_zh,p.name_es)||pick(p.title_zh,p.title_es)||'');

    const status=pick(p.status_name_zh,p.status_name_es)||'';
    if($line1.length){ if(status){$line1.text(status).show();}else{$line1.hide();} }
    const ice=pick(p.ice_name_zh,p.ice_name_es)||'', swt=pick(p.sweetness_name_zh,p.sweetness_name_es)||'';
    const parts=[]; if(ice)parts.push(ice); if(swt)parts.push(swt);
    if($line2.length){ if(parts.length){$line2.text(parts.join(' / ')).show();}else{$line2.hide();} }

    $wrapBase.empty(); $wrapMix.empty(); $wrapTop.empty();
    const isEs = lg()==='es';
    const gp={base:[],mixing:[],topping:[]};
    arr.forEach(r=>gp[normalizeCat(r.step_category)].push(r));

    let i=1; gp.base.forEach(r=>{$wrapBase.append(card(i++, isEs?(r.material_es||r.material_zh||'--'):(r.material_zh||r.material_es||'--'), String(r.quantity??''), isEs?(r.unit_es||r.unit_zh||''):(r.unit_zh||r.unit_es||'')));});
    i=1; gp.mixing.forEach(r=>{$wrapMix.append(card(i++, isEs?(r.material_es||r.material_zh||'--'):(r.material_zh||r.material_es||'--'), String(r.quantity??''), isEs?(r.unit_es||r.unit_zh||''):(r.unit_zh||r.unit_es||'')));});
    i=1; gp.topping.forEach(r=>{$wrapTop.append(card(i++, isEs?(r.material_es||r.material_zh||'--'):(r.material_zh||r.material_es||'--'), String(r.quantity??''), isEs?(r.unit_es||r.unit_zh||''):(r.unit_zh||r.unit_es||'')));});

    if(gp.base.length) showTab('base'); else if(gp.mixing.length) showTab('mixing'); else if(gp.topping.length) showTab('topping'); else showTab('base');
  }

  function fetchSOP(code){
    if(!code) return;
    if($waiting.length) $waiting.text(lg()==='es'?'Esperando consulta…':'等待查询…').show();
    $.getJSON('api/sop_handler.php', {code: code}).done(function(res){
      if(!res || res.status!=='success' || !res.data){ alert(lg()==='es'?'Error del servidor':'查询失败：服务器错误'); return; }
      if($waiting.length) $waiting.hide();
      render({product:res.data.product||{}, recipe:res.data.recipe||[]});
    }).fail(function(){ alert(lg()==='es'?'Error del servidor':'查询失败：服务器错误'); });
  }

  bindTabs();

  if($form.length){
    $form.on('submit', function(e){
      e.preventDefault(); const code=($input.val()||'').trim(); if(code) fetchSOP(code);
    });
  }
  if($input.length){
    $input.on('keydown', function(e){
      if(e.key==='Enter'){ e.preventDefault(); const code=($input.val()||'').trim(); if(code) fetchSOP(code); }
    });
  }
  if($input.length && ($input.val()||'').trim()){
    fetchSOP(($input.val()||'').trim());
  }
})();
