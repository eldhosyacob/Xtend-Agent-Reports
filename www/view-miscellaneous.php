<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';
require_once 'config/database.php';

$db = getDatabaseConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$record = null;

if ($id > 0 && $db) {
    try {
        $stmt = $db->prepare("SELECT * FROM `miscellaneous_details` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Failed to fetch miscellaneous record: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>View Miscellaneous Record | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/new_record.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="styles/view-record.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="new-record-page-container page-containers">
      <div class="form-card-wrapper">
        <?php if ($record): ?>
          <div class="detail-header-info">
            <div class="record-id-title">
              <i class="fa-solid fa-list-check"></i>
              Miscellaneous Record Details
            </div>
            <div class="header-right-actions">
              <span class="badge-dept"><?php echo htmlspecialchars($record['department'] ?? 'Miscellaneous'); ?></span>
            </div>
          </div>

          <div class="grid-form">
            <!-- 1. DATE -->
            <div class="form-group col-4">
              <label>Date</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-calendar input-icon"></i>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($record['date'] ?? ''); ?>" readonly>
              </div>
            </div>

            <!-- 2. AGENT -->
            <div class="form-group col-4">
              <label>Agent</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-user input-icon"></i>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars(strtoupper($record['agent'] ?? '')); ?>" readonly>
              </div>
            </div>

            <!-- 3. DEPARTMENT -->
            <div class="form-group col-4">
              <label>Department</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-sitemap input-icon"></i>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($record['department'] ?? ''); ?>" readonly>
              </div>
            </div>

            <!-- 4. MISCELLANEOUS CATEGORY -->
            <div class="form-group col-6">
              <label>Miscellaneous Category</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-folder-open input-icon"></i>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($record['miscellaneous_category'] ?? ''); ?>" readonly>
              </div>
            </div>

            <!-- 5. TOTAL TIME -->
            <div class="form-group col-6">
              <label>Total Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($record['total_time'] ?? '00:00:00'); ?>" readonly>
              </div>
            </div>

            <!-- 6. START TIME -->
            <div class="form-group col-6">
              <label>Start Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($record['start_time'] ?? 'N/A'); ?>" readonly>
              </div>
            </div>

            <!-- 7. END TIME -->
            <div class="form-group col-6">
              <label>End Time</label>
              <div class="form-control-wrapper">
                <i class="fa-regular fa-clock input-icon"></i>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($record['end_time'] ?? 'N/A'); ?>" readonly>
              </div>
            </div>

            <!-- 8. DESCRIPTION -->
            <div class="form-group col-12">
              <label>Description / Details</label>
              <div class="form-control-wrapper">
                <textarea class="form-control" rows="4" readonly style="resize: vertical; padding-top: 10px;"><?php echo htmlspecialchars($record['description'] ?? ''); ?></textarea>
              </div>
            </div>

            <!-- 9. CREATED AT -->
            <div class="form-group col-12">
              <label>Created At</label>
              <div class="form-control-wrapper">
                <i class="fa-solid fa-calendar-check input-icon"></i>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($record['created_at'] ?? ''); ?>" readonly>
              </div>
            </div>

            <div class="form-actions col-12" style="margin-top: 20px; display: flex; justify-content: flex-end;">
              <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back
              </a>
            </div>
          </div>
        <?php else: ?>
          <div style="text-align: center; padding: 40px 20px;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i>
            <h2>Record Not Found</h2>
            <p style="color: #64748b; margin-bottom: 24px;">The requested miscellaneous record could not be found or you do not have permission to view it.</p>
            <a href="search.php" class="btn btn-primary">
              <i class="fa-solid fa-magnifying-glass"></i> Return to Search
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php include 'components/header-sidebar.php'; ?>
    <script src="plugins/jquery-3.7.1.min.js"></script>
  </body>
</html>
