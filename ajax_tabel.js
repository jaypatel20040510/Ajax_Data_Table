/**
 * ajax_tabel.js
 * Handles: AJAX fetch → render table rows + pagination + sort headers + CRUD
 */

// ─── State ────────────────────────────────────────────────────────────────────
const state = {
    search: '',
    sortBy: 'id',
    order: 'ASC',
    page: 1,
    limit: 10,
};

let debounceTimer = null;
let pendingDeleteId = null;   // id being confirmed for deletion

// ─── Main loader ──────────────────────────────────────────────────────────────
function loadTable() {
    const params = new URLSearchParams({
        search: state.search,
        sort_by: state.sortBy,
        order: state.order,
        page: state.page,
        limit: state.limit,
    });

    $('#tableBody').html(
        '<tr><td colspan="5" class="loading-cell"><span class="spinner"></span> Loading...</td></tr>'
    );
    $('#paginationContainer').html('');
    $('#resultInfo').text('');

    $.ajax({
        type: 'GET',
        url: 'ajax_tabel.php',
        data: params.toString(),
        dataType: 'json',
        cache: true,
        success: function (res) {
            if (!res.success) { showError(res.error || 'Unknown error'); return; }
            renderRows(res.data);
            renderPagination(res);
            renderInfo(res);
            renderSortIcons(res.sort_by, res.order);
        },
        error: function (xhr, status, err) {
            showError('AJAX error: ' + err);
        }
    });
}

// ─── Renderers ────────────────────────────────────────────────────────────────
function renderRows(data) {
    if (!data || data.length === 0) {
        $('#tableBody').html(
            '<tr><td colspan="5" class="empty-cell">No records found.</td></tr>'
        );
        return;
    }

    const rows = data.map(function (row) {
        return `<tr data-id="${escHtml(row.id)}">
            <td>${escHtml(row.id)}</td>
            <td>${escHtml(row.firstname)}</td>
            <td>${escHtml(row.lastname)}</td>
            <td>${escHtml(row.email)}</td>
            <td class="action-cell">
                <!-- Edit button -->
                <button class="btn-icon edit btn-edit"
                        data-id="${escHtml(row.id)}"
                        title="Edit user">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </button>
                <!-- Delete button -->
                <button class="btn-icon delete btn-delete"
                        data-id="${escHtml(row.id)}"
                        data-name="${escHtml(row.firstname)} ${escHtml(row.lastname)}"
                        title="Delete user">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                        <path d="M9 6V4h6v2"/>
                    </svg>
                </button>
            </td>
        </tr>`;
    }).join('');

    $('#tableBody').html(rows);
}

function renderPagination(res) {
    const { page, total_pages } = res;
    if (total_pages <= 1) { $('#paginationContainer').html(''); return; }

    let html = '';
    html += `<button class="page-btn ${page <= 1 ? 'disabled' : ''}"
                data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>&#8592; Prev</button>`;

    const win = 5;
    let start = Math.max(1, page - Math.floor(win / 2));
    let end = Math.min(total_pages, start + win - 1);
    if (end - start + 1 < win) start = Math.max(1, end - win + 1);

    if (start > 1) {
        html += `<button class="page-btn" data-page="1">1</button>`;
        if (start > 2) html += `<span class="page-ellipsis">…</span>`;
    }
    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" data-page="${i}">${i}</button>`;
    }
    if (end < total_pages) {
        if (end < total_pages - 1) html += `<span class="page-ellipsis">…</span>`;
        html += `<button class="page-btn" data-page="${total_pages}">${total_pages}</button>`;
    }
    html += `<button class="page-btn ${page >= total_pages ? 'disabled' : ''}"
                data-page="${page + 1}" ${page >= total_pages ? 'disabled' : ''}>Next &#8594;</button>`;

    $('#paginationContainer').html(html);
}

function renderInfo(res) {
    const { page, limit, total } = res;
    if (total === 0) { $('#resultInfo').text('No records found.'); return; }
    const from = (page - 1) * limit + 1;
    const to = Math.min(page * limit, total);
    $('#resultInfo').text(`Showing ${from}–${to} of ${total} records`);
}

function renderSortIcons(sortBy, order) {
    $('th[data-col]').each(function () {
        const col = $(this).data('col');
        const icon = $(this).find('.sort-icon');
        if (col === sortBy) {
            icon.text(order === 'ASC' ? ' ▲' : ' ▼');
            $(this).addClass('active-col');
        } else {
            icon.text(' ⇅');
            $(this).removeClass('active-col');
        }
    });
}

function showError(msg) {
    $('#tableBody').html(`<tr><td colspan="5" class="error-cell">⚠ ${escHtml(msg)}</td></tr>`);
}

// ─── Toast ────────────────────────────────────────────────────────────────────
let toastTimer = null;
function showToast(msg, type = 'success') {
    const $t = $('#toast');
    clearTimeout(toastTimer);
    $t.removeClass('success error show').addClass(type).text(msg);
    // Force reflow so animation replays
    void $t[0].offsetWidth;
    $t.addClass('show');
    toastTimer = setTimeout(() => $t.removeClass('show'), 3200);
}

// ─── Modal helpers
function openCrudModal(mode, user) {
    const isEdit = mode === 'edit';
    $('#modalTitle').text(isEdit ? 'Edit User' : 'Add User');
    $('#formUserId').val(isEdit ? user.id : '');
    $('#formFirstname').val(isEdit ? user.firstname : '').removeClass('is-invalid');
    $('#formLastname').val(isEdit ? user.lastname : '').removeClass('is-invalid');
    $('#formEmail').val(isEdit ? user.email : '').removeClass('is-invalid');
    $('.field-error').hide();
    $('#modalSaveBtn').prop('disabled', false);
    $('#crudModalOverlay').addClass('open');
    $('#formFirstname').focus();
}

function closeCrudModal() {
    $('#crudModalOverlay').removeClass('open');
}

function openDeleteModal(id, name) {
    pendingDeleteId = id;
    $('#deleteUserName').text(name);
    $('#confirmDeleteBtn').prop('disabled', false);
    $('#deleteModalOverlay').addClass('open');
}

function closeDeleteModal() {
    $('#deleteModalOverlay').removeClass('open');
    pendingDeleteId = null;
}

//Form validation
function validateCrudForm() {
    let ok = true;
    const fn = $('#formFirstname').val().trim();
    const ln = $('#formLastname').val().trim();
    const em = $('#formEmail').val().trim();
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!fn) {
        $('#formFirstname').addClass('is-invalid');
        $('#errFirstname').show();
        ok = false;
    } else {
        $('#formFirstname').removeClass('is-invalid');
        $('#errFirstname').hide();
    }

    if (!ln) {
        $('#formLastname').addClass('is-invalid');
        $('#errLastname').show();
        ok = false;
    } else {
        $('#formLastname').removeClass('is-invalid');
        $('#errLastname').hide();
    }

    if (!em || !emailRe.test(em)) {
        $('#formEmail').addClass('is-invalid');
        $('#errEmail').show();
        ok = false;
    } else {
        $('#formEmail').removeClass('is-invalid');
        $('#errEmail').hide();
    }

    return ok;
}

//Helpers
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

$(document).ready(function () {

    // Initial load
    loadTable();

    // Search — debounced 500 ms
    $('#searchInput').on('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            state.search = $('#searchInput').val().trim();
            state.page = 1;
            loadTable();
        }, 500);
    });

    // Per-page selector
    $('#limitSelect').on('change', function () {
        state.limit = parseInt($(this).val(), 10);
        state.page = 1;
        loadTable();
    });

    // Sortable column headers
    $(document).on('click', 'th[data-col]', function () {
        const col = $(this).data('col');
        if (state.sortBy === col) {
            state.order = (state.order === 'ASC') ? 'DESC' : 'ASC';
        } else {
            state.sortBy = col;
            state.order = 'ASC';
        }
        state.page = 1;
        loadTable();
    });

    // Pagination buttons (delegated)
    $(document).on('click', '.page-btn:not(.disabled)', function () {
        const p = parseInt($(this).data('page'), 10);
        if (!isNaN(p) && p !== state.page) {
            state.page = p;
            loadTable();
        }
    });

    // Cache-clear button
    $(document).on('click', '#clearCacheBtn', function () {
        $.get('ajax_tabel.php', { action: 'clear_cache' }, function (res) {
            if (res.success) {
                showToast(res.message || 'Cache cleared.');
                loadTable();
            }
        }, 'json');
    });

    //Add User button
    $(document).on('click', '#addUserBtn', function () {
        openCrudModal('create', {});
    });

    //Edit button (row)
    $(document).on('click', '.btn-edit', function () {
        const id = $(this).data('id');
        $.ajax({
            type: 'GET',
            url: 'crud.php',
            data: { action: 'read', id: id },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    openCrudModal('edit', res.data);
                } else {
                    showToast(res.message || 'Could not fetch user.', 'error');
                }
            },
            error: function () {
                showToast('Request failed.', 'error');
            }
        });
    });

    //Delete button (row)
    $(document).on('click', '.btn-delete', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');
        openDeleteModal(id, name);
    });

    // CRUD Form submit (Create / Update)
    $(document).on('submit', '#crudForm', function (e) {
        e.preventDefault();
        if (!validateCrudForm()) return;

        const id = $('#formUserId').val();
        const action = id ? 'update' : 'create';
        const $saveBtn = $('#modalSaveBtn');

        $saveBtn.prop('disabled', true).text('Saving…');

        $.ajax({
            type: 'POST',
            url: 'crud.php',
            data: {
                action: action,
                id: id,
                firstname: $('#formFirstname').val().trim(),
                lastname: $('#formLastname').val().trim(),
                email: $('#formEmail').val().trim(),
            },
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    closeCrudModal();
                    showToast(res.message || 'Saved successfully.');
                    loadTable();
                } else {
                    showToast(res.message || 'Something went wrong.', 'error');
                    $saveBtn.prop('disabled', false).text('Save');
                }
            },
            error: function () {
                showToast('Request failed.', 'error');
                $saveBtn.prop('disabled', false).html(`
                    <svg style="vertical-align:-2px;margin-right:5px" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg> Save`);
            }
        });
    });

    // Confirm delete
    $(document).on('click', '#confirmDeleteBtn', function () {
        if (!pendingDeleteId) return;
        const id = pendingDeleteId;
        $('#confirmDeleteBtn').prop('disabled', true).text('Deleting…');

        $.ajax({
            type: 'POST',
            url: 'crud.php',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function (res) {
                closeDeleteModal();
                if (res.success) {
                    showToast(res.message || 'User deleted.');
                    // If we deleted the only row on this page, go to previous page
                    if ($('#tableBody tr').length === 1 && state.page > 1) state.page--;
                    loadTable();
                } else {
                    showToast(res.message || 'Could not delete.', 'error');
                }
            },
            error: function () {
                closeDeleteModal();
                showToast('Request failed.', 'error');
            }
        });
    });

    // Modal close buttons / cancel
    $(document).on('click', '#modalCloseBtn, #modalCancelBtn', closeCrudModal);
    $(document).on('click', '#deleteModalCloseBtn, #deleteModalCancelBtn', closeDeleteModal);

    // Click outside modal → close
    $(document).on('click', '.modal-overlay', function (e) {
        if ($(e.target).hasClass('modal-overlay')) {
            closeCrudModal();
            closeDeleteModal();
        }
    });

    // Escape key → close any open modal
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeCrudModal();
            closeDeleteModal();
        }
    });
});