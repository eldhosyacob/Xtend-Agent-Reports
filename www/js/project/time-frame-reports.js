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

    // Load reports on init
    loadReports(currentPage);

    // Event listeners
    $('#department-filter').on('change', function () {
        currentDept = $(this).val();
        currentPage = 1;
        loadReports(currentPage);
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
        loadReports(currentPage);
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
        loadReports(currentPage);
    });

    $('#from-date-filter, #to-date-filter').on('change', function () {
        if (currentRange === 'custom') {
            customFromDate = $('#from-date-filter').val();
            customToDate = $('#to-date-filter').val();
            if (customFromDate && customToDate && customFromDate <= customToDate) {
                currentPage = 1;
                loadReports(currentPage);
            }
        }
    });

    $('#status-filter').on('change', function () {
        currentStatus = $(this).val();
        currentPage = 1;
        loadReports(currentPage);
    });

    let searchTimer;
    $('#search-input').on('keyup input', function () {
        clearTimeout(searchTimer);
        searchQuery = $(this).val();
        searchTimer = setTimeout(function () {
            currentPage = 1;
            loadReports(currentPage);
        }, 300);
    });

    $('#btnPrevPage').on('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadReports(currentPage);
        }
    });

    $('#btnNextPage').on('click', function () {
        currentPage++;
        loadReports(currentPage);
    });

    function getStatusBadge(status) {
        const s = (status || '').trim();
        const lower = s.toLowerCase();
        let badgeClass = 'status-pending';
        let dotColor = '#e11d48';

        if (lower === 'completed') {
            badgeClass = 'status-completed';
            dotColor = '#10b981';
        } else if (lower === 'in progress' || lower === 'inprogress') {
            badgeClass = 'status-in-progress';
            dotColor = '#3b82f6';
        } else if (lower === 'on hold' || lower === 'onhold' || lower === 'hold') {
            badgeClass = 'status-on-hold';
            dotColor = '#8b5cf6';
        } else {
            badgeClass = 'status-pending';
            dotColor = '#e11d48';
        }

        return `<span class="status-badge ${badgeClass}">
            <span class="status-dot" style="background-color: ${dotColor}"></span>
            ${escapeHtml(s || 'Pending')}
        </span>`;
    }

    function loadReports(page) {
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
            url: '../api/fetch-time-frame-reports.php',
            type: 'GET',
            data: reqData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderReportsTable(response.records);
                    renderPagination(response.page, response.pages, response.total);
                    updateSubtitle(response.department, response.range, response.total);
                } else {
                    $('#reportsTableBody').html(`<tr><td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(response.message)}</td></tr>`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching reports:', error, xhr.responseText);
                let msg = 'Error loading timeframe reports.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#reportsTableBody').html(`<tr><td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(msg)}</td></tr>`);
            },
            complete: function () {
                $('#loadingOverlay').removeClass('active');
            }
        });
    }

    function renderReportsTable(records) {
        const $tbody = $('#reportsTableBody');
        $tbody.empty();

        if (!records || records.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="8">
                        <div class="empty-state" style="text-align: center;">
                            <p>No project reports found matching the selected criteria.</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        records.forEach(function (r) {
            const dateObj = new Date(r.date);
            const formattedDate = isNaN(dateObj.getTime()) ? (r.date || '-') : dateObj.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            const subtaskDisplay = r.subtask_title ?
                `<span>${escapeHtml(r.subtask_id || '-')}</span><span class="timeframe-table-subtask-title">${escapeHtml(r.subtask_title)}</span>` :
                `<span>${escapeHtml(r.subtask_id || '-')}</span>`;

            const html = `
                <tr>
                    <td><strong>${escapeHtml(formattedDate)}</strong></td>
                    <td><span style="font-family: monospace; font-weight: 600;">${escapeHtml(r.project_id || '-')}</span></td>
                    <td>${subtaskDisplay}</td>
                    <td><strong>${escapeHtml(r.agent_name || '-')}</strong></td>
                    <td>${escapeHtml(r.client_name || '-')}</td>
                    <td>${escapeHtml(r.project_name || '-')}</td>
                    <td><span class="time-cell-bold">${escapeHtml(r.total_time || '00:00:00')}</span></td>
                    <td>${getStatusBadge(r.status)}</td>
                </tr>
            `;
            $tbody.append(html);
        });
    }

    function renderPagination(page, totalPages, totalRecords) {
        currentPage = page;
        const start = totalRecords === 0 ? 0 : (page - 1) * 15 + 1;
        const end = Math.min(page * 15, totalRecords);

        $('#paginationInfo').text(`Showing ${start} to ${end} of ${totalRecords} reports`);
        $('#btnPrevPage').prop('disabled', page <= 1);
        $('#btnNextPage').prop('disabled', page >= totalPages);
    }

    function updateSubtitle(dept, range, total) {
        let rangeText = 'Today';
        if (range === 'week') rangeText = 'This Week';
        else if (range === 'month') rangeText = 'This Month';
        else if (range === 'year') rangeText = 'This Year';
        else if (range === 'custom') {
            if (customFromDate && customToDate) {
                rangeText = customFromDate === customToDate ? customFromDate : `${customFromDate} to ${customToDate}`;
            } else {
                rangeText = 'Custom Range';
            }
        }

        $('#pageSubtitle').text(`Showing ${total} report(s) for ${dept} (${rangeText})`);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
