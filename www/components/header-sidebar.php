<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Get current page name from URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');

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
$profile_photo_url = 'images/default-avatar.png'; // default fallback or initials
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
if (!empty($user_id)) {
    foreach ($allowed_extensions as $ext) {
        $photo_path = __DIR__ . '/../images/profile/' . $user_id . '.' . $ext;
        if (file_exists($photo_path)) {
            $profile_photo_url = 'images/profile/' . $user_id . '.' . $ext . '?t=' . filemtime($photo_path);
            break;
        }
    }
}
?>

<!-- Header -->
<header class="header-container">
  <div class="header-left">
    <button class="hamburger-menu" id="hamburgerMenu" aria-label="Toggle menu">
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
      <span class="hamburger-line"></span>
    </button>
    <div class="logo-container">
      <img src="/images/logo.jpg" alt="logo">
    </div>
    <!-- <div class="xtend-logo masked-text">Xtend License Update</div> -->
  </div>

  <div class="header-right">
    <div class="header-user">
      <button class="user-menu-btn" id="userMenuBtn" aria-label="User menu">
        <div class="user-avatar" id="headerUserAvatar">
          <?php if ($profile_photo_url !== 'images/default-avatar.png'): ?>
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
        <a href="user-profile.php" class="dropdown-item">
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
    <div class="sidebar-section">
      <ul class="sidebar-menu">
        <li class="sidebar-menu-item <?php echo ($current_page == 'home' || $current_page == 'dashboard') ? 'active' : ''; ?>">
          <a href="dashboard.php" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M8 3.293l6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293l6-6zm5-.793V1.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293l-2-2z" />
            </svg> -->
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'new-record') ? 'active' : ''; ?>">
          <a href="#" onclick="showNewRecordConfirmModal(); return false;" class="sidebar-link">
            <i class="fa-solid fa-square-plus"></i>
            <span>Add New Record</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'upload-record') ? 'active' : ''; ?>">
          <a href="upload-record.php" class="sidebar-link">
            <i class="fa-solid fa-file-arrow-up"></i>
            <span>Upload Record</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'my-records') ? 'active' : ''; ?>">
          <a href="my-records.php" class="sidebar-link">
            <i class="fa-solid fa-file-lines"></i>
            <span>My Records</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'search') ? 'active' : ''; ?>">
          <a href="search.php" class="sidebar-link">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span>Search</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'user-profile') ? 'active' : ''; ?>">
          <a href="user-profile.php" class="sidebar-link">
            <i class="fa-solid fa-user-shield"></i>
            <span>Profile</span>
          </a>
        </li>

        <?php if ($user_role === 'admin'): ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'user-accounts') ? 'active' : ''; ?>">
          <a href="user-accounts.php" class="sidebar-link">
            <i class="fa-solid fa-users-line"></i>
            <span>Accounts</span>
          </a>
        </li>
        <?php endif; ?>
        <li class="sidebar-menu-item <?php echo ($current_page == 'logout') ? 'active' : ''; ?>">
          <a href="#" onclick="showLogoutModal(); return false;" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd"
                d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z" />
              <path fill-rule="evenodd"
                d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z" />
            </svg> -->
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>
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
        style="padding:10px 30px; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; background-color:#6c757d; color:white; transition:background-color 0.3s;"
        onmouseover="this.style.backgroundColor='#5a6268'"
        onmouseout="this.style.backgroundColor='#6c757d'">Cancel</button>
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
        <p class="confirm-description">Enter The Company Name and Click <span style="color: var(--accent-coral);">Proceed</span> To Continue</p>
        
        <!-- Company Name Selection Field -->
        <div class="confirm-form-group">
          <label for="modal_company_name">Company Name</label>
          <div class="form-control-wrapper autocomplete-wrapper">
            <i class="fa-regular fa-building input-icon"></i>
            <input type="text" id="modal_company_name" name="company_name" class="form-control" placeholder="Enter company / client name" autocomplete="off" required>
            <div class="autocomplete-suggestions" id="modalCompanySuggestions"></div>
          </div>
        </div>

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

<script src="plugins/jquery-3.7.1.min.js"></script>
<script>
  $(document).ready(function () {
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
  });

  // Logout modal functions
  function showLogoutModal() {
    $('#logoutModal').fadeIn(200);
  }

  function hideLogoutModal() {
    $('#logoutModal').fadeOut(200);
  }

  function confirmLogout() {
    window.location.href = 'logout.php?confirm=yes';
  }

  // Confirmation modal functions
  let modalClientList = null;
  let modalCurrentFocus = -1;

  function showNewRecordConfirmModal() {
    $('#newRecordConfirmModal').fadeIn(200);
    // Focus and clear input
    $('#modal_company_name').val('').focus();
    $('#modalCompanySuggestions').empty().hide();
    modalCurrentFocus = -1;

    // Load clients if not already loaded
    if (modalClientList === null) {
      $.ajax({
        url: 'api/get-clients.php',
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

  function initModalAutocomplete() {
    const $input = $('#modal_company_name');
    const $suggestions = $('#modalCompanySuggestions');

    $input.on('input', function() {
      const val = this.value.trim().toLowerCase();
      $suggestions.empty().hide();
      modalCurrentFocus = -1;

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
        setModalActive($items);
        e.preventDefault();
      } else if (e.keyCode === 38) { // Arrow Up
        modalCurrentFocus--;
        setModalActive($items);
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

    function setModalActive($items) {
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
      if (!$(e.target).closest('#modal_company_name').parent().length) {
        $suggestions.empty().hide();
        modalCurrentFocus = -1;
      }
    });
  }

  function proceedToNewRecord() {
    const companyName = $('#modal_company_name').val().trim();
    if (!companyName) {
      alert('Please enter or select a Company Name.');
      $('#modal_company_name').focus();
      return;
    }

    const $btn = $('#newRecordConfirmModal .btn-primary');
    const originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Creating...');

    $.ajax({
      url: 'api/create-pending-record.php',
      type: 'POST',
      data: { company_name: companyName },
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          window.open('new-record.php?id=' + encodeURIComponent(response.record_id), '_blank');
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