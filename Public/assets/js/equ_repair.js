// Public/assets/js/equ_repair.js
document.addEventListener('DOMContentLoaded', function () {
  // =============================
  // 工具：項目列 / 合計
  // =============================
  function createItemRow(content = '', amount = null, company = null) {
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center item-row mt-2';
    const amountAttr  = amount  === null || amount  === undefined ? '' : ` value="${amount}"`;
    const companyAttr = company === null || company === undefined ? '' : ` value="${company}"`;
    row.innerHTML = `
      <div class="col-6">
        <input type="text" class="form-control item-content" placeholder="維修內容" value="${content ?? ''}" required>
      </div>
      <div class="col-2">
        <input type="number" class="form-control item-amount" placeholder="維修金額" step="1" min="0"${amountAttr} required>
      </div>
      <div class="col-3">
        <input type="number" class="form-control item-company" placeholder="公司負擔金額" step="1" min="0"${companyAttr}>
      </div>
      <div class="col-1">
        <button type="button" class="btn btn-outline-danger w-100 btnRemoveItem" title="刪除此項目">&times;</button>
      </div>
    `;
    return row;
  }

  function collectItems(container) {
    const items = [];
    container.querySelectorAll('.item-row').forEach(r => {
      const content = r.querySelector('.item-content')?.value?.trim() || '';
      const amount  = parseFloat(r.querySelector('.item-amount')?.value  || '0') || 0;
      const company = parseFloat(r.querySelector('.item-company')?.value || '0') || 0;
      if (content || amount > 0 || company > 0) items.push({ content, amount, company });
    });
    return items;
  }

  function calcTotals(items) {
    let sum = 0, sumCompany = 0;
    items.forEach(it => {
      sum += Number(it.amount || 0);
      sumCompany += Number(it.company || 0);
    });
    return { repair_cost: Math.round(sum), company_burden: Math.round(sumCompany) };
  }

  function ensureAtLeastOneRow(container) {
    if (container && container.querySelectorAll('.item-row').length === 0) {
      container.appendChild(createItemRow());
    }
  }

  function updateTotalsFor(containerId, totalCostId, totalCompanyId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const items = collectItems(container);
    const totals = calcTotals(items);
    const totalCost = document.getElementById(totalCostId);
    const totalCom  = document.getElementById(totalCompanyId);
    if (totalCost) totalCost.value = totals.repair_cost;
    if (totalCom)  totalCom.value  = totals.company_burden;
  }

  // =============================
  // 新增 Modal
  // =============================
  const addModal = document.getElementById('addRepairModal');
  if (addModal) {
    addModal.addEventListener('show.bs.modal', () => {
      const form = addModal.querySelector('form');
      if (form) form.reset();
    });
    addModal.addEventListener('shown.bs.modal', () => {
      ensureAtLeastOneRow(document.getElementById('itemsContainer'));
      updateTotalsFor('itemsContainer', 'addRepairCost', 'addCompanyBurden');
    });
    const addForm = addModal.querySelector('form');
    if (addForm) {
      addForm.addEventListener('submit', () => {
        const items = collectItems(document.getElementById('itemsContainer'));
        const totals = calcTotals(items);
        document.getElementById('addItemsJson').value = JSON.stringify(items);
        document.getElementById('addRepairCost').value = totals.repair_cost;
        document.getElementById('addCompanyBurden').value = totals.company_burden;
      });
    }
  }

  // =============================
  // 編輯 Modal
  // =============================
  const editModal = document.getElementById('editRepairModal');
  if (editModal) {
    editModal.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      if (!btn) return;

      const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };

      setVal('editRepairId',       btn.getAttribute('data-repair_id'));
      setVal('editRepairDate',     btn.getAttribute('data-repair_date'));
      setVal('editRepairContent',  btn.getAttribute('data-repair_content') || '');
      setVal('editRepairCost',     btn.getAttribute('data-repair_cost')    || '0');
      setVal('editCompanyBurden',  btn.getAttribute('data-company_burden') || '0');

      const cat = btn.getAttribute('data-category') || '';
      const rMaintain = document.getElementById('editCatMaintain');
      const rRepair   = document.getElementById('editCatRepair');
      if (rMaintain && rRepair) {
        rMaintain.checked = (cat === '保養');
        rRepair.checked   = (cat === '維修');
      }

      const v = document.getElementById('editVendorName');
      if (v) v.value = btn.getAttribute('data-vendor_name') || '';
      const m = document.getElementById('editMachineName');
      if (m) m.value = btn.getAttribute('data-machine_name') || '';

      // items_json base64 → JSON
      let items = [];
      try {
        const b64 = btn.getAttribute('data-items-b64') || '';
        if (b64) {
          const bin = atob(b64);
          const bytes = new Uint8Array(bin.length);
          for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
          const jsonText = typeof TextDecoder !== 'undefined'
            ? new TextDecoder('utf-8').decode(bytes)
            : decodeURIComponent(escape(bin));
          items = JSON.parse(jsonText);
        }
      } catch { items = []; }

      const container = document.getElementById('itemsContainerEdit');
      if (container) {
        container.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) items = [{ content: '', amount: null, company: null }];
        items.forEach(it => {
          const amt = (it.amount === 0 || it.amount) ? it.amount : null;
          const com = (Number(it.company) > 0) ? it.company : null;
          container.appendChild(createItemRow(it.content ?? '', amt, com));
        });
      }
      updateTotalsFor('itemsContainerEdit', 'editRepairCost', 'editCompanyBurden');
    });

    const editForm = editModal.querySelector('form');
    if (editForm) {
      editForm.addEventListener('submit', () => {
        const items = collectItems(document.getElementById('itemsContainerEdit'));
        const totals = calcTotals(items);
        document.getElementById('editItemsJson').value    = JSON.stringify(items);
        document.getElementById('editRepairCost').value   = totals.repair_cost;
        document.getElementById('editCompanyBurden').value= totals.company_burden;
      });
    }
  }

  // =============================
  // 刪除 Modal
  // =============================
  const delModal = document.getElementById('deleteRepairModal');
  if (delModal) {
    delModal.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      const idEl = document.getElementById('deleteRepairId');
      if (btn && idEl) idEl.value = btn.getAttribute('data-repair_id');
    });
  }

  // =============================
  // 列增刪 & 即時計算
  // =============================
  document.addEventListener('click', function (e) {
    if (e.target.id === 'btnAddItem' || e.target.id === 'btnAddItemEdit') {
      const containerId = e.target.id === 'btnAddItem' ? 'itemsContainer' : 'itemsContainerEdit';
      const container = document.getElementById(containerId);
      if (container) {
        container.appendChild(createItemRow());
        updateTotalsFor(
          containerId,
          containerId === 'itemsContainer' ? 'addRepairCost' : 'editRepairCost',
          containerId === 'itemsContainer' ? 'addCompanyBurden' : 'editCompanyBurden'
        );
      }
    }
    if (e.target.classList.contains('btnRemoveItem')) {
      const row = e.target.closest('.item-row');
      const container = row?.parentElement;
      row?.remove();
      if (container) {
        ensureAtLeastOneRow(container);
        const isAdd = container.id === 'itemsContainer';
        updateTotalsFor(
          container.id,
          isAdd ? 'addRepairCost' : 'editRepairCost',
          isAdd ? 'addCompanyBurden' : 'editCompanyBurden'
        );
      }
    }
  });

  document.addEventListener('input', function (e) {
    const addC  = document.getElementById('itemsContainer');
    const editC = document.getElementById('itemsContainerEdit');
    if (addC  && addC.contains(e.target))  updateTotalsFor('itemsContainer', 'addRepairCost', 'addCompanyBurden');
    if (editC && editC.contains(e.target)) updateTotalsFor('itemsContainerEdit', 'editRepairCost', 'editCompanyBurden');
  });

  // =============================
  // 動態提示（vendors / machines）
  // =============================
  (function () {
    const API = 'equ_repair_suggest.php';

    const datalistIds = {
      vendors:  document.getElementById('hintVendors')  ? 'hintVendors'  : (document.getElementById('vendorList')  ? 'vendorList'  : null),
      machines: document.getElementById('hintMachines') ? 'hintMachines' : (document.getElementById('machineList') ? 'machineList' : null),
    };

    const $addVendor    = document.getElementById('addVendorName');
    const $editVendor   = document.getElementById('editVendorName');
    const $addMachine   = document.getElementById('addMachineName');
    const $editMachine  = document.getElementById('editMachineName');

    function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), ms); }; }

    function fillDatalistById(listId, items) {
      if (!listId) return;
      const dl = document.getElementById(listId);
      if (!dl) return;
      dl.innerHTML = '';
      const frag = document.createDocumentFragment();
      (items || []).forEach(v => { const opt = document.createElement('option'); opt.value = v; frag.appendChild(opt); });
      dl.appendChild(frag);
    }

    async function fetchHints(kind, q) {
      const tryOnce = async (qq) => {
        const url = `${API}?kind=${encodeURIComponent(kind)}&q=${encodeURIComponent(qq)}`;
        try {
          const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
          if (!res.ok) return [];
          const data = await res.json().catch(() => ({ items: [] }));
          return Array.isArray(data.items) ? data.items : [];
        } catch { return []; }
      };
      if (q === '' || q == null) {
        let items = await tryOnce('');
        if (!items.length) items = await tryOnce('*');
        return items;
      }
      return tryOnce(q);
    }

    const preloaded = { vendors: false, machines: false };

    async function preloadOnFocus(inputEl, kind) {
      const listId = datalistIds[kind];
      if (!listId || !inputEl) return;
      if (preloaded[kind]) return;
      const items = await fetchHints(kind, '');
      fillDatalistById(listId, items);
      preloaded[kind] = true;

      if (document.activeElement === inputEl) {
        inputEl.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }

    const onVendorInput  = debounce(async (e) => {
      const listId = datalistIds.vendors;
      if (!listId) return;
      const items = await fetchHints('vendors', e.target.value || '');
      fillDatalistById(listId, items);
    }, 180);

    const onMachineInput = debounce(async (e) => {
      const listId = datalistIds.machines;
      if (!listId) return;
      const items = await fetchHints('machines', e.target.value || '');
      fillDatalistById(listId, items);
    }, 180);

    [$addVendor, $editVendor].forEach(inp => inp && inp.addEventListener('input', onVendorInput));
    [$addMachine, $editMachine].forEach(inp => inp && inp.addEventListener('input', onMachineInput));

    [$addVendor, $editVendor].forEach(inp => inp && inp.addEventListener('focus', () => preloadOnFocus(inp, 'vendors')));
    [$addMachine, $editMachine].forEach(inp => inp && inp.addEventListener('focus', () => preloadOnFocus(inp, 'machines')));
  })();
});
