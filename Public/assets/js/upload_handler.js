// === 統一 API Base（絕對路徑，不再踩相對路徑地雷） ===
const PUBLIC_BASE = (window.PUBLIC_BASE || '/').replace(/\/+$/, '') + '/';
const API_BASE = PUBLIC_BASE + 'modules/mat/'; // → /jinghong_admin_system/Public/modules/mat/

document.getElementById('uploadButton').addEventListener('click', function (event) {
    event.preventDefault();

    const uploadInput = document.getElementById('upload_s');
    const withdrawDateInput = document.getElementById('withdraw_time');
    const files = uploadInput.files;
    const withdrawDate = withdrawDateInput.value;

    if (!files || files.length === 0) {
        Swal.fire({ icon: 'warning', title: '未選擇檔案', text: '請選擇要上傳的檔案！' });
        return;
    }
    if (!withdrawDate) {
        Swal.fire({ icon: 'warning', title: '未選擇領退料時間', text: '請先選擇領退料時間！' });
        return;
    }

    const formData = new FormData();
    for (let i = 0; i < files.length; i++) formData.append('upload_s[]', files[i]);
    formData.append('withdraw_time', withdrawDate);

    const uploadButton = document.getElementById('uploadButton');
    uploadButton.innerText = '上傳中...';
    uploadButton.disabled = true;

    fetch(API_BASE + 'upload_handler.php', { method: 'POST', body: formData })
        .then((response) => {
            if (!response.ok) throw new Error(`HTTP 錯誤！狀態碼：${response.status}`);
            return response.json();
        })
        .then((result) => {
            uploadButton.innerText = '上傳';
            uploadButton.disabled = false;

            // 清空檔案選擇（日期通常保留，若要清空把下一行註解拿掉）
            uploadInput.value = '';
            // withdrawDateInput.value = '';

            if (result && result.success) {
                Swal.fire({ icon: 'success', title: '資料上傳成功' }).then(() => {
                    // 重新載入近三個月日期卡片
                    if (typeof loadUniqueDates === 'function') loadUniqueDates();
                    // 更新「對帳/領料/退料/用餘」狀態
                    if (typeof onFileUploadSuccess === 'function') onFileUploadSuccess();
                });

                // 若後端回傳缺班別資料，開表單請使用者填
                if (Array.isArray(result.missingShiftRecords) && result.missingShiftRecords.length > 0) {
                    showShiftInputTable(result.missingShiftRecords);
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '上傳失敗',
                    text: (result && result.message) ? result.message : '請稍後再試'
                });
            }
        })
        .catch((error) => {
            uploadButton.innerText = '上傳';
            uploadButton.disabled = false;
            Swal.fire({ icon: 'error', title: '檔案上傳失敗', text: `錯誤訊息：${error.message}` });
            console.error('檔案上傳失敗: ', error);
        });
});

/**
 * 顯示填寫 shift 的表格
 */
function showShiftInputTable(missingShiftRecords) {
    // 先動態獲取班別資料
    fetch(API_BASE + 'update_shift.php', { method: 'GET' })
        .then((response) => {
            if (!response.ok) throw new Error('無法獲取班別資料');
            return response.json();
        })
        .then((shiftData) => {
            if (!shiftData || !shiftData.success) throw new Error(shiftData?.message || '獲取班別資料失敗');

            const shiftOptions = shiftData.data.map(
                (shift) => `<option value="${shift.shift_name}">${shift.shift_name} - ${shift.personnel_name}</option>`
            ).join('');

            const rows = missingShiftRecords.map((record, index) => `
                <tr>
                    <td class="text-center">${record.material_number}</td>
                    <td>${record.name_specification}</td>
                    <td>
                        <select class="text-center" id="shift_${index}" style="width: 100%; font-size: 14px;">
                            <option class="text-center" value="" selected disabled>請選擇班別</option>
                            ${shiftOptions}
                        </select>
                    </td>
                </tr>
            `).join('');

            const tableHtml = `
                <div style="overflow-x:auto; max-width:100%;">
                    <table class="table table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:20%;">材料編號</th>
                                <th class="text-center" style="width:60%;">材料名稱</th>
                                <th class="text-center" style="width:20%;">班別</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;

            Swal.fire({
                title: '上傳成功，惟部分材料尚無班別，請先分班',
                html: tableHtml,
                showCancelButton: true,
                confirmButtonText: '儲存',
                cancelButtonText: '取消',
                customClass: { popup: 'shift-popup' },
                preConfirm: () => {
                    const shiftUpdates = {};
                    let allFilled = true;

                    missingShiftRecords.forEach((record, index) => {
                        const el = document.getElementById(`shift_${index}`);
                        const selectedShift = el ? el.value : '';
                        if (!selectedShift) allFilled = false;
                        shiftUpdates[record.material_number] = selectedShift;
                    });

                    if (!allFilled) {
                        Swal.showValidationMessage('所有班別欄位必須填寫！');
                        return false;
                    }
                    return shiftUpdates;
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    saveShiftUpdates(result.value);
                }
            });
        })
        .catch((error) => {
            Swal.fire({ icon: 'error', title: '錯誤', text: `獲取班別資料失敗：${error.message}` });
        });
}

/**
 * 儲存填寫的班別資訊
 */
function saveShiftUpdates(shiftUpdates) {
    fetch(API_BASE + 'update_shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ shifts: shiftUpdates }),
    })
        .then((response) => {
            if (!response.ok) throw new Error('班別更新失敗');
            return response.json();
        })
        .then((result) => {
            if (result && result.success) {
                Swal.fire({ icon: 'success', title: '更新成功', text: result.message || '' });
            } else {
                Swal.fire({ icon: 'error', title: '更新失敗', text: (result && result.message) ? result.message : '請稍後再試' });
            }
        })
        .catch((error) => {
            Swal.fire({ icon: 'error', title: '錯誤', text: `班別更新失敗：${error.message}` });
        });
}

//（若別處需要）獲取班別資料
function fetchShiftData() {
    return fetch(API_BASE + 'update_shift.php', { method: 'GET' })
        .then((response) => {
            if (!response.ok) throw new Error('無法獲取班別資料');
            return response.json();
        });
}
