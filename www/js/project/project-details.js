$(document).ready(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const projectId = urlParams.get('project_id') || urlParams.get('id') || '';

  if (!projectId) {
    alert('No Project ID specified. Returning to Project Lists.');
    window.location.href = 'project-lists.php';
    return;
  }

  const $loadingOverlay = $('#loadingOverlay');
  const $subtasksTableBody = $('#subtasksTableBody');
  const $subtaskModal = $('#subtaskModal');
  const $subtaskForm = $('#addSubtaskForm');
  const $assigneeSelect = $('#subtask_assigned_to');
  const $btnOpenSubtaskModal = $('#btnOpenSubtaskModal');
  const $btnOpenSubtaskModalTop = $('#btnOpenSubtaskModalTop');
  const $btnCloseModal = $('#btnCloseSubtaskModal, #btnCancelSubtaskModal');

  const containerIsAdmin = $('.project-details-container').data('is-admin') == 1;
  let isAdmin = Boolean(containerIsAdmin);
  let canCreateSubtask = isAdmin;

  if (!canCreateSubtask) {
    $btnOpenSubtaskModal.hide();
    $btnOpenSubtaskModalTop.hide();
  }

  let currentProject = null;
  let currentSubtasks = [];

  function checkCanCompleteProject() {
    if (!currentSubtasks || currentSubtasks.length === 0) {
      return {
        allowed: false,
        message: 'Cannot set project status to "Completed". The project must have at least one subtask.'
      };
    }
    const incompleteSubtasks = currentSubtasks.filter(function (s) {
      return (s.status || '').trim().toLowerCase() !== 'completed';
    });
    if (incompleteSubtasks.length > 0) {
      return {
        allowed: false,
        message: `Cannot set project status to "Completed". All subtasks related to this project must be "Completed" first.`
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
          if (typeof response.can_create_subtask === 'boolean') {
            canCreateSubtask = response.can_create_subtask;
          } else {
            canCreateSubtask = isAdmin;
          }

          if (canCreateSubtask) {
            $btnOpenSubtaskModal.show();
            $btnOpenSubtaskModalTop.show();
          } else {
            $btnOpenSubtaskModal.hide();
            $btnOpenSubtaskModalTop.hide();
          }
          currentProject = response.project;
          currentSubtasks = Array.isArray(response.subtasks) ? response.subtasks : [];
          renderProjectOverview(response.project);
          renderSubtasks(currentSubtasks);
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

    // Render Project Attachments
    const $attList = $('#projectAttachmentsList');
    if ($attList.length) {
      $attList.empty();
      if (Array.isArray(project.attachments) && project.attachments.length > 0) {
        project.attachments.forEach(function (att) {
          const ext = (att.name || '').split('.').pop().toLowerCase();
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
                  <span class="attachment-name">${escapeHtml(att.name)}</span>
                  <span class="attachment-meta">${escapeHtml(att.size)}</span>
                </div>
              </div>
              <a href="${escapeHtml(att.url)}" download class="btn-download-file">
                <i class="fa-solid fa-download"></i>
              </a>
            </div>
          `);
          $attList.append($card);
        });
      } else {
        $attList.html(`
          <div class="no-attachments-text">
            <i class="fa-regular fa-folder-open"></i> No attachments uploaded for this project
          </div>
        `);
      }
    }
  }

  function renderSubtasks(subtasks) {
    const totalCount = subtasks.length;
    let pendingCount = 0;
    let progressCount = 0;
    let completedCount = 0;
    let onHoldCount = 0;

    subtasks.forEach(function (s) {
      if (s.status === 'Pending') pendingCount++;
      else if (s.status === 'In Progress') progressCount++;
      else if (s.status === 'Completed') completedCount++;
      else if (s.status === 'On Hold') onHoldCount++;
    });

    $('#subtaskCounterBadge').text(`${totalCount} Subtask${totalCount === 1 ? '' : 's'}`);
    $('#statTotal').text(`Total: ${totalCount}`);
    $('#statPending').text(`Pending: ${pendingCount}`);
    $('#statInProgress, #statProgress').text(`In Progress: ${progressCount}`);
    $('#statCompleted').text(`Completed: ${completedCount}`);
    $('#statOnHold').text(`On Hold: ${onHoldCount}`);

    if (totalCount === 0) {
      $subtasksTableBody.html(`
        <tr>
          <td colspan="8">
            <div class="empty-state" style="padding: 26px 16px;">
              <i class="fa-solid fa-list-check" style="font-size: 36px; color: var(--text-muted); margin-bottom: 12px;"></i>
              <h3 style="font-size: 15px; margin: 0 0 6px 0;">No subtasks created yet</h3>
            </div>
          </td>
        </tr>
      `);
      return;
    }

    let html = '';
    subtasks.forEach(function (subtask) {
      const isCompleted = subtask.status === 'Completed';
      const prioClass = getPriorityBadgeClass(subtask.priority);
      const prioIcon = getPriorityIcon(subtask.priority);
      const assignedPhoto = subtask.assigned_to_photo || '../images/default-avatar.png';
      const createdByPhoto = subtask.created_by_photo || '../images/default-avatar.png';

      const statusVal = subtask.status || 'Pending';
      const statusClass = getStatusBadgeClass(statusVal);
      const statusIcon = getStatusIcon(statusVal);

      html += `
        <tr class="${isCompleted ? 'subtask-completed' : ''}" data-subtask-id="${subtask.id}">
          <td>
            <span class="subtask-id-pill">${escapeHtml(subtask.subtask_id || '—')}</span>
          </td>
          <td>
            <span class="subtask-title-text">${escapeHtml(subtask.title)}</span>
            ${subtask.description ? `<span class="subtask-desc-text">${escapeHtml(subtask.description)}</span>` : ''}
          </td>
          <td>
            <div class="user-chip">
              <span>${escapeHtml(subtask.assigned_to)}</span>
            </div>
          </td>
          <td>
            <div class="user-chip">
              <span style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(subtask.created_by)} (${subtask.created_at ? subtask.created_at.substring(0, 10) : ''})</span>
            </div>
          </td>
          <td>
            <span class="subtask-time-badge">
              <i class="fa-regular fa-clock"></i> ${escapeHtml(subtask.total_time || '00:00:00')}
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

    $subtasksTableBody.html(html);
  }

  // Load assignable users into modal dropdown
  function loadAssignableUsers() {
    $.ajax({
      url: '../api/get-assignable-users.php',
      type: 'GET',
      dataType: 'json',
      success: function (response) {
        if (response.success && Array.isArray(response.users)) {
          let opts = '<option value="" disabled selected>Select Assignee</option>';
          response.users.forEach(function (user) {
            opts += `<option value="${escapeHtml(user)}">${escapeHtml(user)}</option>`;
          });
          $assigneeSelect.html(opts);
        }
      }
    });
  }

  // Modal open & close
  function openSubtaskModal() {
    $subtaskForm[0].reset();
    $subtaskModal.css('display', 'flex');
    $('#subtask_title').focus();
  }

  function closeSubtaskModal() {
    $subtaskModal.hide();
  }

  $btnOpenSubtaskModal.on('click', openSubtaskModal);
  $btnOpenSubtaskModalTop.on('click', openSubtaskModal);
  $(document).on('click', '.btn-open-modal-trigger', openSubtaskModal);
  $btnCloseModal.on('click', closeSubtaskModal);

  $subtaskModal.on('click', function (e) {
    if (e.target === this) {
      closeSubtaskModal();
    }
  });

  // Handle Subtask Form Submission
  $subtaskForm.on('submit', function (e) {
    e.preventDefault();

    const title = $('#subtask_title').val().trim();
    const assignedTo = $('#subtask_assigned_to').val();
    const priority = $('#subtask_priority').val();
    const dueDate = $('#subtask_due_date').val();
    const description = $('#subtask_description').val().trim();

    if (!title) {
      alert('Please enter a subtask title');
      $('#subtask_title').focus();
      return;
    }

    if (!assignedTo) {
      alert('Please select an assignee');
      $('#subtask_assigned_to').focus();
      return;
    }

    const $submitBtn = $('#btnSubmitSubtask');
    const originalBtnHtml = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...');

    $.ajax({
      url: '../api/create-project-subtask.php',
      type: 'POST',
      data: {
        project_id: currentProject ? currentProject.project_id : projectId,
        title: title,
        assigned_to: assignedTo,
        priority: priority,
        due_date: dueDate,
        description: description
      },
      dataType: 'json',
      success: function (response) {
        $submitBtn.prop('disabled', false).html(originalBtnHtml);
        if (response.success) {
          closeSubtaskModal();
          loadProjectDetails();
        } else {
          alert('Error: ' + (response.message || 'Failed to add subtask.'));
        }
      },
      error: function () {
        $submitBtn.prop('disabled', false).html(originalBtnHtml);
        alert('An error occurred while creating the subtask.');
      }
    });
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

  // Initial loads
  if (isAdmin) {
    loadAssignableUsers();
  }
  loadProjectDetails();
});
