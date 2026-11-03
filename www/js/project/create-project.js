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

  // Handle Form Reset
  $form.on('reset', function () {
    setTimeout(function () {
      $clientDealerSpecific.val('No');
      handleSpecificChange();
      checkCompanyExists();
      $department.val('');
      loadDepartmentUsers('');
      loadDepartmentProducts('');
      $productName.val('');
      $productSubCategory.val('');
      $productSerialNo.val('');
      $lcdId.val('');
      $hardwareId.val('');
      $softwareVersion.val('');
      $haspId.val('');
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

  if ($department.val()) {
    loadDepartmentUsers($department.val());
  } else {
    $assignedToText.text('Select Department first').addClass('placeholder');
    $assignedToDisplay.addClass('disabled');
  }

  // Handle Form Submission
  $form.on('submit', function (e) {
    e.preventDefault();

    // Basic form validation check
    const date = $('#project_date').val().trim();
    const projectName = $('#project_name').val().trim();
    const category = $('#project_category').val();
    const priority = $('#project_priority').val();
    const department = $department.val();
    const assignee = $projectAssignee.val().trim();
    const assignedBy = $('#project_assigned_by').val();
    const projectStatus = $('#project_status').val() || 'Assigned';
    const clientDealerSpecific = $clientDealerSpecific.val() || 'No';
    let clientDealerName = $clientDealerName.val().trim();
    const productName = $productName.val() ? $productName.val().trim() : '';
    const productSubCategory = $productSubCategory.val().trim();

    if (!date || !projectName || !category || !priority || !department || !assignee || !assignedBy || !projectStatus || !productName) {
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

    // Set loading state
    $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Creating...');

    const addToCompanyList = ($addToCompanyList.is(':checked') && $addCompanyCheckboxContainer.hasClass('active')) ? 1 : 0;

    const formData = {
      project_date: date,
      project_name: projectName,
      project_category: category,
      project_priority: priority,
      project_department: department,
      project_assignee: assignee,
      project_assigned_by: assignedBy,
      project_status: projectStatus,
      client_dealer_specific: clientDealerSpecific,
      client_dealer_name: clientDealerName,
      product_name: productName,
      product_sub_category: productSubCategory,
      product_serial_no: $productSerialNo.val().trim(),
      lcd_id: $lcdId.val().trim(),
      hardware_id: $hardwareId.val().trim(),
      software_version: $softwareVersion.val().trim(),
      hasp_id: $haspId.val().trim(),
      project_description: $('#project_description').val().trim(),
      add_to_company_list: addToCompanyList
    };

    $.ajax({
      url: '../api/create-new-project.php',
      type: 'POST',
      data: formData,
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          window.location.href = 'project-lists.php';
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
