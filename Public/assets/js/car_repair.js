document.addEventListener('DOMContentLoaded', function () {
  // ===== 共用：建立一列項目（內容 / 金額 / 公司負擔） =====
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

  // 收集容器中所有項目
  function collectItems(container) {
    const items = [];
    container.querySelectorAll('.item-row').forEach(r => {
      const content = r.querySelector('.item-content')?.value?.trim() || '';
      const amount  = parseFloat(r.querySelector('.item-amount')?.value  || '0') || 0;
      const company = parseFloat(r.querySelector('.item-company')?.value || '0') || 0;
      if (content || amount > 0 || company > 0) {
        items.push({ content, amount, company });
      }
    });
    return items;
  }

  // 計算整單合計（四捨五入取整數）
  function calcTotals(items) {
    let sum = 0, sumCompany = 0;
    items.forEach(it => {
      sum += Number(it.amount || 0);
      sumCompany += Number(it.company || 0);
    });
    return { repair_cost: Math.round(sum), company_burden: Math.round(sumCompany) };
  }

  function ensureAtLeastOneRow(container) {
    if (!container) return;
    if (container.querySelectorAll('.item-row').length === 0) {
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

  // ===== 編輯 Modal =====
  const editRepairModal = document.getElementById('editRepairModal');
  if (editRepairModal) {
    editRepairModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;

      // 基本欄位
      document.getElementById('editRepairId').value      = button.getAttribute('data-repair_id');
      document.getElementById('editVehicleId').value     = button.getAttribute('data-vehicle_id');
      document.getElementById('editRepairDate').value    = button.getAttribute('data-repair_date');
      document.getElementById('editRepairContent').value = button.getAttribute('data-repair_content') || '';
      document.getElementById('editRepairCost').value    = button.getAttribute('data-repair_cost') || '';
      const editVendor = document.getElementById('editVendor');
      if (editVendor) editVendor.value = button.getAttribute('data-vendor') || '';
      const editCompanyTotal = document.getElementById('editCompanyBurden');
      if (editCompanyTotal) editCompanyTotal.value = button.getAttribute('data-company_burden') || '';

      // items_json（Base64）→ UTF-8 → 物件陣列
      let items = [];
      try {
        const b64 = button.getAttribute('data-items-b64') || '';
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

      // 產生列（舊資料沒有 company → 視為 0）
      const container = document.getElementById('itemsContainerEdit');
      container.innerHTML = '';
      if (!Array.isArray(items) || items.length === 0) {
        items = [{ content: '', amount: null, company: null }];
      }
      items.forEach(it => {
        const amt = (it.amount === 0 || it.amount) ? it.amount : null;
        const com = (Number(it.company) > 0) ? it.company : null;
        container.appendChild(createItemRow(it.content ?? '', amt, com));
      });

      updateTotalsFor('itemsContainerEdit', 'editRepairCost', 'editCompanyBurden');
    });

    // 編輯送出：序列化 items_json + 合計
    const editForm = editRepairModal.querySelector('form');
    if (editForm) {
      editForm.addEventListener('submit', () => {
        const container = document.getElementById('itemsContainerEdit');
        const items = collectItems(container);
        const totals = calcTotals(items);
        document.getElementById('editItemsJson').value = JSON.stringify(items);
        document.getElementById('editRepairCost').value = totals.repair_cost;
        const tCom = document.getElementById('editCompanyBurden');
        if (tCom) tCom.value = totals.company_burden;
      });
    }
  }

  // ===== 刪除 Modal（原樣） =====
  const deleteRepairModal = document.getElementById('deleteRepairModal');
  if (deleteRepairModal) {
    deleteRepairModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (button) document.getElementById('deleteRepairId').value = button.getAttribute('data-repair_id');
    });
  }

  // ===== 新增 Modal =====
  const addRepairModal = document.getElementById('addRepairModal');
  if (addRepairModal) {
    addRepairModal.addEventListener('show.bs.modal', function () {
      const form = addRepairModal.querySelector('form');
      if (form) form.reset();
      const container = document.getElementById('itemsContainer');
      if (container) {
        container.innerHTML = '';
        container.appendChild(createItemRow());
      }
      addRepairModal.querySelectorAll('input[name="category"]').forEach(r => (r.checked = false));
      const block = document.getElementById('mileageBlock');
      const input = document.getElementById('addMileage');
      if (block) block.style.display = 'none';
      if (input) { input.required = false; input.value = ''; }
    });

    addRepairModal.addEventListener('shown.bs.modal', function () {
      ensureAtLeastOneRow(document.getElementById('itemsContainer'));
      updateTotalsFor('itemsContainer', 'addRepairCost', 'addCompanyBurden');
    });

    const addForm = addRepairModal.querySelector('form');
    if (addForm) {
      addForm.addEventListener('submit', () => {
        const container = document.getElementById('itemsContainer');
        const items = collectItems(container);
        const totals = calcTotals(items);
        document.getElementById('addItemsJson').value = JSON.stringify(items);
        document.getElementById('addRepairCost').value = totals.repair_cost;
        const tCom = document.getElementById('addCompanyBurden');
        if (tCom) tCom.value = totals.company_burden;
      });
    }
  }

  // ===== 事件委派：新增/刪除列（新增與編輯共用） =====
  document.addEventListener('click', function (e) {
    // + 新增項目
    if (e.target.id === 'btnAddItem' || e.target.id === 'btnAddItemEdit') {
      const containerId = (e.target.id === 'btnAddItem') ? 'itemsContainer' : 'itemsContainerEdit';
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

    // × 刪除列
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

  // ===== 即時輸入時重新計算合計（新增/編輯） =====
  document.addEventListener('input', function (e) {
    if (!e.target.classList) return;
    if (
      e.target.classList.contains('item-amount') ||
      e.target.classList.contains('item-company')
    ) {
      const container = e.target.closest('#itemsContainer, #itemsContainerEdit');
      if (!container) return;
      const isAdd = container.id === 'itemsContainer';
      updateTotalsFor(
        container.id,
        isAdd ? 'addRepairCost' : 'editRepairCost',
        isAdd ? 'addCompanyBurden' : 'editCompanyBurden'
      );
    }
  });
});

// ===== 維修類別/里程數：新增視窗 =====
(function () {
  const mileageBlock = document.getElementById('mileageBlock');
  const mileageInput = document.getElementById('addMileage');
  const catMaintain = document.getElementById('catMaintain');
  const catRepair = document.getElementById('catRepair');

  function toggleMileageByCategory(val) {
    const isMaintain = val === '保養';
    if (mileageBlock) mileageBlock.style.display = isMaintain ? '' : 'none';
    if (mileageInput) {
      mileageInput.required = isMaintain;
      if (!isMaintain) mileageInput.value = '';
    }
  }

  if (catMaintain && catRepair) {
    [catMaintain, catRepair].forEach((r) =>
      r.addEventListener('change', () => toggleMileageByCategory(r.value)),
    );
  }

  // 每次開啟新增 Modal：重設表單、取消 radio 勾選、隱藏里程
  const addRepairModal = document.getElementById('addRepairModal');
  if (addRepairModal) {
    addRepairModal.addEventListener('show.bs.modal', () => {
      const form = addRepairModal.querySelector('form');
      if (form) form.reset();
      addRepairModal.querySelectorAll('input[name="category"]').forEach(r => (r.checked = false));
      toggleMileageByCategory('');
    });
  }
})();

// ===== 維修類別/里程數：編輯視窗 =====
(function () {
  const editRepairModal = document.getElementById('editRepairModal');
  if (!editRepairModal) return;

  const mileageBlockEdit = document.getElementById('mileageBlockEdit');
  const mileageInputEdit = document.getElementById('editMileage');
  const editCatMaintain = document.getElementById('editCatMaintain');
  const editCatRepair = document.getElementById('editCatRepair');

  function toggleMileageEdit(val) {
    const isMaintain = val === '保養';
    if (mileageBlockEdit) mileageBlockEdit.style.display = isMaintain ? '' : 'none';
    if (mileageInputEdit) {
      mileageInputEdit.required = isMaintain;
      if (!isMaintain) mileageInputEdit.value = '';
    }
  }

  // Modal 開啟時帶入 category/mileage
  editRepairModal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    if (!btn) return;
    const category = btn.getAttribute('data-category') || '';
    const mileage  = btn.getAttribute('data-mileage') || '';

    if (category === '保養') {
      editCatMaintain.checked = true;
      editCatRepair.checked = false;
    } else if (category === '維修') {
      editCatMaintain.checked = false;
      editCatRepair.checked = true;
    } else {
      editCatMaintain.checked = false;
      editCatRepair.checked = false;
    }
    if (mileageInputEdit) mileageInputEdit.value = mileage;
    toggleMileageEdit(category);
  });

  // 切換時動態控制
  [editCatMaintain, editCatRepair].forEach((radio) => {
    if (radio) {
      radio.addEventListener('change', () => toggleMileageEdit(radio.value));
    }
  });
})();
