/* TopTea · KDS — SOP 查询绑定（最小接线版）
 * 不改动页面结构；只给你现有输入框/按钮绑定事件并渲染结果。
 * 兼容 P 与 P-A-M-T。需要 jQuery（你已在系统中使用）。
 * 2025-10-31
 */
(function () {
  if (window.__KDS_SOP_BOUND__) return; // 防重复绑定
  window.__KDS_SOP_BOUND__ = true;

  function apiUrl() {
    var path = location.pathname;             // 例如 /kds/index.php 或 /kds/
    var base = path.replace(/\/index\.php.*$/i, '');
    if (!base || base === path) base = path.replace(/\/[^\/]+$/,'');
    if (!base.endsWith('/')) base += '/';
    return base + 'api/sop_handler.php';
  }

  // 仅“寻找”现有输入控件，不创建新的
  function pickInput() {
    var $ipt = $('#kds_code:visible').first();                           // 1) 约定ID（若存在）
    if ($ipt.length) return $ipt;
    $ipt = $('input[placeholder*="产品编码"],input[placeholder*="编码"],input[type="search"]').filter(':visible').first(); // 2) 通过占位/类型兜底
    return $ipt.length ? $ipt : $();
  }
  function pickSearchButton($ipt) {
    if ($ipt && $ipt.length) {
      var $btn = $ipt.closest('.input-group,form,.row').find('button:visible,i.bi-search').first();
      if ($btn.length) return $btn.closest('button');
    }
    return $('.btn-search:visible, button[data-kds-action="query"]:visible').first();
  }

  // 合法化输入：支持 P / P-A / P-A-M / P-A-M-T
  function normalize(raw) {
    if (!raw) return '';
    raw = (''+raw).trim().toUpperCase();
    if (!/^[A-Z0-9-]+$/.test(raw)) return '';
    var seg = raw.split('-').filter(Boolean);
    if (seg.length > 4) return '';
    return seg.join('-');
  }

  // 若页面已有容器（比如 #list-base/#list-mix/#list-top），就用它；否则轻量创建一个，不影响布局
  function ensureResultHost() {
    if ($('#list-base').length && $('#list-mix').length && $('#list-top').length) return;
    if ($('#kds_sop_tabs').length) return;

    var html =
      '<div id="kds_sop_tabs" class="mt-2">' +
      '  <ul class="nav nav-tabs" role="tablist">' +
      '    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-base"  type="button" role="tab">底料</button></li>' +
      '    <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#pane-mix"   type="button" role="tab">调杯</button></li>' +
      '    <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-bs-target="#pane-top"   type="button" role="tab">顶料</button></li>' +
      '  </ul>' +
      '  <div class="tab-content border rounded-bottom p-3" style="min-height:120px;">' +
      '    <div class="tab-pane fade show active" id="pane-base" role="tabpanel"><ul id="list-base" class="list-group list-group-flush"></ul></div>' +
      '    <div class="tab-pane fade"               id="pane-mix"  role="tabpanel"><ul id="list-mix"  class="list-group list-group-flush"></ul></div>' +
      '    <div class="tab-pane fade"               id="pane-top"  role="tabpanel"><ul id="list-top"  class="list-group list-group-flush"></ul></div>' +
      '  </div>' +
      '  <div id="kds_sop_hint" class="text-muted small mt-2">请输入产品编码进行查询（例：101 或 101-1-1-11）。</div>' +
      '</div>';

    var $host = $('.kds-center:visible, .content:visible, .container:visible, main:visible').first();
    if ($host.length) $host.prepend(html); else $('body').append(html);
  }

  function setWaiting(msg){ $('#list-base,#list-mix,#list-top').empty(); $('#kds_sop_hint').text(msg||'正在查询…'); }
  function setMsg(type,msg){ $('#list-base,#list-mix,#list-top').empty(); $('#kds_sop_hint').text(msg|| (type==='error'?'查询失败':'')); }

  // 通用化 payload：兼容 adjusted_recipe / steps / steps_json_zh 三种
  function normPayload(data){
    if (!data) return {base:[],mix:[],top:[]};
    if (data.adjusted_recipe) return Object.assign({base:[],mix:[],top:[]}, data.adjusted_recipe);
    if (data.steps)          return Object.assign({base:[],mix:[],top:[]}, data.steps);
    if (data.base || data.mix || data.top) return Object.assign({base:[],mix:[],top:[]}, data);
    if (data.steps_json_zh) {
      try{ return Object.assign({base:[],mix:[],top:[]}, JSON.parse(data.steps_json_zh)); }catch(e){}
    }
    return {base:[],mix:[data],top:[]};
  }

  function renderList($ul,items){
    $ul.empty();
    if (!items || !items.length){ $ul.append('<li class="list-group-item text-muted">（无）</li>'); return; }
    items.forEach(function(it){
      if (typeof it === 'string') { $ul.append('<li class="list-group-item">'+it+'</li>'); return; }
      var name = it.material_name || it.name || it.label || '未命名';
      var qty  = (it.qty!=null?it.qty:it.quantity);
      var unit = it.unit || '';
      var right = [];
      if (qty!=null) right.push(qty+(unit?unit:''));
      $ul.append('<li class="list-group-item d-flex justify-content-between"><span>'+name+'</span><small class="text-muted">'+right.join(' · ')+'</small></li>');
    });
  }
  function render(data){
    ensureResultHost();
    var s = normPayload(data);
    renderList($('#list-base'), s.base);
    renderList($('#list-mix'),  s.mix);
    renderList($('#list-top'),  s.top);
    $('#kds_sop_hint').text('完成。');
  }

  function query(codeRaw){
    var code = normalize(codeRaw);
    if (!code){ setMsg('error','请输入合法编码（示例：101 或 101-1-1-11）'); return; }
    ensureResultHost();
    setWaiting('正在查询：'+code+' …');
    $.ajax({
      url: apiUrl(),
      method: 'GET',
      dataType: 'json',
      cache: false,
      data: { code: code },
      success: function(resp){
        if (resp && resp.status === 'success' && resp.data){ render(resp.data); }
        else { setMsg('error', (resp && resp.message) || '查询失败'); }
      },
      error: function(xhr){
        var msg='网络/服务器错误';
        if (xhr && xhr.responseText){
          try{ var j=JSON.parse(xhr.responseText); if (j && j.message) msg=j.message; }catch(e){}
        }
        setMsg('error', msg);
      }
    });
  }

  function bind(){
    var $ipt = pickInput();
    var $btn = pickSearchButton($ipt);
    if ($ipt.length){
      $ipt.on('keydown.kds-sop', function(e){
        if (e.key === 'Enter'){ e.preventDefault(); query($ipt.val()); }
      });
    }
    if ($btn.length){
      $btn.on('click.kds-sop', function(){ query($ipt.val()); });
    }
  }

  $(bind);
})();
