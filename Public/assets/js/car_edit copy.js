/* eslint-disable no-console */
(function () {
  'use strict';

  // ===== 基本設定 =====
  const PUBLIC_BASE = (window.PUBLIC_BASE || '').replace(/\/+$/, '');
  const BACKEND = window.CAR_BACKEND_URL || 'car_edit_backend.php';

  // ===== DOM 參照（對齊 car_edit.php 的實際 ID/結構）=====
  const tableWrap = document.getElementById('fleetTableWrap');
  const table =
    document.getElementById('vehicleTable') ||
    (tableWrap ? tableWrap.querySelector('table') : null);
  const tbody = table ? table.tBodies[0] || table.createTBody() : null;

  // 新增 Modal/表單
  const addModalEl = document.getElementById('addVehicleModal');
  const addForm = document.getElementById('addVehicleForm');
  const addBtnSubmit = document.getElementById('add_submit');
  const addFields = {
    image: document.getElementById('add_image'),
    vehicle_id: document.getElementById('add_vehicle_id'),
    license_plate: document.getElementById('add_license_plate'),
    owner: document.getElementById('add_owner'),
    user: document.getElementById('add_user'),
    vehicle_type: document.getElementById('add_vehicle_type'),
    tonnage: document.getElementById('add_tonnage'),
    brand: document.getElementById('add_brand'),
    vehicle_year: document.getElementById('add_vehicle_year'),
    vehicle_price: document.getElementById('add_vehicle_price'),
    truck_bed_price: document.getElementById('add_truck_bed_price'),
    crane_price: document.getElementById('add_crane_price'),
    crane_type: document.getElementById('add_crane_type'),
    inspection_date: document.getElementById('add_inspection_date'),
    insurance_date: document.getElementById('add_insurance_date'),
    // 動態容器
    record_date_wrap: document.getElementById('add_record_date_wrap'),
    emission_date_wrap: document.getElementById('add_emission_date_wrap'),
    // 單選
    record_yes: document.getElementById('add_record_yes'),
    record_no: document.getElementById('add_record_no'),
    emission_yes: document.getElementById('add_emission_yes'),
    emission_no: document.getElementById('add_emission_no'),
  };
  const addErrors = {
    image: document.getElementById('add_image_err'),
    vehicle_id: document.getElementById('add_vehicle_id_err'),
    license_plate: document.getElementById('add_license_plate_err'),
    form: document.getElementById('add_form_err'),
  };

  // 編輯 Modal/表單
  const editModalEl = document.getElementById('editVehicleModal');
  const editForm = document.getElementById('editVehicleForm');
  const editBtnSubmit = document.getElementById('edit_submit');
  const editFields = {
    original_vehicle_id: document.getElementById('edit_original_vehicle_id'),
    preview_image: document.getElementById('edit_preview_image'),
    image_file: document.getElementById('edit_image'),
    image_err: document.getElementById('edit_image_err'),
    vehicle_id_1: document.getElementById('edit_vehicle_id'),
    license_plate: document.getElementById('edit_license_plate'),
    owner: document.getElementById('edit_owner'),
    user: document.getElementById('edit_user'),
    vehicle_type: document.getElementById('edit_vehicle_type'),
    tonnage: document.getElementById('edit_tonnage'),
    brand: document.getElementById('edit_brand'),
    vehicle_year: document.getElementById('edit_vehicle_year'),
    vehicle_price: document.getElementById('edit_vehicle_price'),
    truck_bed_price: document.getElementById('edit_truck_bed_price'),
    crane_price: document.getElementById('edit_crane_price'),
    crane_type: document.getElementById('edit_crane_type'),
    inspection_date: document.getElementById('edit_inspection_date'),
    insurance_date: document.getElementById('edit_insurance_date'),
    // 動態容器
    record_wrap: document.getElementById('edit_record_date_wrap'),
    emission_wrap: document.getElementById('edit_emission_date_wrap'),
    // 單選
    record_yes: document.getElementById('edit_record_yes'),
    record_no: document.getElementById('edit_record_no'),
    emission_yes: document.getElementById('edit_emission_yes'),
    emission_no: document.getElementById('edit_emission_no'),
    // errors
    id_err: document.getElementById('edit_vehicle_id_err'),
    plate_err: document.getElementById('edit_license_plate_err'),
    form_err: document.getElementById('edit_form_err'),
  };

  // 刪除 Modal/表單
  const delModalEl = document.getElementById('deleteVehicleModal');
  const delForm = document.getElementById('deleteVehicleForm');
  const delId = document.getElementById('delete_vehicle_id');
  const delPreview = document.getElementById('delete_preview_image');

  // ====== UI 清場（本檔新增，解決卡住）======
  function ensureCleanUi() {
    // 關掉任何殘留開啟中的 modal
    document.querySelectorAll('.modal.show').forEach((el) => {
      try {
        const inst = bootstrap.Modal.getInstance(el) || bootstrap.Modal.getOrCreateInstance(el);
        inst.hide();
      } catch (_) {}
    });
    // 清除所有殘留的 backdrop
    document.querySelectorAll('.modal-backdrop').forEach((e) => e.remove());
    // 解鎖 body
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('paddingRight');
  }

  // 任何 modal 關閉後，再保險清一次（避免 race condition）
  document.addEventListener('hidden.bs.modal', () => {
    // 放到下一個 frame，等 Bootstrap 把 DOM 調整完
    requestAnimationFrame(() => ensureCleanUi());
  });

  // 若不小心產生多個 backdrop，保留最後一個、移除其餘
  document.addEventListener('shown.bs.modal', () => {
    const backs = document.querySelectorAll('.modal-backdrop');
    if (backs.length > 1) {
      for (let i = 0; i < backs.length - 1; i++) backs[i].remove();
    }
    // 確保 body 狀態正確
    document.body.classList.add('modal-open');
  });

  // 安全關閉 modal（改成事件驅動）
  function closeModal(modalEl) {
    if (!modalEl) return;
    const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
    const onHidden = () => {
      modalEl.removeEventListener('hidden.bs.modal', onHidden);
      ensureCleanUi();
    };
    modalEl.addEventListener('hidden.bs.modal', onHidden);
    try {
      inst.hide();
    } catch (_) {
      ensureCleanUi();
    }
  }

  // ===== 工具 =====
  function resolveImageUrl(raw) {
    const p = String(raw || '').trim();
    if (!p) return '';
    if (/^https?:\/\//i.test(p) || p.startsWith('/')) return p;
    const base = (window.PUBLIC_BASE || '').replace(/\/+$/, '');
    return base ? `${base}/${p.replace(/^\/+/, '')}` : p;
  }
  function fmtMoney(v) {
    if (v === null || v === undefined || v === '') return '';
    const n = Number(v);
    if (Number.isNaN(n)) return '';
    return n.toLocaleString('zh-TW');
  }
  function escapeHtml(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
  function fixZeroDate(d) {
    if (!d || d === '0000-00-00') return '';
    return d;
  }
  function humanOptional(date, required) {
    if (String(required) === '0' || !date || date === '0000-00-00') return '不須檢驗';
    return date;
  }
  function toast(msg, type = 'info') {
    if (window.Swal && Swal.fire) {
      const map = {
        success: { icon: 'success', title: '成功' },
        error: { icon: 'error', title: '錯誤' },
        warning: { icon: 'warning', title: '提醒' },
        info: { icon: 'info', title: '訊息' },
      };
      const conf = map[type] || map.info;
      Swal.fire({ ...conf, text: msg, timer: 1500, showConfirmButton: false });
    } else {
      alert(msg);
    }
  }
  async function api(url, opt = {}) {
    const res = await fetch(url, opt);
    const ct = res.headers.get('content-type') || '';
    if (!res.ok) {
      const text = ct.includes('application/json')
        ? JSON.stringify(await res.json()).slice(0, 200)
        : (await res.text()).slice(0, 200);
      throw new Error(`HTTP ${res.status} ${text}`);
    }
    return ct.includes('application/json') ? res.json() : res.text();
  }

  // ===== 清單載入 =====
  async function loadVehicles() {
    if (!tbody) return;
    tbody.innerHTML = `<tr><td colspan="17" class="text-center">載入中...</td></tr>`;
    try {
      const res = await api(`${BACKEND}?action=list`);
      const data = res?.data;
      if (!Array.isArray(data)) throw new Error('格式錯誤');
      tbody.innerHTML = '';
      data.forEach((v, i) => {
        const tr = document.createElement('tr');
        const imgField = v.image_url || v.image_path || v.image || v.photo || '';
        tr.innerHTML = `
          <td>${i + 1}</td>
          <td>${escapeHtml(v.vehicle_id)}</td>
          <td>${escapeHtml(v.license_plate)}</td>
          <td class="wrap-owner"><span class="cell-wrap">${escapeHtml(v.owner || '')}</span></td>
          <td>${escapeHtml(v.user || '')}</td>
          <td>${escapeHtml(v.vehicle_type || '')}</td>
          <td>${escapeHtml(v.tonnage ?? '')}</td>
          <td>${escapeHtml(v.brand || '')}</td>
          <td>${escapeHtml(v.vehicle_year ?? '')}</td>
          <td>${fmtMoney(v.vehicle_price)}</td>
          <td>${fmtMoney(v.truck_bed_price)}</td>
          <td>${fmtMoney(v.crane_price)}</td>
          <td>${escapeHtml(v.crane_type || '')}</td>
          <td>${escapeHtml(fixZeroDate(v.inspection_date))}</td>
          <td>${escapeHtml(fixZeroDate(v.insurance_date))}</td>
          <td>${escapeHtml(humanOptional(v.record_date, v.record_required))}</td>
          <td>${escapeHtml(humanOptional(v.emission_date, v.emission_required))}</td>
          <td>
            <button class="btn btn-outline-primary btn-sm btn-edit"
              data-image-url="${encodeURIComponent(imgField)}"
              data-vehicle_id="${encodeURIComponent(v.vehicle_id || '')}"
              data-license_plate="${encodeURIComponent(v.license_plate || '')}"
              data-owner="${encodeURIComponent(v.owner || '')}"
              data-user="${encodeURIComponent(v.user || '')}"
              data-vehicle_type="${encodeURIComponent(v.vehicle_type || '')}"
              data-tonnage="${encodeURIComponent(v.tonnage ?? '')}"
              data-brand="${encodeURIComponent(v.brand || '')}"
              data-vehicle_year="${encodeURIComponent(v.vehicle_year ?? '')}"
              data-vehicle_price="${encodeURIComponent(v.vehicle_price ?? '')}"
              data-truck_bed_price="${encodeURIComponent(v.truck_bed_price ?? '')}"
              data-crane_price="${encodeURIComponent(v.crane_price ?? '')}"
              data-crane_type="${encodeURIComponent(v.crane_type || '')}"
              data-inspection_date="${encodeURIComponent(v.inspection_date || '')}"
              data-insurance_date="${encodeURIComponent(v.insurance_date || '')}"
              data-record_required="${encodeURIComponent(v.record_required ?? 1)}"
              data-record_date="${encodeURIComponent(v.record_date || '')}"
              data-emission_required="${encodeURIComponent(v.emission_required ?? 1)}"
              data-emission_date="${encodeURIComponent(v.emission_date || '')}"
            >編輯</button>
            <button class="btn btn-outline-danger btn-sm btn-delete"
              data-vehicle_id="${encodeURIComponent(v.vehicle_id || '')}"
              data-image-url="${encodeURIComponent(imgField)}"
            >刪除</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    } catch (err) {
      console.error(err);
      tbody.innerHTML = `<tr><td colspan="17" class="text-center text-danger">載入失敗</td></tr>`;
    }
  }

  // ====== 驗證（id、plate、file）======
  async function validateUnique(type, value, currentId) {
    if (!value) return { ok: true, exists: false };
    const url = new URL(BACKEND, location.href);
    url.searchParams.set('action', 'validate');
    url.searchParams.set('type', type);
    url.searchParams.set('value', value);
    if (currentId) url.searchParams.set('current_id', currentId);
    const res = await api(url.toString());
    return { ok: true, exists: !!res?.exists };
  }
  async function validateImageName(fileName) {
    if (!fileName) return { ok: true, exists: false };
    const url = new URL(BACKEND, location.href);
    url.searchParams.set('action', 'validate');
    url.searchParams.set('type', 'file');
    url.searchParams.set('file_name', fileName);
    const res = await api(url.toString());
    return { ok: true, exists: !!res?.exists };
  }

  // ===== 新增：動態欄位（須/不須）=====
  function renderAddRecordDate(required) {
    if (!addFields.record_date_wrap) return;
    addFields.record_date_wrap.innerHTML = required
      ? `<input type="date" class="form-control" name="record_date" required>`
      : `<input type="text" class="form-control" value="不須檢驗" readonly>`;
  }
  function renderAddEmissionDate(required) {
    if (!addFields.emission_date_wrap) return;
    addFields.emission_date_wrap.innerHTML = required
      ? `<input type="date" class="form-control" name="emission_date" required>`
      : `<input type="text" class="form-control" value="不須檢驗" readonly>`;
  }

  // 初次開啟新增視窗：重置 + 關閉送出鍵
  function resetAddForm() {
    if (!addForm) return;
    addForm.reset();
    if (addErrors.form) addErrors.form.textContent = '';
    if (addErrors.image) addErrors.image.textContent = '';
    if (addErrors.vehicle_id) addErrors.vehicle_id.textContent = '';
    if (addErrors.license_plate) addErrors.license_plate.textContent = '';
    if (addFields.record_date_wrap) addFields.record_date_wrap.innerHTML = '';
    if (addFields.emission_date_wrap) addFields.emission_date_wrap.innerHTML = '';
    if (addBtnSubmit) addBtnSubmit.disabled = false;
  }
  if (addModalEl) {
    addModalEl.addEventListener('show.bs.modal', resetAddForm);
  }

  // 新增：監聽須/不須
  if (addFields.record_yes && addFields.record_no) {
    addFields.record_yes.addEventListener('change', () => renderAddRecordDate(true));
    addFields.record_no.addEventListener('change', () => renderAddRecordDate(false));
  }
  if (addFields.emission_yes && addFields.emission_no) {
    addFields.emission_yes.addEventListener('change', () => renderAddEmissionDate(true));
    addFields.emission_no.addEventListener('change', () => renderAddEmissionDate(false));
  }

  // 新增：重複驗證
  if (addFields.vehicle_id) {
    addFields.vehicle_id.addEventListener('blur', async () => {
      try {
        const r = await validateUnique('id', addFields.vehicle_id.value.trim(), '');
        if (addErrors.vehicle_id)
          addErrors.vehicle_id.textContent = r.exists ? '車輛編號已存在，請修改' : '';
      } catch (e) {
        if (addErrors.vehicle_id) addErrors.vehicle_id.textContent = '驗證失敗';
      }
    });
  }
  if (addFields.license_plate) {
    addFields.license_plate.addEventListener('blur', async () => {
      try {
        const r = await validateUnique('plate', addFields.license_plate.value.trim(), '');
        if (addErrors.license_plate)
          addErrors.license_plate.textContent = r.exists ? '車牌號碼已存在，請修改' : '';
      } catch (e) {
        if (addErrors.license_plate) addErrors.license_plate.textContent = '驗證失敗';
      }
    });
  }
  if (addFields.image) {
    addFields.image.addEventListener('change', async () => {
      const f = addFields.image.files?.[0];
      if (!f) {
        if (addErrors.image) addErrors.image.textContent = '';
        return;
      }
      try {
        const r = await validateImageName(f.name);
        if (addErrors.image)
          addErrors.image.textContent = r.exists
            ? '已有相同檔名車輛照片，請選擇不同的圖片'
            : '';
      } catch (e) {
        if (addErrors.image) addErrors.image.textContent = '驗證失敗';
      }
    });
  }

  // 新增：送出
  if (addForm) {
    addForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (addErrors.form) addErrors.form.textContent = '';
      const fd = new FormData(addForm);
      fd.set(
        'record_required',
        addFields.record_yes && addFields.record_yes.checked ? '1' : '0',
      );
      fd.set(
        'emission_required',
        addFields.emission_yes && addFields.emission_yes.checked ? '1' : '0',
      );

      try {
        if (addBtnSubmit) addBtnSubmit.disabled = true;
        const res = await api(`${BACKEND}?action=create`, { method: 'POST', body: fd });
        if (res?.success) {
          // 先關 Modal → 再顯示 Swal → 再重整
          if (addModalEl) {
            await new Promise((resolve) => {
              const inst = bootstrap.Modal.getOrCreateInstance(addModalEl);
              addModalEl.addEventListener('hidden.bs.modal', resolve, { once: true });
              try {
                inst.hide();
              } catch (_) {
                resolve();
              }
            });
          }
          await Swal.fire({
            icon: 'success',
            title: '成功',
            text: '新增成功',
            timer: 1500,
            showConfirmButton: false,
          });
          location.reload();
        } else {
          const msg = res?.message || '新增失敗';
          if (addErrors.form) addErrors.form.textContent = msg;
          toast(msg, 'error');
        }
      } catch (err) {
        console.error(err);
        const msg = '新增失敗，請稍後再試';
        if (addErrors.form) addErrors.form.textContent = msg;
        toast(msg, 'error');
      } finally {
        if (addBtnSubmit) addBtnSubmit.disabled = false;
      }
    });
  }

  // 專門清檔案欄位
  function clearEditFileInput() {
    if (editFields.image_file) {
      editFields.image_file.value = '';
    }
    if (editFields.image_err) {
      editFields.image_err.textContent = '';
    }
  }

  // ===== 編輯：開窗帶資料 =====
  function openEditModal(btn) {
    if (!editModalEl) return;
    // ★ 開窗前先清檔案欄位
    clearEditFileInput();
    const d = (attr) => decodeURIComponent(btn.getAttribute(attr) || '');

    const payload = {
      image_url: d('data-image-url'),
      vehicle_id: d('data-vehicle_id'),
      license_plate: d('data-license_plate'),
      owner: d('data-owner'),
      user: d('data-user'),
      vehicle_type: d('data-vehicle_type'),
      tonnage: d('data-tonnage'),
      brand: d('data-brand'),
      vehicle_year: d('data-vehicle_year'),
      vehicle_price: d('data-vehicle_price'),
      truck_bed_price: d('data-truck_bed_price'),
      crane_price: d('data-crane_price'),
      crane_type: d('data-crane_type'),
      inspection_date: d('data-inspection_date'),
      insurance_date: d('data-insurance_date'),
      record_required: d('data-record_required') || '1',
      record_date: d('data-record_date'),
      emission_required: d('data-emission_required') || '1',
      emission_date: d('data-emission_date'),
    };

    // 填值
    if (editFields.original_vehicle_id)
      editFields.original_vehicle_id.value = payload.vehicle_id || '';
    if (editFields.vehicle_id_1) editFields.vehicle_id_1.value = payload.vehicle_id || '';
    if (editFields.license_plate) editFields.license_plate.value = payload.license_plate || '';
    if (editFields.owner) editFields.owner.value = payload.owner || '';
    if (editFields.user) editFields.user.value = payload.user || '';
    if (editFields.vehicle_type) editFields.vehicle_type.value = payload.vehicle_type || '';
    if (editFields.tonnage) editFields.tonnage.value = payload.tonnage || '';
    if (editFields.brand) editFields.brand.value = payload.brand || '';
    if (editFields.vehicle_year) editFields.vehicle_year.value = payload.vehicle_year || '';
    if (editFields.vehicle_price) editFields.vehicle_price.value = payload.vehicle_price || '';
    if (editFields.truck_bed_price)
      editFields.truck_bed_price.value = payload.truck_bed_price || '';
    if (editFields.crane_price) editFields.crane_price.value = payload.crane_price || '';
    if (editFields.crane_type) editFields.crane_type.value = payload.crane_type || '';
    if (editFields.inspection_date)
      editFields.inspection_date.value = fixZeroDate(payload.inspection_date);
    if (editFields.insurance_date)
      editFields.insurance_date.value = fixZeroDate(payload.insurance_date);

    // 圖片
    if (editFields.preview_image) {
      editFields.preview_image.src = payload.image_url
        ? resolveImageUrl(payload.image_url)
        : `${PUBLIC_BASE}/assets/imgs/JH_logo.png`;
    }

    if (editFields.image_err) editFields.image_err.textContent = '';
    if (editFields.id_err) editFields.id_err.textContent = '';
    if (editFields.plate_err) editFields.plate_err.textContent = '';
    if (editFields.form_err) editFields.form_err.textContent = '';

    // Record / Emission 動態欄位
    renderEditOptional(
      editFields.record_wrap,
      'record_date',
      payload.record_required === '1',
      payload.record_date,
    );
    renderEditOptional(
      editFields.emission_wrap,
      'emission_date',
      payload.emission_required === '1',
      payload.emission_date,
    );

    // 單選狀態
    if (editFields.record_yes && editFields.record_no) {
      editFields.record_yes.checked = payload.record_required === '1';
      editFields.record_no.checked = payload.record_required !== '1';
      editFields.record_yes.onchange = () =>
        renderEditOptional(editFields.record_wrap, 'record_date', true, '');
      editFields.record_no.onchange = () =>
        renderEditOptional(editFields.record_wrap, 'record_date', false, '');
    }
    if (editFields.emission_yes && editFields.emission_no) {
      editFields.emission_yes.checked = payload.emission_required === '1';
      editFields.emission_no.checked = payload.emission_required !== '1';
      editFields.emission_yes.onchange = () =>
        renderEditOptional(editFields.emission_wrap, 'emission_date', true, '');
      editFields.emission_no.onchange = () =>
        renderEditOptional(editFields.emission_wrap, 'emission_date', false, '');
    }

    ensureCleanUi(); // 先清場，避免殘留 backdrop
    bootstrap.Modal.getOrCreateInstance(editModalEl).show();
  }

  function renderEditOptional(container, name, required, value) {
    if (!container) return;
    if (required) {
      container.innerHTML = `<input type="date" class="form-control" name="${name}" ${value ? `value="${escapeHtml(value)}"` : ''} required>`;
    } else {
      container.innerHTML = `<input type="text" class="form-control" value="不須檢驗" readonly>`;
    }
  }

  // 編輯：重複驗證
  if (editFields.vehicle_id_1) {
    editFields.vehicle_id_1.addEventListener('blur', async () => {
      if (!editFields.original_vehicle_id) return;
      const v = editFields.vehicle_id_1.value.trim();
      const me = editFields.original_vehicle_id.value.trim();
      if (v === me) {
        if (editFields.id_err) editFields.id_err.textContent = '';
        return;
      }
      try {
        const r = await validateUnique('id', v, me);
        if (editFields.id_err)
          editFields.id_err.textContent = r.exists ? '新的車輛編號已存在' : '';
      } catch {
        if (editFields.id_err) editFields.id_err.textContent = '驗證失敗';
      }
    });
  }
  if (editFields.license_plate) {
    editFields.license_plate.addEventListener('blur', async () => {
      const me = editFields.original_vehicle_id
        ? editFields.original_vehicle_id.value.trim()
        : '';
      try {
        const r = await validateUnique('plate', editFields.license_plate.value.trim(), me);
        if (editFields.plate_err)
          editFields.plate_err.textContent = r.exists ? '此車牌號碼已被其他車輛使用' : '';
      } catch {
        if (editFields.plate_err) editFields.plate_err.textContent = '驗證失敗';
      }
    });
  }
  if (editFields.image_file) {
    editFields.image_file.addEventListener('change', async () => {
      if (!editFields.image_file.files?.[0]) {
        if (editFields.image_err) editFields.image_err.textContent = '';
        return;
      }
      try {
        const r = await validateImageName(editFields.image_file.files[0].name);
        if (editFields.image_err)
          editFields.image_err.textContent = r.exists
            ? '已有相同檔名車輛照片，請選擇不同的圖片'
            : '';
      } catch {
        if (editFields.image_err) editFields.image_err.textContent = '驗證失敗';
      }
    });
  }

  // 編輯：送出
  if (editForm) {
    editForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (editFields.form_err) editFields.form_err.textContent = '';
      const fd = new FormData(editForm);
      fd.set(
        'record_required',
        editFields.record_yes && editFields.record_yes.checked ? '1' : '0',
      );
      fd.set(
        'emission_required',
        editFields.emission_yes && editFields.emission_yes.checked ? '1' : '0',
      );

      try {
        if (editBtnSubmit) editBtnSubmit.disabled = true;
        const res = await api(`${BACKEND}?action=update`, { method: 'POST', body: fd });
        if (res?.success) {
          toast('更新成功', 'success');
          closeModal(editModalEl);
          await loadVehicles();
        } else {
          const msg = res?.message || '更新失敗';
          if (editFields.form_err) editFields.form_err.textContent = msg;
          toast(msg, 'error');
        }
      } catch (err) {
        console.error(err);
        const msg = '更新失敗，請稍後再試';
        if (editFields.form_err) editFields.form_err.textContent = msg;
        toast(msg, 'error');
      } finally {
        if (editBtnSubmit) editBtnSubmit.disabled = false;
      }
    });
  }

  // ===== 刪除 =====
  function openDeleteModal(vehicleId, imageUrl) {
    if (!delModalEl || !delForm || !delId) return;
    delId.value = vehicleId || '';
    if (delPreview) {
      if (imageUrl) {
        delPreview.src = resolveImageUrl(imageUrl);
        delPreview.style.display = 'block';
      } else {
        delPreview.style.display = 'none';
      }
    }
    ensureCleanUi(); // 先清場
    bootstrap.Modal.getOrCreateInstance(delModalEl).show();
  }
  if (delForm) {
    delForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(delForm);
      try {
        const res = await api(`${BACKEND}?action=delete`, { method: 'POST', body: fd });
        if (res?.success) {
          toast('刪除成功', 'success');
          closeModal(delModalEl);
          await loadVehicles();
        } else {
          toast(res?.message || '刪除失敗', 'error');
        }
      } catch (err) {
        console.error(err);
        toast('刪除失敗，請稍後再試', 'error');
      }
    });
  }

  // ===== 事件委派（表格中的編輯/刪除按鈕）=====
  if (tbody) {
    tbody.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;
      if (btn.classList.contains('btn-edit')) {
        openEditModal(btn);
      } else if (btn.classList.contains('btn-delete')) {
        const id = decodeURIComponent(btn.getAttribute('data-vehicle_id') || '');
        const img = decodeURIComponent(btn.getAttribute('data-image-url') || '');
        openDeleteModal(id, img);
      }
    });
  }

  // 在任何 data-bs-toggle="modal" 元素被點擊前，先清場避免殘留
  document.querySelectorAll('[data-bs-toggle="modal"]').forEach((btn) => {
    btn.addEventListener('click', () => ensureCleanUi(), { capture: true });
  });

  // ===== 初始化 =====
  function init() {
    loadVehicles();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
