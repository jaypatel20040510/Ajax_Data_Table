/**
 * ajax_tabel.js
 * Handles: AJAX fetch → render table rows + pagination + sort headers
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

// ─── Main loader ──────────────────────────────────────────────────────────────
function loadTable() {
    const params = new URLSearchParams({
        search: state.search,
        sort_by: state.sortBy,
        order: state.order,
        page: state.page,
        limit: state.limit,
    });

    // Show loading indicator
    $('#tableBody').html(
        '<tr><td colspan="4" class="loading-cell"><span class="spinner"></span> Loading...</td></tr>'
    );
    $('#paginationContainer').html('');
    $('#resultInfo').text('');

    $.ajax({
        type: 'GET',
        url: 'ajax_tabel.php',
        data: params.toString(),
        dataType: 'json',
        // cache: true,
        success: function (res) {
            console.log("Response from ajax", res);

            if (!res.success) {
                showError(res.error || 'Unknown error');
                return;
            }
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
            '<tr><td colspan="4" class="empty-cell">No records found.</td></tr>'
        );
        return;
    }

    const rows = data.map(function (row) {
        return `<tr>
            <td>${escHtml(row.id)}</td>
            <td>${escHtml(row.firstname)}</td>
            <td>${escHtml(row.lastname)}</td>
            <td>${escHtml(row.email)}</td>
        </tr>`;
    }).join('');

    $('#tableBody').html(rows);
}

function renderPagination(res) {
    const { page, total_pages } = res;
    if (total_pages <= 1) {
        $('#paginationContainer').html('');
        return;
    }

    let html = '';

    // Previous
    html += `<button class="page-btn ${page <= 1 ? 'disabled' : ''}"
                data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>&#8592; Prev</button>`;

    // Page numbers — show a window of 5 around current page
    const window_size = 5;
    let start = Math.max(1, page - Math.floor(window_size / 2));
    let end = Math.min(total_pages, start + window_size - 1);
    if (end - start + 1 < window_size) {
        start = Math.max(1, end - window_size + 1);
    }

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

    // Next
    html += `<button class="page-btn ${page >= total_pages ? 'disabled' : ''}"
                data-page="${page + 1}" ${page >= total_pages ? 'disabled' : ''}>Next &#8594;</button>`;

    $('#paginationContainer').html(html);
}

function renderInfo(res) {
    const { page, limit, total } = res;
    if (total === 0) {
        $('#resultInfo').text('No records found.');
        return;
    }
    const from = (page - 1) * limit + 1;
    const to = Math.min(page * limit, total);
    $('#resultInfo').text(`Showing ${from}–${to} of ${total} records`);
}

function renderSortIcons(sortBy, order) {
    // Reset all headers
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
    $('#tableBody').html(`<tr><td colspan="4" class="error-cell">⚠ ${escHtml(msg)}</td></tr>`);
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─── Event Bindings ───────────────────────────────────────────────────────────
$(document).ready(function () {

    // Initial load
    loadTable();

    // Search — debounced 350 ms
    $('#searchInput').on('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            state.search = $('#searchInput').val().trim();
            state.page = 1;   // reset to first page on new search
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
            // Toggle direction
            state.order = (state.order === 'ASC') ? 'DESC' : 'ASC';
        } else {
            state.sortBy = col;
            state.order = 'ASC';
        }
        state.page = 1;
        loadTable();
    });

    // Pagination buttons (delegated — buttons are re-rendered each time)
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
                alert(res.message);
                loadTable();   // reload immediately from fresh DB data
            }
        }, 'json');
    });

});