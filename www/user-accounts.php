<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Retrieve session role and restrict page to administrator users only
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if ($user_role !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$current_user_id = $_SESSION['id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Accounts | Xtend Agent Reports</title>
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/user-accounts.css">
  <link rel="stylesheet" href="styles/header-sidebar.css">
  <link rel="stylesheet" href="styles/common.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
  <div class="accounts-page-container page-containers">
    
    <!-- Top Header section -->
    <div class="accounts-header">
      <div class="accounts-title-section">
        <h1 class="accounts-title">User Accounts</h1>
        <p class="accounts-subtitle">Manage system user accounts, access permissions, and roles.</p>
      </div>
      <button type="button" class="btn btn-primary" id="btnAddUser">
        <i class="fa-solid fa-user-plus"></i> Add New User
      </button>
    </div>

    <!-- Alert Box -->
    <div id="alertBox" class="profile-alert" style="display: none;">
      <i class="fa-solid id-icon"></i>
      <span class="alert-message"></span>
    </div>

    <!-- Users List Table Grid Card -->
    <div class="accounts-card" style="position: relative;">
      <!-- Table loading overlay -->
      <div class="loading-overlay" id="tableLoadingOverlay">
        <div class="spinner"></div>
      </div>

      <div class="accounts-table-wrapper">
        <table class="accounts-table">
          <thead>
            <tr>
              <th>User details</th>
              <th>Username</th>
              <th>Department</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="usersTableBody">
            <!-- Dynamically populated via jQuery AJAX -->
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add New User Modal Dialog Overlay -->
  <div id="addUserModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Add New User</h2>
        <button type="button" class="modal-close" id="btnCloseModalX" aria-label="Close modal">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      
      <form id="addUserForm" autocomplete="off">
        <!-- Username input -->
        <div class="form-group">
          <label class="form-label" for="modalUsername">Username</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-user-tag input-icon"></i>
            <input type="text" id="modalUsername" class="form-control" placeholder="Enter username (alphanumeric)" required autocomplete="new-username">
          </div>
        </div>
        
        <!-- Real Name input -->
        <div class="form-group">
          <label class="form-label" for="modalRealName">Real Name</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-user input-icon"></i>
            <input type="text" id="modalRealName" class="form-control" placeholder="Enter full name" required autocomplete="name">
          </div>
        </div>
        
        <!-- Password input -->
        <div class="form-group">
          <label class="form-label" for="modalPassword">Password</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-key input-icon"></i>
            <input type="password" id="modalPassword" class="form-control" placeholder="Enter password (min 5 chars)" required autocomplete="new-password">
            <button type="button" class="btn-toggle-pwd" data-target="modalPassword" aria-label="Toggle password visibility">
              <i class="fa-solid fa-eye eye-icon"></i>
            </button>
          </div>
        </div>

        <!-- Confirm Password input -->
        <div class="form-group">
          <label class="form-label" for="modalConfirmPassword">Confirm Password</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-key input-icon"></i>
            <input type="password" id="modalConfirmPassword" class="form-control" placeholder="Confirm password" required autocomplete="new-password">
            <button type="button" class="btn-toggle-pwd" data-target="modalConfirmPassword" aria-label="Toggle password visibility">
              <i class="fa-solid fa-eye eye-icon"></i>
            </button>
          </div>
        </div>
        
        <!-- Department Dropdown -->
        <div class="form-group">
          <label class="form-label" for="modalDepartment">Department</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-building input-icon"></i>
            <select id="modalDepartment" class="form-control select-control" required>
              <option value="" disabled selected>Select Department</option>
              <option value="Voice Logger">Voice Logger</option>
              <option value="IVR">IVR</option>
              <option value="Manager">Manager</option>
            </select>
          </div>
        </div>
        
        <!-- Role/Type Dropdown -->
        <div class="form-group">
          <label class="form-label" for="modalUserType">Role</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-user-shield input-icon"></i>
            <select id="modalUserType" class="form-control select-control" required>
              <option value="user" selected>User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelAddUser">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitAddUser">
            <i class="fa-solid fa-user-plus"></i> Add User
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit User Modal Dialog Overlay -->
  <div id="editUserModal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Edit User Account</h2>
        <button type="button" class="modal-close" id="btnCloseEditModalX" aria-label="Close modal">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      
      <form id="editUserForm" autocomplete="off">
        <input type="hidden" id="editUserId">
        
        <!-- Username (Disabled for security) -->
        <div class="form-group">
          <label class="form-label" for="editUsername">Username</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-lock input-icon"></i>
            <input type="text" id="editUsername" class="form-control" disabled>
          </div>
        </div>
        
        <!-- Real Name input -->
        <div class="form-group">
          <label class="form-label" for="editRealName">Real Name</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-user input-icon"></i>
            <input type="text" id="editRealName" class="form-control" placeholder="Enter full name" required autocomplete="name">
          </div>
        </div>
        
        <!-- Optional Password input -->
        <div class="form-group">
          <label class="form-label" for="editPassword">New Password (Optional)</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-key input-icon"></i>
            <input type="password" id="editPassword" class="form-control" placeholder="Leave blank to keep current" autocomplete="new-password">
            <button type="button" class="btn-toggle-pwd" data-target="editPassword" aria-label="Toggle password visibility">
              <i class="fa-solid fa-eye eye-icon"></i>
            </button>
          </div>
        </div>
        
        <!-- Confirm Password input -->
        <div class="form-group">
          <label class="form-label" for="editConfirmPassword">Confirm New Password</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-key input-icon"></i>
            <input type="password" id="editConfirmPassword" class="form-control" placeholder="Confirm new password" autocomplete="new-password">
            <button type="button" class="btn-toggle-pwd" data-target="editConfirmPassword" aria-label="Toggle password visibility">
              <i class="fa-solid fa-eye eye-icon"></i>
            </button>
          </div>
        </div>
        
        <!-- Department Dropdown -->
        <div class="form-group">
          <label class="form-label" for="editDepartment">Department</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-building input-icon"></i>
            <select id="editDepartment" class="form-control select-control" required>
              <option value="Voice Logger">Voice Logger</option>
              <option value="IVR">IVR</option>
              <option value="Manager">Manager</option>
            </select>
          </div>
        </div>
        
        <!-- Role/Type Dropdown -->
        <div class="form-group">
          <label class="form-label" for="editUserType">Role</label>
          <div class="input-wrapper">
            <i class="fa-solid fa-user-shield input-icon"></i>
            <select id="editUserType" class="form-control select-control" required>
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelEditUser">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitEditUser">
            <i class="fa-solid fa-floppy-disk"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Include Sidebar & Header Component -->
  <?php include 'components/header-sidebar.php'; ?>

  <!-- Inject current active admin user ID for comparison -->
  <script>
    const currentUserId = <?php echo json_encode($current_user_id); ?>;
  </script>

  <!-- jQuery & page controller -->
  <script src="plugins/jquery-3.7.1.min.js"></script>
  <script src="js/user-accounts.js"></script>

</body>
</html>
