$(document).ready(function () {
    let currentPage = 1;
    let currentDept = $('#department-filter').val() || 'voice_logger';
    let currentRange = $('#range-filter').val() || 'today';
    let currentStatus = $('#status-filter').val() || '';
    let searchQuery = $('#search-input').val() || '';

    // Load records on init
    loadRecords(currentPage);

    // Event listeners
    $('#department-filter').on('change', function () {
        currentDept = $(this).val();
        currentPage = 1;
        loadRecords(currentPage);
    });

    $('#range-filter').on('change', function () {
        currentRange = $(this).val();
        currentPage = 1;
        loadRecords(currentPage);
    });

    $('#status-filter').on('change', function () {
        currentStatus = $(this).val();
        currentPage = 1;
        loadRecords(currentPage);
    });

    let searchTimer;
    $('#search-input').on('keyup input', function () {
        clearTimeout(searchTimer);
        searchQuery = $(this).val();
        searchTimer = setTimeout(function () {
            currentPage = 1;
            loadRecords(currentPage);
        }, 300);
    });

    $('#btnPrevPage').on('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadRecords(currentPage);
        }
    });

    $('#btnNextPage').on('click', function () {
        currentPage++;
        loadRecords(currentPage);
    });

    function getStatusBadge(status) {
        const s = (status || '').trim();
        let dotColor = '#64748b';
        let bg = 'rgba(100, 116, 139, 0.1)';

        if (s.toLowerCase().startsWith('closed')) {
            dotColor = '#10b981';
            bg = 'rgba(16, 185, 129, 0.12)';
        } else if (s === 'Pending') {
            dotColor = '#ef4444';
            bg = 'rgba(239, 68, 68, 0.12)';
        } else if (s === 'Under Observation' || s === 'Escalated' || s === 'Escalated to Presales') {
            dotColor = '#e8951a';
            bg = 'rgba(232, 149, 26, 0.12)';
        }

        return `<span class="status-badge" style="background: ${bg}; color: ${dotColor};">
            <span class="status-dot" style="background-color: ${dotColor}"></span>
            ${escapeHtml(s)}
        </span>`;
    }

    function loadRecords(page) {
        $('#loadingOverlay').addClass('active');

        $.ajax({
            url: 'api/fetch-dashboard-records.php',
            type: 'GET',
            data: {
                department: currentDept,
                range: currentRange,
                status: currentStatus,
                search: searchQuery,
                page: page,
                limit: 15
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderRecordsTable(response.records);
                    renderPagination(response.page, response.pages, response.total);
                    updateSubtitle(response.department, response.range, response.total);
                } else {
                    $('#recordsTableBody').html(`<tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(response.message)}</td></tr>`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching records:', error, xhr.responseText);
                let msg = 'Error loading records details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#recordsTableBody').html(`<tr><td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(msg)}</td></tr>`);
            },
            complete: function () {
                $('#loadingOverlay').removeClass('active');
            }
        });
    }

    function renderRecordsTable(records) {
        const $tbody = $('#recordsTableBody');
        $tbody.empty();

        if (!records || records.length === 0) {
            $tbody.append(`
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fa-solid fa-folder-open" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                        No records found matching your current filter criteria.
                    </td>
                </tr>
            `);
            return;
        }

        records.forEach(function (row) {
            const dateStr = row.date ? row.date : '-';

            const trHtml = `
                <tr>
                    <td>${escapeHtml(dateStr)}</td>
                    <td>${escapeHtml(row.agent || '-')}</td>
                    <td>${escapeHtml(row.company_name || '-')}</td>
                    <td>${escapeHtml(row.dealer_name || '-')}</td>
                    <td>${escapeHtml(row.location || '-')}</td>
                    <td>${escapeHtml(row.issue_category || '-')}</td>
                    <td>${getStatusBadge(row.support_status)}</td>
                </tr>
            `;
            $tbody.append(trHtml);
        });
    }

    function renderPagination(page, pages, total) {
        currentPage = page;
        $('#paginationInfo').text(`Showing page ${page} of ${pages} (${total} total records)`);
        $('#btnPrevPage').prop('disabled', page <= 1);
        $('#btnNextPage').prop('disabled', page >= pages);
    }

    function updateSubtitle(dept, range, total) {
        let rangeLabel = "Today's";
        if (range === 'week') rangeLabel = "This Week's";
        else if (range === 'month') rangeLabel = "This Month's";
        else if (range === 'year') rangeLabel = "This Year's";

        $('#pageSubtitle').text(`Viewing ${total} record(s) of ${dept} (${rangeLabel})`);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
