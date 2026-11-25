$(document).ready(function () {
  const $form = $('#editProjectForm');
  const $submitBtn = $('#btnUpdateProject');
  const originalBtnHtml = $submitBtn.html();

  const $projectId = $('#project_id');
  const $numericId = $('#numeric_id');
  const $date = $('#project_date');
  const $projectName = $('#project_name');
  const $category = $('#project_category');
  const $priority = $('#project_priority');
  const $projectStatus = $('#project_status');

  const $clientDealerSpecific = $('#client_dealer_specific');
  const $clientDealerName = $('#client_dealer_name');
  const $clientDealerStar = $('#clientDealerStar');
  const $suggestions = $('#clientDealerSuggestions');
  const $addCompanyCheckboxContainer = $('#addCompanyCheckboxContainer');
  const $addToCompanyList = $('#add_to_company_list');

  // Product inputs
  const $productName = $('#product_name');
  const $productSubCategory = $('#product_sub_category');

  // Additional Hardware Details inputs
  const $productSerialNo = $('#product_serial_no');
  const $lcdId = $('#lcd_id');
  const $hardwareId = $('#hardware_id');
  const $softwareVersion = $('#software_version');
  const $haspId = $('#hasp_id');

  // Description
  const $projectDescription = $('#project_description');

  let companyList = null;
  let isLoadingCompanyList = false;
  let companyListCallbacks = [];
  let currentFocus = -1;

  function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // --- Company List / Client Autocomplete ---
  function fetchCompanyList(callback) {
    if (companyList !== null) {
      if (typeof callback === 'function') callback(companyList);
      return;
    }

    if (typeof callback === 'function') {
      companyListCallbacks.push(callback);
    }

    if (isLoadingCompanyList) {
      return;
    }

    isLoadingCompanyList = true;
    $.ajax({
      url: '../api/get-clients.php',
      type: 'GET',
      dataType: 'json',
      success: function (response) {
        isLoadingCompanyList = false;
        if (response.success && Array.isArray(response.data)) {
          companyList = response.data;
          const cbs = companyListCallbacks.slice();
          companyListCallbacks = [];
          cbs.forEach(function (cb) {
            cb(companyList);
          });
        }
      },
      error: function () {
        isLoadingCompanyList = false;
        companyListCallbacks = [];
        console.error('Failed to load company list from server');
      }
    });
  }

  function checkCompanyExists() {
    const isSpecific = $clientDealerSpecific.val() === 'Yes';
    const val = $clientDealerName.val().trim();

    if (!isSpecific || $clientDealerName.prop('disabled') || !val || val.toUpperCase() === 'NA' || val.toUpperCase() === 'N/A') {
      $addCompanyCheckboxContainer.removeClass('active');
      $addToCompanyList.prop('checked', false);
      return;
    }

    if (companyList === null) {
      fetchCompanyList(function () {
        checkCompanyExists();
      });
      return;
    }

    const exists = companyList.some(function (item) {
      return item.trim().toLowerCase() === val.toLowerCase();
    });

    if (!exists) {
      $addCompanyCheckboxContainer.addClass('active');
    } else {
      $addCompanyCheckboxContainer.removeClass('active');
      $addToCompanyList.prop('checked', false);
    }
  }

  function handleSpecificChange() {
    const isSpecific = $clientDealerSpecific.val() === 'Yes';

    if (isSpecific) {
      $clientDealerName.prop('disabled', false);
      $clientDealerStar.addClass('active');
      if ($clientDealerName.val().trim() === 'NA') {
        $clientDealerName.val('');
      }
      $clientDealerName.attr('placeholder', 'Enter or select client/dealer name');
      fetchCompanyList(function () {
        if (document.activeElement === $clientDealerName[0]) {
          renderSuggestions($clientDealerName.val());
        }
        checkCompanyExists();
      });
      $clientDealerName.focus();
    } else {
      $clientDealerName.val('NA');
      $clientDealerName.attr('placeholder', 'NA');
      $clientDealerName.prop('disabled', true);
      $clientDealerStar.removeClass('active');
      $suggestions.empty().hide();
      currentFocus = -1;
      checkCompanyExists();
    }
  }

  $clientDealerSpecific.on('change', handleSpecificChange);

  function renderSuggestions(val) {
    $suggestions.empty().hide();
    currentFocus = -1;

    if (!companyList || companyList.length === 0 || $clientDealerName.prop('disabled')) {
      return;
    }

    const query = (val || '').trim().toLowerCase();
    const matches = query
      ? companyList.filter(function (item) {
          return item.toLowerCase().includes(query);
        }).slice(0, 10)
      : companyList.slice(0, 10);

    if (matches.length > 0) {
      matches.forEach(function (match, index) {
        const $item = $('<div class="autocomplete-suggestion"></div>')
          .text(match)
          .attr('data-index', index)
          .on('mousedown', function (e) {
            e.preventDefault();
            $clientDealerName.val(match);
            $suggestions.empty().hide();
            currentFocus = -1;
            checkCompanyExists();
          });
        $suggestions.append($item);
      });
      $suggestions.show();
    }
  }

  $clientDealerName.on('input change', function () {
    renderSuggestions($(this).val());
    checkCompanyExists();
  });

  $clientDealerName.on('focus', function () {
    if (!$(this).prop('disabled')) {
      fetchCompanyList(function () {
        renderSuggestions($clientDealerName.val());
        checkCompanyExists();
      });
    }
  });

  $clientDealerName.on('keydown', function (e) {
    const $items = $suggestions.find('.autocomplete-suggestion');
    if (!$items.length || !$suggestions.is(':visible')) return;

    if (e.keyCode === 40) { // Arrow Down
      currentFocus++;
      setActiveSuggestion($items);
      e.preventDefault();
    } else if (e.keyCode === 38) { // Arrow Up
      currentFocus--;
      setActiveSuggestion($items);
      e.preventDefault();
    } else if (e.keyCode === 13) { // Enter
      if (currentFocus > -1 && $items.eq(currentFocus).length) {
        e.preventDefault();
        $items.eq(currentFocus).trigger('mousedown');
      }
    } else if (e.keyCode === 27) { // Escape
      $suggestions.empty().hide();
      currentFocus = -1;
    }
  });

  function setActiveSuggestion($items) {
    $items.removeClass('active');
    if (currentFocus >= $items.length) currentFocus = 0;
    if (currentFocus < 0) currentFocus = $items.length - 1;
    const $activeItem = $items.eq(currentFocus);
    $activeItem.addClass('active');
    if ($activeItem[0] && typeof $activeItem[0].scrollIntoView === 'function') {
      $activeItem[0].scrollIntoView({ block: 'nearest' });
    }
  }

  $(document).on('click', function (e) {
    if (!$(e.target).closest('.autocomplete-wrapper').length) {
      $suggestions.empty().hide();
      currentFocus = -1;
    }
  });

  // --- Delete Existing Documents Logic ---
  $('#existingDocumentsList').on('click', '.btn-delete-existing-doc', function (e) {
    e.preventDefault();
    const $btn = $(this);
    const filename = $btn.data('filename');
    const projectId = $btn.data('project-id') || $projectId.val();
    const $item = $btn.closest('.existing-doc-item');

    if (!confirm('Are you sure you want to delete this document?')) {
      return;
    }

    $btn.prop('disabled', true);

    $.ajax({
      url: '../api/delete-project-attachment.php',
      type: 'POST',
      data: {
        project_id: projectId,
        filename: filename
      },
      dataType: 'json',
      success: function (response) {
        if (response && response.success) {
          $item.fadeOut(300, function () {
            $(this).remove();
            const remainingCount = $('#existingDocumentsList .existing-doc-item').length;
            if (remainingCount === 0) {
              $('#existingDocumentsCard').hide();
            }
          });
        } else {
          alert((response && response.message) ? response.message : 'Failed to delete attachment.');
          $btn.prop('disabled', false);
        }
      },
      error: function (xhr) {
        let msg = 'An error occurred while deleting the attachment.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
        $btn.prop('disabled', false);
      }
    });
  });

  // --- Upload Documents Logic ---
  let selectedDocuments = [];
  const $docInput = $('#uploaded_documents');
  const $docPreview = $('#fileListPreview');
  const $dropWrapper = $('.file-upload-wrapper');
  const allowedDocExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'];

  function getExistingDocsCount() {
    return $('#existingDocumentsList .existing-doc-item').length;
  }

  function renderDocumentPreview() {
    $docPreview.empty();
    const docInputEl = $docInput[0];

    if (docInputEl) {
      try {
        const dt = new DataTransfer();
        selectedDocuments.forEach(function (f) { dt.items.add(f); });
        docInputEl.files = dt.files;
      } catch (e) {
        // Fallback for older browsers
      }
    }

    selectedDocuments.forEach(function (file, index) {
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
          <button type="button" class="file-preview-remove" data-index="${index}" title="Remove document">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
      `);
      $docPreview.append($item);
    });
  }

  function handleDocumentFilesInput(newFilesList) {
    const newFiles = Array.from(newFilesList);
    let limitExceeded = false;
    let sizeExceeded = false;
    let hasInvalidExt = false;
    const maxTotalSize = 20 * 1024 * 1024; // 20MB
    const existingCount = getExistingDocsCount();
    let currentTotalSize = selectedDocuments.reduce(function (sum, f) { return sum + f.size; }, 0);

    newFiles.forEach(function (newFile) {
      const ext = (newFile.name || '').split('.').pop().toLowerCase();
      if (!allowedDocExtensions.includes(ext)) {
        hasInvalidExt = true;
        return;
      }
      const exists = selectedDocuments.some(function (f) {
        return f.name === newFile.name && f.size === newFile.size;
      });
      if (!exists) {
        if (existingCount + selectedDocuments.length >= 2) {
          limitExceeded = true;
        } else if (currentTotalSize + newFile.size > maxTotalSize) {
          sizeExceeded = true;
        } else {
          selectedDocuments.push(newFile);
          currentTotalSize += newFile.size;
        }
      }
    });

    if (hasInvalidExt) {
      alert('Invalid file format. Allowed formats: PDF, Images, Documents, CSV, TXT, ZIP.');
    }
    if (limitExceeded) {
      alert(`Maximum 2 documents allowed in total (currently has ${existingCount} existing).`);
    }
    if (sizeExceeded) {
      alert('Total size of documents cannot exceed 20MB.');
    }

    renderDocumentPreview();
  }

  $docInput.on('click', function () {
    this.value = '';
  });

  $docInput.on('change', function () {
    if (this.files && this.files.length) {
      handleDocumentFilesInput(this.files);
    }
    this.value = '';
  });

  $docPreview.on('click', '.file-preview-remove', function () {
    const index = parseInt($(this).data('index'), 10);
    if (!isNaN(index) && index >= 0 && index < selectedDocuments.length) {
      selectedDocuments.splice(index, 1);
      renderDocumentPreview();
    }
  });

  $dropWrapper.on('dragover dragenter', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass('dragover');
  });

  $dropWrapper.on('dragleave dragend', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('dragover');
  });

  $dropWrapper.on('drop', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('dragover');
    const dt = e.originalEvent.dataTransfer;
    if (dt && dt.files && dt.files.length) {
      handleDocumentFilesInput(dt.files);
    }
  });

  // --- Initial Setup on Page Load ---
  if ($clientDealerSpecific.val() === 'Yes') {
    $clientDealerName.prop('disabled', false);
    $clientDealerStar.addClass('active');
    fetchCompanyList(function () {
      checkCompanyExists();
    });
  } else {
    $clientDealerName.val('NA').prop('disabled', true);
    $clientDealerStar.removeClass('active');
    checkCompanyExists();
    fetchCompanyList();
  }

  // --- Form Submission ---
  $form.on('submit', function (e) {
    e.preventDefault();

    const dateVal = $date.val().trim();
    const projectNameVal = $projectName.val().trim();
    const categoryVal = $category.val();
    const priorityVal = $priority.val();
    const projectStatusVal = $projectStatus.val() || 'Assigned';
    const clientDealerSpecificVal = $clientDealerSpecific.val() || 'No';
    let clientDealerNameVal = $clientDealerName.val().trim();
    const productNameVal = $productName.val() ? $productName.val().trim() : '';
    const productSubCategoryVal = $productSubCategory.val().trim();

    if (!dateVal || !projectNameVal || !categoryVal || !priorityVal || !productNameVal) {
      alert('Please fill in all required fields marked with *');
      return;
    }

    if (clientDealerSpecificVal === 'Yes') {
      if (!clientDealerNameVal || clientDealerNameVal.toUpperCase() === 'NA') {
        alert('Please enter or select a Client Name / Dealer Name.');
        $clientDealerName.focus();
        return;
      }
    } else {
      clientDealerNameVal = 'NA';
    }

    const existingCount = getExistingDocsCount();
    if (existingCount + selectedDocuments.length > 2) {
      alert(`Maximum 2 documents allowed per project. Currently has ${existingCount} document(s).`);
      return;
    }

    $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Updating...');

    const addToCompanyListVal = ($addToCompanyList.is(':checked') && $addCompanyCheckboxContainer.hasClass('active')) ? 1 : 0;

    const formData = new FormData();
    formData.append('project_id', $projectId.val().trim());
    formData.append('id', $numericId.val().trim());
    formData.append('project_date', dateVal);
    formData.append('project_name', projectNameVal);
    formData.append('project_category', categoryVal);
    formData.append('project_priority', priorityVal);
    formData.append('project_status', projectStatusVal);
    formData.append('client_dealer_specific', clientDealerSpecificVal);
    formData.append('client_dealer_name', clientDealerNameVal);
    formData.append('product_name', productNameVal);
    formData.append('product_sub_category', productSubCategoryVal);
    formData.append('product_serial_no', $productSerialNo.val().trim());
    formData.append('lcd_id', $lcdId.val().trim());
    formData.append('hardware_id', $hardwareId.val().trim());
    formData.append('software_version', $softwareVersion.val().trim());
    formData.append('hasp_id', $haspId.val().trim());
    formData.append('project_description', $projectDescription.val().trim());
    formData.append('add_to_company_list', addToCompanyListVal);

    selectedDocuments.forEach(function (file) {
      formData.append('uploaded_documents[]', file);
    });

    $.ajax({
      url: '../api/update-project-details.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          window.location.href = 'project-lists.php';
        } else {
          alert('Error: ' + (response.message || 'Failed to update project.'));
          $submitBtn.prop('disabled', false).html(originalBtnHtml);
        }
      },
      error: function (xhr) {
        let msg = 'An unexpected error occurred while updating the project.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
        $submitBtn.prop('disabled', false).html(originalBtnHtml);
      }
    });
  });
});

