$(document).ready(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const projectId = urlParams.get('project_id') || urlParams.get('id') || '';

  if (!projectId) {
    alert('No Project ID specified. Returning to Project Lists.');
    window.location.href = 'project-lists.php';
    return;
  }

  const $loadingOverlay = $('#loadingOverlay');
  const $subprojectsTableBody = $('#subprojectsTableBody');
  const $btnAddSubProject = $('#btnAddSubProject');

  const containerIsAdmin = $('.project-details-container').data('is-admin') == 1;
  const containerCanCreateSubproject = $('.project-details-container').data('can-create-subproject') == 1;
  let isAdmin = Boolean(containerIsAdmin);
  let canCreateSubproject = Boolean(isAdmin || containerCanCreateSubproject);

  let currentProject = null;
  let currentSubprojects = [];

  function checkCanCompleteProject() {
    if (!currentSubprojects || currentSubprojects.length === 0) {
      return {
        allowed: false,
        message: 'Cannot set project status to "Completed". The project must have at least one sub project.'
      };
    }
    const incompleteSubprojects = currentSubprojects.filter(function (s) {
      return (s.project_status || s.status || '').trim().toLowerCase() !== 'completed';
    });
    if (incompleteSubprojects.length > 0) {
      return {
        allowed: false,
        message: 'Cannot set project status to "Completed". All sub projects belonging to this project must be "Completed" first.'
      };
    }
    return { allowed: true };
  }

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
    if (s === 'on hold') return 'badge-status-on-hold';
    return 'badge-status-assigned';
  }

  function getStatusIcon(status) {
    const s = (status || '').toLowerCase();
    // if (s === 'in progress') return 'fa-solid fa-spinner';
    // if (s === 'completed') return 'fa-solid fa-circle-check';
    // if (s === 'pending') return 'fa-solid fa-clock';
    // return 'fa-solid fa-bars-progress';
  }

  function loadProjectDetails() {
    showLoading();

    $.ajax({
      url: '../api/fetch-project-details.php',
      type: 'GET',
      data: { project_id: projectId },
      dataType: 'json',
      success: function (response) {
        hideLoading();
        if (response.success && response.project) {
          if (typeof response.is_admin === 'boolean') {
            isAdmin = response.is_admin;
          }
          if (typeof response.can_create_sub_project === 'boolean') {
            canCreateSubproject = response.can_create_sub_project;
          } else {
            canCreateSubproject = Boolean(isAdmin || containerCanCreateSubproject);
          }

          currentProject = response.project;
          currentSubprojects = Array.isArray(response.subprojects) ? response.subprojects : [];

          if (canCreateSubproject) {
            $btnAddSubProject.show().attr('href', 'create-sub-project.php?project_id=' + encodeURIComponent(currentProject.project_id || projectId));
          } else {
            $btnAddSubProject.hide();
          }

          renderProjectOverview(response.project);
          renderSubprojects(currentSubprojects);
        } else {
          alert(response.message || 'Project not found.');
          window.location.href = 'project-lists.php';
        }
      },
      error: function () {
        hideLoading();
        alert('An error occurred while loading project details.');
      }
    });
  }

  function renderProjectOverview(project) {
    $('#projectTitle').text(project.project_name);
    $('#projectIdBadge').text(project.project_id);
    $('#breadcrumbProjectName').text(project.project_name);
    if (project.project_id) {
      $('#btnProjectSummary').attr('href', `project-summary.php?project_id=${encodeURIComponent(project.project_id)}`);
    }

    const catIcon = getCategoryIcon(project.category);
    $('#projectCategoryBadge').html(`<i class="${catIcon}"></i> ${escapeHtml(project.category)}`);

    const prioClass = getPriorityBadgeClass(project.priority);
    const prioIcon = getPriorityIcon(project.priority);
    $('#projectPriorityBadge').attr('class', `badge-priority ${prioClass}`).html(`<i class="${prioIcon}"></i> ${escapeHtml(project.priority)}`);

    const statusVal = project.project_status || 'Assigned';
    const statusClass = getStatusBadgeClass(statusVal);
    const statusIcon = getStatusIcon(statusVal);
    $('#projectStatusBadge').attr('class', `badge-status ${statusClass}`).html(`<i class="${statusIcon}"></i> ${escapeHtml(statusVal)}`);

    if (project.asignee && project.asignee.trim() !== '') {
      const asigneePhoto = project.asignee_photo || '../images/default-avatar.png';
      $('#projectAssigneeDisplay').show().html(`
        <img src="${asigneePhoto}" class="user-avatar-img" alt="${escapeHtml(project.asignee)}" onerror="this.src='../images/default-avatar.png'">
        <span>${escapeHtml(project.asignee)}</span>
      `);
    } else {
      $('#projectAssigneeDisplay').hide();
    }

    $('#metaProjectDate').text(project.date || '—');
    $('#metaAssignedBy').text(project.assigned_by || '—');
    if (isAdmin) {
      const selectClass = getSelectStatusClass(statusVal);
      const isAssigned = (statusVal === 'Assigned');
      const assignedOption = isAssigned ? '<option value="Assigned" selected>Assigned</option>' : '';
      const completionCheck = checkCanCompleteProject();
      const completedOptionTitle = (!completionCheck.allowed && statusVal !== 'Completed') ? ` title="${escapeHtml(completionCheck.message)}"` : '';
      $('#metaProjectStatus').html(`
        <div class="project-status-select-wrapper">
          <select id="projectStatusSelect" class="project-status-select ${selectClass}" data-prev-status="${escapeHtml(statusVal)}" aria-label="Project Status">
            ${assignedOption}
            <option value="Pending" ${statusVal === 'Pending' ? 'selected' : ''}>Pending</option>
            <option value="In Progress" ${statusVal === 'In Progress' ? 'selected' : ''}>In Progress</option>
            <option value="On Hold" ${statusVal === 'On Hold' ? 'selected' : ''}>On Hold</option>
            <option value="Completed" ${statusVal === 'Completed' ? 'selected' : ''}${completedOptionTitle}>Completed</option>
          </select>
          <i class="fa-solid fa-chevron-down status-dropdown-icon"></i>
        </div>
      `);
    } else {
      $('#metaProjectStatus').html(`
        <span class="badge-status ${statusClass}"><i class="${statusIcon}"></i> ${escapeHtml(statusVal)}</span>
      `);
    }
    const createdByPhoto = project.created_by_photo || '../images/default-avatar.png';
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

          const deleteBtnHtml = isAdmin ? `
            <button type="button" class="btn-delete-file btn-delete-document" data-filename="${escapeHtml(doc.filename)}" data-name="${escapeHtml(doc.name)}" title="Delete document">
              <i class="fa-solid fa-trash-can"></i>
            </button>
          ` : '';

          const actionsHtml = isAdmin ? `
            <div class="attachment-actions">
              <a href="${escapeHtml(doc.url)}" download class="btn-download-file" title="Download">
                <i class="fa-solid fa-download"></i>
              </a>
              ${deleteBtnHtml}
            </div>
          ` : `
            <a href="${escapeHtml(doc.url)}" download class="btn-download-file" title="Download">
              <i class="fa-solid fa-download"></i>
            </a>
          `;

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
              ${actionsHtml}
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
      const subDetailsUrl = `sub-project-details.php?project_id=${encodeURIComponent(sp.sub_project_id)}`;

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
            <span class="department-pill">${dept}</span>
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

  $(document).on('click', '.clickable-subproject-row', function () {
    const href = $(this).data('href');
    if (href) {
      window.location.href = href;
    }
  });

  // Helper to determine select class by status
  function getSelectStatusClass(status) {
    const s = (status || '').toLowerCase();
    if (s === 'pending') return 'status-pending';
    if (s === 'in progress') return 'status-progress';
    if (s === 'on hold') return 'status-on-hold';
    if (s === 'completed') return 'status-completed';
    return 'status-assigned';
  }

  // Toast feedback helper
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
    }, 2800);
  }

  // Handle Admin Project Status Change
  $(document).on('change', '#projectStatusSelect', function () {
    const $select = $(this);
    const newStatus = $select.val();
    const prevStatus = $select.data('prev-status') || '';

    if (newStatus === prevStatus) return;

    if (prevStatus !== 'Assigned' && newStatus === 'Assigned') {
      alert('Cannot change project status back to "Assigned" once updated.');
      $select.val(prevStatus);
      return;
    }

    if (newStatus === 'Completed') {
      const completionCheck = checkCanCompleteProject();
      if (!completionCheck.allowed) {
        alert(completionCheck.message);
        $select.val(prevStatus);
        return;
      }
    }

    $select.prop('disabled', true);

    $.ajax({
      url: '../api/update-project-status.php',
      type: 'POST',
      data: {
        project_id: currentProject ? currentProject.project_id : projectId,
        status: newStatus
      },
      dataType: 'json',
      success: function (response) {
        $select.prop('disabled', false);
        if (response.success) {
          if (currentProject) {
            currentProject.project_status = newStatus;
          }
          $select.data('prev-status', newStatus);

          // Once status has changed from 'Assigned', do not allow setting back to 'Assigned'
          $select.find('option[value="Assigned"]').remove();

          // Update CSS class on select
          $select.removeClass('status-assigned status-pending status-progress status-on-hold status-completed')
            .addClass(getSelectStatusClass(newStatus));

          // Also update header status badge if present
          const sClass = getStatusBadgeClass(newStatus);
          const sIcon = getStatusIcon(newStatus);
          $('#projectStatusBadge').attr('class', `badge-status ${sClass}`).html(`<i class="${sIcon}"></i> ${escapeHtml(newStatus)}`);

          // Update Last Edited audit info if returned
          if (response.edited_by) {
            $('#metaEditedGroup').show();
            const editedByPhoto = response.edited_by_photo || '../images/default-avatar.png';
            $('#metaEditedBy').html(`
              <div class="user-chip">
                <img src="${editedByPhoto}" class="user-avatar-img" alt="${escapeHtml(response.edited_by)}" onerror="this.src='../images/default-avatar.png'">
                <span>${escapeHtml(response.edited_by)} (${response.edited_at ? response.edited_at.substring(0, 16) : ''})</span>
              </div>
            `);
          }

          showStatusToast('Project status updated to ' + newStatus);
        } else {
          $select.val(prevStatus);
          $select.removeClass('status-assigned status-pending status-progress status-completed status-on-hold')
            .addClass(getSelectStatusClass(prevStatus));
          alert(response.message || 'Failed to update project status.');
        }
      },
      error: function () {
        $select.prop('disabled', false);
        $select.val(prevStatus);
        $select.removeClass('status-assigned status-pending status-progress status-completed status-on-hold')
          .addClass(getSelectStatusClass(prevStatus));
        alert('An error occurred while updating project status.');
      }
    });
  });

  // Handle Project Document Deletion (Admin only)
  $('#projectDocumentsList').on('click', '.btn-delete-document', function (e) {
    e.preventDefault();
    if (!isAdmin) {
      alert('Only administrators can delete project documents.');
      return;
    }

    const $btn = $(this);
    const filename = $btn.data('filename');
    const displayName = $btn.data('name') || filename;

    if (!filename) {
      alert('Document filename is missing.');
      return;
    }

    if (!confirm(`Are you sure you want to delete "${displayName}"?`)) {
      return;
    }

    $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

    $.ajax({
      url: '../api/delete-project-attachment.php',
      type: 'POST',
      data: {
        project_id: projectId,
        filename: filename
      },
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          const $card = $btn.closest('.attachment-card');
          $card.fadeOut(300, function () {
            $(this).remove();
            if (currentProject && Array.isArray(currentProject.documents)) {
              currentProject.documents = currentProject.documents.filter(function (d) {
                return d.filename !== filename;
              });
            }
            const $docList = $('#projectDocumentsList');
            if ($docList.children('.attachment-card').length === 0) {
              $docList.html(`
                <div class="no-attachments-text">
                  <i class="fa-regular fa-folder-open"></i> No documents uploaded for this project
                </div>
              `);
            }
          });
        } else {
          alert('Error: ' + (response.message || 'Failed to delete document.'));
          $btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i>');
        }
      },
      error: function (xhr) {
        let msg = 'An error occurred while deleting the document.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
        $btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i>');
      }
    });
  });

  // Initial load
  loadProjectDetails();
});
