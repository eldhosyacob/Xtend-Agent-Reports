$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    let currentPage = 1;
    let currentDept = $('#department-filter').val() || urlParams.get('department') || 'voice_logger';
    let currentRange = $('#range-filter').val() || urlParams.get('range') || 'today';
    let currentStatus = $('#status-filter').val() || urlParams.get('status') || '';
    let searchQuery = $('#search-input').val() || '';
    let customFromDate = urlParams.get('from_date') || '';
    let customToDate = urlParams.get('to_date') || '';

    function initCustomDates() {
        const todayStr = new Date().toISOString().split('T')[0];
        if (!$('#from-date-filter').val()) {
            $('#from-date-filter').val(customFromDate || todayStr);
        }
        if (!$('#to-date-filter').val()) {
            $('#to-date-filter').val(customToDate || todayStr);
        }
    }

    if (currentRange === 'custom') {
        initCustomDates();
        $('#custom-date-inputs').addClass('active');
        customFromDate = $('#from-date-filter').val();
        customToDate = $('#to-date-filter').val();
    }

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
        if (currentRange === 'custom') {
            initCustomDates();
            $('#custom-date-inputs').addClass('active');
            customFromDate = $('#from-date-filter').val();
            customToDate = $('#to-date-filter').val();
        } else {
            $('#custom-date-inputs').removeClass('active');
        }
        currentPage = 1;
        loadRecords(currentPage);
    });

    $('#btn-custom-date-search').on('click', function () {
        customFromDate = $('#from-date-filter').val();
        customToDate = $('#to-date-filter').val();
        if (!customFromDate || !customToDate) {
            alert('Please select both From and To dates.');
            return;
        }
        if (customFromDate > customToDate) {
            alert('From date cannot be after To date.');
            return;
        }
        currentPage = 1;
        loadRecords(currentPage);
    });

    $('#from-date-filter, #to-date-filter').on('change', function () {
        if (currentRange === 'custom') {
            customFromDate = $('#from-date-filter').val();
            customToDate = $('#to-date-filter').val();
            if (customFromDate && customToDate && customFromDate <= customToDate) {
                currentPage = 1;
                loadRecords(currentPage);
            }
        }
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

        const reqData = {
            department: currentDept,
            range: currentRange,
            status: currentStatus,
            search: searchQuery,
            page: page,
            limit: 15
        };

        if (currentRange === 'custom') {
            reqData.from_date = customFromDate;
            reqData.to_date = customToDate;
        }

        $.ajax({
            url: 'api/fetch-dashboard-records.php',
            type: 'GET',
            data: reqData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderRecordsTable(response.records);
                    renderPagination(response.page, response.pages, response.total);
                    updateSubtitle(response.department, response.range, response.total);
                } else {
                    $('#recordsTableBody').html(`<tr><td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(response.message)}</td></tr>`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching records:', error, xhr.responseText);
                let msg = 'Error loading records details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#recordsTableBody').html(`<tr><td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(msg)}</td></tr>`);
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
                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fa-solid fa-folder-open" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                        No records found matching your current filter criteria.
                    </td>
                </tr>
            `);
            return;
        }

        records.forEach(function (row) {
            const dateStr = row.date ? row.date : '-';
            const caseIdStr = row.case_id ? row.case_id : (row.record_id ? row.record_id : '-');

            const trHtml = `
                <tr>
                    <td>${escapeHtml(dateStr)}</td>
                    <td><span style="font-family: monospace; font-weight: 600;">${escapeHtml(caseIdStr)}</span></td>
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
        else if (range === 'custom') {
            if (customFromDate && customToDate) {
                rangeLabel = customFromDate === customToDate ? customFromDate : `${customFromDate} to ${customToDate}`;
            } else {
                rangeLabel = "Custom Period";
            }
        }

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
