$(document).ready(function () {
  const $tableBody = $('#projectsTableBody');
  const $loadingOverlay = $('#loadingOverlay');
  const $projectCount = $('#projectCount');
  const $searchInput = $('#search-input');
  const $categoryFilter = $('#category-filter');
  const $priorityFilter = $('#priority-filter');
  const $btnResetFilters = $('#btnResetFilters');

  let searchTimeout = null;
  let isAdmin = $('.project-lists-container').data('is-admin') == '1';

  function showLoading() {
    $loadingOverlay.css('display', 'flex');
  }

  function hideLoading() {
    $loadingOverlay.hide();
  }

  function escapeHtml(str) {
    if (!str) return '';
    return $('<div>').text(str).html();
  }

  function showStatusToast(message) {
    let $toast = $('#statusToastNotification');
    if (!$toast.length) {
      $toast = $('<div id="statusToastNotification" class="status-toast-notification"></div>');
      $('body').append($toast);
    }
    $toast.html(`<i class="fa-solid fa-circle-check" style="color: #10b981;"></i> <span>${escapeHtml(message)}</span>`);
    $toast.addClass('show');

    clearTimeout(window.statusToastTimer);
    window.statusToastTimer = setTimeout(function () {
      $toast.removeClass('show');
    }, 3000);
  }

  function getInitials(name) {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }

  function getPriorityBadgeClass(priority) {
    const p = (priority || '').toLowerCase();
    if (p === 'high') return 'badge-priority-high';
    if (p === 'medium') return 'badge-priority-medium';
    return 'badge-priority-low';
  }

  function getPriorityIcon(priority) {
    const p = (priority || '').toLowerCase();
    if (p === 'high') return 'fa-solid fa-angles-up';
    if (p === 'medium') return 'fa-solid fa-bars';
    return 'fa-solid fa-angles-down';
  }

  function getCategoryIcon(category) {
    const c = (category || '').toLowerCase();
    // if (c === 'hardware') return 'fa-solid fa-server';
    // if (c === 'software') return 'fa-solid fa-code';
    // return 'fa-solid fa-microchip';
  }

  function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'in progress') return 'badge-status-progress';
    if (s === 'completed') return 'badge-status-completed';
    if (s === 'pending') return 'badge-status-pending';
    return 'badge-status-assigned';
  }

  function getStatusIcon(status) {
    const s = (status || '').toLowerCase();
    // if (s === 'in progress') return 'fa-solid fa-spinner';
    // if (s === 'completed') return 'fa-solid fa-circle-check';
    // if (s === 'pending') return 'fa-solid fa-clock';
    // return 'fa-solid fa-bars-progress';
  }

  function loadProjects() {
    showLoading();

    const params = {
      search: $searchInput.val().trim(),
      category: $categoryFilter.val() || '',
      priority: $priorityFilter.val() || ''
    };

    $.ajax({
      url: '../api/fetch-projects.php',
      type: 'GET',
      data: params,
      dataType: 'json',
      success: function (response) {
        hideLoading();
        if (response.is_admin !== undefined) {
          isAdmin = !!response.is_admin;
        }
        if (response.success && Array.isArray(response.data)) {
          renderProjectsTable(response.data);
          $projectCount.text(response.data.length + (response.data.length === 1 ? ' Project' : ' Projects'));
        } else {
          renderEmptyState('Failed to load projects.');
          $projectCount.text('0 Projects');
        }
      },
      error: function () {
        hideLoading();
        renderEmptyState('An error occurred while fetching projects.');
        $projectCount.text('0 Projects');
      }
    });
  }

  function renderProjectsTable(projects) {
    if (!projects || projects.length === 0) {
      renderEmptyState('No projects found matching your criteria.');
      return;
    }

    let html = '';
    projects.forEach(function (proj) {
      const pid = escapeHtml(proj.project_id);
      const date = escapeHtml(proj.date);
      const name = escapeHtml(proj.project_name);
      const cat = escapeHtml(proj.category);
      const asg = escapeHtml(proj.asignee);
      const prio = escapeHtml(proj.priority);
      const rawStatus = (proj.project_status || 'Assigned').trim();
      const status = escapeHtml(rawStatus);
      const createdBy = escapeHtml(proj.created_by);
      const createdAt = escapeHtml(proj.created_at ? proj.created_at.substring(0, 16) : '—');

      const prioClass = getPriorityBadgeClass(proj.priority);
      const prioIcon = getPriorityIcon(proj.priority);
      const catIcon = getCategoryIcon(proj.category);
      const statusClass = getStatusBadgeClass(proj.project_status);
      const statusIcon = getStatusIcon(proj.project_status);
      const initials = getInitials(proj.asignee);
      const detailsUrl = `project-details.php?project_id=${encodeURIComponent(proj.project_id)}`;

      let actionTd = '';
      if (isAdmin) {
        if (rawStatus.toLowerCase() === 'assigned') {
          actionTd = `<td>
            <button type="button" class="btn-action-icon btn-action-delete btn-delete-project" 
              data-id="${escapeHtml(proj.id)}" 
              data-project-id="${pid}" 
              data-project-name="${name}" 
              title="Delete Project">
              <i class="fa-solid fa-trash-can"></i>
            </button>
          </td>`;
        } else {
          actionTd = `<td><span class="action-na">—</span></td>`;
        }
      }

      html += `
        <tr class="clickable-row" data-href="${detailsUrl}">
          <td><a href="${detailsUrl}" class="project-id-pill" onclick="event.stopPropagation();">${pid}</a></td>
          <td>${date}</td>
          <td><a href="${detailsUrl}" class="project-name-link" onclick="event.stopPropagation();">${name}</a></td>
          <td><span class="badge-category"><i class="${catIcon}"></i> ${cat}</span></td>
          <td><span class="badge-priority ${prioClass}"><i class="${prioIcon}"></i> ${prio}</span></td>
          <td>${createdBy}</td>
          <td><span class="project-created-at">${createdAt}</span></td>
          <td><span class="badge-status ${statusClass}"><i class="${statusIcon}"></i> ${status}</span></td>
          ${actionTd}
        </tr>
      `;
    });

    $tableBody.html(html);
  }

  function renderEmptyState(message) {
    const colSpan = isAdmin ? 9 : 8;
    $tableBody.html(`
      <tr>
        <td colspan="${colSpan}">
          <div class="empty-state">
            <i class="fa-regular fa-folder-open"></i>
            <h3>${escapeHtml(message)}</h3>
          </div>
        </td>
      </tr>
    `);
  }

  // Event Listeners
  $searchInput.on('input', function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function () {
      loadProjects();
    }, 300);
  });

  $categoryFilter.on('change', loadProjects);
  $priorityFilter.on('change', loadProjects);

  $btnResetFilters.on('click', function () {
    $searchInput.val('');
    $categoryFilter.val('');
    $priorityFilter.val('');
    loadProjects();
  });

  // Delegated row click handler
  $tableBody.on('click', '.clickable-row', function (e) {
    if ($(e.target).closest('a, button').length) {
      return;
    }
    const href = $(this).attr('data-href');
    if (href) {
      window.location.href = href;
    }
  });

  // Delete project button handler
  $tableBody.on('click', '.btn-delete-project', function (e) {
    e.stopPropagation();
    e.preventDefault();

    const $btn = $(this);
    const projectId = $btn.data('project-id');
    const projectName = $btn.data('project-name') || projectId;
    const numericId = $btn.data('id');

    if (!confirm(`Are you sure you want to delete project "${projectName}" (${projectId})?`)) {
      return;
    }

    $btn.prop('disabled', true);

    $.ajax({
      url: '../api/delete-project.php',
      type: 'POST',
      data: {
        project_id: projectId,
        id: numericId
      },
      dataType: 'json',
      success: function (response) {
        if (response && response.success) {
          showStatusToast(response.message || 'Project deleted successfully');
          loadProjects();
        } else {
          alert((response && response.message) ? response.message : 'Failed to delete project.');
          $btn.prop('disabled', false);
        }
      },
      error: function (xhr) {
        let msg = 'An error occurred while deleting the project.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
        $btn.prop('disabled', false);
      }
    });
  });

  // Initial load
  loadProjects();
});
