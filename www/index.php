<?php
// Redirect to dashboard if already logged in
require_once('config/login_redirect.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Xtend License | Login</title>
  <link rel="shortcut icon" href="images/favicon.png" />
  <link rel="stylesheet" href="styles/login.css">
</head>

<body>
  <!-- Animated background blobs -->
  <div class="bg-blobs">
    <div class="bg-blob bg-blob-1"></div>
    <div class="bg-blob bg-blob-2"></div>
    <div class="bg-blob bg-blob-3"></div>
  </div>

  <div class="login-page">
    <div class="login-card">
      <div class="brand-header">
        <img src="images/logo.png" alt="Xtend Logo" class="brand-logo" onerror="this.src='images/logo.jpg'; this.onerror=null;">
        <!-- <p class="brand-subtitle">License Management Portal</p> -->
      </div>

      <div id="errorMessage" class="error-message" style="display: none;"></div>

      <form id="loginForm" autocomplete="off">
        <div class="input-group">
          <label for="username">Username</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </span>
            <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
          </div>
        </div>

        <div class="input-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
              </svg>
            </span>
            <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" id="togglePassword" class="toggle-password" aria-label="Toggle password visibility">
              <svg id="eyeIcon" class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              <svg id="eyeOffIcon" class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                <line x1="1" y1="1" x2="23" y2="23"></line>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" id="loginBtn" class="login-btn">Sign In</button>
      </form>
    </div>
  </div>

  <script src="plugins/jquery-3.7.1.min.js"></script>
  <script>
    $(document).ready(function () {
      // Check for URL parameters
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('error') === 'session_expired') {
        showError('Your session has expired');
      }

      $('#togglePassword').on('click', function () {
        const passwordInput = $('#password');
        const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
        passwordInput.attr('type', type);
        
        if (type === 'password') {
          $('#eyeIcon').show();
          $('#eyeOffIcon').hide();
        } else {
          $('#eyeIcon').hide();
          $('#eyeOffIcon').show();
        }
      });

      $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        const username = $('#username').val().trim();
        const password = $('#password').val();

        // Client-side validation
        if (username.length < 3) {
          showError('Username must be at least 3 characters');
          return;
        }

        if (password.length < 5) {
          showError('Password must be at least 5 characters');
          return;
        }

        // Disable button during request
        $('#loginBtn').prop('disabled', true).text('Signing In...');
        hideError();

        // AJAX request
        $.ajax({
          url: 'api/login.php',
          type: 'POST',
          dataType: 'json',
          data: {
            username: username,
            password: password
          },
          success: function (response) {
            console.log("login ajax 1:", response);
            if (response.success) {
              window.location.href = 'dashboard.php';
            } else {
              showError(response.message || 'Login failed. Please try again.');
              $('#loginBtn').prop('disabled', false).text('Sign In');
            }
          },
          error: function (xhr, status, error) {
            console.log("login ajax error:", xhr.responseText);
            showError('An error occurred. Please try again.');
            $('#loginBtn').prop('disabled', false).text('Sign In');
          }
        });
      });

      function showError(message) {
        $('#errorMessage').text(message).slideDown();
      }

      function hideError() {
        $('#errorMessage').slideUp();
      }
    });
  </script>
</body>

</html>