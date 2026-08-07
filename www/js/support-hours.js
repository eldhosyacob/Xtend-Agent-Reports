$(document).ready(function () {
    let currentPage = 1;
    let currentDept = $('#department-filter').val() || 'voice_logger';
    let currentRange = $('#range-filter').val() || 'today';
    let searchQuery = $('#search-input').val() || '';

    loadSupportHours(currentPage);

    $('#department-filter').on('change', function () {
        currentDept = $(this).val();
        currentPage = 1;
        loadSupportHours(currentPage);
    });

    $('#range-filter').on('change', function () {
        currentRange = $(this).val();
        currentPage = 1;
        loadSupportHours(currentPage);
    });

    let searchTimer;
    $('#search-input').on('keyup input', function () {
        clearTimeout(searchTimer);
        searchQuery = $(this).val();
        searchTimer = setTimeout(function () {
            currentPage = 1;
            loadSupportHours(currentPage);
        }, 300);
    });

    $('#btnPrevPage').on('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadSupportHours(currentPage);
        }
    });

    $('#btnNextPage').on('click', function () {
        currentPage++;
        loadSupportHours(currentPage);
    });

    function loadSupportHours(page) {
        $('#loadingOverlay').addClass('active');

        $.ajax({
            url: 'api/fetch-support-hours-details.php',
            type: 'GET',
            data: {
                department: currentDept,
                range: currentRange,
                search: searchQuery,
                page: page,
                limit: 15
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    let rangeLabel = "Today";
                    if (response.range === 'week') rangeLabel = "This Week";
                    else if (response.range === 'month') rangeLabel = "This Month";
                    else if (response.range === 'year') rangeLabel = "This Year";

                    $('#pageSubtitle').text(`Showing agent wise total support time for ${response.department} (${rangeLabel})`);
                    $('#valSummarySupportHours').text(response.summary.total_support_hours || '00:00:00');
                    $('#valSummaryRecords').text(response.summary.total_timeframe_records || 0);

                    renderSupportHoursTable(response.agents, rangeLabel);
                    renderPagination(response.page, response.pages, response.total);
                } else {
                    $('#supportHoursTableBody').html(`<tr><td colspan="5" style="text-align: center; padding: 30px;">${escapeHtml(response.message)}</td></tr>`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching support hours:', error, xhr.responseText);
                let msg = 'Error loading support hours details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#supportHoursTableBody').html(`<tr><td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(msg)}</td></tr>`);
            },
            complete: function () {
                $('#loadingOverlay').removeClass('active');
            }
        });
    }

    function renderSupportHoursTable(agents, rangeLabel) {
        const $tbody = $('#supportHoursTableBody');
        $tbody.empty();

        if (!agents || agents.length === 0) {
            $tbody.append(`
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fa-solid fa-clock-rotate-left" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                        No support time records found for this selection.
                    </td>
                </tr>
            `);
            return;
        }

        agents.forEach(function (agent) {
            const onlineDot = agent.is_online ? '<span class="online-pulse-dot" title="Online"></span>' : '';
            const trHtml = `
                <tr>
                    <td>
                        <div class="agent-user-info">
                            <div class="avatar-wrapper">
                                <img src="${escapeHtml(agent.profile_photo_url)}" alt="Avatar" class="agent-avatar">
                                ${onlineDot}
                            </div>
                            <div class="agent-names">
                                <span class="agent-real-name">${escapeHtml(agent.agent_name)}</span>
                                <span class="agent-username">@${escapeHtml(agent.username)}</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="role-badge">${escapeHtml(agent.role)}</span></td>
                    <td><span class="support-badge">${escapeHtml(agent.timeframe_support_time || '00:00:00')}</span></td>
                    <td><strong>${agent.timeframe_records}</strong></td>
                    <td>${escapeHtml(agent.last_record_date || agent.last_activity || '-')}</td>
                </tr>
            `;
            $tbody.append(trHtml);
        });
    }

    function renderPagination(page, pages, total) {
        currentPage = page;
        $('#paginationInfo').text(`Showing page ${page} of ${pages} (${total} agent(s))`);
        $('#btnPrevPage').prop('disabled', page <= 1);
        $('#btnNextPage').prop('disabled', page >= pages);
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
