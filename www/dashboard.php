<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Resolve user details and profile photo path
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : '';
$user_full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin';

// Extract first name for the greeting (e.g. "Hello, Eldhos!")
$user_first_name = 'Admin';
if (!empty($user_full_name)) {
    $name_parts = explode(' ', trim($user_full_name));
    $user_first_name = $name_parts[0];
}

$profile_photo_url = 'images/default-avatar.png'; // default fallback
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
if (!empty($user_id)) {
    foreach ($allowed_extensions as $ext) {
        $photo_path = __DIR__ . '/images/profile/' . $user_id . '.' . $ext;
        if (file_exists($photo_path)) {
            $profile_photo_url = 'images/profile/' . $user_id . '.' . $ext . '?t=' . filemtime($photo_path);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/dashboard.css">
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/common.css">
  </head>

  <body>
    <div class="dashboard-page-container page-containers">
      
      <!-- Top Header Title Section -->
      <div class="dashboard-header">
        <div class="dashboard-title-section">
          <div class="dashboard-profile-avatar-container">
            <?php if ($profile_photo_url !== 'images/default-avatar.png'): ?>
              <img src="<?php echo $profile_photo_url; ?>" alt="User Avatar" class="dashboard-profile-avatar-img">
            <?php else: ?>
              <div class="dashboard-profile-avatar-initials">
                <?php 
                  $initials = '';
                  if (!empty($user_full_name)) {
                    $words = explode(' ', $user_full_name);
                    foreach ($words as $word) {
                      $initials .= strtoupper(substr($word, 0, 1));
                    }
                    $initials = substr($initials, 0, 2);
                  }
                  echo htmlspecialchars($initials ?: '?');
                ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="dashboard-title-text-wrapper">
            <div class="dashboard-title">
              Hello, <?php echo htmlspecialchars($user_first_name); ?>!
            </div>
            <div class="dashboard-subtitle">Track team progress and performance here.</div>
          </div>
        </div>
        <div class="select-filter-wrapper">
          <i class="fa-solid fa-calendar-days select-icon"></i>
          <select id="date-range-filter" class="filter-select-control" aria-label="Filter by date range">
            <option value="today" selected>Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="year">This Year</option>
          </select>
        </div>
      </div>

      <!-- Department Tabs (Pill Buttons) -->
      <div class="tabs-wrapper">
        <div class="tabs-container">
          <button class="tab-btn active" data-dept="voice_logger">
            <i class="fa-solid fa-microphone-lines"></i> Voice Logger
          </button>
          <button class="tab-btn" data-dept="ivr">
            <i class="fa-solid fa-phone-volume"></i> IVR
          </button>
        </div>
      </div>

      <!-- Stats Summary Cards Row -->
      <div class="stats-grid">

        <!-- Card 2: Today's Records -->
        <div class="stat-card today-card">
          <div class="stat-icon">
            <i class="fa-solid fa-calendar-day"></i>
          </div>
          <div class="stat-details">
            <span class="stat-value" id="valTodayRecords">-</span>
            <span class="stat-label" id="lblTodayRecords">Today's Records</span>
          </div>
        </div>

        <!-- Card 3: Registered/Active Agents -->
        <div class="stat-card agents-card">
          <div class="stat-icon">
            <i class="fa-solid fa-users"></i>
          </div>
          <div class="stat-details">
            <span class="stat-value" id="valActiveAgents">-</span>
            <span class="stat-label">Total Active Agents</span>
          </div>
        </div>

        <!-- Card 4: Agents Active Today -->
        <div class="stat-card active-card">
          <div class="stat-icon">
            <i class="fa-solid fa-user-check"></i>
          </div>
          <div class="stat-details">
            <span class="stat-value" id="valAgentsActiveToday">-</span>
            <span class="stat-label" id="lblAgentsActiveToday">Agents Active Today</span>
          </div>
        </div>
      </div>

      <!-- Two-Column Grid (Breakdown Details) -->
      <div class="dashboard-details-grid" style="position: relative;">
        <!-- Loading Overlay for AJAX switching -->
        <div class="loading-overlay" id="dashboardLoading">
          <div class="spinner"></div>
        </div>

        <!-- Column 1: Status Breakdown -->
        <div class="details-card">
          <div class="details-card-header">
            <h2>Status Breakdown</h2>
          </div>
          <div class="status-list" id="statusListContainer">
            <!-- Dynamically populated status items -->
          </div>
        </div>

        <!-- Column 2: Agent Performance Table -->
        <div class="details-card">
          <div class="details-card-header">
            <h2>Agent Performance Details</h2>
          </div>
          <div class="agent-table-wrapper">
            <table class="agent-table">
              <thead>
                <tr>
                  <th>Agent Name</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th id="thAgentTimeframeRecords">Today's Records</th>
                  <th>Last Active</th>
                </tr>
              </thead>
              <tbody id="agentTableBody">
                <!-- Dynamically populated agent rows -->
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- Include Sidebar & Header Component -->
    <?php include 'components/header-sidebar.php'; ?>

    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script>
      $(document).ready(function() {
        let currentDept = 'voice_logger';
        let currentRange = 'today';

        // Load statistics on initialization
        loadDashboardStats(currentDept, currentRange);

        // Tab selection click handler
        $('.tab-btn').on('click', function() {
          if ($(this).hasClass('active')) return;

          $('.tab-btn').removeClass('active');
          $(this).addClass('active');

          currentDept = $(this).data('dept');
          loadDashboardStats(currentDept, currentRange);
        });

        // Date range select change handler
        $('#date-range-filter').on('change', function() {
          currentRange = $(this).val() || 'today';
          loadDashboardStats(currentDept, currentRange);
        });

        // Helper function to map statuses to standard theme colors
        function getStatusColor(status) {
          const s = (status || '').toLowerCase().trim();
          if (s === 'closed' || s === 'closed-device replaced') {
            return 'var(--success-color)';
          }
          if (s === 'pending' || s === 'under observation') {
            return 'var(--warning-color)';
          }
          if (s.startsWith('escalated')) {
            return 'var(--error-color)';
          }
          return 'var(--text-secondary)';
        }

        // Fetch dashboard stats from backend API
        function loadDashboardStats(department, range) {
          // Display the loader overlay
          $('#dashboardLoading').addClass('active');

          range = range || currentRange;

          $.ajax({
            url: 'api/fetch-dashboard-stats.php',
            type: 'GET',
            data: { department: department, range: range },
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                // Dynamically update card labels and headers based on range
                let rangeLabel = "Today's Records";
                let activeLabel = "Agents Active Today";
                let thLabel = "Today's Records";
                
                if (range === 'week') {
                  rangeLabel = "This Week's Records";
                  activeLabel = "Agents Active This Week";
                  thLabel = "This Week's Records";
                } else if (range === 'month') {
                  rangeLabel = "This Month's Records";
                  activeLabel = "Agents Active This Month";
                  thLabel = "This Month's Records";
                } else if (range === 'year') {
                  rangeLabel = "This Year's Records";
                  activeLabel = "Agents Active This Year";
                  thLabel = "This Year's Records";
                }

                $('#lblTodayRecords').text(rangeLabel);
                $('#lblAgentsActiveToday').text(activeLabel);
                $('#thAgentTimeframeRecords').text(thLabel);

                // 1. Animate card values
                animateCardValue($('#valTodayRecords'), response.summary.timeframe_records);
                animateCardValue($('#valActiveAgents'), response.summary.active_agents);
                animateCardValue($('#valAgentsActiveToday'), response.summary.agents_active_timeframe);

                // 2. Render Status Breakdown
                const $statusContainer = $('#statusListContainer');
                $statusContainer.empty();

                if (response.status_breakdown && response.status_breakdown.length > 0) {
                  const maxCount = response.status_breakdown.reduce((sum, item) => sum + parseInt(item.count || 0), 0) || 1;
                  response.status_breakdown.forEach(function(item) {
                    const statusName = item.status || 'Unspecified';
                    const count = parseInt(item.count || 0);
                    const pct = Math.round((count / maxCount) * 100);
                    const color = getStatusColor(statusName);

                    const html = `
                      <div class="status-item">
                        <div class="status-info">
                          <span class="status-name">
                            <span class="status-dot" style="background-color: ${color}"></span>
                            ${escapeHtml(statusName)}
                          </span>
                          <span class="status-count">${count} (${pct}%)</span>
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
                      <p>No status records found for this department.</p>
                    </div>
                  `);
                }

                // 3. Render Agent Leaderboard/Performance Details
                const $agentBody = $('#agentTableBody');
                $agentBody.empty();

                if (response.agent_details && response.agent_details.length > 0) {
                  response.agent_details.forEach(function(agent) {
                    // Extract initials for placeholder avatar
                    const initials = agent.agent_name
                      .split(' ')
                      .filter(Boolean)
                      .map(word => word[0])
                      .join('')
                      .toUpperCase()
                      .substring(0, 2);

                    // Map role to design badges
                    let roleClass = 'badge-role-agent';
                    if (agent.role.toLowerCase() === 'admin') {
                      roleClass = 'badge-role-admin';
                    } else if (agent.role.toLowerCase() === 'manager') {
                      roleClass = 'badge-role-manager';
                    }

                    // Format timeframe count badge
                    const timeframeVal = parseInt(agent.timeframe_records || 0);
                    const rangeLabelText = range === 'today' ? 'today' : (range === 'week' ? 'this week' : (range === 'month' ? 'this month' : 'this year'));
                    const timeframeBadge = timeframeVal > 0 ? 
                      `<span class="record-count-today">+${timeframeVal} ${rangeLabelText}</span>` : 
                      `<span class="record-count-zero">-</span>`;

                    // Format date
                    const dateObj = new Date(agent.last_active);
                    const formattedDate = isNaN(dateObj.getTime()) ? '-' : dateObj.toLocaleDateString('en-US', {
                      year: 'numeric',
                      month: 'short',
                      day: 'numeric'
                    });

                    const html = `
                      <tr>
                        <td>
                          <div class="agent-profile">
                            <div class="agent-avatar">${escapeHtml(initials) || '?'}</div>
                            <div class="agent-meta">
                              <span class="agent-realname">${escapeHtml(agent.agent_name)}</span>
                            </div>
                          </div>
                        </td>
                        <td><span style="font-family: monospace;">${escapeHtml(agent.username)}</span></td>
                        <td><span class="badge ${roleClass}">${escapeHtml(agent.role)}</span></td>
                        <td>${timeframeBadge}</td>
                        <td><span class="agent-last-active">${formattedDate}</span></td>
                      </tr>
                    `;
                    $agentBody.append(html);
                  });
                } else {
                  $agentBody.append(`
                    <tr>
                      <td colspan="5">
                        <div class="empty-state">
                          <i class="fa-solid fa-users-slash"></i>
                          <p>No agent activity documented for this department.</p>
                        </div>
                      </td>
                    </tr>
                  `);
                }
              } else {
                alert('Dashboard Error: ' + response.message);
              }
            },
            error: function(xhr, status, error) {
              console.error('AJAX error fetching dashboard stats:', error);
            },
            complete: function() {
              // Dismiss loading overlay
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
    </script>
  </body>

</html>