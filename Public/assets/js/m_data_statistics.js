// Public/assets/js/m_data_statistics.js
// 各班領退統計頁：資料載入 / 渲染 / PDF 匯出 / ICON 導航
// 依新網站結構改寫：所有 API 路徑走 PUBLIC_BASE（由 PHP 注入）
// 功能保留：A~F 班查詢渲染、B 班筆數明細、D 班對帳資料、F 班合計列、ICON 導航、PDF 匯出、卡片標題（承辦人）

(function () {
  "use strict";

  // ---- 安全取得 PUBLIC_BASE ----
  const PUBLIC_BASE = (window.PUBLIC_BASE || "").replace(/\/+$/, "");
  const API_STATS = `${PUBLIC_BASE}/modules/mat/m_data_statistics_backend.php`;
  const API_PDF = `${PUBLIC_BASE}/modules/mat/m_data_statistics_PDF.php`;
  const API_EDIT = `${PUBLIC_BASE}/modules/mat/m_data_editing_backend.php`;

  // ---- DOM ----
  const datePicker = document.getElementById("datePicker");
  const displayDate = document.getElementById("displayDate");
  const exportPDFButton = document.getElementById("exportPDF");

  // A~F 對應的 tbody / 標題 h5 / 右下 ICON
  const SHIFT_KEYS = ["A", "B", "C", "D", "E", "F"];
  const bodies = Object.fromEntries(
    SHIFT_KEYS.map((k) => [k, document.getElementById(`statistics-body-${k.toLowerCase()}`)])
  );
  const headers = Object.fromEntries(
    SHIFT_KEYS.map((k) => [k, document.getElementById(`class_${k}`)])
  );
  const icons = Object.fromEntries(
    SHIFT_KEYS.map((k) => [k, document.getElementById(`icon-${k}`)])
  );

  // ===== 共用小工具 =====

  /**
   * 轉字串數值：顯示用途；0 與非數值 -> 空字串；去掉多餘小數 0
   */
  function formatNumber(v) {
    const n = Number(v);
    if (!isFinite(n) || n === 0) return "";
    // 盡量保持兩位，最後去除多餘 0
    return (Math.round(n * 100) / 100).toFixed(2).replace(/\.?0+$/, "");
  }

  /**
   * 將 "1,0,2,0" 這類字串 → 過濾為 "1+2"（僅顯示非 0 且格式化）
   */
  function formatDetailsList(detailStr) {
    if (!detailStr) return "";
    const arr = String(detailStr)
      .split(",")
      .map((s) => Number(s.trim()))
      .filter((n) => isFinite(n) && n !== 0);
    if (arr.length === 0) return "";
    return arr.map(formatNumber).join("+");
  }

  /**
   * 設定數字色彩：負數紅、正數(合計新)藍、其餘黑
   * @param {number} n 數值
   * @param {'new'|'old'|'warn'} mode new:正數藍；old:正數黑；warn:負數紅
   */
  function colorStyle(n, mode) {
    const x = Number(n) || 0;
    if (x < 0) return "color:red;";
    if (x > 0) return mode === "new" ? "color:blue;" : "color:black;";
    return "color:black;";
  }

  /**
   * 渲染空資料列
   */
  function renderEmptyRow(tbody, colspan) {
    tbody.insertAdjacentHTML(
      "beforeend",
      `<tr><td colspan="${colspan}">查無數據</td></tr>`
    );
  }

  /**
   * 清空 tbody
   */
  function clearBody(tbody) {
    if (tbody) tbody.innerHTML = "";
  }

  // ===== 後端資料抓取 =====

  async function fetchStatistics(shift, date) {
    const form = new URLSearchParams({ shift, date });
    const res = await fetch(API_STATS, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: form.toString(),
    });
    const json = await res.json();
    if (json.status !== "success") throw new Error(json.message || "載入失敗");
    return json.data || [];
  }

  // ===== 各班渲染（合併重構） =====

  // A / C：共用（有合計）
  function renderAC(data, tbody) {
    clearBody(tbody);
    if (!data || data.length === 0) return renderEmptyRow(tbody, 9);

    data.forEach((row, idx) => {
      const nNew = Number(row.total_collar_New) || 0;
      const nOld = Number(row.total_collar_Old) || 0;
      const rNew = Number(row.total_recede_New) || 0;
      const rOld = Number(row.total_recede_Old) || 0;

      const diffNew = nNew - rNew;
      const diffOld = nOld - rOld;

      const html = `
        <tr>
          <td>${idx + 1}</td>
          <td class="hidden">${row.material_number ?? ""}</td>
          <td class="text-start">${row.material_name ?? ""}</td>
          <td>${formatNumber(nNew)}</td>
          <td>${formatNumber(nOld)}</td>
          <td>${formatNumber(rNew)}</td>
          <td>${formatNumber(rOld)}</td>
          <td style="${colorStyle(diffNew, 'new')}">${formatNumber(diffNew)}</td>
          <td style="${colorStyle(diffOld, 'old')}">${formatNumber(diffOld)}</td>
        </tr>`;
      tbody.insertAdjacentHTML("beforeend", html);
    });
  }

  // B：含筆數明細（GROUP_CONCAT）
  function renderB(data, tbody) {
    clearBody(tbody);
    if (!data || data.length === 0) return renderEmptyRow(tbody, 14);

    data.forEach((row, idx) => {
      const nNew = Number(row.total_collar_New) || 0;
      const nOld = Number(row.total_collar_Old) || 0;
      const rNew = Number(row.total_recede_New) || 0;
      const rOld = Number(row.total_recede_Old) || 0;

      const diffNew = nNew - rNew;
      const diffOld = nOld - rOld;

      const html = `
        <tr>
          <td>${idx + 1}</td>
          <td class="hidden">${row.material_number ?? ""}</td>
          <td class="text-start">${row.new_material_name ?? ""}</td>

          <td>${formatNumber(nNew)}</td>
          <td class="text-muted">${formatDetailsList(row.details_collar_New)}</td>

          <td>${formatNumber(nOld)}</td>
          <td class="text-muted">${formatDetailsList(row.details_collar_Old)}</td>

          <td>${formatNumber(rNew)}</td>
          <td class="text-muted">${formatDetailsList(row.details_recede_New)}</td>

          <td>${formatNumber(rOld)}</td>
          <td class="text-muted">${formatDetailsList(row.details_recede_Old)}</td>

          <td style="${colorStyle(diffNew, 'new')}">${formatNumber(diffNew)}</td>
          <td style="${colorStyle(diffOld, 'old')}">${formatNumber(diffOld)}</td>
        </tr>`;
      tbody.insertAdjacentHTML("beforeend", html);
    });
  }

  // D：含對帳資料
  function renderD(data, tbody) {
    clearBody(tbody);
    if (!data || data.length === 0) return renderEmptyRow(tbody, 10);

    data.forEach((row, idx) => {
      const nNew = Number(row.total_collar_New) || 0;
      const nOld = Number(row.total_collar_Old) || 0;
      const rNew = Number(row.total_recede_New) || 0;
      const rOld = Number(row.total_recede_Old) || 0;
      const reconc = Number(row.reconciliation_value) || 0;

      const diffNew = nNew - rNew;                   // 新合計
      const diffOld = nOld - rOld + reconc;          // 舊合計（含對帳）

      const html = `
        <tr>
          <td>${idx + 1}</td>
          <td class="hidden">${row.material_number ?? ""}</td>
          <td class="text-start">${row.material_name ?? ""}</td>

          <td>${formatNumber(nNew)}</td>
          <td>${formatNumber(nOld)}</td>
          <td>${formatNumber(rNew)}</td>
          <td>${formatNumber(rOld)}</td>

          <td style="${colorStyle(reconc,'old')}">${formatNumber(reconc)}</td>
          <td style="${colorStyle(diffNew,'new')}">${formatNumber(diffNew)}</td>
          <td style="${colorStyle(diffOld,'old')}">${formatNumber(diffOld)}</td>
        </tr>`;
      tbody.insertAdjacentHTML("beforeend", html);
    });
  }

  // E / F：無合計；F 在表尾顯示總計
  function renderEF(data, tbody, isF) {
    clearBody(tbody);
    if (!data || data.length === 0) return renderEmptyRow(tbody, 7);

    let totalNew = 0, totalOld = 0, totalRNew = 0, totalROld = 0;

    data.forEach((row, idx) => {
      const nNew = Number(row.total_collar_New) || 0;
      const nOld = Number(row.total_collar_Old) || 0;
      const rNew = Number(row.total_recede_New) || 0;
      const rOld = Number(row.total_recede_Old) || 0;

      totalNew += nNew;
      totalOld += nOld;
      totalRNew += rNew;
      totalROld += rOld;

      const html = `
        <tr>
          <td>${idx + 1}</td>
          <td class="hidden">${row.material_number ?? ""}</td>
          <td class="text-start">${row.material_name ?? ""}</td>
          <td style="color:blue;">${formatNumber(nNew)}</td>
          <td>${formatNumber(nOld)}</td>
          <td>${formatNumber(rNew)}</td>
          <td style="color:red;">${formatNumber(rOld)}</td>
        </tr>`;
      tbody.insertAdjacentHTML("beforeend", html);
    });

    if (isF) {
      const totalRow = `
        <tr style="font-weight:bold;background:#f5f5f5;">
          <td class="text-center"></td>
          <td class="hidden"></td>
          <td class="text-center">合計</td>
          <td style="color:blue;">${formatNumber(totalNew)}</td>
          <td>${formatNumber(totalOld)}</td>
          <td>${formatNumber(totalRNew)}</td>
          <td style="color:red;">${formatNumber(totalROld)}</td>
        </tr>`;
      tbody.insertAdjacentHTML("beforeend", totalRow);
    }
  }

  // ===== 載入與事件 =====

  // 初始化日期：優先讀 URL ?date=，再退回 localStorage，最後才是今天
function initDate() {
  const params = new URLSearchParams(window.location.search);
  const urlDate = params.get("date"); // 例如 m_data_statistics.php?date=2025-08-20

  const saved = localStorage.getItem("selectedDate");
  const today = new Date().toISOString().split("T")[0];

  const d = urlDate || saved || today;

  // 套進 UI
  if (datePicker) datePicker.value = d;
  if (displayDate) displayDate.textContent = d;

  // 同步回 localStorage（避免重新整理遺失）
  localStorage.setItem("selectedDate", d);

  return d;
}


  async function loadAll(date) {
    // 平行抓取，彼此不互相卡住
    const tasks = SHIFT_KEYS.map(async (k) => {
      try {
        const data = await fetchStatistics(k, date);
        if (k === "A" || k === "C") return renderAC(data, bodies[k]);
        if (k === "B") return renderB(data, bodies[k]);
        if (k === "D") return renderD(data, bodies[k]);
        if (k === "E") return renderEF(data, bodies[k], false);
        if (k === "F") return renderEF(data, bodies[k], true);
      } catch (err) {
        console.error(`載入 ${k} 班失敗：`, err);
        clearBody(bodies[k]);
        renderEmptyRow(
          bodies[k],
          k === "B" ? 14 : k === "D" ? 10 : k === "E" || k === "F" ? 7 : 9
        );
      }
    });
    await Promise.all(tasks);
  }

  // 卡片標題（承辦人）
  async function loadTitles() {
    try {
      const res = await fetch(`${API_EDIT}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "fetch" }),
      });
      const arr = await res.json();
      if (!Array.isArray(arr)) return;
      arr.forEach((x) => {
        const s = String(x.shift_name || "").trim().toUpperCase();
        const p = String(x.personnel_name || "").trim();
        const h = headers[s];
        if (h) h.textContent = `${s}班-${p}`;
      });
    } catch (e) {
      console.warn("承辦人標題載入失敗：", e);
    }
  }

  // ICON 顯示/隱藏（與點擊滾動）
  function initIcons() {
    const obs = new IntersectionObserver(
      (entries) => {
        entries.forEach((en) => {
          const id = en.target.id; // class_A
          const key = id.split("_")[1]; // A
          if (icons[key]) icons[key].style.display = en.isIntersecting ? "none" : "block";
        });
      },
      { root: null, threshold: 0.1 }
    );
    Object.values(headers).forEach((h) => h && obs.observe(h));

    Object.keys(icons).forEach((k) => {
      icons[k].addEventListener("click", () => {
        headers[k].scrollIntoView({ behavior: "smooth", block: "center" });
      });
    });
  }

  // 匯出 PDF（將當前 DOM 表格序列化送後端）
  async function exportPDF(dateStr) {
    const classData = {};
    const titles = [];

    SHIFT_KEYS.forEach((k) => {
      const tbody = bodies[k];
      if (!tbody) return;
      const rows = [];
      tbody.querySelectorAll("tr").forEach((tr) => {
        const cols = [];
        tr.querySelectorAll("td").forEach((td) => cols.push(td.textContent.trim()));
        if (cols.length) rows.push(cols);
      });
      classData[`${k}班`] = rows;
      const h = headers[k];
      titles.push({ className: `${k}班`, title: h ? h.textContent.trim() : `${k}班` });
    });

    const res = await fetch(API_PDF, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ classData, titles, date: dateStr }),
    });

    if (!res.ok) {
      throw new Error(`PDF 產生失敗 (HTTP ${res.status})`);
    }

    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${dateStr}領退料資料.pdf`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  // ===== 綁定事件 =====

  document.addEventListener("DOMContentLoaded", async () => {
    const d = initDate();
    initIcons();
    await Promise.all([loadTitles(), loadAll(d)]);
  });

  datePicker.addEventListener("change", async function () {
    const d = this.value;
    localStorage.setItem("selectedDate", d);
    displayDate.textContent = d;
    await loadAll(d);
  });

  exportPDFButton.addEventListener("click", async () => {
    try {
      await exportPDF(datePicker.value);
    } catch (e) {
      console.error(e);
      alert("匯出 PDF 發生錯誤，請稍後再試。");
    }
  });
})();
