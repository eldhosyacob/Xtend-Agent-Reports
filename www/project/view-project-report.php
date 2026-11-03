<?php
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$db = getDatabaseConnection();

$reportId = trim($_GET['id'] ?? $_GET['report_id'] ?? '');
$report = null;
$error = '';

if (empty($reportId) || !is_numeric($reportId)) {
    $error = 'Invalid Report ID specified.';
} else {
    if (!$db) {
        $error = 'Database connection failed.';
    } else {
        $stmt = $db->prepare("
            SELECT 
                pr.*,
                pst.title AS subtask_title,
                pst.priority AS subtask_priority,
                p.project_name,
                p.category AS project_category,
                p.priority AS project_priority
            FROM `project_reports` pr
            LEFT JOIN `project_subtasks` pst 
                ON (pr.subtask_id = pst.subtask_id OR (pr.subtask_id = CAST(pst.id AS CHAR) AND pr.project_id = pst.project_id))
            LEFT JOIN `projects` p 
                ON (pr.project_id = p.project_id OR pr.project_id = CAST(p.id AS CHAR))
            WHERE pr.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => (int)$reportId]);
        $report = $stmt->fetch();

        if (!$report) {
            $error = 'Project report #' . htmlspecialchars($reportId) . ' was not found.';
        }
    }
}

$isCurrentDate = false;
$statusClass = 'status-pending';

if ($report) {
    $today = date('Y-m-d');
    $isCurrentDate = (trim((string)$report['date']) === $today);

    $statusLower = strtolower(trim((string)$report['status']));
    if ($statusLower === 'completed') {
        $statusClass = 'status-completed';
    } elseif ($statusLower === 'in progress') {
        $statusClass = 'status-in-progress';
    } elseif ($statusLower === 'on hold') {
        $statusClass = 'status-on-hold';
    }
}

// Fetch attachments for this report from file-uploads/project/
$reportAttachments = [];
$projectUploadDir = __DIR__ . '/../file-uploads/project/';
if ($report && is_dir($projectUploadDir) && !empty($report['project_id']) && !empty($report['id'])) {
    $cleanProjId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $report['project_id']);
    $repPrefix = $cleanProjId . '_' . (int)$report['id'] . '_';
    $pFiles = scandir($projectUploadDir);
    foreach ($pFiles as $f) {
        if ($f === '.' || $f === '..') continue;
        if (strpos($f, $repPrefix) === 0) {
            $rest = substr($f, strlen($repPrefix));
            $origName = preg_replace('/^[a-f0-9]{13}_/', '', $rest);
            $filePath = $projectUploadDir . $f;
            $sizeBytes = file_exists($filePath) ? filesize($filePath) : 0;
            $units = ['B', 'KB', 'MB', 'GB'];
            $pow = $sizeBytes ? floor(log($sizeBytes, 1024)) : 0;
            $pow = min($pow, count($units) - 1);
            $sizeFormatted = round($sizeBytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
            $reportAttachments[] = [
                'filename' => $f,
                'name' => $origName,
                'size' => $sizeFormatted,
                'url' => '../file-uploads/project/' . rawurlencode($f)
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo $report ? 'View Report #' . htmlspecialchars($report['id']) : 'Report Not Found'; ?> | Xtend Agent Reports</title>
    <link rel="shortcut icon" href="../images/favicon.png" />
    <link rel="stylesheet" href="../styles/header-sidebar.css">
    <link rel="stylesheet" href="../styles/common.css">
    <link rel="stylesheet" href="../styles/my-records.css">
    <link rel="stylesheet" href="../styles/project/my-project-reports.css">
    <link rel="stylesheet" href="../styles/project/view-project-report.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  </head>

  <body>
    <div class="view-project-report-container page-containers">

      <?php if (!empty($error) || !$report): ?>
        <div class="view-report-error-card">
          <div class="view-report-error-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
          <h2 class="view-report-error-title">Report Not Found</h2>
          <p class="view-report-error-msg"><?php echo htmlspecialchars($error); ?></p>
          <a href="my-project-reports.php" class="btn-header-back">
            <i class="fa-solid fa-arrow-left"></i> Back to My Reports
          </a>
        </div>
      <?php else: ?>

        <div class="view-report-card">

          <!-- Header -->
          <div class="view-report-header">
            <div class="view-report-title-area">
              <div class="view-report-top-row">
                <h1 class="view-report-title">
                  <i class="fa-solid fa-file-lines"></i> Project Report
                </h1>
                <!-- <span class="view-report-id-pill">#<?php echo htmlspecialchars($report['id']); ?></span>
                <span class="status-pill <?php echo $statusClass; ?>">
                  <i class="fa-solid fa-circle"></i> <?php echo htmlspecialchars($report['status'] ?? 'Pending'); ?>
                </span> -->
              </div>
            </div>

            <div class="view-report-header-actions">
              <!-- <a href="my-project-reports.php" class="btn-header-back">
                <i class="fa-solid fa-arrow-left"></i> My Reports
              </a> -->
              <?php if (!empty($report['project_id'])): ?>
                <a href="my-project-details.php?id=<?php echo urlencode($report['project_id']); ?>" class="btn-header-project">
                  <i class="fa-solid fa-diagram-project"></i> Project Details
                </a>
              <?php endif; ?>
              <!-- <?php if ($isCurrentDate): ?>
                <a href="new-project-report.php?id=<?php echo urlencode($report['id']); ?>" target="_blank" class="btn-header-edit">
                  <i class="fa-solid fa-pen-to-square"></i> Edit Report
                </a>
              <?php endif; ?> -->
            </div>
          </div>

          <!-- Section 1: Project & Subtask Overview -->
          <div class="detail-section">
            <!-- <div class="detail-section-title">
              <i class="fa-solid fa-diagram-project"></i> Project & Subtask Details
            </div> -->
            <div class="detail-grid">
              
              <div class="field-item col-4">
                <span class="field-label">Project ID</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-folder-tree field-icon"></i>
                  <div class="field-value monospace highlight"><?php echo htmlspecialchars($report['project_id'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Subtask ID</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-list-check field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['subtask_id'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Subtask Title</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-heading field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['subtask_title'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Product Name</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-box field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['product_name'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Product Sub Category</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-layer-group field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['product_sub_category'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Product Type</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-cubes field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['product_type'] ?: '—'); ?></div>
                </div>
              </div>

            </div>
          </div>

          <!-- Section 2: Assignment & Client Information -->
          <div class="detail-section">
            <!-- <div class="detail-section-title">
              <i class="fa-solid fa-user-check"></i> Assignment & Client Information
            </div> -->
            <div class="detail-grid">
              
              <div class="field-item col-4">
                <span class="field-label">Agent Name</span>
                <div class="field-control-wrapper">
                  <i class="fa-regular fa-user field-icon"></i>
                  <div class="field-value highlight"><?php echo htmlspecialchars($report['agent_name'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Department</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-sitemap field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['department'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Assigned By</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-users-gear field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['assigned_by'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Client Name</span>
                <div class="field-control-wrapper">
                  <i class="fa-regular fa-building field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['client_name'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Product ID / Serial Number</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-barcode field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['product_id'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Software Version</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-code-branch field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['software_version'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">LCD ID</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-desktop field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['lcd_id'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Hardware ID/Card ID</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-microchip field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['hardware_id'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">HASP ID</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-key field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['hasp_id'] ?: '—'); ?></div>
                </div>
              </div>

              <!-- <div class="field-item col-4">
                <span class="field-label">Youtrack ID</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-bug field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['youtrack_id'] ?: '—'); ?></div>
                </div>
              </div> -->

            </div>
          </div>

          <!-- Section 3: Dates & Time Tracking -->
          <div class="detail-section">
            <!-- <div class="detail-section-title">
              <i class="fa-regular fa-clock"></i> Schedule & Time Tracking
            </div> -->
            <div class="detail-grid">

              <div class="field-item col-4">
                <span class="field-label">Report Date</span>
                <div class="field-control-wrapper">
                  <i class="fa-regular fa-calendar-check field-icon"></i>
                  <div class="field-value highlight"><?php echo htmlspecialchars($report['date'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Received From (Date)</span>
                <div class="field-control-wrapper">
                  <i class="fa-regular fa-calendar field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['received_from'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Sent To (Date)</span>
                <div class="field-control-wrapper">
                  <i class="fa-regular fa-calendar-plus field-icon"></i>
                  <div class="field-value"><?php echo htmlspecialchars($report['sent_to'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Start Time</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-hourglass-start field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['start_time'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">End Time</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-hourglass-end field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['end_time'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-4">
                <span class="field-label">Total Time</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-clock field-icon"></i>
                  <div class="field-value monospace highlight"><?php echo htmlspecialchars($report['total_time'] ?: '00:00:00'); ?></div>
                </div>
              </div>

            </div>
          </div>

          <!-- Section 4: Testing Description -->
          <div class="detail-section">
            <!-- <div class="detail-section-title">
              <i class="fa-regular fa-file-lines"></i> Testing Description & Observations
            </div> -->
            <div class="detail-grid">
              <div class="field-item col-12">
                <span class="field-label">Testing Description</span>
                <div class="field-control-wrapper textarea-wrapper">
                  <i class="fa-regular fa-pen-to-square field-icon"></i>
                  <div class="field-textarea-value"><?php echo htmlspecialchars($report['testing_description']); ?></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 5: Attachments -->
          <div class="detail-section">
            <div class="detail-section-title">
              <i class="fa-solid fa-paperclip"></i> Uploaded Attachments
            </div>
            <div class="detail-grid">
              <div class="field-item col-12">
                <?php if (!empty($reportAttachments)): ?>
                  <div class="attachments-list">
                    <?php foreach ($reportAttachments as $att): 
                      $ext = strtolower(pathinfo($att['name'], PATHINFO_EXTENSION));
                      $iconClass = 'fa-regular fa-file';
                      if (in_array($ext, ['pdf'])) $iconClass = 'fa-regular fa-file-pdf';
                      elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $iconClass = 'fa-regular fa-file-image';
                      elseif (in_array($ext, ['csv', 'xls', 'xlsx'])) $iconClass = 'fa-regular fa-file-excel';
                      elseif (in_array($ext, ['doc', 'docx'])) $iconClass = 'fa-regular fa-file-word';
                      elseif (in_array($ext, ['zip', 'rar', '7z'])) $iconClass = 'fa-regular fa-file-zipper';
                    ?>
                      <div class="attachment-card">
                        <div class="attachment-info">
                          <div class="attachment-icon">
                            <i class="<?php echo $iconClass; ?>"></i>
                          </div>
                          <div class="attachment-details">
                            <span class="attachment-name"><?php echo htmlspecialchars($att['name']); ?></span>
                            <span class="attachment-meta"><?php echo htmlspecialchars($att['size']); ?></span>
                          </div>
                        </div>
                        <a href="<?php echo htmlspecialchars($att['url']); ?>" download class="btn-download-file">
                          <i class="fa-solid fa-download"></i> Download
                        </a>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="no-attachments-text">
                    <i class="fa-regular fa-folder-open"></i> No attachments uploaded for this report
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Section 6: System Audit Timestamps -->
          <div class="detail-section">
            <!-- <div class="detail-section-title">
              <i class="fa-solid fa-database"></i> Record Metadata & Timestamps
            </div> -->
            <!-- <div class="detail-grid">
              
              <div class="field-item col-6">
                <span class="field-label">Created At</span>
                <div class="field-control-wrapper">
                  <i class="fa-regular fa-clock field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['created_at'] ?: '—'); ?></div>
                </div>
              </div>

              <div class="field-item col-6">
                <span class="field-label">Updated At</span>
                <div class="field-control-wrapper">
                  <i class="fa-solid fa-rotate field-icon"></i>
                  <div class="field-value monospace"><?php echo htmlspecialchars($report['updated_at'] ?: '—'); ?></div>
                </div>
              </div>

            </div> -->
          </div>

          <!-- Footer Actions -->
          <div class="view-report-footer-actions">
            <!-- <a href="my-project-reports.php" class="btn-header-back">
              <i class="fa-solid fa-arrow-left"></i> Back to My Reports
            </a> -->
            <?php if ($isCurrentDate): ?>
              <!-- <a href="new-project-report.php?id=<?php echo urlencode($report['id']); ?>" target="_blank" class="btn-header-edit">
                <i class="fa-solid fa-pen-to-square"></i> Edit Report
              </a> -->
            <?php endif; ?>
          </div>

        </div>

      <?php endif; ?>

    </div>

    <!-- Include Header & Sidebar Component -->
    <?php include __DIR__ . '/../components/header-sidebar.php'; ?>

    <script src="../plugins/jquery-3.7.1.min.js"></script>
  </body>

</html>
