<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Include database configuration
require_once 'config/database.php';
$db = getDatabaseConnection();

if (!$db) {
    die("Database connection failed.");
}

// Retrieve user's department
$user_department = isset($_SESSION['department']) ? trim($_SESSION['department']) : '';

// Restrict access ONLY to Managers
if (strcasecmp($user_department, 'Manager') !== 0) {
    header("Location: dashboard.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Upload Record</title>
    <link rel="shortcut icon" href="images/favicon.png" />
    <link rel="stylesheet" href="styles/header-sidebar.css">
    <link rel="stylesheet" href="styles/common.css">
    <link rel="stylesheet" href="styles/upload-record.css">

  </head>

  <body>
    <div class="upload-record-page-container page-containers">
      
      <div class="upload-card">
        <div class="upload-card-header">
          <div class="upload-header-left">
            <div class="upload-header-icon">
              <i class="fa-solid fa-file-arrow-up"></i>
            </div>
            <div class="upload-header-text">
              <h1>Upload Support Details</h1>
              <p>Upload CSV, ODS, or Excel spreadsheet to import records</p>
            </div>
          </div>

          <a href="uploads/sample.csv" download="sample.csv" class="sample-csv-btn">
            <i class="fa-solid fa-download"></i> Download Sample CSV
          </a>
        </div>

        <div class="upload-card-body">
          <div id="uploadAlert" class="upload-alert"></div>

          <form id="uploadForm" enctype="multipart/form-data" autocomplete="off">
            <div class="upload-form-row">
              
              <!-- Department Selection Field -->
              <div class="upload-form-group">
                <label for="department_select">Select Department <span class="required-star">*</span></label>
                <div class="upload-select-wrapper">
                  <i class="fa-solid fa-sitemap input-icon"></i>
                  <select id="department_select" name="department" class="upload-form-control" required>
                    <option value="" disabled selected>Select Department</option>
                    <option value="Voice Logger">Voice Logger</option>
                    <option value="IVR">IVR</option>
                  </select>
                  <i class="fa-solid fa-chevron-down arrow-icon"></i>
                </div>
              </div>

              <!-- File Drag and Drop Zone -->
              <div class="upload-form-group">
                <label>Select File <span class="required-star">*</span></label>
                <div class="file-dropzone" id="fileDropzone">
                  <input type="file" id="fileInput" name="file" accept=".csv,.ods,.xlsx,.xls" required>
                  <div class="dropzone-icon">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                  </div>
                  <div class="dropzone-title">Drag & drop your file here or click to browse</div>
                  <div class="dropzone-subtitle">Supported formats: CSV, ODS, XLSX, XLS</div>
                  <!-- <div class="dropzone-accepted">Auto Column Mapping</div> -->
                </div>

                <!-- Selected File Card Preview -->
                <div class="selected-file-card" id="selectedFileCard" style="display: none;">
                  <div class="selected-file-details">
                    <i class="fa-solid fa-file-excel selected-file-icon"></i>
                    <div>
                      <div class="selected-file-name" id="selectedFileName">file.csv</div>
                      <div class="selected-file-size" id="selectedFileSize">0 KB</div>
                    </div>
                  </div>
                  <button type="button" class="remove-file-btn" id="removeFileBtn" title="Remove File">
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                </div>
              </div>

            </div>

            <div class="upload-form-actions">
              <button type="submit" class="btn-upload-submit" id="btnSubmitUpload" disabled>
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload
              </button>
            </div>
          </form>

          <!-- Upload Results Summary Section -->
          <div class="upload-results-section" id="uploadResultsSection" style="display: none;">
            <div class="upload-results-title">
              <i class="fa-solid fa-list-check"></i> Uploaded Record Summary
            </div>
            <div class="results-table-container">
              <table class="results-table">
                <thead>
                  <tr>
                    <th>Sl No</th>
                    <th>Date</th>
                    <th>Agent</th>
                    <th>Company Name</th>
                    <th>Dealer Name</th>
                    <th>Product Category</th>
                  </tr>
                </thead>
                <tbody id="resultsTableBody">
                  <!-- Dynamically populated by JS -->
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>

    </div>

    <?php include 'components/header-sidebar.php'; ?>
    
    <script src="plugins/jquery-3.7.1.min.js"></script>
    <script src="js/upload-record.js?v=<?php echo time(); ?>"></script>

  </body>

</html>