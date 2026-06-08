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
</header>

<!-- Sidebar -->
<aside class="sidebar-container" id="sidebar">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <nav class="sidebar-nav">
    <div class="sidebar-section">
      <ul class="sidebar-menu">
        <li class="sidebar-menu-item <?php echo ($current_page == 'home' || $current_page == 'dashboard') ? 'active' : ''; ?>">
          <a href="home.php" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path
                d="M8 3.293l6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293l6-6zm5-.793V1.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293l-2-2z" />
            </svg> -->
            <i class="fa-solid fa-house" style="color:#d1d1d1"></i>
            <span>Home</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'new-record' || $current_page == 'new-record') ? 'active' : ''; ?>">
          <a href="new-record.php" target="_blank" class="sidebar-link">
            <i class="fa-solid fa-square-plus" style="color:#71cbae"></i>
            <span>Add New Record</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'upload-record') ? 'active' : ''; ?>">
          <a href="upload-record.php" class="sidebar-link">
            <i class="fa-solid fa-file-arrow-up" style="color:#c19149"></i>
            <span>Upload Record</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'my-records') ? 'active' : ''; ?>">
          <a href="my-records.php" class="sidebar-link">
            <i class="fa-solid fa-file-lines" style="color:#71cbae"></i>
            <span>My Records</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'search') ? 'active' : ''; ?>">
          <a href="search.php" class="sidebar-link">
            <i class="fa-solid fa-magnifying-glass" style="color:#2fa7cd"></i>
            <span>Search</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'administration') ? 'active' : ''; ?>">
          <a href="administration.php" class="sidebar-link">
            <i class="fa-solid fa-user-shield" style="color:#a88be4"></i>
            <span>Administration</span>
          </a>
        </li>
        <li class="sidebar-menu-item <?php echo ($current_page == 'logout') ? 'active' : ''; ?>">
          <a href="#" onclick="showLogoutModal(); return false;" class="sidebar-link">
            <!-- <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor">
              <path fill-rule="evenodd"
                d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z" />
              <path fill-rule="evenodd"
                d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z" />
            </svg> -->
            <i class="fa-solid fa-right-from-bracket" style="color:#d95555"></i>
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
</script>