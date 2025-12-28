// Public/assets/js/equ_statistics.js
document.addEventListener('DOMContentLoaded', function () {
  const $filterType = document.getElementById('filterType');
  const $secondary  = document.getElementById('secondaryFilter');
  const $tertiary   = document.getElementById('tertiaryFilter');
  const $statsBody  = document.querySelector('#statisticsTable tbody');
  const $detBody    = document.querySelector('#repairTable tbody');
  const $totalAmt   = document.getElementById('totalAmount');
  const $btnPrint   = document.getElementById('btnPrint');

  let currentVendorKey = '';
  let $activeRow = null;

  // ===== Filters =====
  function buildYearSelect(id = 'year', backYears = 6) {
    const now = new Date();
    const y = now.getFullYear();                       // 今年為最大
    const sel = document.createElement('select');
    sel.className = 'form-select';
    sel.id = id;
    let html = '';
    for (let i = y; i >= y - backYears; i--) {
      html += `<option value="${i}">${i} 年</option>`;
    }
    sel.innerHTML = html;
    return sel;
  }
  function buildHalfSelect() {
    const sel = document.createElement('select');
    sel.className = 'form-select';
    sel.id = 'half';
    sel.innerHTML = `<option value="1">上半年</option><option value="2">下半年</option>`;
    return sel;
  }
  function buildMonthSelect() {
    const sel = document.createElement('select');
    sel.className = 'form-select';
    sel.id = 'month';
    sel.innerHTML = Array.from({length:12}, (_,i)=>i+1)
      .map(m => `<option value="${m}">${m} 月</option>`).join('');
    return sel;
  }

  function renderFilters() {
    $secondary.innerHTML = '';
    $tertiary.innerHTML  = '';
    const type = $filterType.value;

    if (type === 'year') {
      $secondary.appendChild(buildYearSelect('year'));
    } else if (type === 'half_year') {
      $secondary.appendChild(buildYearSelect('year'));
      $tertiary.appendChild(buildHalfSelect());
    } else if (type === 'month') {
      $secondary.appendChild(buildYearSelect('monthYear'));
      $tertiary.appendChild(buildMonthSelect());
    }
  }

  // ===== Backend =====
  async function fetchData() {
    const type = $filterType.value;
    const params = new URLSearchParams();
    params.set('filterType', type);

    if (type === 'year') {
      params.set('year', String(document.getElementById('year')?.value || ''));
    } else if (type === 'half_year') {
      params.set('halfYear', String(document.getElementById('year')?.value || ''));
      params.set('half',     String(document.getElementById('half')?.value || ''));
    } else if (type === 'month') {
      params.set('monthYear', String(document.getElementById('monthYear')?.value || ''));
      params.set('month',     String(document.getElementById('month')?.value || ''));
    }
    if (currentVendorKey) params.set('vendorKey', currentVendorKey);

    const res = await fetch('equ_statistics_backend.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8' },
      body: params.toString()
    });
    if (!res.ok) throw new Error('backend error');
    return res.json();
  }

  function nf(n) { n = Number(n||0); return n ? n.toLocaleString() : '0'; }

  // ===== 左表 =====
  function renderStatistics(list) {
    $statsBody.innerHTML = '';
    const frag = document.createDocumentFragment();
    (list || []).forEach((row, idx) => {
      const tr = document.createElement('tr');
      tr.className = 'vendor-row';
      tr.setAttribute('tabindex', '0');
      tr.setAttribute('role', 'button');
      tr.dataset.vendorKey = row.vendor_key || '';
      tr.innerHTML = `
        <td>${idx + 1}</td>
        <td class="text-center">${row.vendor_name}</td>
        <td>${nf(row.total_repairs)}</td>
        <td class="text-end">${nf(row.total_repair_cost)} 元</td>
        <td class="text-end">${nf(row.total_company_burden)} 元</td>
      `;
      frag.appendChild(tr);
    });
    $statsBody.appendChild(frag);

    $statsBody.onclick = function (e) {
      const tr = e.target.closest('tr.vendor-row');
      if (!tr) return;
      toggleVendorRow(tr);
    };
    $statsBody.onkeydown = function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const tr = e.target.closest('tr.vendor-row');
      if (!tr) return;
      e.preventDefault();
      toggleVendorRow(tr);
    };

    highlightActiveRow();
  }

  function highlightActiveRow() {
    if ($activeRow) $activeRow.classList.remove('table-active');
    if (!currentVendorKey) { $activeRow = null; return; }
    const tr = [...$statsBody.querySelectorAll('tr.vendor-row')]
      .find(x => x.dataset.vendorKey === currentVendorKey);
    if (tr) { $activeRow = tr; $activeRow.classList.add('table-active'); }
    else { $activeRow = null; }
  }

  function toggleVendorRow(tr) {
    const key = tr.dataset.vendorKey || '';
    currentVendorKey = (currentVendorKey === key) ? '' : key;
    loadAll();
  }

  // 點擊表格外 → 取消選取
  document.addEventListener('click', function (e) {
    const insideTable = e.target.closest('#statisticsTable');
    if (!insideTable && currentVendorKey) {
      currentVendorKey = '';
      highlightActiveRow();
      loadAll();
    }
  });

  // ===== 右表 =====
  function renderDetails(list) {
    $detBody.innerHTML = '';
    const frag = document.createDocumentFragment();
    (list || []).forEach(row => {
      const title = (row.itemsTitle || '').replace(/"/g,'&quot;');
      const items = row.itemsSummary || '';
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${row.repair_date || ''}</td>
        <td class="text-center">${row.vendor_name || ''}</td>
        <td class="text-center">${row.machine_name || ''}</td>
        <td class="text-start" title="${title}">${items}</td>
        <td class="text-end">${nf(row.repair_cost)} 元</td>
        <td class="text-end">${nf(row.company_burden)} 元</td>
      `;
      frag.appendChild(tr);
    });
    $detBody.appendChild(frag);
  }

  function renderTotals(totalRepairCost, totalCompanyBurden) {
    if ($totalAmt) {
      $totalAmt.textContent = `維修金額 ${nf(totalRepairCost)} 元｜公司負擔 ${nf(totalCompanyBurden)} 元`;
    }
  }

  async function loadAll() {
    try {
      const data = await fetchData();
      renderStatistics(data.statistics || []);
      renderDetails(data.details || []);
      renderTotals(data.totalRepairCost || 0, data.totalCompanyBurden || 0);
    } catch (e) {
      console.error(e);
    }
  }

  // ✅ 只有 filterType 改變時才重建 UI；其它改變只重新查詢
  document.getElementById('filterForm').addEventListener('change', function (e) {
    if (e.target && e.target.id === 'filterType') {
      currentVendorKey = '';
      renderFilters();
    }
    loadAll();
  });

  // ===== 列印（直接開 PDF；非半年度則提示） =====
  if ($btnPrint) {
    $btnPrint.addEventListener('click', function () {
      const type = $filterType.value;
      if (type !== 'half_year') {
        alert('僅提供半年度列印');
        return;
      }
      const y = document.getElementById('year')?.value || '';
      const h = document.getElementById('half')?.value || '';
      if (!y || !h) {
        alert('請在半年度查詢中選擇年份與上／下半年。');
        return;
      }
      const url = `equ_statistics_summary_pdf.php?filterType=half_year&halfYear=${encodeURIComponent(y)}&half=${encodeURIComponent(h)}`;
      window.open(url, '_blank');
    });
  }

  // 初始化
  renderFilters();
  loadAll();

  // ===== 回頂端 =====
const backTopBtn = document.getElementById('btnBackToTop');
if (backTopBtn) {
  window.addEventListener('scroll', () => {
    backTopBtn.style.display = window.scrollY > 200 ? 'block' : 'none';
  });
  backTopBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

});
