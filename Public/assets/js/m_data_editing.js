// 讓 JS 能穩定拿到 Public 根
const ASSET_BASE = (window.PUBLIC_BASE || '/') + 'assets/';

// ================= 承辦人員資料 ================= //
function loadPersonnelData() {
  fetch('m_data_editing_backend.php?action=fetch')
    .then((r) => r.json())
    .then((data) => {
      const tableBody = document.getElementById('personnelTableBody');
      if (!tableBody) return console.error('未找到 id="personnelTableBody" 的元素');
      tableBody.innerHTML = '';

      data.forEach((row) => {
        const tr = document.createElement('tr');
        tr.dataset.id = row.id;
        tr.innerHTML = `
                    <td class="align-middle">${row.shift_name}</td>
                    <td class="align-middle">${row.personnel_name}</td>
                    <td class="align-middle">
                        <button class="btn btn-sm btn-primary" onclick="editPersonnel('${row.id}', '${row.personnel_name}')">編輯</button>
                    </td>
                `;
        tableBody.appendChild(tr);
      });

      const personnelForTitle = data.find((row) => Number(row.id) === 4);
      const personnelName = personnelForTitle ? personnelForTitle.personnel_name : '未指定';
      const titleElement = document.getElementById('dynamicPersonnelName');
      if (titleElement) titleElement.textContent = personnelName;
    })
    .catch((err) => console.error('Error loading personnel data:', err));
}

function editPersonnel(id, currentName) {
  Swal.fire({
    title: '編輯承辦人員',
    input: 'text',
    inputLabel: '請輸入新的承辦人姓名',
    inputValue: currentName,
    showCancelButton: true,
    confirmButtonText: '更新',
    cancelButtonText: '取消',
    inputValidator: (v) => !v && '姓名不得為空！',
  }).then((res) => {
    if (!res.isConfirmed) return;
    fetch('m_data_editing_backend.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'update', id, personnel_name: res.value }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          Swal.fire('成功', '承辦人已更新', 'success');
          loadPersonnelData();
        } else {
          Swal.fire('錯誤', data.error || data.message, 'error');
        }
      })
      .catch((err) => {
        Swal.fire('錯誤', '更新失敗，請稍後再試', 'error');
        console.error('Error updating personnel:', err);
      });
  });
}

// ================= 材料對帳資料（表格架構） ================= //
function loadReconciliationData() {
  fetch('m_data_editing_backend.php?action=fetch_material')
    .then((r) => r.json())
    .then((data) => {
      const tableBody = document.getElementById('reconciliationTableBody');
      tableBody.innerHTML = '';
      data.forEach((row) => {
        const tr = document.createElement('tr');
        tr.dataset.id = row.id;
        tr.innerHTML = `
                    <td class="align-middle">${row.material_name}</td>
                    <td>
                        <input type="number" class="form-control text-center"
                               data-column="${row.reference_column}"
                               placeholder="輸入對帳數量">
                    </td>
                `;
        tableBody.appendChild(tr);
      });

      // ✅ 如果日期欄已有值，載入該日的對帳數量
      const t = document.getElementById('withdraw_time')?.value;
      if (t) loadReconciliationValuesForDate(t);
    })
    .catch((err) => console.error('Error loading reconciliation data:', err));
}

// ✅ 新增：依日期把數值回填到左側表單，並更新 (資料時間：)
function loadReconciliationValuesForDate(withdrawTime) {
  if (!withdrawTime) return;
  fetch(
    `m_data_editing_backend.php?action=fetch_reconciliation&withdraw_time=${encodeURIComponent(withdrawTime)}`,
  )
    .then((r) => r.json())
    .then((res) => {
      const inputs = document.querySelectorAll('#reconciliationTableBody input[data-column]');
      const dateLabel = document.getElementById('dynamicDate');

      if (res.success && res.data) {
        inputs.forEach((input) => {
          const col = input.dataset.column;
          input.value = (res.data[col] ?? '') === null ? '' : (res.data[col] ?? '');
        });
        const dt = res.data.withdraw_time
          ? String(res.data.withdraw_time).slice(0, 10)
          : withdrawTime;
        if (dateLabel) dateLabel.textContent = `(資料時間：${dt})`;
      } else {
        // 找不到資料 → 清空欄位，清空日期提示
        inputs.forEach((input) => (input.value = ''));
        if (dateLabel) dateLabel.textContent = '(資料時間：)';
      }
    })
    .catch((err) => console.error('載入對帳數值失敗：', err));
}

document.getElementById('editMaterialsButton').addEventListener('click', () => {
  fetch('m_data_editing_backend.php?action=fetch_material')
    .then((r) => r.json())
    .then((data) => {
      const materialList = data
        .map(
          (row) => `
                <div class="sortable-item d-flex align-items-center" data-id="${row.id}">
                    <span class="drag-handle me-2">&#x2195;</span>
                    <input type="text" class="form-control material-name" value="${row.material_name}" />
                </div>
            `,
        )
        .join('');

      Swal.fire({
        title: '編輯材料順序',
        html: `
                    <p class="text-muted text-center mb-3">拖曳箭頭可調整順序，輸入框可編輯名稱</p>
                    <div id="sortableContainer" style="max-height:400px;overflow-y:auto;">${materialList}</div>
                `,
        showCancelButton: true,
        confirmButtonText: '保存',
        cancelButtonText: '取消',
        didOpen: () => {
          new Sortable(document.getElementById('sortableContainer'), {
            animation: 150,
            ghostClass: 'sortable-ghost',
            handle: '.drag-handle',
          });
        },
        preConfirm: () => {
          return Array.from(document.querySelectorAll('.sortable-item')).map((item, index) => ({
            id: item.dataset.id,
            material_name: item.querySelector('.material-name').value.trim(),
            new_order: index + 1,
          }));
        },
      }).then((result) => {
        if (!result.isConfirmed) return;
        fetch('m_data_editing_backend.php?action=reorder_materials', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(result.value),
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              Swal.fire('成功', '材料已更新', 'success');
              // 重新載入表格結構 + 依當前日期回填數值
              loadReconciliationData();
            } else {
              Swal.fire('錯誤', data.message, 'error');
            }
          })
          .catch((err) => {
            Swal.fire('錯誤', '更新失敗', 'error');
            console.error(err);
          });
      });
    })
    .catch((err) => console.error('Error loading materials:', err));
});

document.getElementById('updateReconciliationButton').addEventListener('click', () => {
  const withdrawTime = document.getElementById('withdraw_time').value;
  if (!withdrawTime) return Swal.fire('錯誤', '請選擇領退料時間', 'error');

  const payload = { withdraw_time: withdrawTime };
  document.querySelectorAll('#reconciliationTableBody input[data-column]').forEach((input) => {
    payload[input.dataset.column] = parseFloat(input.value) || 0;
  });

  fetch('m_data_editing_backend.php?action=update_reconciliation', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        Swal.fire('成功', '對帳資料已更新', 'success');
        // ✅ 立刻重新抓該日數值與狀態，畫面同步
        loadReconciliationValuesForDate(withdrawTime);
        updateStatus(withdrawTime);
      } else {
        Swal.fire('錯誤', data.message, 'error');
      }
    })
    .catch((err) => {
      Swal.fire('錯誤', '更新失敗，請稍後再試', 'error');
      console.error('Error updating reconciliation:', err);
    });
});

// ================= 日期與 Voucher ================= //
function loadUniqueDates() {
  fetch('m_data_editing_backend.php?action=fetch_dates_and_voucher_base')
    .then((r) => r.json())
    .then((data) => {
      const container = document.getElementById('dateCardContainer');
      container.innerHTML = '';
      if (data.success && data.data.length > 0) {
        const uniqueDates = new Set();
        data.data.forEach((item) => {
          if (!uniqueDates.has(item.unique_date)) {
            const card = document.createElement('div');
            card.className = 'date-card';
            card.textContent = item.unique_date;
            card.addEventListener('click', () => handleDateCardClick(item.unique_date));
            container.appendChild(card);
            uniqueDates.add(item.unique_date);
          }
        });
      } else {
        container.innerHTML = '<p class="text-center text-muted">無可用資料</p>';
      }
    })
    .catch((err) => console.error('Error loading dates:', err));
}

function handleDateCardClick(withdrawDate) {
  if (document.querySelector('.swal2-container')) return; // 防重複
  fetch(`m_data_editing_backend.php?action=fetch_voucher_by_date&withdraw_date=${withdrawDate}`)
    .then((r) => r.json())
    .then((data) => {
      if (data.success && data.data.length > 0) {
        const voucherList = data.data
          .map(
            (item) => `
                    <div class="voucher-item d-flex justify-content-between align-items-center">
                        <span>${item.voucher_base}</span>
                        <button class="btn btn-sm btn-danger delete-voucher-btn"
                                data-voucher="${item.voucher_base}" data-date="${withdrawDate}">刪除</button>
                    </div>
                `,
          )
          .join('');
        Swal.fire({
          title: `日期: ${withdrawDate}`,
          html: `<div class="voucher-list">${voucherList}</div>`,
          showCloseButton: true,
          didRender: bindDeleteVoucherEvents,
        });
      } else {
        Swal.fire('提示', '無數據', 'info');
      }
    })
    .catch((err) => {
      console.error('查詢失敗:', err);
      Swal.fire('錯誤', '查詢失敗，請稍後再試', 'error');
    });
}

function bindDeleteVoucherEvents() {
  document.querySelectorAll('.delete-voucher-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
      const voucher = this.dataset.voucher;
      const date = this.dataset.date;
      fetch('m_data_editing_backend.php?action=delete_voucher', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ voucher, withdraw_date: date }),
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) refreshVoucherList(date);
          else Swal.fire('錯誤', data.message, 'error');
        })
        .catch((err) => {
          console.error('刪除失敗:', err);
          Swal.fire('錯誤', '刪除失敗，請稍後再試', 'error');
        });
    });
  });
}

function refreshVoucherList(date) {
  fetch(`m_data_editing_backend.php?action=fetch_voucher_by_date&withdraw_date=${date}`)
    .then((r) => r.json())
    .then((data) => {
      const container = document.querySelector('.voucher-list');
      if (data.success && data.data.length > 0) {
        container.innerHTML = data.data
          .map(
            (item) => `
                    <div class="voucher-item d-flex justify-content-between align-items-center">
                        <span>${item.voucher_base}</span>
                        <button class="btn btn-sm btn-danger delete-voucher-btn"
                                data-voucher="${item.voucher_base}" data-date="${date}">刪除</button>
                    </div>
                `,
          )
          .join('');
        bindDeleteVoucherEvents();
      } else {
        Swal.close();
        loadUniqueDates();
      }
    })
    .catch((err) => {
      console.error('刷新彈窗內容失敗:', err);
      Swal.fire('錯誤', '刷新彈窗內容失敗，請稍後再試', 'error');
    });
}

// ================= 狀態（對帳/領料/退料/用餘） ================= //
function updateStatus(withdrawTime) {
  const statusContainer = document.getElementById('statusContainer');

  // 小工具：統一設定「前往各班統計表」連結（帶上日期並同步 localStorage）
  function setupGoStatisticsLink(d) {
    const goBtn = document.getElementById('go-statistics');
    if (!goBtn) return;
    const dateStr = d || document.getElementById('withdraw_time')?.value || new Date().toISOString().split('T')[0];
    localStorage.setItem('selectedDate', dateStr);
    goBtn.href = 'm_data_statistics.php?date=' + encodeURIComponent(dateStr);

    // 再保險：點擊當下再以當前欄位值覆寫一次
    goBtn.addEventListener('click', function () {
      const live = document.getElementById('withdraw_time')?.value || dateStr;
      localStorage.setItem('selectedDate', live);
      this.href = 'm_data_statistics.php?date=' + encodeURIComponent(live);
    }, { once: true });
  }

  // 沒選日期：也要把按鈕印出來，並讓它帶今天
  if (!withdrawTime) {
    statusContainer.innerHTML = `
      <p class="text-muted">請選擇日期</p>
      <div class="status-item">
        <a id="go-statistics" href="m_data_statistics.php" class="btn btn-gradient-primary">前往各班統計表</a>
      </div>
    `;
    setupGoStatisticsLink(null);
    return;
  }

  // 有選日期 → 查狀態並渲染
  fetch(`m_data_editing_backend.php?action=check_status&withdraw_time=${withdrawTime}`)
    .then((r) => r.json())
    .then((data) => {
      if (!data.success) {
        statusContainer.innerHTML = '<p class="text-danger">查詢失敗，請稍後再試。</p>';
        return;
      }
      const { reconciliation, pickup, return: returnStatus, scrap } = data.data;
      statusContainer.innerHTML = `
        <div class="status-item">
          <img src="${ASSET_BASE}imgs/${reconciliation ? 'check' : 'cross'}.png" class="status-icon" alt="">
          <span>對帳資料</span>
        </div>
        <div class="status-item">
          <img src="${ASSET_BASE}imgs/${pickup ? 'check' : 'cross'}.png" class="status-icon" alt="">
          <span>領料</span>
        </div>
        <div class="status-item">
          <img src="${ASSET_BASE}imgs/${returnStatus ? 'check' : 'cross'}.png" class="status-icon" alt="">
          <span>退料</span>
        </div>
        <div class="status-item">
          <img src="${ASSET_BASE}imgs/${scrap ? 'check' : 'cross'}.png" class="status-icon" alt="">
          <span>用餘</span>
        </div>
        <div class="status-item">
          <a id="go-statistics" href="m_data_statistics.php" class="btn btn-gradient-primary">前往各班統計表</a>
        </div>
      `;
      // 這裡一定把目前選的 withdrawTime 帶進 URL 與 localStorage
      setupGoStatisticsLink(withdrawTime);
    })
    .catch((err) => {
      console.error('查詢失敗：', err);
      statusContainer.innerHTML = '<p class="text-danger">查詢失敗，請稍後再試。</p>';
    });
}


document.getElementById('withdraw_time').addEventListener('change', (e) => {
  const v = e.target.value;
  updateStatus(v);
  // ✅ 變更日期時，把數值回填、更新(資料時間：)
  loadReconciliationValuesForDate(v);
});

function onFileUploadSuccess() {
  const t = document.getElementById('withdraw_time').value;
  if (t)
    setTimeout(() => {
      updateStatus(t);
      loadReconciliationValuesForDate(t);
    }, 500);
}

// ========= 新增 / 移除 材料 ========= //
function apiFetch(action, payload = {}, method = 'POST') {
  const opt = { method, headers: { 'Content-Type': 'application/json' } };
  if (method === 'POST') opt.body = JSON.stringify(payload);
  return fetch('m_data_editing_backend.php?action=' + action, opt).then((r) => r.json());
}

document.getElementById('manageMaterialButton').addEventListener('click', async () => {
  // 先載入目前材料清單
  const rows = await fetch('m_data_editing_backend.php?action=fetch_material').then((r) =>
    r.json(),
  );
  const list = Array.isArray(rows) ? rows : [];

  const html = `
    <div class="mb-3">
      <label class="form-label">新增材料（可一次多筆，每行一個）</label>
      <textarea id="newMaterials" class="form-control" rows="4" placeholder="例：\n螺絲\n伸縮桿"></textarea>
      <button id="btnAddMaterials" class="btn btn-sm btn-success mt-2">批次新增</button>
    </div>
    <hr>
    <label class="form-label">現有材料（可刪除）</label>
    <div id="materialList" style="max-height:320px;overflow:auto;">
      ${list
        .map(
          (r) => `
        <div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1" data-id="${r.id}">
          <div class="text-truncate" title="${r.material_name}">${r.material_name}</div>
          <button class="btn btn-sm btn-outline-danger btnDelMaterial">移除</button>
        </div>
      `,
        )
        .join('')}
    </div>
  `;

  await Swal.fire({
    title: '新增 / 移除材料',
    html,
    showCloseButton: true,
    showCancelButton: true,
    confirmButtonText: '完成', // ✅ 改成「完成」
    cancelButtonText: '返回', // ✅ 改成「返回」
    didOpen: () => {
      // 批次新增
      document.getElementById('btnAddMaterials').addEventListener('click', async () => {
        const lines = document
          .getElementById('newMaterials')
          .value.split('\n')
          .map((s) => s.trim())
          .filter(Boolean);
        if (!lines.length) return Swal.fire('提示', '請輸入材料名稱', 'info');

        for (const name of lines) {
          const res = await apiFetch('add_material', { material_name: name });
          if (!res.success) {
            return Swal.fire(
              '錯誤',
              `新增「${name}」失敗：${res.message || '未知錯誤'}`,
              'error',
            );
          }
        }
        Swal.fire('成功', '材料已新增', 'success');
        // 重新整理左側表格結構
        loadReconciliationData();
        document.getElementById('newMaterials').value = '';
      });

      // 單筆刪除
      document.querySelectorAll('#materialList .btnDelMaterial').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
          const row = e.currentTarget.closest('[data-id]');
          const id = row?.dataset.id;
          if (!id) return;

          const ok = await Swal.fire({
            title: '確認刪除？',
            text: '此操作會刪除對應欄位',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '刪除', // ✅ 改成「刪除」
            cancelButtonText: '取消', // ✅ 改成「取消」
          });
          if (!ok.isConfirmed) return;

          const res = await apiFetch('delete_material', { id: Number(id) });
          if (res.success) {
            row.remove();
            Swal.fire('成功', '材料已刪除', 'success');
            // 重新整理左側表格結構
            loadReconciliationData();
          } else {
            Swal.fire('錯誤', res.message || '刪除失敗', 'error');
          }
        });
      });
    },
  });
});

// ================= 初始化 ================= //
document.addEventListener('DOMContentLoaded', function () {
  loadPersonnelData();
  loadReconciliationData();
  loadUniqueDates();

  // ✅ 若頁面載入時日期欄已有值，也回填一次
  const init = document.getElementById('withdraw_time')?.value;
  if (init) {
    updateStatus(init);
    loadReconciliationValuesForDate(init);
  }
});
