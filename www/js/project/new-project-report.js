$(document).ready(function () {
  let formSubmitted = false;
  const reportId = $('input[name="id"]').val();
  const projectId = $('input[name="project_id"]').val();

  // 1. Live Session Stopwatch Timer Mechanism
  let timerInterval = null;
  let startTime = null;
  const offsetSeconds = typeof window.projectReportOffsetSeconds === 'number' ? window.projectReportOffsetSeconds : 0;

  function startTimer() {
    if (timerInterval) clearInterval(timerInterval);

    startTime = Date.now() - (offsetSeconds * 1000);
    updateTimerDisplay();

    timerInterval = setInterval(updateTimerDisplay, 1000);
  }

  function updateTimerDisplay() {
    const totalSeconds = Math.max(0, Math.floor((Date.now() - startTime) / 1000));

    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const formattedTime =
      String(hours).padStart(2, '0') + ':' +
      String(minutes).padStart(2, '0') + ':' +
      String(seconds).padStart(2, '0');

    $('#runningTimer').text(formattedTime);
    $('#total_time').val(formattedTime);
  }

  startTimer();

  // 2. Periodic Auto-save (Strict 1-Minute Interval & Single Request for Time and Fields)
  let isAutoSaving = false;
  let lastAutoSaveTime = 0;

  function autoSaveReport(isUnloading = false, force = false, callback = null) {
    if (formSubmitted || !reportId) {
      if (typeof callback === 'function') callback(false);
      return;
    }

    // Prevent concurrent / duplicate overlapping requests unless forced
    if (!isUnloading && !force && isAutoSaving) {
      if (typeof callback === 'function') callback(false);
      return;
    }

    const now = Date.now();
    // Throttle to ensure no duplicate requests within 3 seconds unless forced
    if (!force && (now - lastAutoSaveTime < 3000)) {
      if (typeof callback === 'function') callback(false);
      return;
    }
    lastAutoSaveTime = now;

    const nowStr = new Date().toTimeString().split(' ')[0];
    $('#end_time').val(nowStr);

    const formElem = document.getElementById('projectReportForm');
    const formData = formElem ? new FormData(formElem) : new FormData();

    if (!formData.has('id')) {
      formData.append('id', reportId);
    }
    formData.set('end_time', nowStr);
    formData.set('total_time', $('#total_time').val() || '00:00:00');

    // Remove file inputs to keep beacon and auto-save lightweight
    formData.delete('uploaded_files[]');
    formData.delete('uploaded_files');

    if (isUnloading) {
      if (navigator.sendBeacon) {
        navigator.sendBeacon('../api/log-project-report-time.php', formData);
      } else {
        fetch('../api/log-project-report-time.php', {
          method: 'POST',
          body: formData,
          keepalive: true
        });
      }
      if (typeof callback === 'function') callback(true);
      return;
    }

    isAutoSaving = true;
    // Exactly one single unified AJAX request saving project time and all fields
    $.ajax({
      url: '../api/log-project-report-time.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (res) {
        if (typeof callback === 'function') {
          callback(res && res.success, res ? res.message : '');
        }
      },
      error: function (xhr) {
        if (typeof callback === 'function') {
          callback(false, xhr.responseJSON ? xhr.responseJSON.message : 'Error');
        }
      },
      complete: function () {
        isAutoSaving = false;
      }
    });
  }

  $(window).on('beforeunload', function (e) {
    if (!formSubmitted) {
      e.preventDefault();
      e.returnValue = 'You have unsaved changes in this report, are you sure you want to leave?';
      return 'You have unsaved changes in this report, are you sure you want to leave?';
    }
  });

  window.addEventListener('pagehide', function () {
    autoSaveReport(true);
  });

  // Exactly one request every 1 minute interval
  const autoSaveInterval = setInterval(function () {
    if (!formSubmitted) {
      autoSaveReport(false);
    } else {
      clearInterval(autoSaveInterval);
    }
  }, 60000);

  // 3. Dynamic Auto-growing Textarea
  $('#testing_description').on('input', function () {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight + 2) + 'px';
  });

  // 4. Client Name Autocomplete
  let clientList = Array.isArray(window.projectReportClients) ? window.projectReportClients : [];
  let modalCurrentFocus = -1;

  if (clientList.length === 0) {
    $.ajax({
      url: '../api/get-clients.php',
      type: 'GET',
      dataType: 'json',
      success: function (res) {
        if (res.success && Array.isArray(res.data)) {
          clientList = res.data;
        }
      }
    });
  }

  const $clientInput = $('#client_name');
  const $clientSuggestions = $('#clientSuggestions');

  $clientInput.on('input', function () {
    const val = this.value.trim().toLowerCase();
    $clientSuggestions.empty().hide();
    modalCurrentFocus = -1;

    if (!val || clientList.length === 0) return;

    const matches = clientList.filter(function (c) {
      return c.toLowerCase().includes(val);
    }).slice(0, 10);

    if (matches.length > 0) {
      matches.forEach(function (match, index) {
        const $item = $('<div class="autocomplete-suggestion"></div>')
          .text(match)
          .attr('data-index', index)
          .on('click', function () {
            $clientInput.val(match);
            $clientSuggestions.empty().hide();
          });
        $clientSuggestions.append($item);
      });
      $clientSuggestions.show();
    }
  });

  $clientInput.on('keydown', function (e) {
    const $items = $clientSuggestions.find('.autocomplete-suggestion');
    if (!$items.length) return;

    if (e.keyCode === 40) { // Arrow Down
      modalCurrentFocus++;
      setActiveSuggestion($items);
      e.preventDefault();
    } else if (e.keyCode === 38) { // Arrow Up
      modalCurrentFocus--;
      setActiveSuggestion($items);
      e.preventDefault();
    } else if (e.keyCode === 13) { // Enter
      if (modalCurrentFocus > -1 && $items.eq(modalCurrentFocus).length) {
        $items.eq(modalCurrentFocus).trigger('click');
        e.preventDefault();
      }
    } else if (e.keyCode === 27) { // Escape
      $clientSuggestions.empty().hide();
      modalCurrentFocus = -1;
    }
  });

  function setActiveSuggestion($items) {
    $items.removeClass('active');
    if (modalCurrentFocus >= $items.length) modalCurrentFocus = 0;
    if (modalCurrentFocus < 0) modalCurrentFocus = $items.length - 1;
    $items.eq(modalCurrentFocus).addClass('active');
  }

  $(document).on('click', function (e) {
    if (!$(e.target).closest('.autocomplete-wrapper').length) {
      $clientSuggestions.empty().hide();
      modalCurrentFocus = -1;
    }
  });

  // 5. Interactive File Upload & Drag-and-Drop Handler
  let selectedFiles = [];

  function renderFilePreview() {
    const $preview = $('#fileListPreview').empty();
    const fileInput = $('#uploaded_files')[0];

    if (fileInput) {
      try {
        const dt = new DataTransfer();
        selectedFiles.forEach(function (f) { dt.items.add(f); });
        fileInput.files = dt.files;
      } catch (e) {
        // Fallback for older browsers
      }
    }

    selectedFiles.forEach(function (file, index) {
      let iconClass = 'fa-regular fa-file';
      const ext = (file.name || '').split('.').pop().toLowerCase();
      if (['pdf'].includes(ext)) iconClass = 'fa-regular fa-file-pdf';
      else if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) iconClass = 'fa-regular fa-file-image';
      else if (['csv', 'xls', 'xlsx'].includes(ext)) iconClass = 'fa-regular fa-file-excel';
      else if (['doc', 'docx'].includes(ext)) iconClass = 'fa-regular fa-file-word';
      else if (['zip', 'rar', '7z'].includes(ext)) iconClass = 'fa-regular fa-file-zipper';

      const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
      const sizeText = file.size >= 1024 * 1024 ? sizeMB + ' MB' : Math.round(file.size / 1024) + ' KB';

      const $item = $(`
        <div class="file-preview-item">
          <div class="file-preview-info">
            <i class="${iconClass}"></i>
            <span>${escapeHtml(file.name)}</span>
            <span class="file-preview-size">(${sizeText})</span>
          </div>
          <button type="button" class="file-preview-remove" data-index="${index}" title="Remove file">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      `);
      $preview.append($item);
    });
  }

  function handleFilesInput(newFilesList) {
    const newFiles = Array.from(newFilesList);
    let limitExceeded = false;
    let sizeExceeded = false;
    let currentTotalSize = selectedFiles.reduce(function (sum, f) { return sum + f.size; }, 0);
    const maxTotalSize = 10 * 1024 * 1024;

    newFiles.forEach(function (newFile) {
      const exists = selectedFiles.some(function (f) {
        return f.name === newFile.name && f.size === newFile.size;
      });
      if (!exists) {
        if (selectedFiles.length >= 4) {
          limitExceeded = true;
        } else if (currentTotalSize + newFile.size > maxTotalSize) {
          sizeExceeded = true;
        } else {
          selectedFiles.push(newFile);
          currentTotalSize += newFile.size;
        }
      }
    });

    if (limitExceeded) {
      alert('Maximum 4 files can be uploaded at a time.');
    }
    if (sizeExceeded) {
      alert('Total size of attachments cannot exceed 10MB.');
    }

    renderFilePreview();
  }

  $('#uploaded_files').on('change', function () {
    if (this.files && this.files.length) {
      handleFilesInput(this.files);
    }
  });

  $('#fileListPreview').on('click', '.file-preview-remove', function () {
    const index = parseInt($(this).data('index'), 10);
    if (!isNaN(index) && index >= 0 && index < selectedFiles.length) {
      selectedFiles.splice(index, 1);
      renderFilePreview();
    }
  });

  const $dropWrapper = $('.file-upload-wrapper');
  $dropWrapper.on('dragover dragenter', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass('dragover');
  });

  $dropWrapper.on('dragleave dragend drop', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('dragover');
  });

  $dropWrapper.on('drop', function (e) {
    const dt = e.originalEvent.dataTransfer;
    if (dt && dt.files && dt.files.length) {
      handleFilesInput(dt.files);
    }
  });

  // 6. Reset Action
  $('#resetBtn').on('click', function () {
    if (confirm('Are you sure you want to reset all modifications?')) {
      window.location.reload();
    }
  });

  // 6. Custom Success Modal
  function showSuccessModal(message, reportIdVal, projIdVal) {
    if ($('#modal-style').length === 0) {
      $('<style id="modal-style">')
        .prop('type', 'text/css')
        .html(`
          .custom-success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
          }
          .custom-success-modal {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 24px;
            padding: 40px;
            width: 90%;
            max-width: 440px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            transform: scale(0.9) translateY(10px);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s ease;
            opacity: 0;
          }
          .custom-success-overlay.show {
            opacity: 1;
          }
          .custom-success-overlay.show .custom-success-modal {
            transform: scale(1) translateY(0);
            opacity: 1;
          }
          .success-icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #f0fdf4;
            border: 2px solid #bbf7d0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px auto;
            color: #10b981;
            font-size: 36px;
          }
          .success-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
          }
          .success-record-badge {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            font-family: monospace;
            font-size: 15px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
          }
          .modal-btn-ok {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 15px;
            font-weight: 600;
            padding: 14px 0;
            width: 60%;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            transition: all 0.3s ease;
            outline: none;
          }
          .modal-btn-ok:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #34d399 0%, #059669 100%);
          }
        `)
        .appendTo('head');
    }

    const $overlay = $('<div class="custom-success-overlay"></div>').appendTo('body');
    const $modal = $(`
      <div class="custom-success-modal">
        <div class="success-icon-wrapper">
          <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="success-title">Project Report Saved</div>
        <div class="success-record-badge">Report ID: #${reportIdVal}</div><br>
        <button type="button" class="modal-btn-ok">OK</button>
      </div>
    `).appendTo($overlay);

    setTimeout(function () {
      $overlay.addClass('show');
    }, 50);

    function closeCurrentTab() {
      try {
        window.close();
      } catch (e) {}

      try {
        self.close();
      } catch (e) {}

      try {
        window.open('', '_self', '');
        window.close();
      } catch (e) {}

      try {
        if (window.top && window.top !== window) {
          window.top.close();
        }
      } catch (e) {}
    }

    $modal.find('.modal-btn-ok').on('click', function () {
      formSubmitted = true;
      $(window).off('beforeunload');
      window.onbeforeunload = null;

      // Notify and update the opener tab ("old tab") if available
      if (window.opener && !window.opener.closed) {
        try {
          const openerHref = window.opener.location.href || '';
          if (openerHref.indexOf('my-project-details.php') !== -1 ||
              openerHref.indexOf('view-related-reports.php') !== -1 ||
              openerHref.indexOf('my-project-reports.php') !== -1) {
            window.opener.location.reload();
          } else if (projIdVal) {
            window.opener.location.href = 'my-project-details.php?id=' + encodeURIComponent(projIdVal);
          } else {
            window.opener.location.reload();
          }
          window.opener.focus();
        } catch (err) {
          console.warn('Could not communicate with opener tab:', err);
        }
      }

      // Close the current tab immediately
      closeCurrentTab();

      // If browser strictly blocks window.close() (e.g. URL opened directly in existing tab history)
      setTimeout(function () {
        if (!window.closed) {
          $modal.find('.success-title').text('Report Saved Successfully');
          $modal.find('.success-record-badge').html('You can now close this tab.<br><span style="font-size:12px;font-weight:normal;color:#64748b;">(Or press Ctrl+W to close)</span>');
          $modal.find('.modal-btn-ok').text('Close Tab').off('click').on('click', function () {
            closeCurrentTab();
          });
        }
      }, 500);
    });
  }

  // 7. Form Submission Handler
  $('#projectReportForm').on('submit', function (e) {
    e.preventDefault();

    // Set end time on submission
    const nowStr = new Date().toTimeString().split(' ')[0];
    $('#end_time').val(nowStr);

    // Validate files limit before submit
    if (selectedFiles.length > 4) {
      alert('Maximum 4 files can be uploaded at a time.');
      return;
    }
    const currentTotalSize = selectedFiles.reduce(function (sum, f) { return sum + f.size; }, 0);
    if (currentTotalSize > 10 * 1024 * 1024) {
      alert('Total size of attachments cannot exceed 10MB.');
      return;
    }

    const $btn = $('#submitBtn');
    const originalText = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving Report...');

    const formData = new FormData(this);
    if (selectedFiles.length > 0) {
      formData.delete('uploaded_files[]');
      formData.delete('uploaded_files');
      selectedFiles.forEach(function (file) {
        formData.append('uploaded_files[]', file);
      });
    }

    $.ajax({
      url: '../api/save-project-report.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          formSubmitted = true;
          if (timerInterval) clearInterval(timerInterval);
          showSuccessModal(response.message, reportId, projectId);
        } else {
          alert('Error: ' + (response.message || 'Failed to save report.'));
          $btn.prop('disabled', false).html(originalText);
        }
      },
      error: function (xhr) {
        let msg = 'An error occurred while saving the project report.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
        $btn.prop('disabled', false).html(originalText);
      }
    });
  });

  // 8. Status Change Handler: Updates status of all reports with this subtask_id immediately
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
    }, 2800);
  }

  $('#status').on('change', function () {
    const newStatus = $(this).val();
    if (!newStatus) return;

    autoSaveReport(false, true, function (success) {
      if (success) {
        showStatusToast(`Status updated to "${newStatus}"`);
      }
    });
  });
});
