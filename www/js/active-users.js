$(document).ready(function () {
    const urlParams = new URLSearchParams(window.location.search);
    let currentPage = 1;
    let currentDept = $('#department-filter').val() || urlParams.get('department') || 'voice_logger';
    let currentRange = $('#range-filter').val() || urlParams.get('range') || 'today';
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

    loadActiveUsers(currentPage);

    $('#department-filter').on('change', function () {
        currentDept = $(this).val();
        currentPage = 1;
        loadActiveUsers(currentPage);
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
        loadActiveUsers(currentPage);
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
        loadActiveUsers(currentPage);
    });

    $('#from-date-filter, #to-date-filter').on('change', function () {
        if (currentRange === 'custom') {
            customFromDate = $('#from-date-filter').val();
            customToDate = $('#to-date-filter').val();
            if (customFromDate && customToDate && customFromDate <= customToDate) {
                currentPage = 1;
                loadActiveUsers(currentPage);
            }
        }
    });

    let searchTimer;
    $('#search-input').on('keyup input', function () {
        clearTimeout(searchTimer);
        searchQuery = $(this).val();
        searchTimer = setTimeout(function () {
            currentPage = 1;
            loadActiveUsers(currentPage);
        }, 300);
    });

    $('#btnPrevPage').on('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadActiveUsers(currentPage);
        }
    });

    $('#btnNextPage').on('click', function () {
        currentPage++;
        loadActiveUsers(currentPage);
    });

    function loadActiveUsers(page) {
        $('#loadingOverlay').addClass('active');

        const reqData = {
            department: currentDept,
            range: currentRange,
            search: searchQuery,
            page: page,
            limit: 15
        };

        if (currentRange === 'custom') {
            reqData.from_date = customFromDate;
            reqData.to_date = customToDate;
        }

        $.ajax({
            url: 'api/fetch-active-users-details.php',
            type: 'GET',
            data: reqData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderActiveUsersTable(response.active_users);
                    renderPagination(response.page, response.pages, response.total);

                    let rangeLabel = "Today";
                    if (response.range === 'week') rangeLabel = "This Week";
                    else if (response.range === 'month') rangeLabel = "This Month";
                    else if (response.range === 'year') rangeLabel = "This Year";
                    else if (response.range === 'custom') {
                        if (customFromDate && customToDate) {
                            rangeLabel = customFromDate === customToDate ? customFromDate : `${customFromDate} to ${customToDate}`;
                        } else {
                            rangeLabel = "Custom Period";
                        }
                    }

                    $('#pageSubtitle').text(`Showing ${response.total} active user(s) of ${response.department} (${rangeLabel})`);
                } else {
                    $('#activeUsersTableBody').html(`<tr><td colspan="6" style="text-align: center; padding: 30px;">${escapeHtml(response.message)}</td></tr>`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching active users:', error, xhr.responseText);
                let msg = 'Error loading active user details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#activeUsersTableBody').html(`<tr><td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(msg)}</td></tr>`);
            },
            complete: function () {
                $('#loadingOverlay').removeClass('active');
            }
        });
    }

    function renderActiveUsersTable(users) {
        const $tbody = $('#activeUsersTableBody');
        $tbody.empty();

        if (!users || users.length === 0) {
            $tbody.append(`
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fa-solid fa-user-clock" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                        No active users currently online for this department.
                    </td>
                </tr>
            `);
            return;
        }

        users.forEach(function (user) {
            const trHtml = `
                <tr>
                    <td>
                        <div class="agent-user-info">
                            <div class="avatar-wrapper">
                                <img src="${escapeHtml(user.profile_photo_url)}" alt="Avatar" class="agent-avatar">
                                <span class="online-pulse-dot"></span>
                            </div>
                            <div class="agent-names">
                                <span class="agent-real-name">${escapeHtml(user.real_name)}</span>
                                <span class="agent-username">@${escapeHtml(user.username)}</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="role-badge">${escapeHtml(user.role)}</span></td>
                    <td>${escapeHtml(user.department)}</td>
                    <td><strong>${user.timeframe_records}</strong></td>
                    <td>${user.total_records}</td>
                    <td>${escapeHtml(user.last_activity || '-')}</td>
                </tr>
            `;
            $tbody.append(trHtml);
        });
    }

    function renderPagination(page, pages, total) {
        currentPage = page;
        $('#paginationInfo').text(`Showing page ${page} of ${pages} (${total} active users)`);
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
