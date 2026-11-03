$(document).ready(function () {
  const urlParams = new URLSearchParams(window.location.search);
  const projectId = urlParams.get('project_id') || urlParams.get('id') || '';

  if (!projectId) {
    alert('No Project ID specified. Returning to My Projects.');
    window.location.href = 'my-projects.php';
    return;
  }

  const $loadingOverlay = $('#loadingOverlay');
  const $subtasksTableBody = $('#subtasksTableBody');
  const $btnOpenSubtaskModal = $('#btnOpenSubtaskModal');
  const $subtaskModal = $('#subtaskModal');
  const $subtaskForm = $('#addSubtaskForm');
  const $btnCloseSubtaskModal = $('#btnCloseSubtaskModal, #btnCancelSubtaskModal');

  let currentProject = null;
  let canCreateSubtask = false;

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
    // if (c === 'hardware') return 'fa-solid fa-server';
    // if (c === 'software') return 'fa-solid fa-code';
    // return 'fa-solid fa-microchip';
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
    // if (s === 'in progress') return 'fa-solid fa-spinner';
    // if (s === 'completed') return 'fa-solid fa-circle-check';
    // if (s === 'pending') return 'fa-solid fa-clock';
    // if (s === 'on hold' || s === 'hold') return 'fa-solid fa-circle-pause';
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
        project_id: projectId,
        my_subtasks_only: 1
      },
      dataType: 'json',
      success: function (response) {
        if (!silent) {
          hideLoading();
        }
        if (response.success && response.project) {
          canCreateSubtask = Boolean(response.can_create_subtask || response.is_admin);
          if (canCreateSubtask) {
            $btnOpenSubtaskModal.show();
          } else {
            $btnOpenSubtaskModal.hide();
          }
          currentProject = response.project;
          renderProjectOverview(response.project);
          renderSubtasks(response.subtasks || []);
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
    $('#projectCategoryBadge').html(`<i class="${catIcon}"></i> ${escapeHtml(project.category)}`);

    const prioClass = getPriorityBadgeClass(project.priority);
    const prioIcon = getPriorityIcon(project.priority);
    $('#projectPriorityBadge').attr('class', `badge-priority ${prioClass}`).html(`<i class="${prioIcon}"></i> ${escapeHtml(project.priority)}`);

    const statusVal = project.project_status || 'Assigned';
    const statusClass = getStatusBadgeClass(statusVal);
    const statusIcon = getStatusIcon(statusVal);
    $('#projectStatusBadge').attr('class', `badge-status ${statusClass}`).html(`<i class="${statusIcon}"></i> ${escapeHtml(statusVal)}`);

    if (project.asignee && project.asignee.trim() !== '') {
      $('#projectAssigneeDisplay').show().html(`
        <span>${escapeHtml(project.asignee)}</span>
      `);
    } else {
      $('#projectAssigneeDisplay').hide();
    }

    $('#metaProjectDate').text(project.date || '—');
    $('#metaAssignedBy').text(project.assigned_by || '—');
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
                  <span class="attachment-name" title="${escapeHtml(att.name)}">${escapeHtml(att.name)}</span>
                  <span class="attachment-meta">${escapeHtml(att.size)}</span>
                </div>
              </div>
              <div class="attachment-actions">
                <a href="${escapeHtml(att.url)}" download class="btn-download-file">
                  <i class="fa-solid fa-download"></i>
                </a>
                <button type="button" class="btn-delete-file" data-filename="${escapeHtml(att.filename)}" data-name="${escapeHtml(att.name)}">
                  <i class="fa-solid fa-trash-can"></i>
                </button>
              </div>
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

  let currentSubtasks = [];

  function renderSubtasks(subtasks) {
    currentSubtasks = subtasks || [];
    const totalCount = subtasks.length;
    let pendingCount = 0;
    let progressCount = 0;
    let completedCount = 0;
    let onHoldCount = 0;
    let assignedTotalSeconds = 0;

    subtasks.forEach(function (s) {
      const st = (s.status || '').toLowerCase();
      if (st === 'pending') pendingCount++;
      else if (st === 'in progress') progressCount++;
      else if (st === 'completed') completedCount++;
      else if (st === 'on hold') onHoldCount++;

      if (s.total_seconds !== undefined && s.total_seconds !== null) {
        assignedTotalSeconds += parseInt(s.total_seconds, 10) || 0;
      } else if (s.total_time) {
        const parts = s.total_time.split(':');
        if (parts.length === 3) {
          assignedTotalSeconds += (parseInt(parts[0], 10) || 0) * 3600 + (parseInt(parts[1], 10) || 0) * 60 + (parseInt(parts[2], 10) || 0);
        }
      }
    });

    const assignedH = Math.floor(assignedTotalSeconds / 3600);
    const assignedM = Math.floor((assignedTotalSeconds % 3600) / 60);
    const assignedS = assignedTotalSeconds % 60;
    const formattedAssignedTime = `${String(assignedH).padStart(2, '0')}:${String(assignedM).padStart(2, '0')}:${String(assignedS).padStart(2, '0')}`;
    $('#metaTotalTime').text(formattedAssignedTime);

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
            <div class="empty-state" style="padding: 36px 16px;">
              <i class="fa-solid fa-list-check" style="font-size: 36px; color: var(--text-muted); margin-bottom: 12px;"></i>
              <h3 style="font-size: 15px; margin: 0 0 6px 0;">No subtasks found</h3>
            </div>
          </td>
        </tr>
      `);
      return;
    }

    let html = '';
    subtasks.forEach(function (subtask) {
      const isCompleted = (subtask.status || '').toLowerCase() === 'completed' || (subtask.subtask_table_status || '').toLowerCase() === 'completed';
      const prioClass = getPriorityBadgeClass(subtask.priority);
      const prioIcon = getPriorityIcon(subtask.priority);
      const assignedPhoto = subtask.assigned_to_photo || '../images/default-avatar.png';
      const createdByPhoto = subtask.created_by_photo || '../images/default-avatar.png';

      const statusVal = subtask.status || 'Pending';
      const statusClass = getStatusBadgeClass(statusVal);
      const statusIcon = getStatusIcon(statusVal);

      const hasTodayReport = Boolean(subtask.today_report_id);
      let reportActionBtn = '';
      if (!isCompleted) {
        if (hasTodayReport) {
          reportActionBtn = `
            <a href="new-project-report.php?id=${encodeURIComponent(subtask.today_report_id)}" target="_blank" rel="opener" class="btn-subtask-action btn-action-edit" data-subtask-id="${subtask.id}" data-report-id="${subtask.today_report_id}" title="Edit Report" aria-label="Edit Report">
              <i class="fa-solid fa-pen-to-square"></i>
            </a>
          `;
        } else {
          reportActionBtn = `
            <button type="button" class="btn-subtask-action btn-action-report" data-subtask-id="${subtask.id}" title="Create Report" aria-label="Create Report">
              <i class="fa-solid fa-file-circle-plus"></i>
            </button>
          `;
        }
      }

      const effectiveSubtaskId = subtask.subtask_id || subtask.id;
      const effectiveProjectId = subtask.project_id || projectId;
      const viewReportsUrl = `view-related-reports.php?subtask_id=${encodeURIComponent(effectiveSubtaskId)}&project_id=${encodeURIComponent(effectiveProjectId)}`;

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
          <td class="actions-cell">
            <div class="subtask-actions-group">
              <a href="${viewReportsUrl}" class="btn-subtask-action btn-action-view" data-subtask-id="${subtask.id}" title="View Related Reports" aria-label="View Related Reports">
                <i class="fa-solid fa-eye"></i>
              </a>
              ${reportActionBtn}
            </div>
          </td>
        </tr>
      `;
    });

    $subtasksTableBody.html(html);
  }

  // View Subtask Modal Handler
  const $viewModal = $('#viewSubtaskModal');

  let activeViewSubtask = null;

  function openViewModal(subtaskId) {
    const subtask = currentSubtasks.find(function (s) {
      return String(s.id) === String(subtaskId);
    });
    if (!subtask) return;
    activeViewSubtask = subtask;

    $('#viewSubtaskIdPill').text(subtask.subtask_id || '—');
    $('#viewSubtaskTitle').text(subtask.title);

    const statusVal = subtask.status || 'Pending';
    const statusClass = getStatusBadgeClass(statusVal);
    const statusIcon = getStatusIcon(statusVal);
    $('#viewSubtaskStatus').html(`<span class="badge-status ${statusClass}"><i class="${statusIcon}"></i> ${escapeHtml(statusVal)}</span>`);

    const prioClass = getPriorityBadgeClass(subtask.priority);
    const prioIcon = getPriorityIcon(subtask.priority);
    $('#viewSubtaskPriority').html(`<span class="badge-priority ${prioClass}"><i class="${prioIcon}"></i> ${escapeHtml(subtask.priority)}</span>`);

    $('#viewSubtaskDueDate').text(subtask.due_date || '—');
    $('#viewSubtaskTotalTime').text(subtask.total_time || '00:00:00');

    const assignedPhoto = subtask.assigned_to_photo || '../images/default-avatar.png';
    $('#viewSubtaskAssignee').html(`
      <div class="user-chip">
        <img src="${assignedPhoto}" class="user-avatar-img" alt="${escapeHtml(subtask.assigned_to)}" onerror="this.src='../images/default-avatar.png'">
        <span>${escapeHtml(subtask.assigned_to || '—')}</span>
      </div>
    `);

    const createdByPhoto = subtask.created_by_photo || '../images/default-avatar.png';
    $('#viewSubtaskCreatedBy').html(`
      <div class="user-chip">
        <img src="${createdByPhoto}" class="user-avatar-img" alt="${escapeHtml(subtask.created_by)}" onerror="this.src='../images/default-avatar.png'">
        <span>${escapeHtml(subtask.created_by || '—')} ${subtask.created_at ? '(' + escapeHtml(subtask.created_at.substring(0, 16)) + ')' : ''}</span>
      </div>
    `);

    if (currentProject) {
      $('#viewSubtaskProject').text(`${currentProject.project_name} (${currentProject.project_id})`);
    } else {
      $('#viewSubtaskProject').text(subtask.project_id || '—');
    }

    if (subtask.description && subtask.description.trim() !== '') {
      $('#viewSubtaskDesc').text(subtask.description);
    } else {
      $('#viewSubtaskDesc').html('<span style="color: var(--text-muted); font-style: italic;">No description provided for this subtask.</span>');
    }

    // Modal action button (Create Report vs Edit Report)
    const isCompleted = (subtask.status || '').toLowerCase() === 'completed' || (subtask.subtask_table_status || '').toLowerCase() === 'completed';
    const $modalBtn = $('#btnModalCreateReport');
    if (isCompleted) {
      $modalBtn.hide();
    } else {
      $modalBtn.show();
      if (subtask.today_report_id) {
        $modalBtn.html('<i class="fa-solid fa-pen-to-square"></i> Edit Report').attr('title', 'Edit Report');
      } else {
        $modalBtn.html('<i class="fa-solid fa-file-circle-plus"></i> Create Report').attr('title', 'Create Report');
      }
    }

    $viewModal.css('display', 'flex');
  }

  function closeViewModal() {
    $viewModal.hide();
  }

  $('#btnCloseViewSubtaskModal, #btnCancelViewSubtaskModal').on('click', closeViewModal);

  $viewModal.on('click', function (e) {
    if (e.target === this) {
      closeViewModal();
    }
  });

  $(document).on('keydown', function (e) {
    if (e.key === 'Escape' && $viewModal.is(':visible')) {
      closeViewModal();
    }
  });

  // Create / Edit Report Action Handler
  function triggerCreateReport(subtask, $btn) {
    if (!subtask) return;

    if ((subtask.status || '').toLowerCase() === 'completed' || (subtask.subtask_table_status || '').toLowerCase() === 'completed') {
      alert('Cannot create or edit reports for a completed subtask.');
      return;
    }

    if (subtask.today_report_id) {
      const reportUrl = 'new-project-report.php?id=' + encodeURIComponent(subtask.today_report_id);
      const newTab = window.open(reportUrl, '_blank');
      if (!newTab) {
        window.location.href = reportUrl;
      }
      return;
    }

    const projectId = subtask.project_id || (currentProject ? currentProject.project_id : '');
    const subtaskId = subtask.subtask_id || subtask.id;

    if (!projectId || !subtaskId) {
      alert('Unable to identify project or subtask for report creation.');
      return;
    }

    let originalHtml = '';
    if ($btn && $btn.length) {
      originalHtml = $btn.html();
      $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i>');
    }

    $.ajax({
      url: '../api/create-project-report.php',
      type: 'POST',
      data: {
        project_id: projectId,
        subtask_id: subtaskId
      },
      dataType: 'json',
      success: function (response) {
        if (response.success && response.report_id) {
          subtask.today_report_id = response.report_id;
          renderSubtasks(currentSubtasks);

          const reportUrl = 'new-project-report.php?id=' + encodeURIComponent(response.report_id);
          const newTab = window.open(reportUrl, '_blank');
          if (!newTab) {
            window.location.href = reportUrl;
          }
        } else {
          alert('Error: ' + (response.message || 'Failed to create report record.'));
        }
      },
      error: function (xhr) {
        let msg = 'An error occurred while creating project report.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        } else if (xhr.responseText) {
          console.error('Create report error details:', xhr.responseText);
        }
        alert(msg);
      },
      complete: function () {
        if ($btn && $btn.length) {
          $btn.prop('disabled', false).html(originalHtml);
        }
      }
    });
  }

  $subtasksTableBody.on('click', '.btn-action-report', function (e) {
    e.preventDefault();
    const subtaskId = $(this).attr('data-subtask-id');
    const subtask = currentSubtasks.find(function (s) {
      return String(s.id) === String(subtaskId);
    });
    if (subtask) {
      triggerCreateReport(subtask, $(this));
    } else {
      alert('Subtask details not found.');
    }
  });

  $subtasksTableBody.on('click', '.btn-action-edit', function (e) {
    const reportUrl = $(this).attr('href');
    if (reportUrl) {
      e.preventDefault();
      window.open(reportUrl, '_blank');
    }
  });

  $('#btnModalCreateReport').on('click', function (e) {
    e.preventDefault();
    let subtask = activeViewSubtask;
    if (!subtask) {
      const subtaskIdText = $('#viewSubtaskIdPill').text().trim();
      subtask = currentSubtasks.find(function (s) {
        return s.subtask_id === subtaskIdText || String(s.id) === subtaskIdText;
      });
    }
    if (subtask) {
      closeViewModal();
      if (subtask.today_report_id) {
        const reportUrl = 'new-project-report.php?id=' + encodeURIComponent(subtask.today_report_id);
        const newTab = window.open(reportUrl, '_blank');
        if (!newTab) {
          window.location.href = reportUrl;
        }
      } else {
        triggerCreateReport(subtask, $(this));
      }
    } else {
      alert('Subtask details not found.');
    }
  });

  // Handle Project Attachment Deletion
  $('#projectAttachmentsList').on('click', '.btn-delete-file', function (e) {
    e.preventDefault();
    const $btn = $(this);
    const filename = $btn.data('filename');
    const displayName = $btn.data('name') || filename;

    if (!filename) {
      alert('Attachment filename is missing.');
      return;
    }

    if (!confirm(`Are you sure you want to delete "${displayName}"?`)) {
      return;
    }

    $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Deleting...');

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
            if (currentProject && Array.isArray(currentProject.attachments)) {
              currentProject.attachments = currentProject.attachments.filter(function (a) {
                return a.filename !== filename;
              });
            }
            const $attList = $('#projectAttachmentsList');
            if ($attList.children('.attachment-card').length === 0) {
              $attList.html(`
                <div class="no-attachments-text">
                  <i class="fa-regular fa-folder-open"></i> No attachments uploaded for this project
                </div>
              `);
            }
          });
        } else {
          alert('Error: ' + (response.message || 'Failed to delete attachment.'));
          $btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i> Delete');
        }
      },
      error: function (xhr) {
        let msg = 'An error occurred while deleting the attachment.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
        $btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i> Delete');
      }
    });
  });

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
  $btnCloseSubtaskModal.on('click', closeSubtaskModal);
  $subtaskModal.on('click', function (e) {
    if ($(e.target).is($subtaskModal)) {
      closeSubtaskModal();
    }
  });

  // Handle Subtask Submission (auto-assigns to current user)
  $subtaskForm.on('submit', function (e) {
    e.preventDefault();

    const title = $('#subtask_title').val().trim();
    const dueDate = $('#subtask_due_date').val().trim();
    const description = $('#subtask_description').val().trim();

    if (!title) {
      alert('Please enter a subtask title.');
      $('#subtask_title').focus();
      return;
    }

    const $btn = $('#btnSubmitSubtask');
    const origHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...');

    $.ajax({
      url: '../api/create-project-subtask.php',
      type: 'POST',
      data: {
        project_id: projectId,
        title: title,
        // priority: priority,
        due_date: dueDate,
        description: description
      },
      dataType: 'json',
      success: function (response) {
        $btn.prop('disabled', false).html(origHtml);
        if (response.success) {
          closeSubtaskModal();
          loadProjectDetails(true);
        } else {
          alert('Error: ' + (response.message || 'Failed to add subtask.'));
        }
      },
      error: function (xhr) {
        $btn.prop('disabled', false).html(origHtml);
        let msg = 'An unexpected error occurred.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
      }
    });
  });

  // Refresh subtasks when returning to the window/tab
  window.addEventListener('focus', function () {
    loadProjectDetails(true);
  });

  // Initial load
  loadProjectDetails();
});
