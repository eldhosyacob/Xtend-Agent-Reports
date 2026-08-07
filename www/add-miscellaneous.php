<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Include database configuration
require_once 'config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    die("Database connection failed.");
}

// Get current logged in user name
$current_user_name = isset($_SESSION['full_name']) && !empty($_SESSION['full_name']) 
    ? $_SESSION['full_name'] 
    : (isset($_SESSION['real_name']) && !empty($_SESSION['real_name']) ? $_SESSION['real_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : ''));

// Fetch categories from miscellaneous_categories table
$categories = [];
try {
    $stmt = $db->query("SELECT categories FROM miscellaneous_categories ORDER BY categories ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Failed to fetch miscellaneous categories: " . $e->getMessage());
}

$default_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Add Miscellaneous</title>
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="styles/add-miscellaneous.css">
  </head>

  <body>
    <div class="miscellaneous-page-container page-containers">
      
      <div class="misc-card">
        <div class="misc-card-header">
          <div class="misc-header-icon">
            <i class="fa-solid fa-file-circle-plus"></i>
          </div>
          <div class="misc-header-text">
            <h1>Add Miscellaneous Record</h1>
            <p>Enter miscellaneous activity details</p>
          </div>
        </div>

        <div class="misc-card-body">
          <div id="miscAlertContainer" class="misc-alert"></div>

          <form id="addMiscellaneousForm" autocomplete="off">
            <!-- Row 1: Date & Agent -->
            <div class="misc-form-row misc-form-row-2">
              <!-- Date Field -->
              <div class="misc-form-group">
                <label for="misc_date">Date <span class="required-star">*</span></label>
                <div class="misc-input-wrapper">
                  <i class="fa-regular fa-calendar-days misc-input-icon"></i>
                  <input type="date" id="misc_date" name="date" class="misc-form-control" value="<?php echo htmlspecialchars($default_date); ?>" required readonly>
                </div>
              </div>

              <!-- Agent Field -->
              <div class="misc-form-group">
                <label for="misc_agent">Agent <span class="required-star">*</span></label>
                <div class="misc-input-wrapper">
                  <i class="fa-regular fa-user misc-input-icon"></i>
                  <input type="text" id="misc_agent" name="agent" class="misc-form-control" value="<?php echo htmlspecialchars($current_user_name); ?>" required readonly>
                </div>
              </div>
            </div>

            <!-- Row 2: Miscellaneous Category & Description -->
            <div class="misc-form-row misc-form-row-2">
              <!-- Miscellaneous Category Field -->
              <div class="misc-form-group">
                <label for="misc_category">Miscellaneous Category <span class="required-star">*</span></label>
                <div class="misc-input-wrapper">
                  <i class="fa-solid fa-list-check misc-input-icon"></i>
                  <select id="misc_category" name="miscellaneous_category" class="misc-form-control" required>
                    <option value="" disabled selected>Select Miscellaneous Category</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php echo htmlspecialchars($cat); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <!-- Description Field -->
              <div class="misc-form-group">
                <label for="misc_description">Description</label>
                <div class="misc-input-wrapper misc-input-wrapper-textarea">
                  <i class="fa-solid fa-align-left misc-input-icon"></i>
                  <textarea id="misc_description" name="description" class="misc-form-control" placeholder="Enter activity description or remarks..."></textarea>
                </div>
              </div>
            </div>

            <!-- Row 3: Start Time, End Time, Total Time -->
            <div class="misc-form-row misc-form-row-3">
              <!-- Start Time -->
              <div class="misc-form-group">
                <label for="misc_start_time">Start Time</label>
                <div class="misc-input-wrapper">
                  <i class="fa-regular fa-clock misc-input-icon"></i>
                  <input type="time" step="1" id="misc_start_time" name="start_time" class="misc-form-control" required>
                </div>
              </div>

              <!-- End Time -->
              <div class="misc-form-group">
                <label for="misc_end_time">End Time</label>
                <div class="misc-input-wrapper">
                  <i class="fa-regular fa-clock misc-input-icon"></i>
                  <input type="time" step="1" id="misc_end_time" name="end_time" class="misc-form-control" required>
                </div>
              </div>

              <!-- Total Time (Auto Calculated) -->
              <div class="misc-form-group">
                <label for="misc_total_time">Total Time</label>
                <div class="misc-input-wrapper">
                  <i class="fa-solid fa-stopwatch misc-input-icon"></i>
                  <input type="text" id="misc_total_time" name="total_time" class="misc-form-control" readonly placeholder="00:00:00">
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="misc-actions">
              <button type="button" id="btnMiscReset" class="btn-misc-reset">
                <i class="fa-solid fa-rotate-left"></i> Reset Form
              </button>
              <button type="submit" id="btnMiscSubmit" class="btn-misc-submit">
                <i class="fa-solid fa-paper-plane"></i> Submit
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>

    <?php include 'components/header-sidebar.php'; ?>

    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script src="js/add-miscellaneous.js"></script>

  </body>

</html>