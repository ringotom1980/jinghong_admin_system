// /Public/assets/js/car_statistics.js
// 車輛維修統計（精簡版）— 全部 / 年度 / 半年度 / 月份；動態載入
$(document).ready(function () {
  const BASE = (window.PUBLIC_BASE || '').replace(/\/+$/, '');
  const API = BASE + '/modules/car/car_statistics_backend.php';
  const PDF_DETAILS = BASE + '/modules/car/car_statistics_details_pdf.php';
  const PDF_SUMMARY = BASE + '/modules/car/car_statistics_summary_pdf.php';

  const currentYear = new Date().getFullYear();
  let detailsReqSeq = 0; // 防止舊回應覆蓋右表

  // ---------- 小工具 ----------
  function escapeHtml(s) {
    return String(s ?? '').replace(
      /[&<>"']/g,
      (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m],
    );
  }
  function nf(n) {
    return (Number(n) || 0).toLocaleString('zh-TW');
  }

  function yearOptions() {
    let opt = '';
    for (let y = currentYear; y >= currentYear - 10; y--)
      opt += `<option value="${y}">${y} 年</option>`;
    return opt;
  }
  function monthOptions() {
    let opt = '';
    for (let m = 1; m <= 12; m++) opt += `<option value="${m}">${m} 月</option>`;
    return opt;
  }

  // ---------- DOM ----------
  const $filterType = $('#filterType');
  const $secondary = $('#secondaryFilter');
  const $tertiary = $('#tertiaryFilter');

  // ---------- 渲染：右表明細 ----------
  function renderDetailsTable(details) {
    const $tbody = $('#repairTable tbody');
    $tbody.empty();

    if (!Array.isArray(details) || details.length === 0) {
      $tbody.append('<tr><td colspan="6">無維修紀錄</td></tr>');
      return;
    }

    details.forEach((row) => {
      const cost = Number(row.repair_cost) || 0;
      const burd = Number(row.company_burden || 0);
      const summary =
        row.itemsSummary && row.itemsSummary.length
          ? row.itemsSummary
          : row.repair_content || '';
      const titleTxt =
        row.itemsTitle && row.itemsTitle.length ? row.itemsTitle : row.repair_content || '';

      $('#repairTable tbody').append(`
        <tr>
          <td>${row.vehicle_id}</td>
          <td>${row.license_plate}</td>
          <td>${row.repair_date}</td>
          <td class="text-start">
            <div class="cell-ellipsis" title="${escapeHtml(titleTxt)}">${escapeHtml(summary)}</div>
          </td>
          <td>${nf(cost)} 元</td>
          <td>${nf(burd)} 元</td>
        </tr>
      `);
    });
  }

  // ---------- 渲染：左表統計 + 合計 ----------
  function updateTables(statistics, details, totalRepairCost, raw) {
    const $statBody = $('#statisticsTable tbody');
    const $total = $('#totalAmount');
    $statBody.empty();

    if (Array.isArray(statistics) && statistics.length > 0) {
      const rows = statistics
        .map(
          (row) => `
      <tr class="clickable-row"
          data-vehicle-id="${String(row.vehicle_id)}"
          data-plate="${row.license_plate || ''}"
          role="button" tabindex="0" style="cursor:pointer;">
        <td>${row.vehicle_id}</td>
        <td>${row.license_plate}</td>
        <td>${(Number(row.total_repairs) || 0).toLocaleString('zh-TW')}</td>
        <td>${(Number(row.total_repair_cost) || 0).toLocaleString('zh-TW')} 元</td>
        <td>${(Number(row.total_company_burden) || 0).toLocaleString('zh-TW')} 元</td>
      </tr>
    `,
        )
        .join('');
      $statBody.append(rows);

      // 若先前已選過某車，恢復高亮；但不在這裡觸發 fetchDetails（避免覆蓋右表）
      if (window.__carStatSelectedVehicleId) {
        $statBody
          .find(`tr[data-vehicle-id="${window.__carStatSelectedVehicleId}"]`)
          .addClass('table-active');
      }
    } else {
      $statBody.append('<tr><td colspan="5">無統計資料</td></tr>');
    }

    // 右表的規則：
    // - 若目前「沒有選定車輛」→ 用列表回傳的 details 畫「全部車輛的明細」；
    // - 若已選定車輛 → 保留點擊後 fetchDetails() 的結果，不在這裡重畫，避免被覆蓋。
    if (!window.__carStatSelectedVehicleId) {
      renderDetailsTable(details || []);
    }

    const totalBurden = raw && raw.totalCompanyBurden ? raw.totalCompanyBurden : 0;
    $total.html(
      `<strong>維修金額 ${(Number(totalRepairCost) || 0).toLocaleString('zh-TW')} 元｜公司負擔 ${(Number(totalBurden) || 0).toLocaleString('zh-TW')} 元</strong>`,
    );
  }

  
  // ---------- 依目前條件載入（未選車 => 右表為全車明細） ----------
  function currentFilterPayload(extra = {}) {
    const d = { filterType: $('#filterType').val() };

    const set = (sel, key) => {
      const $el = $(sel);
      if ($el.length) {
        const v = $el.val();
        if (v !== null && v !== '') d[key] = v; // 只送有值的
      }
    };

    set('#yearSelect', 'year');
    set('#halfYearSelect', 'halfYear');
    set('#halfSelect', 'half');
    set('#monthYearSelect', 'monthYear');
    set('#monthSelect', 'month');

    // extra 可能含 vehicleId；維持覆蓋
    return Object.assign(d, extra);
  }

  function loadStatistics() {
    window.__carStatSelectedVehicleId = null;
    window.__carStatSelectedPlate = null;
    $('#statisticsTable tbody tr').removeClass('table-active');

    $.ajax({
      url: API,
      method: 'POST',
      data: currentFilterPayload(),
      dataType: 'json',
      success: function (data) {
        updateTables(
          data.statistics || [],
          data.details || [],
          data.totalRepairCost || 0,
          data,
        );
      },
      error: function () {
        alert('無法載入資料，請稍後再試！');
      },
    });
  }

  function fetchDetails(vehicleId) {
    const vid = String(vehicleId ?? '').trim();
    const plate = String(window.__carStatSelectedPlate ?? '').trim();
    if (!vid && !plate) return; // 沒有任何識別就不查

    const seq = ++detailsReqSeq;

    $.ajax({
      url: API,
      method: 'POST',
      data: currentFilterPayload({ vehicleId: vid, vehiclePlate: plate }), // ← 同送車牌
      dataType: 'json',
      success: function (data) {
        if (seq === detailsReqSeq) {
          renderDetailsTable(data.details || []);
        }
      },
      error: function () {
        alert('無法載入詳細資料！');
      },
    });
  }

  // 取消選取並顯示目前篩選期間的「全部車輛」明細
  function clearSelectionAndShowAll() {
    if (!window.__carStatSelectedVehicleId) return; // 沒選就不用動
    window.__carStatSelectedVehicleId = null;
    window.__carStatSelectedPlate = null;
    $('#statisticsTable tbody tr').removeClass('table-active');

    // 只拉右表全部明細（不重載左表）
    const seq = ++detailsReqSeq;
    $.ajax({
      url: API,
      method: 'POST',
      data: currentFilterPayload(), // 不帶 vehicleId => 全部車
      dataType: 'json',
      success: function (data) {
        if (seq === detailsReqSeq) renderDetailsTable(data.details || []);
      },
    });
  }

  // ---------- 事件：左表選列 => 載入該車右表 ----------
  window.__carStatSelectedVehicleId = null;
  window.__carStatSelectedPlate = null;

  $(document).on('click', '#statisticsTable tbody tr.clickable-row', function () {
    // 用原生取 attribute，最穩
    const vehicleId = (this.getAttribute('data-vehicle-id') || '').trim();
    if (!vehicleId) return;

    $(this).addClass('table-active').siblings().removeClass('table-active');
    window.__carStatSelectedVehicleId = vehicleId;
    // 先取 data-plate，沒有就從第2格拿
    window.__carStatSelectedPlate =
      this.getAttribute('data-plate') || $(this).find('td').eq(1).text().trim();

    fetchDetails(vehicleId);
  });

  $(document).on('keydown', '#statisticsTable tbody tr.clickable-row', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      this.click();
    }
  });

// 回頂端：用 jQuery，不要再綁 DOMContentLoaded（事件已經發生）
const $backTop = $('#btnBackToTop');
if ($backTop.length) {
  $(window)
    .off('scroll.backTop')
    .on('scroll.backTop', function () {
      $backTop.toggle($(this).scrollTop() > 200);
    });

  $backTop.off('click').on('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // 若載入時已經在下方，立刻判斷一次
  $(window).trigger('scroll.backTop');

  // 開啟列印 Modal 時先隱藏，關閉後依滾動狀態再顯示
  $('#printModal')
    .on('show.bs.modal', function(){ $backTop.hide(); })
    .on('hidden.bs.modal', function(){ $(window).trigger('scroll.backTop'); });
}



  // ===== 取消選取：點擊表格外，但要排除列印相關 =====
let __suppressClear = false;

// 列印 Modal 開關期間，不做清除
$('#printModal')
  .on('show.bs.modal', function () { __suppressClear = true; })
  .on('hidden.bs.modal', function () { __suppressClear = false; });

$(document)
  .off('click.carstatOutside')
  .on('click.carstatOutside', function (e) {
    if (__suppressClear) return; // Modal 開著時不清除

    const $t = $(e.target);

    if ($t.closest('#statisticsTable').length) return; // 左表內
    if ($t.closest('#printModal, .modal-backdrop').length) return; // Modal 或遮罩
    if ($t.closest('[data-bs-target="#printModal"]').length) return; // 打開 Modal 的按鈕
    if ($t.closest('#printGo').length) return; // 列印執行鈕
    if ($t.closest('#printBtn').length) return; // 若你的開啟鈕有這個 id

    clearSelectionAndShowAll();
  });


  // ---------- 事件：查詢條件動態切換（任何改變即觸發載入） ----------
  function rebuildSecondaryInputs() {
    const t = $filterType.val();
    $secondary.empty();
    $tertiary.empty();

    if (t === 'year') {
      $secondary.append(
        `<select id="yearSelect" class="form-select">${yearOptions()}</select>`,
      );
    } else if (t === 'half_year') {
      $secondary.append(
        `<select id="halfYearSelect" class="form-select">${yearOptions()}</select>`,
      );
      $tertiary.append(`
        <select id="halfSelect" class="form-select">
          <option value="1">上半年</option>
          <option value="2">下半年</option>
        </select>
      `);
    } else if (t === 'month') {
      // 注意：monthYearSelect 要放年份選項（之前有人寫錯放了月份）
      $secondary.append(
        `<select id="monthYearSelect" class="form-select">${yearOptions()}</select>`,
      );
      $tertiary.append(
        `<select id="monthSelect" class="form-select">${monthOptions()}</select>`,
      );
    }
  }

  $filterType.on('change', function () {
    rebuildSecondaryInputs();
    bindDynamicChangeAndLoad(); // 綁定並立即載入一次
  });

  function bindDynamicChangeAndLoad() {
    // 先解除舊綁定再重綁
    $('#yearSelect, #halfYearSelect, #halfSelect, #monthYearSelect, #monthSelect')
      .off('change.carstat')
      .on('change.carstat', function () {
        loadStatistics();
      });

    loadStatistics();
  }

  // --------- 列印（Modal） ----------
function buildFilterQuery() {
  const p = new URLSearchParams();
  const val = (sel) => ($(sel).length ? $(sel).val() : null);

  p.set('filterType', $('#filterType').val() || 'all');
  [
    ['#yearSelect','year'],
    ['#halfYearSelect','halfYear'],
    ['#halfSelect','half'],
    ['#quarterYearSelect','quarterYear'],
    ['#quarterSelect','quarter'],
    ['#monthYearSelect','monthYear'],
    ['#monthSelect','month'],
  ].forEach(([sel,key])=>{
    const v = val(sel);
    if (v !== null && v !== '') p.set(key, v);
  });

  const sd = $('#startDate').val(); if (sd) p.set('startDate', sd);
  const ed = $('#endDate').val();   if (ed) p.set('endDate', ed);
  return p.toString();
}

function updatePrintModalState() {
  const isDetails = $('input[name="printType"]:checked').val() === 'details';
  const hasSelected = !!window.__carStatSelectedVehicleId;

  // 只有「各車維修明細」才顯示勾選區
  $('#printCurrentVehicleWrap').toggle(isDetails);

  // 有選車 -> 可勾；沒選車 -> 取消勾選並禁用
  if (!hasSelected) {
    $('#printCurrentVehicle').prop({ checked: false, disabled: true });
  } else {
    $('#printCurrentVehicle').prop({ disabled: false });
    // 不自動勾選，是否勾選由 show.bs.modal 的預設邏輯決定
  }

  // 提示
  const $hint = $('#currentVehicleHint');
  if (hasSelected) {
    const plate = window.__carStatSelectedPlate ? `（${window.__carStatSelectedPlate}）` : '';
    $hint.text(`目前選擇：${window.__carStatSelectedVehicleId}${plate}`);
  } else {
    $hint.text('尚未選擇車輛（請在左側列表點選一列）');
  }
}

// 打開 Modal：依是否有選車輛，設定預設選項
$('#printModal').off('show.bs.modal.prefill').on('show.bs.modal.prefill', function () {
  const hasSelected = !!window.__carStatSelectedVehicleId;

  if (hasSelected) {
    $('input[name="printType"][value="details"]').prop('checked', true);
    $('#printCurrentVehicle').prop({ checked: true, disabled: false });
  } else {
    $('input[name="printType"][value="summary"]').prop('checked', true);
    $('#printCurrentVehicle').prop({ checked: false, disabled: true });
  }
  updatePrintModalState();
});

// 切換「維修統計表 / 各車維修明細」
$(document).off('change.printType').on('change.printType', 'input[name="printType"]', function () {
  updatePrintModalState();
});


// 執行列印
$(document).on('click', '#printGo', function () {
  // 先檢查查詢條件
  const filterType = $('#filterType').val();
  const halfYear   = $('#halfSelect').val(); // 半年度選擇（1=上半年, 2=下半年）

  if (filterType !== 'half_year' || !halfYear) {
    alert('僅提供半年度列印，請選擇半年度統計期間');
    return;
  }

  const type = $('input[name="printType"]:checked').val();
  const qsBase = buildFilterQuery();

  let url =
    (type === 'details' ? 'car_statistics_details_pdf.php' : 'car_statistics_summary_pdf.php')
    + '?' + qsBase;

  if (type === 'details') {
    const onlyCurrent = $('#printCurrentVehicle').is(':checked');

    if (onlyCurrent) {
      const vid = (window.__carStatSelectedVehicleId || '').toString().trim();
      if (!vid) {
        alert('請先在左側列表選擇車輛');
        return;
      }
      url += (qsBase ? '&' : '') + 'vehicleId=' + encodeURIComponent(vid);
    }
  }

  window.open(url, '_blank');

  const modalEl = document.getElementById('printModal');
  if (modalEl) {
    const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    inst.hide();
  }
});


  // ---------- 初始化 ----------
  $filterType.val('all').trigger('change'); // 會重建選單並自動 loadStatistics()
});
