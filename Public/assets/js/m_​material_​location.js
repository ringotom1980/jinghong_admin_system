// Public/assets/js/m_material_location.js
// 僅依賴：SweetAlert2、Font Awesome（請由 header.php 統一載入）
(function () {
  const BASE = window.PUBLIC_BASE || '/Public';
  const API = (action) =>
    `${BASE}/modules/mat/m_material_location_backend.php?action=${encodeURIComponent(action)}`;

  const materialTableBody = document.getElementById('materialTableBody');
  const personnelTableBody = document.getElementById('personnelTableBody');
  const backToTop = document.getElementById('backToTop');

  // 回到頂部顯示/隱藏
  window.addEventListener('scroll', () => {
    const y = window.scrollY || document.documentElement.scrollTop;
    backToTop?.classList.toggle('show', y > 300);
    backToTop?.classList.toggle('hidden', y <= 300);
  });
  backToTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  // B 班材料位置
  const loadMaterialTable = () => {
    fetch(API('fetch'))
      .then((r) => r.json())
      .then(({ success, data, message }) => {
        if (!success) throw new Error(message || '取得失敗');
        materialTableBody.innerHTML = (data || [])
          .map(
            (row) => `
          <tr>
            <td class="hidden">${row.material_number ?? ''}</td>
            <td>${row.name_specification ?? ''}</td>
            <td>
              <input type="text" class="form-control material-location"
                     value="${row.material_location ?? ''}"
                     data-material-number="${row.material_number ?? ''}">
            </td>
            <td>
              <button class="btn btn-outline-primary update-btn"
                      data-material-number="${row.material_number ?? ''}">
                更新
              </button>
            </td>
          </tr>
        `,
          )
          .join('');
      })
      .catch((err) => {
        console.error('B班資料失敗:', err);
        materialTableBody.innerHTML = '<tr><td colspan="4">無法獲取資料</td></tr>';
      });
  };

  // D 班對照分組
  const loadMappingTable = () => {
    fetch(API('fetch_mapping'))
      .then((r) => r.json())
      .then(({ success, data, message }) => {
        if (!success) throw new Error(message || '取得失敗');
        personnelTableBody.innerHTML = (data || [])
          .map(
            (row) => `
          <tr>
            <td class="hidden">${row.reference_number ?? ''}</td>
            <td>${row.material_name ?? ''}</td>
            <td style="white-space: normal;">${row.material_numbers || '無'}</td>
            <td>
              <button class="btn btn-outline-primary edit-btn"
                      data-reference="${row.reference_number ?? ''}"
                      data-material-name="${row.material_name ?? ''}"
                      data-material-numbers="${row.material_numbers || ''}">
                編輯
              </button>
            </td>
          </tr>
        `,
          )
          .join('');
      })
      .catch((err) => {
        console.error('對照分組失敗:', err);
        personnelTableBody.innerHTML = '<tr><td colspan="5">無法獲取對照表資料</td></tr>';
      });
  };

  // 初始化
  loadMaterialTable();
  loadMappingTable();

  // D 班編輯 -> 選取與儲存
  personnelTableBody.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.edit-btn');
    if (!btn) return;

    const referenceNumber = btn.dataset.reference;
    const materialName = btn.dataset.materialName;
    const materialNumbers = (btn.dataset.materialNumbers || '')
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);

    if (!referenceNumber) return console.error('reference_number 未定義');

    fetch(API('fetch_shift_a'))
      .then((r) => r.json())
      .then(({ success, data, message }) => {
        if (!success) throw new Error(message || '取得失敗');

        // ✅ 統一把料號規格化為「去空白的字串」
        const norm = (v) => String(v ?? '').trim();

        // 你從 data-material-numbers 拆出來的是字串陣列，這裡進一步規格化
        const selected = new Set((materialNumbers || []).map(norm));

        const tableHTML = `
      <table style="width: 100%; border-collapse: collapse; font-size:14px;">
        <thead>
          <tr>
            <th style="border: 1px solid #ddd; padding: 8px;">材料名稱</th>
            <th style="border: 1px solid #ddd; padding: 8px;">組合</th>
          </tr>
        </thead>
        <tbody>
          ${(data || [])
            .map((row) => {
              const mn = norm(row.material_number); // ✅ 統一成字串
              const checked = selected.has(mn); // ✅ 型別一致，才能命中
              return `
              <tr>
                <td class="material-name"
                    style="border:1px solid #ddd; padding:8px; cursor:pointer;"
                    data-material-number="${mn}">
                  ${row.name_specification ?? ''}
                </td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center;">
                  ${checked ? '<i class="fas fa-check-circle text-success"  style="font-size:20px;"></i>' : ''}
                </td>
              </tr>
            `;
            })
            .join('')}
        </tbody>
      </table>`;

        Swal.fire({
          title: `${materialName} 材料編號組合`,
          html: tableHTML,
          showCancelButton: true,
          confirmButtonText: '儲存',
          cancelButtonText: '取消',
          width: '600px',
          preConfirm: () => {
            const picked = Array.from(document.querySelectorAll('.material-name'))
              .filter((el) => el.nextElementSibling.innerHTML.includes('fa-check-circle'))
              .map((el) => norm(el.dataset.materialNumber)); // ✅ 收集也規格化
            return { referenceNumber, selected: picked };
          },
        }).then((result) => {
          if (!result.isConfirmed) return;
          const { referenceNumber, selected: picked } = result.value || {
            referenceNumber,
            selected: [],
          };

          fetch(API('save_combination'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              reference_number: referenceNumber,
              selected_materials: picked,
            }),
          })
            .then((r) => r.json())
            .then(({ success, message }) => {
              if (success) {
                Swal.fire({
                  icon: 'success',
                  title: '儲存成功',
                  text: '材料組合已更新！',
                  timer: 1500,
                  showConfirmButton: false,
                }).then(() => loadMappingTable());
              } else {
                Swal.fire({ icon: 'error', title: '儲存失敗', text: message || '無法儲存' });
              }
            })
            .catch((err) => {
              console.error('儲存失敗:', err);
              Swal.fire({ icon: 'error', title: '儲存失敗', text: '發生錯誤，請稍後再試。' });
            });
        });

        // 點擊切換打勾（同樣規格化料號）
        setTimeout(() => {
          document.querySelectorAll('.material-name').forEach((el) => {
            el.addEventListener('click', () => {
              const mn = norm(el.dataset.materialNumber);
              const cell = el.nextElementSibling;
              if (cell.innerHTML.includes('fa-check-circle')) {
                cell.innerHTML = '';
                selected.delete(mn);
              } else {
                cell.innerHTML = '<i class="fas fa-check-circle text-success" style="font-size:20px;"></i>';
                selected.add(mn);
              }
            });
          });
        }, 0);
      })
      .catch((err) => {
        console.error('提取資料失敗:', err);
        Swal.fire({
          title: '錯誤',
          text: '無法提取資料，請稍後再試。',
          showConfirmButton: true,
        });
      });
  });

  // B 班材料位置更新
  materialTableBody.addEventListener('click', (ev) => {
    const btn = ev.target.closest('.update-btn');
    if (!btn) return;
    const mn = btn.dataset.materialNumber;
    const input = document.querySelector(`input[data-material-number="${mn}"]`);
    if (!mn || !input) return console.error('無法獲取材料編號或位置輸入框');

    const loc = input.value.trim();
    fetch(API('update'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ material_number: mn, material_location: loc }),
    })
      .then((r) => r.json())
      .then(({ success, message }) => {
        if (success) {
          Swal.fire({
            icon: 'success',
            title: '更新成功',
            text: '材料位置已更新！',
            timer: 1400,
            showConfirmButton: false,
          });
          loadMaterialTable();
        } else {
          Swal.fire({ icon: 'error', title: '更新失敗', text: message || '無法更新材料位置' });
        }
      })
      .catch((err) => {
        console.error('更新失敗:', err);
        Swal.fire({ icon: 'error', title: '更新失敗', text: '發生錯誤，請稍後再試。' });
      });
  });
})();
