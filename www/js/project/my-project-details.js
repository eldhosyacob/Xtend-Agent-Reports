$(document).ready(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const projectId = urlParams.get('project_id') || urlParams.get('id') || '';

  if (!projectId) {
    alert('No Project ID specified. Returning to My Projects.');
    window.location.href = 'my-projects.php';
    return;
  }

  // If this ID is for a subproject, redirect directly to subproject details page
  if (projectId.indexOf('_SP') !== -1) {
    window.location.href = `my-subproject-details.php?project_id=${encodeURIComponent(projectId)}`;
    return;
  }

  const $loadingOverlay = $('#loadingOverlay');
  const $subprojectsTableBody = $('#subprojectsTableBody');

  let currentProject = null;
  let currentSubprojects = [];

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
    return 'fa-solid fa-folder';
  }

  function getStatusBadgeClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'in progress') return 'badge-status-progress';
    if (s === 'completed') return 'badge-status-completed';
    if (s === 'pending') return 'badge-status-pending';
    if (s === 'on hold' || s === 'hold') return 'badge-status-on-hold';
    return 'badge-status-assigned';
  }

  function getStatusIcon(status) {
    const s = (status || '').toLowerCase();
    if (s === 'in progress') return 'fa-solid fa-spinner';
    if (s === 'completed') return 'fa-solid fa-circle-check';
    if (s === 'pending') return 'fa-solid fa-clock';
    if (s === 'on hold' || s === 'hold') return 'fa-solid fa-circle-pause';
    // return 'fa-solid fa-bars-progress';
  }

  function loadProjectDetails(silent = false) {
    if (!silent) {
      showLoading();
    }

    $.ajax({
      url: '../api/fetch-project-details.php',
      type: 'GET',
      data: {
        project_id: projectId
      },
      dataType: 'json',
      success: function (response) {
        if (!silent) {
          hideLoading();
        }
        if (response.success && response.project) {
          // If the fetched entity is a subproject, redirect to subproject details
          if (response.project.is_sub_project) {
            window.location.href = `my-subproject-details.php?project_id=${encodeURIComponent(response.project.project_id || projectId)}`;
            return;
          }
          currentProject = response.project;
          renderProjectOverview(response.project);
          renderSubprojects(response.subprojects || []);
        } else if (!silent) {
          alert(response.message || 'Project not found.');
          window.location.href = 'my-projects.php';
        }
      },
      error: function () {
        if (!silent) {
          hideLoading();
          alert('An error occurred while loading project details.');
        }
      }
    });
  }

  function renderProjectOverview(project) {
    $('#projectTitle').text(project.project_name);
    $('#projectIdBadge').text(project.project_id);
    $('#breadcrumbProjectName').text(project.project_name);

    const catIcon = getCategoryIcon(project.category);
    $('#projectCategoryBadge').html(`${escapeHtml(project.category)}`);

    const prioClass = getPriorityBadgeClass(project.priority);
    const prioIcon = getPriorityIcon(project.priority);
    $('#projectPriorityBadge').attr('class', `badge-priority ${prioClass}`).html(`<i class="${prioIcon}"></i> ${escapeHtml(project.priority)}`);

    const statusVal = project.project_status || 'Assigned';
    const statusClass = getStatusBadgeClass(statusVal);
    const statusIcon = getStatusIcon(statusVal);
    $('#projectStatusBadge').attr('class', `badge-status ${statusClass}`).html(` ${escapeHtml(statusVal)}`);

    if (project.asignee && project.asignee.trim() !== '') {
      $('#projectAssigneeDisplay').show().html(`
        <span>${escapeHtml(project.asignee)}</span>
      `);
    } else {
      $('#projectAssigneeDisplay').hide();
    }

    $('#metaProjectDate').text(project.date || '—');
    // $('#metaAssignedBy').text(project.assigned_by || '—');
    $('#metaProjectStatus').html(`
      <span class="badge-status ${statusClass}"><i class="${statusIcon}"></i> ${escapeHtml(statusVal)}</span>
    `);
    $('#metaCreatedBy').html(`
      <div class="user-chip">
        <span>${escapeHtml(project.created_by)}</span>
      </div>
    `);
    $('#metaCreatedAt').text(project.created_at ? project.created_at.substring(0, 16) : '—');
    $('#metaClientDealerSpecific').text(project.client_dealer_specific || 'No');
    $('#metaClientDealerName').text(project.client_dealer_name || 'NA');
    $('#metaTotalTime').text(project.total_time || '00:00:00');

    if (project.edited_by) {
      $('#metaEditedGroup').show();
      const editedByPhoto = project.edited_by_photo || '../images/default-avatar.png';
      $('#metaEditedBy').html(`
        <div class="user-chip">
          <img src="${editedByPhoto}" class="user-avatar-img" alt="${escapeHtml(project.edited_by)}" onerror="this.src='../images/default-avatar.png'">
          <span>${escapeHtml(project.edited_by)} (${project.edited_at ? project.edited_at.substring(0, 16) : ''})</span>
        </div>
      `);
    } else {
      $('#metaEditedGroup').hide();
    }

    if (project.project_description && project.project_description.trim() !== '') {
      $('#projectDescBox').removeClass('desc-empty').text(project.project_description);
    } else {
      $('#projectDescBox').addClass('desc-empty').text('No project description provided.');
    }

    // Render Project Documents (Uploaded Documents)
    const $docList = $('#projectDocumentsList');
    if ($docList.length) {
      $docList.empty();
      if (Array.isArray(project.documents) && project.documents.length > 0) {
        project.documents.forEach(function (doc) {
          const ext = (doc.name || '').split('.').pop().toLowerCase();
          let iconClass = 'fa-regular fa-file';
          if (['pdf'].includes(ext)) iconClass = 'fa-regular fa-file-pdf';
          else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) iconClass = 'fa-regular fa-file-image';
          else if (['csv', 'xls', 'xlsx'].includes(ext)) iconClass = 'fa-regular fa-file-excel';
          else if (['doc', 'docx'].includes(ext)) iconClass = 'fa-regular fa-file-word';
          else if (['zip', 'rar', '7z'].includes(ext)) iconClass = 'fa-regular fa-file-zipper';

          const $card = $(`
            <div class="attachment-card">
              <div class="attachment-info">
                <div class="attachment-icon">
                  <i class="${iconClass}"></i>
                </div>
                <div class="attachment-details">
                  <span class="attachment-name" title="${escapeHtml(doc.name)}">${escapeHtml(doc.name)}</span>
                  <span class="attachment-meta">${escapeHtml(doc.size)}</span>
                </div>
              </div>
              <a href="${escapeHtml(doc.url)}" download class="btn-download-file" title="Download">
                <i class="fa-solid fa-download"></i>
              </a>
            </div>
          `);
          $docList.append($card);
        });
      } else {
        $docList.html(`
          <div class="no-attachments-text">
            <i class="fa-regular fa-folder-open"></i> No documents uploaded for this project
          </div>
        `);
      }
    }
  }

  function renderSubprojects(subprojects) {
    currentSubprojects = subprojects || [];
    const totalCount = subprojects.length;
    let assignedCount = 0;
    let pendingCount = 0;
    let progressCount = 0;
    let completedCount = 0;
    let onHoldCount = 0;

    subprojects.forEach(function (sp) {
      const s = (sp.project_status || sp.status || 'Assigned').trim().toLowerCase();
      if (s === 'assigned') assignedCount++;
      else if (s === 'pending') pendingCount++;
      else if (s === 'in progress') progressCount++;
      else if (s === 'completed') completedCount++;
      else if (s === 'on hold') onHoldCount++;
    });

    $('#subprojectCounterBadge').text(`${totalCount} Sub Project${totalCount === 1 ? '' : 's'}`);
    $('#statSubTotal').text(`Total: ${totalCount}`);
    $('#statSubAssigned').text(`Assigned: ${assignedCount}`);
    $('#statSubPending').text(`Pending: ${pendingCount}`);
    $('#statSubInProgress').text(`In Progress: ${progressCount}`);
    $('#statSubCompleted').text(`Completed: ${completedCount}`);
    $('#statSubOnHold').text(`On Hold: ${onHoldCount}`);

    if (totalCount === 0) {
      $subprojectsTableBody.html(`
        <tr>
          <td colspan="7">
            <div class="empty-state" style="padding: 36px 16px; text-align: center;">
              <h3 style="font-size: 16px; margin: 0 0 6px 0; color: var(--text-primary);">No sub projects created</h3>
            </div>
          </td>
        </tr>
      `);
      return;
    }

    let html = '';
    subprojects.forEach(function (sp) {
      const spid = escapeHtml(sp.sub_project_id || '—');
      const name = escapeHtml(sp.sub_project_name || '—');
      const dept = escapeHtml(sp.department || '—');
      const assignee = escapeHtml(sp.asignee || 'Unassigned');
      const prio = escapeHtml(sp.priority || 'Medium');
      const prioClass = getPriorityBadgeClass(sp.priority);
      const prioIcon = getPriorityIcon(sp.priority);
      const statusVal = sp.project_status || 'Assigned';
      const statusClass = getStatusBadgeClass(statusVal);
      const statusIcon = getStatusIcon(statusVal);
      const timeVal = escapeHtml(sp.total_time || '00:00:00');
      const desc = sp.project_description ? escapeHtml(sp.project_description) : '';
      const subDetailsUrl = `my-subproject-details.php?project_id=${encodeURIComponent(sp.sub_project_id)}`;

      html += `
        <tr class="clickable-subproject-row" data-href="${subDetailsUrl}">
          <td>
            <a href="${subDetailsUrl}" class="subproject-id-badge" onclick="event.stopPropagation();">${spid}</a>
          </td>
          <td style="text-align: left;">
            <a href="${subDetailsUrl}" class="subproject-name-link" onclick="event.stopPropagation();">${name}</a>
            ${desc ? `<span class="subproject-desc-text">${desc}</span>` : ''}
          </td>
          <td>
            <span class="department-pill"> ${dept}</span>
          </td>
          <td>
            <div class="user-chip" title="${assignee}">
              <span>${assignee}</span>
            </div>
          </td>
          <td>
            <span class="badge-priority ${prioClass}"><i class="${prioIcon}"></i> ${prio}</span>
          </td>
          <td>
            <span class="subproject-time-badge">
              <i class="fa-regular fa-clock"></i> ${timeVal}
            </span>
          </td>
          <td>
            <span class="badge-status ${statusClass}">
              <i class="${statusIcon}"></i> ${escapeHtml(statusVal)}
            </span>
          </td>
        </tr>
      `;
    });

    $subprojectsTableBody.html(html);
  }

  // Row click handler: opens my-subproject-details.php
  $(document).on('click', '.clickable-subproject-row', function () {
    const href = $(this).data('href');
    if (href) {
      window.location.href = href;
    }
  });

  // Refresh subprojects when returning to the window/tab
  window.addEventListener('focus', function () {
    loadProjectDetails(true);
  });

  // Initial load
  loadProjectDetails();
});
