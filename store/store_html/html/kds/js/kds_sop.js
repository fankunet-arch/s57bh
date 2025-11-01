/**
 * TopTea · KDS · SOP
 * - 语言切换（作用域化，杜绝误拦截导航；支持 ?lang=zh / ?lang=es、点击旗帜、快捷键 Alt+Z / Alt+E）
 * - 静态 UI 文案 i18n（不改模板：JS 动态套文案，placeholder / 按钮 / 分组标签 / 等待提示）
 * - 仅左侧精准隐藏旧“请输入编码/--/虚线”提示（不会再让整页变白）
 * - SOP 动态卡片渲染按三分组（底料/调杯/顶料）
 */
$(function () {
  "use strict";

  /* ========================= I18N ========================= */
  const I18N = {
    zh: {
      tip: "每步动作做到位，口感品质才会好。",
      waiting: "等待查询…",
      err: "查询失败：服务器错误",
      // 静态 UI
      input_placeholder: "输入产品编码...",
      btn_finish: "制茶完成",
      btn_shortage: "缺料申报",
      tab_base: "底料",
      tab_mixing: "调杯",
      tab_topping: "顶料",
    },
    es: {
      tip: "Haz bien cada paso: mejoran la textura y la calidad.",
      waiting: "Esperando consulta…",
      err: "Error del servidor",
      // 静态 UI
      input_placeholder: "Introducir código del producto...",
      btn_finish: "Terminar",
      btn_shortage: "Informe de faltantes",
      tab_base: "Base",
      tab_mixing: "Mezcla",
      tab_topping: "Toppings",
    },
  };

  /* ========================= Lang helpers ========================= */
  function qp(name) {
    const m = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i").exec(
      window.location.search.slice(1)
    );
    return m ? decodeURIComponent(m[2]) : null;
  }

  // 初始化：URL 参数 → localStorage → html[lang]
  (function initLang() {
    const fromUrl = (qp("lang") || "").toLowerCase();
    if (fromUrl === "es" || fromUrl === "zh") {
      localStorage.setItem("TOPTEA_LANG", fromUrl);
      document.documentElement.setAttribute(
        "lang",
        fromUrl === "es" ? "es-ES" : "zh-CN"
      );
    } else {
      const saved = localStorage.getItem("TOPTEA_LANG");
      if (saved === "es" || saved === "zh") {
        document.documentElement.setAttribute(
          "lang",
          saved === "es" ? "es-ES" : "zh-CN"
        );
      }
    }
  })();

  function getLang() {
    const htmlLang =
      (document.documentElement.getAttribute("lang") || "").toLowerCase();
    if (htmlLang.startsWith("es")) return "es";
    if (htmlLang.startsWith("zh")) return "zh";
    const saved = localStorage.getItem("TOPTEA_LANG");
    return saved === "es" || saved === "zh" ? saved : "zh";
  }

  function t(key) {
    const lang = getLang();
    return (I18N[lang] || I18N.zh)[key] || key;
  }

  function pick(zhVal, esVal) {
    return getLang() === "es" ? esVal || zhVal : zhVal || esVal;
  }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, (m) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    })[m]);
  }

  function setLang(lang) {
    document.documentElement.setAttribute(
      "lang",
      lang === "es" ? "es-ES" : "zh-CN"
    );
    localStorage.setItem("TOPTEA_LANG", lang === "es" ? "es" : "zh");
    // 同步渲染静态 + 动态
    renderStaticUI();
    renderAll();
  }

  /* ========================= 语言切换（作用域化） =========================
   * 重点：不再用“全局捕获”去拦截任何点击，避免误伤导航/菜单。
   * 只对 “明确标注 data-lang 的元素” 进行事件委托；另外仅在 header 区域尝试自动标注旗帜。
   */

  // 1) 清理任何旧版捕获监听（如果存在）
  try {
    document.removeEventListener("click", window.__kdsLangCapture, true);
  } catch (_) {}

  // 2) 仅对 data-lang 元素做事件委托（全局冒泡，安全）
  $(document)
    .off("click.kds.lang", "[data-lang='zh']")
    .on("click.kds.lang", "[data-lang='zh']", function (e) {
      e.preventDefault();
      e.stopPropagation();
      setLang("zh");
    });

  $(document)
    .off("click.kds.lang.es", "[data-lang='es']")
    .on("click.kds.lang.es", "[data-lang='es']", function (e) {
      e.preventDefault();
      e.stopPropagation();
      setLang("es");
    });

  // 3) 仅在 header/导航区域内，自动给旗帜打 data-lang（避免误标一切元素）
  (function annotateHeaderFlags() {
    const header =
      document.querySelector("header, .topbar, .navbar, .header, .app-header") ||
      document;
    const cands = header.querySelectorAll("img,svg,span,i,a,button,div");

    cands.forEach((el) => {
      const has = (el.getAttribute("data-lang") || "").toLowerCase();
      if (has === "zh" || has === "es") return;

      const label = (
        el.getAttribute("alt") ||
        el.getAttribute("title") ||
        el.textContent ||
        ""
      ).toLowerCase();
      const src = (el.getAttribute("src") || "").toLowerCase();
      let bg = "";
      try {
        bg = (getComputedStyle(el).backgroundImage || "").toLowerCase();
      } catch (_) {}

      const hay = `${label} ${src} ${bg} ${el.id || ""} ${
        el.className || ""
      }`.toLowerCase();

      // 命中非常明确的旗帜线索，才标记
      if (
        /(flag|bandera)/.test(hay) &&
        (/(^|\W)(es|esp|spanish|espa)\b/.test(hay) || /\/es\b|spain/.test(hay))
      ) {
        el.setAttribute("data-lang", "es");
      }
      if (
        /(flag|bandera)/.test(hay) &&
        (/(^|\W)(zh|cn|china|中文|简体|汉)\b/.test(hay) || /\/cn\b|china/.test(hay))
      ) {
        el.setAttribute("data-lang", "zh");
      }
    });
  })();

  // 4) 键盘快捷：Alt+Z 中文 / Alt+E 西语
  document.addEventListener(
    "keydown",
    function (e) {
      if (e.altKey && !e.ctrlKey && !e.shiftKey) {
        const k = (e.key || "").toLowerCase();
        if (k === "z") {
          e.preventDefault();
          setLang("zh");
        }
        if (k === "e") {
          e.preventDefault();
          setLang("es");
        }
      }
    },
    false
  );

  /* ========================= DOM refs ========================= */
  const $form = $("#sku-search-form");
  const $input = $("#sku-input, #kds_code_input").first();

  const $tip = $("#kds-step-tip, .sop-tip, [data-role='sop-tip']").first();

  const $tabBase = $("#tab-base, .kds-step-tab[data-step='base']").first();
  const $tabMix = $("#tab-mixing, .kds-step-tab[data-step='mixing']").first();
  const $tabTop = $("#tab-topping, .kds-step-tab[data-step='topping']").first();

  const $wrapBase = $("#cards-base");
  const $wrapMix = $("#cards-mixing");
  const $wrapTop = $("#cards-topping");
  const $waiting = $("#cards-waiting");
  const $allWraps = $wrapBase.add($wrapMix).add($wrapTop);

  function leftHost() {
    return (
      $("#product-info-area, .kds-left, #left-panel, #kds_left").first()[0] ||
      $("#sku-search-form").closest(".col, .col-xxl-3, aside").first()[0] ||
      document.querySelector("aside") ||
      document.querySelector(".kds-left") ||
      document.body
    );
  }

  /* ========================= 仅左侧隐藏旧提示 ========================= */
  function removeLegacyHints() {
    const host = leftHost();
    if (!host) return;

    [
      "[data-i18n='info_enter_sku']",
      ".kds-enter-code",
      ".kds-enter-code-wrapper",
      ".enter-code-hint",
      "#enter-code-hint",
      "#kds_enter_code_title",
    ].forEach((sel) =>
      host.querySelectorAll(sel).forEach((n) => {
        n.style.display = "none";
      })
    );

    Array.from(host.querySelectorAll("*")).forEach((el) => {
      if (el.children.length === 0) {
        const tx = (el.textContent || "").trim();
        if (tx === "--") el.style.display = "none";
        if (/^[-—\s]{3,}$/.test(tx)) el.style.display = "none";
        if (tx === "请先输入编码" || /introduce\s+el\s+c[oó]digo/i.test(tx))
          el.style.display = "none";
      }
    });
  }

  /* ========================= 静态 UI 文案渲染 =========================
   * 不改模板：只要节点存在，就替换 label / placeholder / 按钮文字 / 分组标签。
   */
  function setTextIfExists($nodes, text) {
    if (!$nodes || !$nodes.length) return;
    $nodes.each(function () {
      // 尽量不破坏内部结构（比如徽章/图标），仅替换“末尾文本节点”或 data-i18n-holder
      const holder =
        this.querySelector("[data-i18n-holder]") ||
        this.querySelector(".kds-tab-label") ||
        null;

      if (holder) {
        holder.textContent = text;
        return;
      }

      // 如果是按钮/标签且内部有多个子元素，替换最后一个文本节点
      let replaced = false;
      for (let i = this.childNodes.length - 1; i >= 0; i--) {
        const n = this.childNodes[i];
        if (n.nodeType === 3 && n.nodeValue.trim().length > 0) {
          n.nodeValue = text;
          replaced = true;
          break;
        }
      }
      if (!replaced) {
        // 没有文本节点，则追加一个可控 holder
        const span = document.createElement("span");
        span.setAttribute("data-i18n-holder", "1");
        span.style.marginLeft = "6px";
        span.textContent = text;
        this.appendChild(span);
      }
    });
  }

  function renderStaticUI() {
    const lang = getLang();

    // 顶部蓝条提示
    if ($tip.length) $tip.text(t("tip"));

    // 左侧搜索输入框 placeholder
    const $inputs = $("#sku-input, #kds_code_input, [name='kds_code']");
    $inputs.each(function () {
      if (this.tagName === "INPUT") {
        this.setAttribute("placeholder", t("input_placeholder"));
      }
    });

    // 左侧两个按钮（尽量匹配一组候选选择器；匹配到就替换）
    setTextIfExists(
      $(
        "#btn-finish, .btn-finish, [data-role='finish'], .kds-finish-btn, .btn-primary"
      ).filter(function () {
        // 过滤成“左侧大按钮”，避免误伤顶部导航
        const rect = this.getBoundingClientRect();
        return rect.width > 120 && rect.height > 36;
      }),
      t("btn_finish")
    );

    setTextIfExists(
      $(
        "#btn-shortage, .btn-shortage, [data-role='shortage'], .kds-shortage-btn"
      ),
      t("btn_shortage")
    );

    // 三个分组 Tab 文案
    if ($tabBase.length) setTextIfExists($tabBase, t("tab_base"));
    if ($tabMix.length) setTextIfExists($tabMix, t("tab_mixing"));
    if ($tabTop.length) setTextIfExists($tabTop, t("tab_topping"));

    // 中央“等待查询…”（若可见）
    if ($waiting.length && $waiting.is(":visible")) {
      $waiting.text(t("waiting"));
    }
  }

  /* ========================= 状态 ========================= */
  let DATA = { product: {}, recipe: [] };

  /* ========================= 左侧（编号/名称/状态/冰糖） ========================= */
  function ensureLeft() {
    const host = leftHost();
    if (!host) return {};

    let code = document.querySelector(
      "#kds_code_big, .kds-sku-big, [data-role='product-code']"
    );
    if (!code) {
      code = document.createElement("div");
      code.id = "kds_code_big";
      code.style.cssText =
        "font-size:48px;font-weight:900;line-height:1;margin:12px 0 6px;";
      host.insertBefore(code, host.firstChild);
    }

    let name = document.querySelector(
      "#kds_product_title, .kds-name-big, [data-role='product-name']"
    );
    if (!name) {
      name = document.createElement("div");
      name.id = "kds_product_title";
      name.style.cssText =
        "font-size:24px;font-weight:800;margin:4px 0 10px;";
      code.after(name);
    }

    let l1 = document.querySelector(
      "#kds_line1, .kds-line1, #kds_overview_line1"
    );
    if (!l1) {
      l1 = document.createElement("div");
      l1.id = "kds_line1";
      l1.className = "kds-info-line";
      l1.style.cssText =
        "background:#f3f4f6;border-radius:10px;padding:10px 12px;margin:8px 0;font-weight:600;";
      name.after(l1);
    }

    let l2 = document.querySelector(
      "#kds_line2, .kds-line2, #kds_overview_line2"
    );
    if (!l2) {
      l2 = document.createElement("div");
      l2.id = "kds_line2";
      l2.className = "kds-info-line";
      l2.style.cssText =
        "background:#f3f4f6;border-radius:10px;padding:10px 12px;margin:8px 0;font-weight:600;";
      l1.after(l2);
    }

    return { code, name, l1, l2 };
  }

  function renderLeft() {
    const nodes = ensureLeft();
    const p = DATA.product || {};

    if (nodes.code)
      nodes.code.textContent = p.product_code || p.product_no || "";
    if (nodes.name)
      nodes.name.textContent =
        pick(p.name_zh, p.name_es) ||
        pick(p.title_zh, p.title_es) ||
        "";

    const statusTxt = pick(p.status_name_zh, p.status_name_es) || "";
    if (nodes.l1) {
      nodes.l1.style.display = statusTxt ? "" : "none";
      nodes.l1.textContent = statusTxt;
    }

    const ice = pick(p.ice_name_zh, p.ice_name_es) || "";
    const swt = pick(p.sweetness_name_zh, p.sweetness_name_es) || "";
    const parts = [];
    if (ice) parts.push(ice);
    if (swt) parts.push(swt);
    if (nodes.l2) {
      nodes.l2.style.display = parts.length ? "" : "none";
      nodes.l2.textContent = parts.join(" / ");
    }

    removeLegacyHints();
  }

  /* ========================= 卡片渲染 ========================= */
  const $tabWraps = {
    base: $wrapBase,
    mixing: $wrapMix,
    topping: $wrapTop,
  };

  function normalizeCat(cat) {
    const s = String(cat || "").toLowerCase();
    if (s.startsWith("mix") || s.includes("调")) return "mixing";
    if (s.startsWith("top") || s.includes("顶")) return "topping";
    return "base";
  }

  function cardHTML(i, name, qty, unit) {
    return `
      <div class="col-xxl-6 col-xl-6 col-lg-12 col-md-12">
        <div class="kds-ingredient-card">
          <div class="step-number" style="position:absolute;left:16px;top:16px;background:#16a34a;color:#fff;width:28px;height:28px;border-radius:999px;display:flex;align-items:center;justify-content:center;font-weight:900;">${i}</div>
          <div class="kds-card-thumb" style="width:140px;height:140px;background:#6b7280;border-radius:.8rem;margin:56px auto 8px auto;"></div>
          <div class="text-center" style="font-size:1.6rem;font-weight:900;letter-spacing:.6px;">${esc(
            name
          )}</div>
          <div class="kds-quantity text-center">${esc(qty)}</div>
          <div class="kds-unit-measure text-center">${esc(unit)}</div>
        </div>
      </div>`;
  }

  function renderCards() {
    $allWraps.empty();
    if ($waiting.length) $waiting.hide();

    const gp = { base: [], mixing: [], topping: [] };
    (DATA.recipe || []).forEach((r) => gp[normalizeCat(r.step_category)].push(r));

    const isEs = getLang() === "es";

    let i = 1;
    gp.base.forEach((r) => {
      const name = isEs
        ? r.material_es || r.material_zh || "--"
        : r.material_zh || r.material_es || "--";
      const unit = isEs
        ? r.unit_es || r.unit_zh || ""
        : r.unit_zh || r.unit_es || "";
      const qty = r.quantity != null ? r.quantity : "";
      $wrapBase.append(cardHTML(i++, name, String(qty), unit));
    });

    i = 1;
    gp.mixing.forEach((r) => {
      const name = isEs
        ? r.material_es || r.material_zh || "--"
        : r.material_zh || r.material_es || "--";
      const unit = isEs
        ? r.unit_es || r.unit_zh || ""
        : r.unit_zh || r.unit_es || "";
      const qty = r.quantity != null ? r.quantity : "";
      $wrapMix.append(cardHTML(i++, name, String(qty), unit));
    });

    i = 1;
    gp.topping.forEach((r) => {
      const name = isEs
        ? r.material_es || r.material_zh || "--"
        : r.material_zh || r.material_es || "--";
      const unit = isEs
        ? r.unit_es || r.unit_zh || ""
        : r.unit_zh || r.unit_es || "";
      const qty = r.quantity != null ? r.quantity : "";
      $wrapTop.append(cardHTML(i++, name, String(qty), unit));
    });

    // 默认显示有内容的分组
    function showTab(step) {
      $(".kds-step-tab").removeClass("active");
      $(`.kds-step-tab[data-step='${step}']`).addClass("active");
      $wrapBase.addClass("d-none");
      $wrapMix.addClass("d-none");
      $wrapTop.addClass("d-none");
      $tabWraps[step].removeClass("d-none");
    }
    if (gp.base.length) showTab("base");
    else if (gp.mixing.length) showTab("mixing");
    else if (gp.topping.length) showTab("topping");
    else {
      if ($waiting.length) $waiting.text(t("waiting")).show();
      showTab("base");
    }
  }

  /* ========================= 标签可点 ========================= */
  function bindTabs() {
    if ($tabBase.length && !$tabBase.data("step"))
      $tabBase.attr("data-step", "base").addClass("kds-step-tab");
    if ($tabMix.length && !$tabMix.data("step"))
      $tabMix.attr("data-step", "mixing").addClass("kds-step-tab");
    if ($tabTop.length && !$tabTop.data("step"))
      $tabTop.attr("data-step", "topping").addClass("kds-step-tab");

    $(document)
      .off("click.kdsStep", ".kds-step-tab")
      .on("click.kdsStep", ".kds-step-tab", function (e) {
        e.preventDefault();
        const step = $(this).data("step");
        $(".kds-step-tab").removeClass("active");
        $(this).addClass("active");
        $wrapBase.addClass("d-none");
        $wrapMix.addClass("d-none");
        $wrapTop.addClass("d-none");
        $tabWraps[step].removeClass("d-none");
      });
  }

  /* ========================= SOP Ajax ========================= */
  function fetchSop(code) {
    if (!code) return;
    if ($waiting.length) $waiting.text(t("waiting")).show();
    $allWraps.empty();

    $.ajax({
      url: "api/sop_handler.php",
      type: "GET",
      dataType: "json",
      data: { code },
    })
      .done(function (res) {
        if (!res || res.status !== "success" || !res.data) {
          alert(t("err"));
          return;
        }
        DATA = { product: res.data.product || {}, recipe: res.data.recipe || [] };
        if ($waiting.length) $waiting.hide();
        renderAll();
      })
      .fail(function () {
        alert(t("err"));
      });
  }

  /* ========================= 渲染入口 ========================= */
  function renderAll() {
    // 提示条由 renderStaticUI 控制
    renderLeft();
    renderCards();
  }

  /* ========================= 启动 ========================= */
  bindTabs();
  renderStaticUI(); // 先把静态 UI 换到当前语言
  removeLegacyHints();

  // 表单提交 / 回车查询
  if ($form.length) {
    $form.on("submit", function (e) {
      e.preventDefault();
      const code = ($input.val() || "").trim();
      if (code) fetchSop(code);
    });
  }
  if ($input.length) {
    $input.on("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();
        const code = ($input.val() || "").trim();
        if (code) fetchSop(code);
      }
    });
    // 输入框有初值则自动查询一次
    if (($input.val() || "").trim()) fetchSop(($input.val() || "").trim());
  }
});
