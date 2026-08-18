$(document).ready(function () {
  const $dropzone = $('#fileDropzone');
  const $fileInput = $('#fileInput');
  const $selectedCard = $('#selectedFileCard');
  const $selectedFileName = $('#selectedFileName');
  const $selectedFileSize = $('#selectedFileSize');
  const $removeBtn = $('#removeFileBtn');
  const $deptSelect = $('#department_select');
  const $btnSubmit = $('#btnSubmitUpload');
  const $alert = $('#uploadAlert');
  const $resultsSection = $('#uploadResultsSection');
  const $resultsTableBody = $('#resultsTableBody');

  const allowedExtensions = ['csv', 'ods', 'xlsx', 'xls'];

  // Check form state to enable submit button
  function checkFormValidity() {
    const deptValid = $deptSelect.val() !== null && $deptSelect.val() !== '';
    const fileValid = $fileInput[0].files && $fileInput[0].files.length > 0;
    $btnSubmit.prop('disabled', !(deptValid && fileValid));
  }

  // Format file size
  function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Handle file selection
  function handleFileSelected(file) {
    if (!file) return;

    const ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(ext)) {
      showAlert('error', 'Invalid file type. Please select a .csv, .ods, .xlsx, or .xls file.');
      resetFileInput();
      return;
    }

    $selectedFileName.text(file.name);
    $selectedFileSize.text(formatBytes(file.size));
    $selectedCard.slideDown(200);
    hideAlert();
    checkFormValidity();
  }

  function resetFileInput() {
    $fileInput.val('');
    $selectedCard.slideUp(200);
    checkFormValidity();
  }

  // Event Listeners for File Dropzone
  $fileInput.on('change', function () {
    if (this.files && this.files[0]) {
      handleFileSelected(this.files[0]);
    } else {
      resetFileInput();
    }
  });

  $deptSelect.on('change', function () {
    checkFormValidity();
  });

  $removeBtn.on('click', function (e) {
    e.preventDefault();
    resetFileInput();
  });

  // Drag and Drop Events
  $dropzone.on('dragover dragenter', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $dropzone.addClass('dragover');
  });

  $dropzone.on('dragleave dragend drop', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $dropzone.removeClass('dragover');
  });

  $dropzone.on('drop', function (e) {
    const dt = e.originalEvent.dataTransfer;
    if (dt && dt.files && dt.files.length > 0) {
      $fileInput[0].files = dt.files;
      handleFileSelected(dt.files[0]);
    }
  });

  // Alert helpers
  function showAlert(type, message) {
    $alert.removeClass('upload-alert-success upload-alert-error')
      .addClass(type === 'success' ? 'upload-alert-success' : 'upload-alert-error')
      .html((type === 'success' ? '<i class="fa-solid fa-circle-check"></i> ' : '<i class="fa-solid fa-triangle-exclamation"></i> ') + message)
      .fadeIn(200);
  }

  function hideAlert() {
    $alert.fadeOut(150);
  }

  // Form Submit Handler
  $('#uploadForm').on('submit', function (e) {
    e.preventDefault();

    const dept = $deptSelect.val();
    const file = $fileInput[0].files[0];

    if (!dept) {
      showAlert('error', 'Please select a department.');
      return;
    }
    if (!file) {
      showAlert('error', 'Please select a file to upload.');
      return;
    }

    const formData = new FormData(this);

    const originalBtnHtml = $btnSubmit.html();
    $btnSubmit.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Processing & Importing...');
    hideAlert();
    $resultsSection.slideUp(150);

    $.ajax({
      url: 'api/upload-record.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          showAlert('success', response.message);
          
          if (response.details && response.details.length > 0) {
            renderResultsTable(response.details);
            $resultsSection.slideDown(250);
          }

          resetFileInput();
        } else {
          showAlert('error', response.message || 'Failed to upload and import records.');
        }
      },
      error: function (xhr, status, error) {
        let errorMsg = 'An error occurred while uploading the file.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        } else if (xhr.responseText) {
          try {
            const parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.message) {
              errorMsg = parsed.message;
            } else {
              errorMsg = xhr.responseText.replace(/<[^>]*>?/gm, '').substring(0, 200);
            }
          } catch(e) {
            errorMsg = xhr.responseText.replace(/<[^>]*>?/gm, '').substring(0, 200);
          }
        }
        showAlert('error', errorMsg);
      },
      complete: function () {
        $btnSubmit.html(originalBtnHtml);
        checkFormValidity();
      }
    });
  });

  // Render summary of imported rows
  function renderResultsTable(details) {
    $resultsTableBody.empty();

    details.forEach(function (item, index) {
      const slNo = item.sl_no || item.row_num || (index + 1);
      const tr = `
        <tr>
          <td>${slNo}</td>
          <td>${escapeHtml(item.date || '-')}</td>
          <td>${escapeHtml(item.agent || '-')}</td>
          <td>${escapeHtml(item.company_name || '-')}</td>
          <td>${escapeHtml(item.dealer_name || '-')}</td>
          <td>${escapeHtml(item.product_category || '-')}</td>
        </tr>
      `;
      $resultsTableBody.append(tr);
    });
  }

  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
});
