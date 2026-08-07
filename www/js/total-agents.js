$(document).ready(function () {
    let currentPage = 1;
    let currentDept = $('#department-filter').val() || 'voice_logger';
    let searchQuery = $('#search-input').val() || '';

    loadAgents(currentPage);

    $('#department-filter').on('change', function () {
        currentDept = $(this).val();
        currentPage = 1;
        loadAgents(currentPage);
    });

    let searchTimer;
    $('#search-input').on('keyup input', function () {
        clearTimeout(searchTimer);
        searchQuery = $(this).val();
        searchTimer = setTimeout(function () {
            currentPage = 1;
            loadAgents(currentPage);
        }, 300);
    });

    $('#btnPrevPage').on('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadAgents(currentPage);
        }
    });

    $('#btnNextPage').on('click', function () {
        currentPage++;
        loadAgents(currentPage);
    });

    function loadAgents(page) {
        $('#loadingOverlay').addClass('active');

        $.ajax({
            url: 'api/fetch-total-agents-details.php',
            type: 'GET',
            data: {
                department: currentDept,
                search: searchQuery,
                page: page,
                limit: 15
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    renderAgentsTable(response.agents);
                    renderPagination(response.page, response.pages, response.total);
                    $('#pageSubtitle').text(`Displaying ${response.total} agent(s) of ${response.department}`);
                } else {
                    $('#agentsTableBody').html(`<tr><td colspan="6" style="text-align: center; padding: 30px;">${escapeHtml(response.message)}</td></tr>`);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching total agents:', error, xhr.responseText);
                let msg = 'Error loading agent details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#agentsTableBody').html(`<tr><td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 30px;">${escapeHtml(msg)}</td></tr>`);
            },
            complete: function () {
                $('#loadingOverlay').removeClass('active');
            }
        });
    }

    function renderAgentsTable(agents) {
        const $tbody = $('#agentsTableBody');
        $tbody.empty();

        if (!agents || agents.length === 0) {
            $tbody.append(`
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fa-solid fa-users-slash" style="font-size: 32px; margin-bottom: 10px; display: block;"></i>
                        No agents found for this selection.
                    </td>
                </tr>
            `);
            return;
        }

        agents.forEach(function (agent) {
            const statusClass = agent.is_online ? 'online' : 'offline';
            const statusText = agent.is_online ? 'Online' : 'Offline';
            const dotColor = agent.is_online ? 'green' : 'gray';

            const trHtml = `
                <tr>
                    <td>
                        <div class="agent-user-info">
                            <img src="${escapeHtml(agent.profile_photo_url)}" alt="Avatar" class="agent-avatar">
                            <div class="agent-names">
                                <span class="agent-real-name">${escapeHtml(agent.real_name)}</span>
                                <span class="agent-username">@${escapeHtml(agent.username)}</span>
                            </div>
                        </div>
                    </td>
                    <td><span class="role-badge">${escapeHtml(agent.role)}</span></td>
                    <td>${escapeHtml(agent.department)}</td>
                    <td>
                        <span class="online-status-badge ${statusClass}">
                            <span class="status-dot-sm ${dotColor}"></span>
                            ${statusText}
                        </span>
                    </td>
                    <td><strong>${agent.total_records}</strong></td>
                    <td>${escapeHtml(agent.last_record_date || agent.last_activity || '-')}</td>
                </tr>
            `;
            $tbody.append(trHtml);
        });
    }

    function renderPagination(page, pages, total) {
        currentPage = page;
        $('#paginationInfo').text(`Showing page ${page} of ${pages} (${total} total agents)`);
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
