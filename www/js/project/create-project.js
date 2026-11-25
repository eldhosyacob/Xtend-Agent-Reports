$(document).ready(function () {
  const $form = $('#createProjectForm');
  const $submitBtn = $form.find('button[type="submit"]');
  const originalBtnHtml = $submitBtn.html();

  const $department = $('#project_department');
  const $assignedToWrapper = $('#assignedToWrapper');
  const $assignedToDisplay = $('#assignedToDisplay');
  const $assignedToText = $('#assignedToText');
  const $assignedToDropdown = $('#assignedToDropdown');
  const $assignedToOptionsList = $('#assignedToOptionsList');
  const $projectAssignee = $('#project_assignee');

  const $clientDealerSpecific = $('#client_dealer_specific');
  const $clientDealerName = $('#client_dealer_name');
  const $clientDealerStar = $('#clientDealerStar');
  const $suggestions = $('#clientDealerSuggestions');
  const $addCompanyCheckboxContainer = $('#addCompanyCheckboxContainer');
  const $addToCompanyList = $('#add_to_company_list');

  // Product Name & Product Sub Category inputs
  const $productName = $('#product_name');
  const $productSubCategory = $('#product_sub_category');

  // Additional Hardware Details inputs
  const $productSerialNo = $('#product_serial_no');
  const $lcdId = $('#lcd_id');
  const $hardwareId = $('#hardware_id');
  const $softwareVersion = $('#software_version');
  const $haspId = $('#hasp_id');

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

  // Load clients from company_list table via get-clients API
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

  // Check if client/dealer name exists in company_list
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

  // Handle Client/Dealer Specific dropdown changes
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

  // Render autocomplete suggestions
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

  // Keyboard navigation for suggestions
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

  // Close suggestions when clicking outside
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.autocomplete-wrapper').length) {
      $suggestions.empty().hide();
      currentFocus = -1;
    }
  });

  // --- Assigned To Multiselect Logic ---

  // Toggle Assigned To dropdown
  $assignedToDisplay.on('click', function (e) {
    if ($(this).hasClass('disabled')) return;
    $assignedToWrapper.toggleClass('open');
    $assignedToDropdown.toggleClass('show');
  });

  $assignedToDisplay.on('keydown', function (e) {
    if ($(this).hasClass('disabled')) return;
    if (e.keyCode === 13 || e.keyCode === 32) { // Enter or Space
      e.preventDefault();
      $assignedToWrapper.toggleClass('open');
      $assignedToDropdown.toggleClass('show');
    } else if (e.keyCode === 27) { // Escape
      $assignedToWrapper.removeClass('open');
      $assignedToDropdown.removeClass('show');
    }
  });

  // Close Assigned To dropdown when clicking outside
  $(document).on('click', function (e) {
    if (!$(e.target).closest('#assignedToWrapper').length) {
      $assignedToWrapper.removeClass('open');
      $assignedToDropdown.removeClass('show');
    }
  });

  // Handle department change to fetch and populate users
  function loadDepartmentUsers(dept) {
    $assignedToOptionsList.empty();
    $projectAssignee.val('');

    if (!dept) {
      $assignedToText.text('Select Department first').addClass('placeholder');
      $assignedToDisplay.addClass('disabled').removeAttr('title');
      $assignedToWrapper.removeClass('open');
      $assignedToDropdown.removeClass('show');
      return;
    }

    $assignedToText.text('Loading users...').addClass('placeholder');
    $assignedToDisplay.addClass('disabled').removeAttr('title');

    $.ajax({
      url: '../api/get-assignable-users.php',
      type: 'GET',
      data: { department: dept },
      dataType: 'json',
      success: function (response) {
        if (response.success && Array.isArray(response.users) && response.users.length > 0) {
          renderAssignedToOptions(response.users);
          $assignedToText.text('Select Assignee').addClass('placeholder');
          $assignedToDisplay.removeClass('disabled');
        } else {
          $assignedToText.text('No users found in this department').addClass('placeholder');
          $assignedToDisplay.addClass('disabled');
        }
      },
      error: function () {
        $assignedToText.text('Failed to load users').addClass('placeholder');
        $assignedToDisplay.addClass('disabled');
      }
    });
  }

  function renderAssignedToOptions(users) {
    $assignedToOptionsList.empty();

    // Option 1: 'All' option without checkbox
    const $allOption = $(`
      <div class="assigned-to-option assigned-to-all-option" id="assignedToAllOption">
        <span>All</span>
      </div>
    `);

    $allOption.on('click', function (e) {
      e.preventDefault();
      const $checkboxes = $assignedToOptionsList.find('.assigned-to-checkbox');
      const allChecked = $checkboxes.length > 0 && $checkboxes.filter(':checked').length === $checkboxes.length;

      if (allChecked) {
        // Deselect all
        $checkboxes.prop('checked', false);
        $(this).removeClass('active');
        updateAssignedToState();
      } else {
        // Select all
        $checkboxes.prop('checked', true);
        $(this).addClass('active');
        updateAssignedToState();
      }
    });

    $assignedToOptionsList.append($allOption);

    // User options each with a checkbox
    users.forEach(function (user) {
      const $userOption = $(`
        <label class="assigned-to-option assigned-to-user-option">
          <input type="checkbox" class="assigned-to-checkbox" value="${escapeHtml(user)}">
          <span class="assigned-to-user-name">${escapeHtml(user)}</span>
        </label>
      `);

      $userOption.find('.assigned-to-checkbox').on('change', function () {
        updateAssignedToState();
      });

      $assignedToOptionsList.append($userOption);
    });
  }

  function updateAssignedToState() {
    const $checkboxes = $assignedToOptionsList.find('.assigned-to-checkbox');
    const $checked = $checkboxes.filter(':checked');
    const $allOption = $('#assignedToAllOption');

    if ($checked.length === 0) {
      $assignedToText.text('Select Assignee').addClass('placeholder');
      $assignedToDisplay.removeAttr('title');
      $projectAssignee.val('');
      $allOption.removeClass('active');
    } else if ($checked.length === $checkboxes.length) {
      $assignedToText.text('All').removeClass('placeholder');
      $assignedToDisplay.attr('title', 'All');
      $projectAssignee.val('All');
      $allOption.addClass('active');
    } else {
      const selectedNames = [];
      $checked.each(function () {
        selectedNames.push($(this).val());
      });
      const namesStr = selectedNames.join(', ');
      $assignedToText.text(namesStr).removeClass('placeholder');
      $assignedToDisplay.attr('title', namesStr);
      $projectAssignee.val(namesStr);
      $allOption.removeClass('active');
    }
  }

  function loadDepartmentProducts(dept) {
    $.ajax({
      url: '../api/get-project-products.php',
      type: 'GET',
      data: { department: dept || '' },
      dataType: 'json',
      success: function (response) {
        if (response.success && Array.isArray(response.data)) {
          const currentVal = $productName.val();
          $productName.find('option:not(:first)').remove();
          response.data.forEach(function (prod) {
            $productName.append($('<option>', {
              value: prod,
              text: prod
            }));
          });
          if (currentVal && response.data.some(function (p) { return p.toLowerCase() === currentVal.toLowerCase(); })) {
            $productName.val(currentVal);
          } else {
            $productName.val('');
          }
        }
      },
      error: function () {
        console.warn('Failed to load products for department:', dept);
      }
    });
  }

  $department.on('change', function () {
    const dept = $(this).val();
    loadDepartmentUsers(dept);
    loadDepartmentProducts(dept);
  });

  // Upload Documents logic (Maximum 2 files)
  let selectedDocuments = [];
  const $docInput = $('#uploaded_documents');
  const $docPreview = $('#fileListPreview');
  const $dropWrapper = $('.file-upload-wrapper');
  const allowedDocExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'];

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
    const maxTotalSize = 20 * 1024 * 1024; // 20MB total
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
        if (selectedDocuments.length >= 2) {
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
      alert('Maximum 2 documents can be uploaded.');
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

  $(window).on('dragover drop', function (e) {
    e.preventDefault();
  });

  // Handle Form Reset
  $form.on('reset', function () {
    setTimeout(function () {
      $clientDealerSpecific.val('No');
      handleSpecificChange();
      checkCompanyExists();
      $productName.val('');
      $productSubCategory.val('');
      $productSerialNo.val('');
      $lcdId.val('');
      $hardwareId.val('');
      $softwareVersion.val('');
      $haspId.val('');
      selectedDocuments = [];
      renderDocumentPreview();
    }, 0);
  });

  // Initial state check
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

  // Handle Form Submission
  $form.on('submit', function (e) {
    e.preventDefault();

    // Basic form validation check
    const date = $('#project_date').val().trim();
    const projectName = $('#project_name').val().trim();
    const category = $('#project_category').val();
    const priority = $('#project_priority').val();
    const projectStatus = $('#project_status').val() || 'Assigned';
    const clientDealerSpecific = $clientDealerSpecific.val() || 'No';
    let clientDealerName = $clientDealerName.val().trim();
    const productName = $productName.val() ? $productName.val().trim() : '';
    const productSubCategory = $productSubCategory.val().trim();

    if (!date || !projectName || !category || !priority || !productName) {
      alert('Please fill in all required fields marked with *');
      return;
    }

    if (clientDealerSpecific === 'Yes') {
      if (!clientDealerName || clientDealerName.toUpperCase() === 'NA') {
        alert('Please enter or select a Client Name / Dealer Name.');
        $clientDealerName.focus();
        return;
      }
    } else {
      clientDealerName = 'NA';
    }

    if (selectedDocuments.length > 2) {
      alert('Maximum 2 documents can be uploaded.');
      return;
    }

    // Set loading state
    $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Creating...');

    const addToCompanyList = ($addToCompanyList.is(':checked') && $addCompanyCheckboxContainer.hasClass('active')) ? 1 : 0;

    const formData = new FormData();
    formData.append('project_date', date);
    formData.append('project_name', projectName);
    formData.append('project_category', category);
    formData.append('project_priority', priority);
    formData.append('project_status', projectStatus);
    formData.append('client_dealer_specific', clientDealerSpecific);
    formData.append('client_dealer_name', clientDealerName);
    formData.append('product_name', productName);
    formData.append('product_sub_category', productSubCategory);
    formData.append('product_serial_no', $productSerialNo.val().trim());
    formData.append('lcd_id', $lcdId.val().trim());
    formData.append('hardware_id', $hardwareId.val().trim());
    formData.append('software_version', $softwareVersion.val().trim());
    formData.append('hasp_id', $haspId.val().trim());
    formData.append('project_description', $('#project_description').val().trim());
    formData.append('add_to_company_list', addToCompanyList);

    selectedDocuments.forEach(function (file) {
      formData.append('uploaded_documents[]', file);
    });

    $.ajax({
      url: '../api/create-new-project.php',
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          const targetPid = response.project_id || '';
          window.location.href = 'create-sub-project.php' + (targetPid ? '?project_id=' + encodeURIComponent(targetPid) : '');
        } else {
          alert('Error: ' + (response.message || 'Failed to create project.'));
          $submitBtn.prop('disabled', false).html(originalBtnHtml);
        }
      },
      error: function (xhr, status, error) {
        let msg = 'An unexpected error occurred while creating the project.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          msg = xhr.responseJSON.message;
        }
        alert(msg);
        $submitBtn.prop('disabled', false).html(originalBtnHtml);
      }
    });
  });
});
