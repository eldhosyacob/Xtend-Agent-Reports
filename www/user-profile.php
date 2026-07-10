<?php
// Secure the page - ensure user is authenticated
require_once 'config/auth_check.php';

// Retrieve session user details
$user_id = $_SESSION['id'];
$user_full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$user_department = isset($_SESSION['department']) ? $_SESSION['department'] : '';

// Resolve user profile photo path
$profile_photo_url = 'images/default-avatar.png'; // default fallback
$has_custom_photo = false;
$allowed_extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
foreach ($allowed_extensions as $ext) {
    $photo_path = __DIR__ . '/images/profile/' . $user_id . '.' . $ext;
    if (file_exists($photo_path)) {
        $profile_photo_url = 'images/profile/' . $user_id . '.' . $ext . '?t=' . filemtime($photo_path);
        $has_custom_photo = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Profile | Xtend Agent Reports</title>
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/user-profile.css">
  <link rel="stylesheet" href="styles/header-sidebar.css">
  <link rel="stylesheet" href="styles/common.css">
</head>

<body>
  <div class="profile-page-container page-containers">
    
    <!-- Header Section -->
    <div class="profile-header">
      <div class="profile-title-section">
        <h1 class="profile-title">User Profile</h1>
        <p class="profile-subtitle">Manage your personal details, credentials, and profile picture.</p>
      </div>
    </div>

    <!-- Alert Box -->
    <div id="alertBox" class="profile-alert" style="display: none;">
      <i class="fa-solid id-icon"></i>
      <span class="alert-message"></span>
    </div>

    <div class="profile-grid">
      
      <!-- Left Column: Photo Card -->
      <div class="profile-card">
        <div class="profile-photo-wrapper" id="profilePhotoWrapper" title="Click to upload profile photo">
          <div class="profile-photo-overlay">
            <i class="fa-solid fa-camera"></i>
            <span>Change Photo</span>
          </div>
          <?php if ($has_custom_photo): ?>
            <img src="<?php echo $profile_photo_url; ?>" alt="Profile Preview" class="profile-img-preview" id="profileImgPreview">
            <div class="profile-initials-preview" id="profileInitialsPreview" style="display: none;"></div>
          <?php else: ?>
            <img src="" alt="Profile Preview" class="profile-img-preview" id="profileImgPreview" style="display: none;">
            <div class="profile-initials-preview" id="profileInitialsPreview">
              <?php 
                $initials = '';
                if ($user_full_name) {
                  $words = explode(' ', $user_full_name);
                  foreach ($words as $word) {
                    $initials .= strtoupper(substr($word, 0, 1));
                  }
                  $initials = substr($initials, 0, 2);
                }
                echo htmlspecialchars($initials ?: '?');
              ?>
            </div>
          <?php endif; ?>
        </div>

        <input type="file" id="profilePhotoInput" class="profile-file-input" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp">

        <div class="profile-photo-actions">
          <button type="button" class="btn-upload-trigger" id="btnUploadTrigger">
            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo
          </button>
          <button type="button" class="btn-delete-photo" id="btnDeletePhoto" style="<?php echo $has_custom_photo ? '' : 'display: none;'; ?>">
            Delete Photo
          </button>
        </div>

        <div class="profile-info-divider"></div>

        <div class="profile-meta-info">
          <h2 class="profile-display-name" id="profileDisplayName"><?php echo htmlspecialchars($user_full_name); ?></h2>
          <!-- <p class="profile-display-role"><?php echo htmlspecialchars($user_role ?: 'User'); ?></p> -->
          
          <div class="profile-info-divider"></div>
          
          <div class="profile-meta-list">
            <div class="profile-meta-item">
              <span class="profile-meta-label">Username:</span>
              <span class="profile-meta-val"><?php echo htmlspecialchars($username); ?></span>
            </div>
            <div class="profile-meta-item">
              <span class="profile-meta-label">Department:</span>
              <span class="profile-meta-val"><?php echo htmlspecialchars($user_department ?: '-'); ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Details Card -->
      <div class="profile-details-card">
        <form id="profileDetailsForm" autocomplete="off">
          
          <!-- Personal Details -->
          <h3 class="profile-form-section-title">Personal Details</h3>
          <div class="profile-form-grid">
            <div class="profile-form-group">
              <label class="profile-form-label" for="usernameInput">Username</label>
              <div class="profile-input-wrapper">
                <i class="fa-solid fa-lock profile-input-icon"></i>
                <input type="text" id="usernameInput" class="profile-form-control" value="<?php echo htmlspecialchars($username); ?>" disabled>
              </div>
            </div>

            <div class="profile-form-group">
              <label class="profile-form-label" for="realNameInput">Real Name</label>
              <div class="profile-input-wrapper">
                <i class="fa-solid fa-lock profile-input-icon"></i>
                <input type="text" id="realNameInput" name="real_name" class="profile-form-control" value="<?php echo htmlspecialchars($user_full_name); ?>" disabled>
              </div>
            </div>
          </div>

          <!-- Change Password -->
          <h3 class="profile-form-section-title">Change Password</h3>
          <div class="profile-form-grid">
            <div class="profile-form-group">
              <label class="profile-form-label" for="newPassword">New Password</label>
              <div class="profile-input-wrapper">
                <i class="fa-solid fa-key profile-input-icon"></i>
                <input type="password" id="newPassword" name="password" class="profile-form-control" placeholder="Leave blank to keep current" autocomplete="new-password">
                <button type="button" class="btn-toggle-pwd" data-target="newPassword" aria-label="Toggle password visibility">
                  <i class="fa-solid fa-eye eye-icon"></i>
                </button>
              </div>
            </div>

            <div class="profile-form-group">
              <label class="profile-form-label" for="confirmPassword">Confirm New Password</label>
              <div class="profile-input-wrapper">
                <i class="fa-solid fa-key profile-input-icon"></i>
                <input type="password" id="confirmPassword" class="profile-form-control" placeholder="Confirm new password" autocomplete="new-password">
                <button type="button" class="btn-toggle-pwd" data-target="confirmPassword" aria-label="Toggle password visibility">
                  <i class="fa-solid fa-eye eye-icon"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="profile-form-actions">
            <button type="submit" class="btn-save-profile" id="btnSaveProfile">
              <i class="fa-solid fa-floppy-disk"></i> Save Changes
            </button>
          </div>
          
        </form>
      </div>
    </div>
  </div>

  <!-- Profile Photo Reposition/Crop Modal -->
  <div id="cropModal" class="profile-modal">
    <div class="profile-modal-content">
      <div class="profile-modal-header">
        <h2 class="profile-modal-title">Adjust Profile Photo</h2>
        <button type="button" class="profile-modal-close" id="btnCancelCropX" aria-label="Close modal">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      <div class="crop-workspace" id="cropWorkspace">
        <div class="crop-viewport-overlay"></div>
        <img src="" alt="To Crop" id="cropImage" class="crop-image-element">
      </div>
      <div class="zoom-controls">
        <i class="fa-solid fa-image zoom-icon" style="font-size: 12px;"></i>
        <input type="range" id="zoomSlider" class="zoom-slider" min="1" max="4" step="0.01" value="1">
        <i class="fa-solid fa-image zoom-icon" style="font-size: 18px;"></i>
      </div>
      <div class="profile-modal-actions">
        <button type="button" class="btn-cancel-crop" id="btnCancelCrop">Cancel</button>
        <button type="button" class="btn-upload-cropped" id="btnUploadCropped">
          <i class="fa-solid fa-upload"></i> Save & Upload
        </button>
      </div>
    </div>
  </div>

  <!-- Include Sidebar & Header Component -->
  <?php include 'components/header-sidebar.php'; ?>

  <script src="plugins/jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function() {
      // Toggle password visibility
      $('.btn-toggle-pwd').on('click', function() {
        const targetId = $(this).data('target');
        const $input = $('#' + targetId);
        const $icon = $(this).find('.eye-icon');

        if ($input.attr('type') === 'password') {
          $input.attr('type', 'text');
          $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
          $input.attr('type', 'password');
          $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
      });

      // Show alert helper
      function showAlert(type, message) {
        const $alert = $('#alertBox');
        const $icon = $alert.find('.id-icon');
        
        $alert.removeClass('profile-alert-success profile-alert-error');
        
        if (type === 'success') {
          $alert.addClass('profile-alert-success');
          $icon.removeClass('fa-circle-exclamation').addClass('fa-circle-check');
        } else {
          $alert.addClass('profile-alert-error');
          $icon.removeClass('fa-circle-check').addClass('fa-circle-exclamation');
        }
        
        $alert.find('.alert-message').text(message);
        $alert.slideDown(200);

        // Auto-scroll to alert
        $('html, body').animate({ scrollTop: 0 }, 200);

        // Auto hide success alerts after 5 seconds
        if (type === 'success') {
          setTimeout(function() {
            $alert.slideUp(200);
          }, 5000);
        }
      }

      // Generate initials helper
      function getInitials(name) {
        if (!name) return '?';
        return name
          .split(' ')
          .filter(Boolean)
          .map(word => word[0])
          .join('')
          .toUpperCase()
          .substring(0, 2);
      }

      // Cropping & Repositioning variables
      let baseWidth = 0;
      let baseHeight = 0;
      let imgX = 0;
      let imgY = 0;
      let w = 0;
      let h = 0;
      let zoomVal = 1;
      let isDragging = false;
      let startX = 0;
      let startY = 0;
      let initLeft = 0;
      let initTop = 0;
      let originalFile = null;
      let workspaceWidth = 392; // Will be measured dynamically
      const workspaceHeight = 280;

      function clamp(val, min, max) {
        return Math.max(min, Math.min(max, val));
      }

      // File upload trigger click
      $('#profilePhotoWrapper, #btnUploadTrigger').on('click', function() {
        $('#profilePhotoInput').click();
      });

      // Handle image selection
      $('#profilePhotoInput').on('change', function() {
        const file = this.files[0];
        if (!file) return;

        // Perform basic client side check
        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
          showAlert('error', 'Invalid file type. Allowed formats: PNG, JPG, JPEG, GIF, WEBP');
          this.value = '';
          return;
        }

        if (file.size > 5 * 1024 * 1024) { // 5MB
          showAlert('error', 'File size exceeds maximum limit of 5MB');
          this.value = '';
          return;
        }

        originalFile = file;

        // Load into crop workspace
        const reader = new FileReader();
        reader.onload = function(e) {
          $('#cropImage').attr('src', e.target.result);
          $('#cropModal').addClass('active');
        };
        reader.readAsDataURL(file);
      });

      // Initialize reposition layout once image loads inside the crop container
      $('#cropImage').on('load', function() {
        const $workspace = $('#cropWorkspace');
        workspaceWidth = $workspace.width() || 392;

        const naturalWidth = this.naturalWidth;
        const naturalHeight = this.naturalHeight;

        // Scale so shorter side covers the 200px viewport circle
        const minScale = 200 / Math.min(naturalWidth, naturalHeight);
        baseWidth = naturalWidth * minScale;
        baseHeight = naturalHeight * minScale;

        zoomVal = 1;
        $('#zoomSlider').val(1);

        w = baseWidth;
        h = baseHeight;

        // Center the image in the workspace box
        imgX = (workspaceWidth - baseWidth) / 2;
        imgY = (workspaceHeight - baseHeight) / 2;

        $(this).css({
          width: w + 'px',
          height: h + 'px',
          left: imgX + 'px',
          top: imgY + 'px'
        });
      });

      // Handle Zoom Slider Input
      $('#zoomSlider').on('input', function() {
        const newZoom = parseFloat($(this).val());
        const oldZoom = zoomVal;
        zoomVal = newZoom;

        const cx = workspaceWidth / 2;
        const cy = workspaceHeight / 2;

        // Zoom around viewport center relative to image offset
        const relX = (cx - imgX) / (baseWidth * oldZoom);
        const relY = (cy - imgY) / (baseHeight * oldZoom);

        w = baseWidth * zoomVal;
        h = baseHeight * zoomVal;

        imgX = cx - relX * w;
        imgY = cy - relY * h;

        // Constraint boundaries: circle viewport (200x200) must remain covered
        const cxMin = cx - 100;
        const cxMax = cx + 100;
        const cyMin = cy - 100;
        const cyMax = cy + 100;

        imgX = clamp(imgX, cxMax - w, cxMin);
        imgY = clamp(imgY, cyMax - h, cyMin);

        $('#cropImage').css({
          width: w + 'px',
          height: h + 'px',
          left: imgX + 'px',
          top: imgY + 'px'
        });
      });

      // Mouse drag handlers
      $('#cropImage').on('mousedown', function(e) {
        e.preventDefault();
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        initLeft = imgX;
        initTop = imgY;
        $(this).css('cursor', 'grabbing');
      });

      $(document).on('mousemove', function(e) {
        if (!isDragging) return;

        const dx = e.clientX - startX;
        const dy = e.clientY - startY;

        let targetLeft = initLeft + dx;
        let targetTop = initTop + dy;

        const cx = workspaceWidth / 2;
        const cy = workspaceHeight / 2;

        targetLeft = clamp(targetLeft, cx + 100 - w, cx - 100);
        targetTop = clamp(targetTop, cy + 100 - h, cy - 100);

        imgX = targetLeft;
        imgY = targetTop;

        $('#cropImage').css({
          left: imgX + 'px',
          top: imgY + 'px'
        });
      });

      $(document).on('mouseup mouseleave', function() {
        if (isDragging) {
          isDragging = false;
          $('#cropImage').css('cursor', 'grab');
        }
      });

      // Touch drag handlers (Mobile responsive)
      $('#cropImage').on('touchstart', function(e) {
        if (e.originalEvent.touches.length !== 1) return;
        isDragging = true;
        const touch = e.originalEvent.touches[0];
        startX = touch.clientX;
        startY = touch.clientY;
        initLeft = imgX;
        initTop = imgY;
      });

      $('#cropImage').on('touchmove', function(e) {
        if (!isDragging || e.originalEvent.touches.length !== 1) return;
        e.preventDefault();

        const touch = e.originalEvent.touches[0];
        const dx = touch.clientX - startX;
        const dy = touch.clientY - startY;

        let targetLeft = initLeft + dx;
        let targetTop = initTop + dy;

        const cx = workspaceWidth / 2;
        const cy = workspaceHeight / 2;

        targetLeft = clamp(targetLeft, cx + 100 - w, cx - 100);
        targetTop = clamp(targetTop, cy + 100 - h, cy - 100);

        imgX = targetLeft;
        imgY = targetTop;

        $(this).css({
          left: imgX + 'px',
          top: imgY + 'px'
        });
      });

      $('#cropImage').on('touchend touchcancel', function() {
        isDragging = false;
      });

      // Close modal helpers
      function closeCropModal() {
        $('#cropModal').removeClass('active');
        $('#cropImage').attr('src', '');
        $('#profilePhotoInput').val('');
        originalFile = null;
      }

      $('#btnCancelCrop, #btnCancelCropX').on('click', closeCropModal);

      // Perform client side canvas crop and upload
      $('#btnUploadCropped').on('click', function() {
        if (!originalFile) return;

        const img = $('#cropImage')[0];
        const cx = workspaceWidth / 2;
        const cy = workspaceHeight / 2;

        // Viewport left and top offsets relative to the image bounds
        const offsetX = (cx - 100) - imgX;
        const offsetY = (cy - 100) - imgY;

        // Ratios of natural size vs viewport size
        const ratioX = img.naturalWidth / w;
        const ratioY = img.naturalHeight / h;

        // Coordinates on source image
        const sX = offsetX * ratioX;
        const sY = offsetY * ratioY;
        const sW = 200 * ratioX;
        const sH = 200 * ratioY;

        const canvas = document.createElement('canvas');
        canvas.width = 300;
        canvas.height = 300;
        const ctx = canvas.getContext('2d');

        // Draw cropped view on 300x300 canvas
        ctx.drawImage(img, sX, sY, sW, sH, 0, 0, 300, 300);

        const $uploadBtn = $(this);
        const origBtnHtml = $uploadBtn.html();
        $uploadBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Uploading...');

        canvas.toBlob(function(blob) {
          if (!blob) {
            showAlert('error', 'Failed to generate cropped image.');
            $uploadBtn.prop('disabled', false).html(origBtnHtml);
            return;
          }

          const formData = new FormData();
          formData.append('action', 'upload_photo');
          formData.append('profile_photo', blob, 'avatar.png');

          $.ajax({
            url: 'api/user-profile-actions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
              if (response.success) {
                const newPhotoUrl = response.data.photo_url;

                // Update UI preview
                $('#profileInitialsPreview').hide();
                $('#profileImgPreview').attr('src', newPhotoUrl).show();
                $('#btnDeletePhoto').show();

                // Update header avatar
                const $headerAvatar = $('#headerUserAvatar');
                $headerAvatar.html(`<img src="${newPhotoUrl}" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; display: block;">`);

                closeCropModal();
                showAlert('success', 'Profile photo updated successfully!');
              } else {
                showAlert('error', response.message);
              }
            },
            error: function() {
              showAlert('error', 'An error occurred during file upload');
            },
            complete: function() {
              $uploadBtn.prop('disabled', false).html(origBtnHtml);
            }
          });
        }, 'image/png');
      });

      // Delete photo interaction
      $('#btnDeletePhoto').on('click', function() {
        if (!confirm('Are you sure you want to delete your profile photo?')) return;

        const $deleteBtn = $(this);
        $deleteBtn.prop('disabled', true).text('Deleting...');

        $.ajax({
          url: 'api/user-profile-actions.php',
          type: 'POST',
          data: { action: 'delete_photo' },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              // Get current real name to set initials fallback
              const realName = $('#realNameInput').val().trim();
              const initials = getInitials(realName);

              // Update profile card preview
              $('#profileImgPreview').hide().attr('src', '');
              $('#profileInitialsPreview').text(initials).show();
              $deleteBtn.hide();

              // Update top header avatar preview
              $('#headerUserAvatar').text(initials);

              showAlert('success', 'Profile photo deleted successfully');
            } else {
              showAlert('error', response.message);
            }
          },
          error: function() {
            showAlert('error', 'An error occurred while deleting the profile photo');
          },
          complete: function() {
            $deleteBtn.prop('disabled', false).text('Delete Photo');
          }
        });
      });

      // Save Profile Form Submission
      $('#profileDetailsForm').on('submit', function(e) {
        e.preventDefault();

        const realName = $('#realNameInput').val().trim();
        const password = $('#newPassword').val();
        const confirmPassword = $('#confirmPassword').val();

        if (realName.length < 2) {
          showAlert('error', 'Real name must be at least 2 characters');
          return;
        }

        if (password) {
          if (password.length < 5) {
            showAlert('error', 'New password must be at least 5 characters');
            return;
          }
          if (password !== confirmPassword) {
            showAlert('error', 'New passwords do not match');
            return;
          }
        }

        const $saveBtn = $('#btnSaveProfile');
        const origBtnHtml = $saveBtn.html();
        $saveBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...');

        $.ajax({
          url: 'api/user-profile-actions.php',
          type: 'POST',
          data: {
            action: 'update_profile',
            real_name: realName,
            password: password
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              // Update Display Name labels on page
              $('#profileDisplayName').text(realName);
              $('.user-name').text(realName); // Header display name

              // Clear password inputs
              $('#newPassword').val('');
              $('#confirmPassword').val('');

              // If using initials fallback, update initials since real name might have changed
              if ($('#profileInitialsPreview').is(':visible')) {
                const initials = getInitials(realName);
                $('#profileInitialsPreview').text(initials);
                $('#headerUserAvatar').text(initials);
              }

              showAlert('success', 'Profile details updated successfully!');
            } else {
              showAlert('error', response.message);
            }
          },
          error: function() {
            showAlert('error', 'An error occurred while updating profile details');
          },
          complete: function() {
            $saveBtn.prop('disabled', false).html(origBtnHtml);
          }
        });
      });
    });
  </script>
</body>

</html>