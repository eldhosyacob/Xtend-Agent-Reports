<?php
// Ensure session is initialized with application session name
require_once __DIR__ . '/../config/session.php';

// Get current page name from URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$in_project_folder = (strpos($script_name, '/project/') !== false || $current_page === 'create-project');
$rel_path = $in_project_folder ? '../' : './';

// Store or preserve active module state in session
if ($in_project_folder) {
    $_SESSION['active_module'] = 'project_management';
} elseif (in_array($current_page, ['dashboard', 'home', 'new-record', 'add-miscellaneous', 'upload-record', 'my-records', 'today-records', 'view-record', 'edit-record', 'view-pending-records'], true)) {
    $_SESSION['active_module'] = 'report_management';
}

$active_module = isset($_SESSION['active_module']) ? $_SESSION['active_module'] : 'report_management';
$is_project_module = ($active_module === 'project_management');

// Define page titles
$page_titles = [
  'home' => 'Home',
  'dashboard' => 'Home',
  'add_new_record' => 'Add New Record',
  'new_record' => 'Add New Record',
  'upload_record' => 'Upload Record',
  'search' => 'Search',
  'administration' => 'Administration'
];

// Get the page title, default to the current page name with first letter capitalized
$page_title = isset($page_titles[$current_page]) ? $page_titles[$current_page] : ucfirst($current_page);

// Get user information from session
$user_full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin';
$user_id = isset($_SESSION['id']) ? $_SESSION['id'] : '';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';

// Resolve user profile photo path
$profile_photo_url = $rel_path . 'images/default-avatar.png'; // default fallback or initials
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
if (!empty($user_id)) {
    foreach ($allowed_extensions as $ext) {
        $photo_path = __DIR__ . '/../images/profile/' . $user_id . '.' . $ext;
        if (file_exists($photo_path)) {
            $profile_photo_url = $rel_path . 'images/profile/' . $user_id . '.' . $ext . '?t=' . filemtime($photo_path);
            break;
        }
    }
}
?>
<script src="<?php echo $rel_path; ?>js/theme.js"></script>

<!-- Header -->
<header class="header-container">
  <div class="header-left">
    <button class="hamburger-menu" id="hamburgerMenu" aria-label="Toggle menu">
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
    </button>
    <div class="logo-container">
      <img src="<?php echo $rel_path; ?>images/logo.png" alt="logo">
    </div>
    <!-- <div class="xtend-logo masked-text">Xtend License Update</div> -->
  </div>

  <div class="header-right">
    <button class="theme-toggle-btn" id="themeToggleBtn" aria-label="Toggle dark mode" title="Toggle Theme">
      <i class="fa-solid fa-moon theme-icon-moon"></i>
      <i class="fa-solid fa-sun theme-icon-sun"></i>
    </button>
    <div class="header-datetime" id="headerDateTime">
      <div class="datetime-icon-wrapper">
        <i class="fa-regular fa-clock"></i>
      </div>
      <div class="datetime-content">
        <span class="datetime-date" id="headerDateText"></span>
        <span class="datetime-divider"></span>
        <span class="datetime-time" id="headerTimeText"></span>
      </div>
    </div>
    <div class="header-user">
      <?php 
        $is_user_menu_disabled = ($current_page === 'new-record' || $current_page === 'edit-record');
      ?>
      <button class="user-menu-btn" id="userMenuBtn" aria-label="User menu" <?php echo $is_user_menu_disabled ? 'disabled' : ''; ?>>
        <div class="user-avatar" id="headerUserAvatar">
          <?php if ($profile_photo_url !== ($rel_path . 'images/default-avatar.png')): ?>
            <img src="<?php echo $profile_photo_url; ?>" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block;">
          <?php else: ?>
            <?php 
              $initials = '';
              if (!empty($user_full_name)) {
                $words = explode(' ', $user_full_name);
                foreach ($words as $word) {
                  $initials .= strtoupper(substr($word, 0, 1));
                }
                $initials = substr($initials, 0, 2);
              }
              echo htmlspecialchars($initials ?: '?');
            ?>
          <?php endif; ?>
        </div>
        <span class="user-name"><?php echo htmlspecialchars($user_full_name); ?></span>
        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
      </button>
      <div class="user-dropdown" id="userDropdown">
        <a href="<?php echo $rel_path; ?>user-profile.php" class="dropdown-item">
          <i class="fa-solid fa-user"></i> My Profile
        </a>
        <div class="dropdown-divider"></div>
        <a href="#" onclick="showLogoutModal(); return false;" class="dropdown-item">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
      </div>
    </div>
  </div>
</header>

<!-- Sidebar -->
<aside class="sidebar-container" id="sidebar">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <nav class="sidebar-nav">
    <div class="sidebar-module-container">
      <div class="sidebar-module-wrapper">
        <i class="fa-solid fa-layer-group sidebar-module-icon"></i>
        <select id="sidebarModuleSelect" class="sidebar-module-select" aria-label="Select System Module">
          <option value="report_management" <?php echo ($active_module === 'report_management') ? 'selected' : ''; ?>>Report Management</option>
          <option value="project_management" <?php echo ($active_module === 'project_management') ? 'selected' : ''; ?>>Project Management</option>
        </select>
        <i class="fa-solid fa-chevron-down sidebar-module-arrow"></i>
      </div>
    </div>
    <div class="sidebar-section">
      <ul class="sidebar-menu">
        <?php if ($active_module === 'project_management'): ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'create-project') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>project/create-project.php" class="sidebar-link">
            <i class="fa-solid fa-folder-plus"></i>
            <span style="font-weight:700;">Create Project</span>
          </a>
        </li>
        <!-- <li class="sidebar-menu-item <?php echo ($current_page == 'search') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>search.php" class="sidebar-link">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span style="font-weight:700;">Search</span>
          </a>
        </li> -->
        <li class="sidebar-menu-item <?php echo ($current_page == 'user-profile') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>user-profile.php" class="sidebar-link">
            <i class="fa-solid fa-user-shield"></i>
            <span style="font-weight:700;">Profile</span>
          </a>
        </li>
        <?php if ($user_role === 'admin'): ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'user-accounts') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>user-accounts.php" class="sidebar-link">
            <i class="fa-solid fa-users-line"></i>
            <span style="font-weight:700;">Accounts</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'logout') ? 'active' : ''; ?>">
          <a href="#" onclick="showLogoutModal(); return false;" class="sidebar-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span style="font-weight:700;">Logout</span>
          </a>
        </li>
        <?php else: ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'home' || $current_page == 'dashboard') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>dashboard.php" class="sidebar-link">
            <i class="fa-solid fa-house"></i>
            <span style="font-weight:700;">Dashboard</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'new-record') ? 'active' : ''; ?>">
          <a href="#" onclick="showNewRecordConfirmModal(); return false;" class="sidebar-link">
            <i class="fa-solid fa-square-plus"></i>
            <span style="font-weight:700;">Add New Support</span>
          </a>
        </li>

        <li class="sidebar-menu-item <?php echo ($current_page == 'add-miscellaneous') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>add-miscellaneous.php" class="sidebar-link">
            <i class="fa-solid fa-file-zipper"></i>
            <span style="font-weight:700;">Add Miscellaneous</span>
          </a>
        </li>
        <?php if (strcasecmp($user_department, 'Manager') === 0): ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'upload-record') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>upload-record.php" class="sidebar-link">
            <i class="fa-solid fa-file-arrow-up"></i>
            <span style="font-weight:700;">Upload Support Details</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'my-records') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>my-records.php" class="sidebar-link">
            <i class="fa-solid fa-file-lines"></i>
            <span style="font-weight:700;">My Records</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'search') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>search.php" class="sidebar-link">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span style="font-weight:700;">Search</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'user-profile') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>user-profile.php" class="sidebar-link">
            <i class="fa-solid fa-user-shield"></i>
            <span style="font-weight:700;">Profile</span>
          </a>
        </li>

        <?php if ($user_role === 'admin'): ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'user-accounts') ? 'active' : ''; ?>">
          <a href="<?php echo $rel_path; ?>user-accounts.php" class="sidebar-link">
            <i class="fa-solid fa-users-line"></i>
            <span style="font-weight:700;">Accounts</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'logout') ? 'active' : ''; ?>">
          <a href="#" onclick="showLogoutModal(); return false;" class="sidebar-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span style="font-weight:700;">Logout</span>
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
  </nav>
  <div class="sidebar-footer-logo">
    <img src="<?php echo $rel_path; ?>images/xtend-logo.png" alt="Xtend Logo">
  </div>
</aside>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Logout Modal -->
<div id="logoutModal"
  style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
  <div
    style="background-color:#fff; margin:15% auto; padding:30px; border-radius:12px; width:400px; max-width:90%; text-align:center; box-shadow:0 4px 8px rgba(0,0,0,0.2);">
    <h2 style="margin-bottom:20px; color:#333; font-size:24px;">Confirm Logout</h2>
    <p style="margin-bottom:30px; color:#666; font-size:16px;">Are you sure you want to logout?</p>
    <div style="display:flex; gap:10px; justify-content:center;">
      <button onclick="hideLogoutModal()"
        style="padding:10px 30px; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; background-color:#25b0a3; color:white; transition:background-color 0.3s;"
        onmouseover="this.style.backgroundColor='#009688'"
        onmouseout="this.style.backgroundColor='#25b0a3'">Cancel</button>
      <button onclick="confirmLogout()"
        style="padding:10px 30px; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; background-color:#dc3545; color:white; transition:background-color 0.3s;"
        onmouseover="this.style.backgroundColor='#c82333'"
        onmouseout="this.style.backgroundColor='#dc3545'">Logout</button>
    </div>
  </div>
</div>

<!-- Add New Record Confirmation Modal -->
<div id="newRecordConfirmModal" class="confirm-modal-overlay" style="display: none;">
  <div class="confirm-modal-wrapper">
    <div class="confirm-modal-card split-design">
      
      <!-- Gradient Header Section -->
      <div class="confirm-modal-header-section">
        <button type="button" class="confirm-modal-close-btn light-version" onclick="hideNewRecordConfirmModal()">
          <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="confirm-icon-badge">
          <i class="fa-solid fa-file-circle-plus"></i>
        </div>
      </div>
      
      <!-- Body Content Section -->
      <div class="confirm-modal-body-section">
        <h2 class="confirm-title">Create New Record</h2>
        <p class="confirm-description">Enter Company Name or Dealer Name and Click <span style="color: red;">Proceed</span> To Continue</p>
        
        <!-- Company Name Selection Field -->
        <div class="confirm-form-group">
          <label for="modal_company_name">Company Name</label>
          <div class="form-control-wrapper autocomplete-wrapper">
            <i class="fa-regular fa-building input-icon"></i>
            <input type="text" id="modal_company_name" name="company_name" class="form-control" placeholder="Enter company / client name" autocomplete="off">
            <div class="autocomplete-suggestions" id="modalCompanySuggestions"></div>
          </div>
        </div>

        <!-- Dealer Name Selection Field -->
        <div class="confirm-form-group" style="margin-top: 15px;">
          <label for="modal_dealer_name">Dealer Name</label>
          <div class="form-control-wrapper autocomplete-wrapper">
            <i class="fa-solid fa-user-tie input-icon"></i>
            <input type="text" id="modal_dealer_name" name="dealer_name" class="form-control" placeholder="Enter dealer name" autocomplete="off">
            <div class="autocomplete-suggestions" id="modalDealerSuggestions"></div>
          </div>
          <div id="addCompanyCheckboxContainer" class="add-company-checkbox-wrapper">
            <input type="checkbox" id="add_to_company_list" name="add_to_company_list">
            <label for="add_to_company_list">Add to company list</label>
          </div>
        </div>

        <?php if (strcasecmp($user_department, 'Manager') === 0): ?>
        <!-- Department Selection Field for Manager -->
        <div class="confirm-form-group" style="margin-top: 15px;">
          <label for="modal_department">Department</label>
          <div class="form-control-wrapper">
            <i class="fa-solid fa-sitemap input-icon"></i>
            <select id="modal_department" name="department" class="form-control" style="padding-left: 42px;" required>
              <option value="" disabled selected>Select Department</option>
              <option value="Voice Logger">Voice Logger</option>
              <option value="IVR">IVR</option>
            </select>
          </div>
        </div>
        <?php endif; ?>

        <div class="confirm-actions">
          <button type="button" class="btn btn-secondary" onclick="hideNewRecordConfirmModal()">
            Cancel
          </button>
          <button type="button" class="btn btn-primary" onclick="proceedToNewRecord()">
            Proceed
          </button>
        </div>
      </div>
      
    </div>
  </div>
</div>

<!-- Reopen Record Confirmation Modal -->
<div id="reopenConfirmModal" class="confirm-modal-overlay" style="display: none;">
  <div class="confirm-modal-wrapper">
    <div class="confirm-modal-card split-design">
      
      <!-- Gradient Header Section -->
      <div class="confirm-modal-header-section" style="background: linear-gradient(135deg, #18ad9f 0%, #18678e 100%);">
        <button type="button" class="confirm-modal-close-btn light-version" onclick="hideReopenConfirmModal()">
          <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="confirm-icon-badge">
          <i class="fa-solid fa-folder-open"></i>
        </div>
      </div>
      
      <!-- Body Content Section -->
      <div class="confirm-modal-body-section" style="padding: 40px 30px;">
        <h2 class="confirm-title" style="margin-bottom: 12px;">Reopen Record</h2>
        <p class="confirm-description" style="margin-bottom: 24px; font-size: 14px;">Are you sure you want to reopen the record for <strong id="reopen_company_name" style="color: #18678e;"></strong>?</p>
        
        <div class="confirm-actions">
          <button type="button" class="btn btn-secondary" onclick="hideReopenConfirmModal()">
            Cancel
          </button>
          <button type="button" class="btn btn-primary" id="btnReopenConfirmProceed">
            Reopen
          </button>
        </div>
      </div>
      
    </div>
  </div>
</div>

<script src="<?php echo $rel_path; ?>plugins/jquery-3.7.1.min.js"></script>
<script>
  $(document).ready(function () {
    // Live date and time update
    function updateHeaderDateTime() {
      const now = new Date();
      const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      
      const dayName = days[now.getDay()];
      const monthName = months[now.getMonth()];
      const dateNum = now.getDate();
      const year = now.getFullYear();
      
      let hours = now.getHours();
      const minutes = String(now.getMinutes()).padStart(2, '0');
      const seconds = String(now.getSeconds()).padStart(2, '0');
      const ampm = hours >= 12 ? 'PM' : 'AM';
      
      hours = hours % 12;
      hours = hours ? hours : 12;
      const formattedHours = String(hours).padStart(2, '0');
      
      const timeString = `${formattedHours}:${minutes}:${seconds} ${ampm}`;
      const dateString = `${dayName}, ${monthName} ${dateNum}, ${year}`;
      
      $('#headerDateText').text(dateString);
      $('#headerTimeText').text(timeString);
    }
    
    updateHeaderDateTime();
    setInterval(updateHeaderDateTime, 1000);

    // Hamburger menu toggle
    $('#hamburgerMenu').on('click', function () {
      $('#sidebar').toggleClass('active');
      $('#sidebarOverlay').toggleClass('active');
      $(this).toggleClass('active');
    });

    // Close sidebar when clicking overlay
    $('#sidebarOverlay').on('click', function () {
      $('#sidebar').removeClass('active');
      $(this).removeClass('active');
      $('#hamburgerMenu').removeClass('active');
    });

    // User dropdown toggle
    $('#userMenuBtn').on('click', function (e) {
      if ($(this).prop('disabled')) return;
      e.stopPropagation();
      $('#userDropdown').toggleClass('active');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function (e) {
      if (!$(e.target).closest('.header-user').length) {
        $('#userDropdown').removeClass('active');
      }
    });

    // Close confirmation modal when clicking on overlay backdrop
    $('#newRecordConfirmModal').on('click', function(e) {
      if (e.target === this || $(e.target).hasClass('confirm-modal-wrapper')) {
        hideNewRecordConfirmModal();
      }
    });

    // Close reopen confirmation modal on overlay click
    $('#reopenConfirmModal').on('click', function(e) {
      if (e.target === this || $(e.target).hasClass('confirm-modal-wrapper')) {
        hideReopenConfirmModal();
      }
    });

    // Reopen proceed button handler
    $('#btnReopenConfirmProceed').on('click', function() {
      if (reopenRecordId) {
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Opening...');

        $.ajax({
          url: '<?php echo $rel_path; ?>api/create-reopened-record.php',
          type: 'POST',
          data: {
            record_id: reopenRecordId,
            department: reopenDepartment || ''
          },
          dataType: 'json',
          success: function(response) {
            if (response.success && response.record_id) {
              let targetUrl = '<?php echo $rel_path; ?>edit-record.php?record_id=' + encodeURIComponent(response.record_id);
              if (reopenDepartment) {
                targetUrl += '&department=' + encodeURIComponent(reopenDepartment);
              }
              window.open(targetUrl, '_blank');
              hideReopenConfirmModal();
            } else {
              alert('Error: ' + (response.message || 'Failed to create reopened record.'));
            }
          },
          error: function() {
            alert('An error occurred while reopening the record.');
          },
          complete: function() {
            $btn.prop('disabled', false).html(originalHtml);
          }
        });
      }
    });

    // Delegated click handler for reopen action buttons
    $(document).on('click', '.btn-action-reopen', function(e) {
      e.preventDefault();
      const recordId = $(this).attr('data-record-id');
      const department = $(this).attr('data-department') || '';
      const companyName = ($(this).attr('data-company-name') || '').trim();
      const dealerName = ($(this).attr('data-dealer-name') || '').trim();
      const displayName = companyName !== '' ? companyName : (dealerName !== '' ? dealerName : '');
      showReopenConfirmModal(recordId, displayName, department);
    });

    // Search functionality
    $('.search-btn').on('click', function () {
      var searchQuery = $('.search-input').val();
      if (searchQuery) {
        console.log('Searching for:', searchQuery);
        // Add your search logic here
      }
    });

    $('.search-input').on('keypress', function (e) {
      if (e.which === 13) { // Enter key
        $('.search-btn').click();
      }
    });

    // Notification button
    $('.notification-btn').on('click', function () {
      console.log('Show notifications');
      // Add notification panel logic here
    });

    // Sidebar module selector navigation
    $('#sidebarModuleSelect').on('change', function () {
      const selectedModule = $(this).val();
      const relPath = '<?php echo $rel_path; ?>';

      localStorage.setItem('active_module', selectedModule);

      $.ajax({
        url: relPath + 'api/set-active-module.php',
        type: 'POST',
        data: { module: selectedModule },
        complete: function() {
          if (selectedModule === 'project_management') {
            window.location.href = relPath + 'project/create-project.php';
          } else {
            window.location.href = relPath + 'dashboard.php';
          }
        }
      });
    });
  });

  // Logout modal functions
  function showLogoutModal() {
    $('#logoutModal').fadeIn(200);
  }

  function hideLogoutModal() {
    $('#logoutModal').fadeOut(200);
  }

  function confirmLogout() {
    window.location.href = '<?php echo $rel_path; ?>logout.php?confirm=yes';
  }

  // Confirmation modal functions
  let modalClientList = null;
  let modalCurrentFocus = -1;

  function showNewRecordConfirmModal() {
    $('#newRecordConfirmModal').fadeIn(200);
    // Focus and clear input
    $('#modal_company_name').val('').focus();
    $('#modal_dealer_name').val('');
    $('#modalCompanySuggestions').empty().hide();
    $('#modalDealerSuggestions').empty().hide();
    $('#addCompanyCheckboxContainer').hide();
    $('#add_to_company_list').prop('checked', false);
    if ($('#modal_department').length) {
      $('#modal_department').val('');
    }
    modalCurrentFocus = -1;

    // Load clients if not already loaded
    if (modalClientList === null) {
      $.ajax({
        url: '<?php echo $rel_path; ?>api/get-clients.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            modalClientList = response.data;
            initModalAutocomplete();
          }
        }
      });
    }
  }

  function hideNewRecordConfirmModal() {
    $('#newRecordConfirmModal').fadeOut(200);
  }

  // Reopen confirmation functions
  let reopenRecordId = null;
  let reopenDepartment = null;

  function showReopenConfirmModal(recordId, companyName, department) {
    reopenRecordId = recordId;
    reopenDepartment = department || '';
    $('#reopen_company_name').text(companyName || 'Unknown');
    $('#reopenConfirmModal').fadeIn(200);
  }

  function hideReopenConfirmModal() {
    $('#reopenConfirmModal').fadeOut(200);
    reopenRecordId = null;
    reopenDepartment = null;
  }

  function checkCompanyExists() {
    const compVal = $('#modal_company_name').val().trim();
    const dealerVal = $('#modal_dealer_name').val().trim();

    if (!modalClientList) {
      $('#addCompanyCheckboxContainer').hide();
      $('#add_to_company_list').prop('checked', false);
      return;
    }

    const compExists = !compVal || modalClientList.some(client => client.toLowerCase() === compVal.toLowerCase());
    const dealerExists = !dealerVal || modalClientList.some(client => client.toLowerCase() === dealerVal.toLowerCase());

    if ((compVal && !compExists) || (dealerVal && !dealerExists)) {
      $('#addCompanyCheckboxContainer').css('display', 'flex');
    } else {
      $('#addCompanyCheckboxContainer').hide();
      $('#add_to_company_list').prop('checked', false);
    }
  }

  function initModalAutocomplete() {
    const fields = [
      { $input: $('#modal_company_name'), $suggestions: $('#modalCompanySuggestions') },
      { $input: $('#modal_dealer_name'), $suggestions: $('#modalDealerSuggestions') }
    ];

    fields.forEach(f => {
      const $input = f.$input;
      const $suggestions = f.$suggestions;

      $input.on('input', function() {
        const val = this.value.trim().toLowerCase();
        $suggestions.empty().hide();
        modalCurrentFocus = -1;

        checkCompanyExists();

        if (!val || !modalClientList) return;

        // Filter matches (case-insensitive, max 10 results)
        const matches = modalClientList.filter(client => client.toLowerCase().includes(val)).slice(0, 10);

        if (matches.length > 0) {
          matches.forEach((match, index) => {
            const $suggestion = $('<div class="autocomplete-suggestion"></div>')
              .text(match)
              .attr('data-index', index)
              .on('click', function() {
                $input.val(match);
                $suggestions.empty().hide();
                checkCompanyExists();
              });
            $suggestions.append($suggestion);
          });
          $suggestions.show();
        }
      });

      // Keyboard navigation helper
      $input.on('keydown', function(e) {
        const $items = $suggestions.find('.autocomplete-suggestion');
        if (!$items.length) return;

        if (e.keyCode === 40) { // Arrow Down
          modalCurrentFocus++;
          setModalActive($items, $suggestions);
          e.preventDefault();
        } else if (e.keyCode === 38) { // Arrow Up
          modalCurrentFocus--;
          setModalActive($items, $suggestions);
          e.preventDefault();
        } else if (e.keyCode === 13) { // Enter
          if (modalCurrentFocus > -1) {
            if ($items.eq(modalCurrentFocus).length) {
              $items.eq(modalCurrentFocus).trigger('click');
              e.preventDefault();
            }
          }
        } else if (e.keyCode === 27) { // Escape
          $suggestions.empty().hide();
          modalCurrentFocus = -1;
        }
      });
    });

    function setModalActive($items, $suggestions) {
      $items.removeClass('active');
      if (modalCurrentFocus >= $items.length) modalCurrentFocus = 0;
      if (modalCurrentFocus < 0) modalCurrentFocus = $items.length - 1;
      const $activeItem = $items.eq(modalCurrentFocus);
      $activeItem.addClass('active');
      
      // Scroll suggestion container into view if needed
      const containerHeight = $suggestions.height();
      const itemHeight = $activeItem.outerHeight();
      const itemTop = $activeItem.position().top;

      if (itemTop + itemHeight > containerHeight) {
        $suggestions.scrollTop($suggestions.scrollTop() + itemTop + itemHeight - containerHeight);
      } else if (itemTop < 0) {
        $suggestions.scrollTop($suggestions.scrollTop() + itemTop);
      }
    }

    // Dismiss suggestions when clicking outside
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.autocomplete-wrapper').length) {
        $('#modalCompanySuggestions, #modalDealerSuggestions').empty().hide();
        modalCurrentFocus = -1;
      }
    });
  }

  function proceedToNewRecord() {
    const companyName = $('#modal_company_name').val().trim();
    const dealerName = $('#modal_dealer_name').val().trim();
    if (!companyName && !dealerName) {
      alert('Please enter either a Company Name or a Dealer Name (or both).');
      $('#modal_company_name').focus();
      return;
    }

    let department = '';
    if ($('#modal_department').length) {
      department = $('#modal_department').val();
      if (!department) {
        alert('Please select a Department.');
        $('#modal_department').focus();
        return;
      }
    }

    const $btn = $('#newRecordConfirmModal .btn-primary');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Checking...');

    const addToCompanyList = $('#add_to_company_list').is(':checked') ? 1 : 0;

    const queryData = { company_name: companyName, dealer_name: dealerName };
    if (department) {
      queryData.department = department;
    }

    $.ajax({
      url: '<?php echo $rel_path; ?>api/fetch-pending-records.php',
      type: 'GET',
      data: queryData,
      dataType: 'json',
      success: function(response) {
        if (response.success && response.count > 0) {
          let redirectUrl = '<?php echo $rel_path; ?>view-pending-records.php?company_name=' + encodeURIComponent(companyName) + '&dealer_name=' + encodeURIComponent(dealerName) + '&add_to_company_list=' + addToCompanyList;
          if (department) {
            redirectUrl += '&department=' + encodeURIComponent(department);
          }
          window.location.href = redirectUrl;
        } else {
          createPendingRecordDirectly(companyName, dealerName, addToCompanyList, $btn, originalHtml, department);
        }
      },
      error: function() {
        createPendingRecordDirectly(companyName, dealerName, addToCompanyList, $btn, originalHtml, department);
      }
    });
  }

  function createPendingRecordDirectly(companyName, dealerName, addToCompanyList, $btn, originalHtml, department) {
    $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Creating...');
    const postData = { 
      company_name: companyName,
      dealer_name: dealerName,
      add_to_company_list: addToCompanyList
    };
    if (department) {
      postData.department = department;
    }
    $.ajax({
      url: '<?php echo $rel_path; ?>api/create-pending-record.php',
      type: 'POST',
      data: postData,
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          if (addToCompanyList) {
            modalClientList = null;
          }
          window.open('<?php echo $rel_path; ?>new-record.php?id=' + encodeURIComponent(response.record_id), '_blank');
          hideNewRecordConfirmModal();
        } else {
          alert('Error: ' + response.message);
        }
      },
      error: function() {
        alert('An error occurred while creating the record.');
      },
      complete: function() {
        $btn.prop('disabled', false).html(originalHtml);
      }
    });
  }
</script>