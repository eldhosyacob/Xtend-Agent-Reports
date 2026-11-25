$(document).ready(function () {
  const $cardsContainer = $('#projectsCardsContainer');
  const $loadingOverlay = $('#loadingOverlay');
  const $projectCountBadge = $('#projectCountBadge');
  const $searchInput = $('#search-input');
  const $categoryFilter = $('#category-filter');
  const $priorityFilter = $('#priority-filter');
  const $statusFilter = $('#status-filter');
  const $statusFilterWrapper = $('#statusFilterWrapper');
  const $statusFilterDisplay = $('#statusFilterDisplay');
  const $statusFilterDropdown = $('#statusFilterDropdown');
  const $statusFilterText = $('#statusFilterText');
  const $statusFilterAll = $('#statusFilterAll');
  const $btnResetFilters = $('#btnResetFilters');
  const $btnToggleAll = $('#btnToggleAll');

  // Stats elements
  const $statTotalVal = $('#statTotalVal');
  const $statAssignedVal = $('#statAssignedVal');
  const $statProgressVal = $('#statProgressVal');
  const $statPendingVal = $('#statPendingVal');
  const $statHoldVal = $('#statHoldVal');
  const $statCompletedVal = $('#statCompletedVal');

  let searchTimeout = null;
  let allProjectsData = [];
  let isAllExpanded = false;
  let expandedProjectId = null;

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
    if (c === 'hardware') return 'fa-solid fa-server';
    if (c === 'software') return 'fa-solid fa-code';
    return 'fa-solid fa-microchip';
  }

  function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'in progress') return 'badge-status-progress';
    if (s === 'completed') return 'badge-status-completed';
    if (s === 'pending') return 'badge-status-pending';
    if (s === 'on hold' || s === 'hold') return 'badge-status-hold';
    return 'badge-status-assigned';
  }

  function getStatusIcon(status) {
    const s = (status || '').toLowerCase();
    if (s === 'in progress') return 'fa-solid fa-spinner';
    if (s === 'completed') return 'fa-solid fa-circle-check';
    if (s === 'pending') return 'fa-solid fa-clock';
    if (s === 'on hold' || s === 'hold') return 'fa-solid fa-circle-pause';
    return 'fa-solid fa-bars-progress';
  }

  function updatePageStats(stats) {
    if (!stats) return;
    $statTotalVal.text(stats.total ?? stats.total_projects ?? 0);
    $statAssignedVal.text(stats.assigned ?? 0);
    $statProgressVal.text(stats.in_progress ?? 0);
    $statPendingVal.text(stats.pending ?? 0);
    $statHoldVal.text(stats.hold ?? 0);
    $statCompletedVal.text(stats.completed ?? 0);
  }

  function updateStatusFilterDisplay() {
    const $allBoxes = $('.status-filter-checkbox');
    const $checkedBoxes = $('.status-filter-checkbox:checked');
    const totalCount = $allBoxes.length;
    const checkedCount = $checkedBoxes.length;

    $statusFilterAll.prop('checked', totalCount > 0 && checkedCount === totalCount).prop('indeterminate', false);

    if (checkedCount === totalCount) {
      $statusFilterText.text('All Statuses');
      $statusFilterDisplay.attr('title', 'All Statuses');
      $statusFilter.val('All');
    } else if (checkedCount === 0) {
      $statusFilterText.text('No Status');
      $statusFilterDisplay.attr('title', 'No Status Selected');
      $statusFilter.val('__none__');
    } else if (checkedCount === 1) {
      const singleName = $checkedBoxes.first().val();
      $statusFilterText.text(singleName);
      $statusFilterDisplay.attr('title', singleName);
      $statusFilter.val(singleName);
    } else {
      const names = [];
      $checkedBoxes.each(function () {
        names.push($(this).val());
      });
      $statusFilterText.text('Statuses');
      $statusFilterDisplay.attr('title', names.join(', '));
      $statusFilter.val(names.join(','));
    }
  }

  function getStatusParamValue() {
    const $checkedBoxes = $('.status-filter-checkbox:checked');
    if ($checkedBoxes.length === 0) {
      return '__none__';
    }
    const selected = [];
    $checkedBoxes.each(function () {
      selected.push($(this).val());
    });
    return selected.join(',');
  }

  function resetStatusFilter() {
    $('.status-filter-checkbox').each(function () {
      const val = $(this).val();
      $(this).prop('checked', val !== 'Completed');
    });
    updateStatusFilterDisplay();
  }

  function loadMyProjects(keepExpandedId) {
    showLoading();

    const params = {
      search: $searchInput.val().trim(),
      category: $categoryFilter.val() || '',
      priority: $priorityFilter.val() || '',
      status: getStatusParamValue()
    };

    $.ajax({
      url: '../api/fetch-my-projects.php',
      type: 'GET',
      data: params,
      dataType: 'json',
      success: function (response) {
        hideLoading();
        if (response.success && Array.isArray(response.data)) {
          allProjectsData = response.data;
          renderProjectsCards(response.data, keepExpandedId);
          updatePageStats(response.stats);
          $projectCountBadge.text(response.data.length + (response.data.length === 1 ? ' Project' : ' Projects'));
        } else {
          renderEmptyState('No projects with assigned sub projects found.');
          $projectCountBadge.text('0 Projects');
        }
      },
      error: function () {
        hideLoading();
        renderEmptyState('An error occurred while fetching your projects.');
        $projectCountBadge.text('0 Projects');
      }
    });
  }

  function renderProjectsCards(projects, keepExpandedId) {
    if (!projects || projects.length === 0) {
      renderEmptyState();
      return;
    }

    let html = '';
    projects.forEach(function (proj, projIdx) {
      const pid = escapeHtml(proj.project_id || '');
      const name = escapeHtml(proj.project_name || 'Untitled Project');
      const date = escapeHtml(proj.date || '—');
      const cat = escapeHtml(proj.category || '—');
      const prio = escapeHtml(proj.priority || 'Medium');
      const status = escapeHtml(proj.project_status || 'Assigned');
      const desc = escapeHtml(proj.project_description || '');
      const productName = escapeHtml(proj.product_name || '—');
      const productSubCategory = escapeHtml(proj.product_sub_category || '');
      const productDisplay = productSubCategory && productName !== '—'
        ? `${productName} (${productSubCategory})`
        : productName;
      const clientDealerName = escapeHtml(proj.client_dealer_name || 'NA');
      const createdBy = escapeHtml(proj.created_by || '—');
      const createdAt = escapeHtml(proj.created_at ? proj.created_at.substring(0, 16) : '—');

      const prioClass = getPriorityBadgeClass(proj.priority);
      const prioIcon = getPriorityIcon(proj.priority);
      const catIcon = getCategoryIcon(proj.category);
      const statusClass = getStatusBadgeClass(status);
      const statusIcon = getStatusIcon(status);

      const subprojects = Array.isArray(proj.subprojects) ? proj.subprojects : [];
      const subProjectsCount = proj.subprojects_count ?? subprojects.length;
      const detailsUrl = `my-project-details.php?project_id=${encodeURIComponent(proj.project_id || '')}`;
      const isExpanded = (keepExpandedId && keepExpandedId === pid) || isAllExpanded;

      html += `
        <div class="project-card ${isExpanded ? 'expanded' : ''}" data-project-id="${pid}" id="projectCard_${projIdx}">
          
          <!-- Clickable Header: Initially shows Project Details only -->
          <div class="project-card-header" data-target="#projectBody_${projIdx}">
            <div class="project-header-main">
              <div class="project-id-badge-box">
                <span class="id-label">Project</span>
                <span class="id-value">${pid}</span>
              </div>

              <div class="project-title-meta-wrap">
                <div class="project-title-row">
                  <a href="${detailsUrl}" class="project-title-link">${name}</a>
                </div>

                <div class="project-meta-pills">
                  <span class="badge-category"> ${cat}</span>
                  <span class="badge-priority ${prioClass}"><i class="${prioIcon}"></i> ${prio}</span>
                  <span class="badge-status ${statusClass}">${status}</span>
                  <span class="project-date-chip"><i class="fa-regular fa-calendar"></i> ${date}</span>
                </div>
              </div>
            </div>

            <div class="project-header-right">
              <div class="sub-projects-stats">
                <span class="sub-project-chip total-sub-project-chip" title="Total Assigned Sub Projects">
                   ${subProjectsCount} Sub Project${subProjectsCount === 1 ? '' : 's'}
                </span>
              </div>

              <a href="${detailsUrl}" class="btn-open-details" title="View Project Details">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
              </a>

              <div class="accordion-chevron">
                <i class="fa-solid fa-chevron-down"></i>
              </div>
            </div>
          </div>

          <!-- Expandable Body Content: Project Details Grid + Assigned Subprojects Table -->
          <div class="project-card-body" id="projectBody_${projIdx}">
            
            <!-- Project Meta Details Grid -->
            <div class="project-info-grid">
              <div class="project-info-item">
                <span class="info-label"><i class="fa-solid fa-box"></i> Product</span>
                <span class="info-value">${productDisplay}</span>
              </div>

              <div class="project-info-item">
                <span class="info-label"><i class="fa-regular fa-building"></i> Client / Dealer</span>
                <span class="info-value">${clientDealerName}</span>
              </div>

              <div class="project-info-item">
                <span class="info-label"><i class="fa-solid fa-user"></i> Created By</span>
                <span class="info-value">
                  <span class="user-chip">
                    <span>${createdBy}</span>
                  </span>
                </span>
              </div>

              <div class="project-info-item">
                <span class="info-label"><i class="fa-regular fa-clock"></i> Created At</span>
                <span class="info-value">${createdAt}</span>
              </div>
            </div>

            ${desc ? `
              <div class="project-desc-row">
                <span class="info-label"><i class="fa-solid fa-align-left"></i> Description</span>
                <div class="project-desc-text">${desc}</div>
              </div>
            ` : ''}

            <!-- Assigned Sub Projects Table Section -->
            <div class="subprojects-section-wrapper">
              <div class="subprojects-section-header">
                <div class="subprojects-header-title">
                  <h3>Assigned Sub Projects</h3>
                  <span class="subprojects-count-pill">${subProjectsCount}</span>
                </div>
              </div>

              ${renderSubprojectsTableHtml(subprojects)}
            </div>

          </div>

        </div>
      `;
    });

    $cardsContainer.html(html);
  }

  function renderSubprojectsTableHtml(subprojects) {
    if (!subprojects || subprojects.length === 0) {
      return `
        <div class="subprojects-empty-state">
          <i class="fa-solid fa-folder-open"></i>
          <p>No sub projects assigned to you under this project.</p>
        </div>
      `;
    }

    let rowsHtml = '';
    subprojects.forEach(function (sp) {
      const spId = escapeHtml(sp.sub_project_id || '—');
      const spName = escapeHtml(sp.sub_project_name || '—');
      const spDesc = escapeHtml(sp.project_description || '');
      const spDate = escapeHtml(sp.date || '');
      const spDept = escapeHtml(sp.department || '—');
      const spAssignedBy = escapeHtml(sp.assigned_by || '—');
      const spAssignee = escapeHtml(sp.asignee || '—');
      const spPrio = escapeHtml(sp.priority || 'Medium');
      const spStatus = escapeHtml(sp.project_status || 'Assigned');

      const spPrioClass = getPriorityBadgeClass(sp.priority);
      const spPrioIcon = getPriorityIcon(sp.priority);
      const spStatusClass = getStatusBadgeClass(spStatus);
      const spStatusIcon = getStatusIcon(spStatus);

      rowsHtml += `
        <tr>
          <td class="col-subproject-id">
            <span class="subproject-id-badge">${spId}</span>
          </td>
          <td class="col-subproject-name">
            <div class="subproject-name-cell">
              <span class="subproject-name-text">${spName}</span>
              ${spDesc ? `<span class="subproject-desc-text">${spDesc}</span>` : ''}
              ${spDate ? `<span class="subproject-date-meta"><i class="fa-regular fa-calendar"></i> ${spDate}</span>` : ''}
            </div>
          </td>
          <td class="col-subproject-dept">
            <span class="department-pill"> ${spDept}</span>
          </td>
          <td class="col-subproject-assigned-by">${spAssignedBy}</td>
          <td class="col-subproject-assignee">
            <span class="user-chip" title="${spAssignee}">
              <span>${spAssignee}</span>
            </span>
          </td>
          <td class="col-subproject-priority">
            <span class="badge-priority ${spPrioClass}"><i class="${spPrioIcon}"></i> ${spPrio}</span>
          </td>
          <td class="col-subproject-status">
            <span class="badge-status ${spStatusClass}">
               ${spStatus}
            </span>
          </td>
        </tr>
      `;
    });

    return `
      <div class="subprojects-table-responsive">
        <table class="my-subprojects-table">
          <thead>
            <tr>
              <th>Sub Project ID</th>
              <th class="col-subproject-name-head">Sub Project Name</th>
              <th>Department</th>
              <th>Assigned By</th>
              <th>Assigned To</th>
              <th>Priority</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml}
          </tbody>
        </table>
      </div>
    `;
  }

  function renderEmptyState(message) {
    $cardsContainer.html(`
      <div class="empty-state-card">
        <div class="empty-icon-box">
          <i class="fa-solid fa-folder-open"></i>
        </div>
        <h2>No Projects Found</h2>
      </div>
    `);
  }

  // --- Accordion Toggle ---
  $cardsContainer.on('click', '.project-card-header', function (e) {
    if ($(e.target).closest('a, button, select, input').length) {
      return;
    }
    const $card = $(this).closest('.project-card');
    const $body = $card.find('.project-card-body');

    $body.slideToggle(250, function () {
      if ($body.is(':visible')) {
        $card.addClass('expanded');
        expandedProjectId = $card.data('project-id');
      } else {
        $card.removeClass('expanded');
        if (expandedProjectId === $card.data('project-id')) {
          expandedProjectId = null;
        }
      }
    });
  });

  // Toggle Expand All / Collapse All
  $btnToggleAll.on('click', function () {
    isAllExpanded = !isAllExpanded;
    if (isAllExpanded) {
      $('.project-card-body').slideDown(250);
      $('.project-card').addClass('expanded');
      $btnToggleAll.html('<i class="fa-solid fa-compress"></i> Collapse All');
    } else {
      $('.project-card-body').slideUp(250);
      $('.project-card').removeClass('expanded');
      $btnToggleAll.html('<i class="fa-solid fa-expand"></i> Expand All');
    }
  });

  // --- Filter Events ---
  // Status Filter Dropdown Toggle
  $statusFilterDisplay.on('click', function (e) {
    e.stopPropagation();
    $statusFilterWrapper.toggleClass('open');
    $statusFilterDropdown.toggleClass('show');
    const isOpen = $statusFilterDropdown.hasClass('show');
    $statusFilterDisplay.attr('aria-expanded', isOpen ? 'true' : 'false');
  });

  $statusFilterDropdown.on('click', function (e) {
    e.stopPropagation();
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('#statusFilterWrapper').length) {
      $statusFilterWrapper.removeClass('open');
      $statusFilterDropdown.removeClass('show');
      $statusFilterDisplay.attr('aria-expanded', 'false');
    }
  });

  $statusFilterAll.on('change', function () {
    const isChecked = $(this).is(':checked');
    $('.status-filter-checkbox').prop('checked', isChecked);
    updateStatusFilterDisplay();
    loadMyProjects();
  });

  $(document).on('change', '.status-filter-checkbox', function () {
    updateStatusFilterDisplay();
    loadMyProjects();
  });

  $searchInput.on('input', function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function () {
      loadMyProjects();
    }, 300);
  });

  $categoryFilter.on('change', loadMyProjects);
  $priorityFilter.on('change', loadMyProjects);

  $btnResetFilters.on('click', function () {
    $searchInput.val('');
    $categoryFilter.val('');
    $priorityFilter.val('');
    resetStatusFilter();
    loadMyProjects();
  });

  $('.stats-summary-grid').on('click', '.stat-metric-card', function () {
    const status = $(this).data('status');
    if (!status) {
      $('.status-filter-checkbox').prop('checked', true);
    } else {
      $('.status-filter-checkbox').each(function () {
        $(this).prop('checked', $(this).val() === status);
      });
    }
    updateStatusFilterDisplay();
    loadMyProjects();
  });

  // Initial load
  updateStatusFilterDisplay();
  loadMyProjects();
});
