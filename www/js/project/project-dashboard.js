$(document).ready(function() {
  let currentDept = $('.dashboard-page-container').data('default-dept') || 'voice_logger';
  let currentRange = 'today';
  let customFromDate = '';
  let customToDate = '';

  // Format local date string as YYYY-MM-DD
  function getLocalDateStr() {
    const d = new Date();
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  // Default custom date inputs to today's date if empty
  function initCustomDates() {
    const todayStr = getLocalDateStr();
    if (!$('#from-date-filter').val()) {
      $('#from-date-filter').val(todayStr);
    }
    if (!$('#to-date-filter').val()) {
      $('#to-date-filter').val(todayStr);
    }
    customFromDate = $('#from-date-filter').val();
    customToDate = $('#to-date-filter').val();
  }
  initCustomDates();

  // Load project dashboard stats on initialization
  loadProjectDashboardStats(currentDept, currentRange);

  // Auto-refresh stats silently every 30 seconds
  setInterval(function() {
    if (currentRange === 'custom') {
      loadProjectDashboardStats(currentDept, currentRange, true, $('#from-date-filter').val(), $('#to-date-filter').val());
    } else {
      loadProjectDashboardStats(currentDept, currentRange, true);
    }
  }, 30000);

  // Department Tab selection click handler (Voice Logger / IVR)
  $('.tab-btn').on('click', function() {
    if ($(this).hasClass('active')) return;

    $('.tab-btn').removeClass('active');
    $(this).addClass('active');

    currentDept = $(this).data('dept') || 'voice_logger';
    if (currentRange === 'custom') {
      loadProjectDashboardStats(currentDept, currentRange, false, $('#from-date-filter').val(), $('#to-date-filter').val());
    } else {
      loadProjectDashboardStats(currentDept, currentRange);
    }
  });

  // Date range select change handler
  $('#date-range-filter').on('change', function() {
    currentRange = $(this).val() || 'today';
    if (currentRange === 'custom') {
      initCustomDates();
      $('#custom-date-inputs').addClass('active').show();
      customFromDate = $('#from-date-filter').val();
      customToDate = $('#to-date-filter').val();
      loadProjectDashboardStats(currentDept, currentRange, false, customFromDate, customToDate);
    } else {
      $('#custom-date-inputs').removeClass('active').hide();
      loadProjectDashboardStats(currentDept, currentRange);
    }
  });

  // Search button click handler for custom date range
  $('#btn-custom-date-search').on('click', function() {
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
    loadProjectDashboardStats(currentDept, 'custom', false, customFromDate, customToDate);
  });

  // Auto update when custom date range input changes
  $('#from-date-filter, #to-date-filter').on('change', function() {
    if (currentRange === 'custom') {
      customFromDate = $('#from-date-filter').val();
      customToDate = $('#to-date-filter').val();
      if (customFromDate && customToDate && customFromDate <= customToDate) {
        loadProjectDashboardStats(currentDept, 'custom', false, customFromDate, customToDate);
      }
    }
  });

  // Click handlers for stats cards to navigate to relevant project pages
  $('.today-card').on('click', function() {
    let url = 'time-frame-reports.php?department=' + encodeURIComponent(currentDept) + '&range=' + encodeURIComponent(currentRange);
    if (currentRange === 'custom') {
      url += '&from_date=' + encodeURIComponent($('#from-date-filter').val()) + '&to_date=' + encodeURIComponent($('#to-date-filter').val());
    }
    window.location.href = url;
  });

  // Helper function to map statuses to standard theme colors
  function getStatusColor(status) {
    const s = (status || '').toLowerCase().trim();
    if (s === 'completed') {
      return 'var(--success-color, #10b981)';
    }
    if (s === 'in progress' || s === 'inprogress') {
      return '#3b82f6';
    }
    if (s === 'pending') {
      return 'var(--warning-color, #f59e0b)';
    }
    if (s === 'on hold' || s === 'onhold' || s === 'hold') {
      return '#8b5cf6';
    }
    return 'var(--text-secondary, #64748b)';
  }

  // Fetch project dashboard stats from backend API
  function loadProjectDashboardStats(department, range, isSilent = false, fromDate = '', toDate = '') {
    if (!isSilent) {
      $('#dashboardLoading').addClass('active');
    }

    department = department || currentDept;
    range = range || currentRange;
    const requestParams = { department: department, range: range };

    if (range === 'custom') {
      requestParams.from_date = fromDate || $('#from-date-filter').val();
      requestParams.to_date = toDate || $('#to-date-filter').val();
    }

    $.ajax({
      url: '../api/fetch-project-dashboard.php',
      type: 'GET',
      data: requestParams,
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          // Dynamically update card labels and table headers based on range
          let rangeLabel = "Today's Reports";
          let activeLabel = "Agents Active Today";
          let thLabel = "Today's Reports";

          if (range === 'week') {
            rangeLabel = "This Week's Reports";
            thLabel = "This Week's Reports";
          } else if (range === 'month') {
            rangeLabel = "This Month's Reports";
            thLabel = "This Month's Reports";
          } else if (range === 'year') {
            rangeLabel = "This Year's Reports";
            thLabel = "This Year's Reports";
          } else if (range === 'custom') {
            const fDate = requestParams.from_date || '';
            const tDate = requestParams.to_date || '';
            if (fDate && tDate) {
              if (fDate === tDate) {
                rangeLabel = "Reports (" + fDate + ")";
                thLabel = "Reports (" + fDate + ")";
              } else {
                rangeLabel = "Reports (" + fDate + " - " + tDate + ")";
                thLabel = "Reports (" + fDate + " - " + tDate + ")";
              }
            } else {
              rangeLabel = "Custom Range Reports";
              thLabel = "Custom Range Reports";
            }
          }

          $('#lblTodayReports').text(rangeLabel);
          $('#lblAgentsActiveToday').text(activeLabel);
          $('#thAgentTimeframeReports').text(thLabel);

          // 1. Animate / Update summary card values
          animateCardValue($('#valTodayReports'), response.summary.timeframe_reports);
          animateCardValue($('#valActiveAgents'), response.summary.total_agents || response.summary.active_agents);
          animateCardValue($('#valAgentsActiveToday'), response.summary.online_agents || response.summary.agents_active_timeframe);
          $('#valTotalTime').text(response.summary.total_time || response.summary.total_support_hours || '00:00:00');

          // 2. Render Status Breakdown
          const $statusContainer = $('#statusListContainer');
          $statusContainer.empty();

          if (response.status_breakdown && response.status_breakdown.length > 0) {
            const maxCount = response.status_breakdown.reduce((sum, item) => sum + parseInt(item.count || 0), 0) || 1;
            response.status_breakdown.forEach(function(item) {
              const statusName = item.status || 'Unspecified';
              const count = parseInt(item.count || 0);
              const pct = maxCount > 0 ? Math.round((count / maxCount) * 100) : 0;
              const color = getStatusColor(statusName);

              const html = `
                <div class="status-item">
                  <div class="status-info">
                    <span class="status-name">
                      <span class="status-dot" style="background-color: ${color}"></span>
                      ${escapeHtml(statusName)}
                      <span class="status-count">${count}</span>
                    </span>
                    <span class="status-pct">${pct}%</span>
                  </div>
                  <div class="progress-bar-wrapper">
                    <div class="progress-bar" style="width: ${pct}%; background-color: ${color}"></div>
                  </div>
                </div>
              `;
              $statusContainer.append(html);
            });
          } else {
            $statusContainer.append(`
              <div class="empty-state">
                <i class="fa-solid fa-circle-nodes"></i>
                <p>No status reports found for this department.</p>
              </div>
            `);
          }

          // 3. Render Agent Performance Table
          const $agentBody = $('#agentTableBody');
          $agentBody.empty();

          if (response.agent_details && response.agent_details.length > 0) {
            response.agent_details.forEach(function(agent) {
              let roleClass = 'badge-role-agent';
              const roleLower = (agent.role || '').toLowerCase();
              if (roleLower === 'admin') {
                roleClass = 'badge-role-admin';
              } else if (roleLower === 'manager') {
                roleClass = 'badge-role-manager';
              }

              // Timeframe reports count badge
              const timeframeVal = parseInt(agent.timeframe_reports !== undefined ? agent.timeframe_reports : (agent.timeframe_records || 0));
              const timeframeBadge = timeframeVal > 0 ? 
                `<span class="record-count-today">${timeframeVal}</span>` : 
                `<span class="record-count-zero">-</span>`;

              // Format last report date
              let formattedDate = '-';
              const rawDate = agent.last_report || agent.last_active;
              if (rawDate && rawDate !== '-') {
                const dateObj = new Date(rawDate);
                formattedDate = isNaN(dateObj.getTime()) ? rawDate : dateObj.toLocaleDateString('en-US', {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric'
                });
              }

              const html = `
                <tr>
                  <td>
                    <div class="agent-profile">
                      <span class="online-status-dot ${agent.is_online ? 'online' : 'offline'}" title="${agent.is_online ? 'Online' : 'Offline'}"></span>
                      <div class="agent-avatar">
                        <img src="${agent.profile_photo_url}" alt="${escapeHtml(agent.agent_name)}" class="agent-avatar-img">
                      </div>
                      <div class="agent-meta">
                        <span class="agent-realname">${escapeHtml(agent.agent_name)}</span>
                      </div>
                    </div>
                  </td>
                  <td><span style="font-family: monospace;">${escapeHtml(agent.username)}</span></td>
                  <td><span class="badge ${roleClass}">${escapeHtml(agent.role)}</span></td>
                  <td>${timeframeBadge}</td>
                  <td><span class="timeframe-time-cell">${escapeHtml(agent.timeframe_total_time || agent.timeframe_support_time || '00:00:00')}</span></td>
                  <td><span class="agent-last-active">${escapeHtml(formattedDate)}</span></td>
                </tr>
              `;
              $agentBody.append(html);
            });
          } else {
            $agentBody.append(`
              <tr>
                <td colspan="6">
                  <div class="empty-state">
                    <i class="fa-solid fa-users-slash"></i>
                    <p>No active users found for this department.</p>
                  </div>
                </td>
              </tr>
            `);
          }
        } else {
          alert('Project Dashboard Error: ' + response.message);
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX error fetching project dashboard stats:', error);
      },
      complete: function() {
        $('#dashboardLoading').removeClass('active');
      }
    });
  }

  // Animate stat number values (counting up)
  function animateCardValue($el, endValue) {
    const end = parseInt(endValue) || 0;
    if ($el.text() === '-') {
      $el.text('0');
    }
    const start = parseInt($el.text()) || 0;
    if (start === end) {
      $el.text(end);
      return;
    }

    let startTimestamp = null;
    const duration = 800; // ms
    const step = (timestamp) => {
      if (!startTimestamp) startTimestamp = timestamp;
      const progress = Math.min((timestamp - startTimestamp) / duration, 1);
      $el.text(Math.floor(progress * (end - start) + start));
      if (progress < 1) {
        window.requestAnimationFrame(step);
      } else {
        $el.text(end);
      }
    };
    window.requestAnimationFrame(step);
  }

  // Simple HTML Escaping
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
