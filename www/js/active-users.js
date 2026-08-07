$(document).ready(function () {
    let currentPage = 1;
    let currentDept = $('#department-filter').val() || 'voice_logger';
    let currentRange = $('#range-filter').val() || 'today';
    let searchQuery = $('#search-input').val() || '';

    loadActiveUsers(currentPage);

    $('#department-filter').on('change', function () {
        currentDept = $(this).val();
        currentPage = 1;
        loadActiveUsers(currentPage);
    });

    $('#range-filter').on('change', function () {
        currentRange = $(this).val();
        currentPage = 1;
        loadActiveUsers(currentPage);
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

        $.ajax({
            url: 'api/fetch-active-users-details.php',
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
                    renderActiveUsersTable(response.active_users);
                    renderPagination(response.page, response.pages, response.total);

                    let rangeLabel = "Today";
                    if (response.range === 'week') rangeLabel = "This Week";
                    else if (response.range === 'month') rangeLabel = "This Month";
                    else if (response.range === 'year') rangeLabel = "This Year";

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
