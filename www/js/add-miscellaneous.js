$(document).ready(function () {
  const $form = $('#addMiscellaneousForm');
  const $submitBtn = $('#btnMiscSubmit');
  const $alertContainer = $('#miscAlertContainer');
  const originalBtnContent = $submitBtn.html();

  let alertTimer = null;

  function showAlert(message, type = 'success', autoHideSeconds = 0) {
    if (alertTimer) {
      clearTimeout(alertTimer);
      alertTimer = null;
    }

    $alertContainer
      .removeClass('misc-alert-success misc-alert-error')
      .addClass(type === 'success' ? 'misc-alert-success' : 'misc-alert-error')
      .html(`
        <i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i>
        <span>${message}</span>
      `)
      .stop(true, true)
      .fadeIn(200);

    $('html, body').animate({
      scrollTop: $alertContainer.offset().top - 80
    }, 200);

    if (autoHideSeconds > 0) {
      alertTimer = setTimeout(function () {
        hideAlert();
      }, autoHideSeconds * 1000);
    }
  }

  function hideAlert() {
    if (alertTimer) {
      clearTimeout(alertTimer);
      alertTimer = null;
    }
    $alertContainer.fadeOut(300);
  }

  // Calculate Total Time Automatically
  function calculateTotalTime() {
    const startTimeVal = $('#misc_start_time').val();
    const endTimeVal = $('#misc_end_time').val();

    if (!startTimeVal || !endTimeVal) {
      $('#misc_total_time').val('');
      return;
    }

    const startParts = startTimeVal.split(':').map(Number);
    const endParts = endTimeVal.split(':').map(Number);

    const startSec = (startParts[0] || 0) * 3600 + (startParts[1] || 0) * 60 + (startParts[2] || 0);
    const endSec = (endParts[0] || 0) * 3600 + (endParts[1] || 0) * 60 + (endParts[2] || 0);

    let diffSec = endSec - startSec;
    if (diffSec < 0) {
      diffSec += 86400; // Across midnight handling
    }

    const hours = Math.floor(diffSec / 3600);
    const minutes = Math.floor((diffSec % 3600) / 60);
    const seconds = diffSec % 60;

    const formatted = 
      String(hours).padStart(2, '0') + ':' + 
      String(minutes).padStart(2, '0') + ':' + 
      String(seconds).padStart(2, '0');

    $('#misc_total_time').val(formatted);
  }

  // Trigger calculation whenever start or end time changes
  $('#misc_start_time, #misc_end_time').on('input change', calculateTotalTime);

  // Handle Form Submission
  $form.on('submit', function (e) {
    e.preventDefault();
    hideAlert();

    const dateVal = $('#misc_date').val().trim();
    const agentVal = $('#misc_agent').val().trim();
    const categoryVal = $('#misc_category').val().trim();
    const descriptionVal = $('#misc_description').val().trim();
    const startTimeVal = $('#misc_start_time').val();
    const endTimeVal = $('#misc_end_time').val();
    const totalTimeVal = $('#misc_total_time').val();

    if (!dateVal) {
      showAlert('Please select a Date.', 'error');
      $('#misc_date').focus();
      return;
    }

    if (!agentVal) {
      showAlert('Please select an Agent.', 'error');
      $('#misc_agent').focus();
      return;
    }

    if (!categoryVal) {
      showAlert('Please select a Miscellaneous Category.', 'error');
      $('#misc_category').focus();
      return;
    }

    // Disable button and show spinner
    $submitBtn.prop('disabled', true).html('<i class="fa-solid fa-circle-notch fa-spin"></i> Saving...');

    $.ajax({
      url: 'api/add-new-miscellaneous.php',
      type: 'POST',
      data: {
        date: dateVal,
        agent: agentVal,
        miscellaneous_category: categoryVal,
        description: descriptionVal,
        start_time: startTimeVal,
        end_time: endTimeVal,
        total_time: totalTimeVal
      },
      dataType: 'json',
      success: function (response) {
        if (response && response.success) {
          showAlert(response.message || 'Miscellaneous details added successfully', 'success', 5);
          // Reset form fields except date and agent
          $('#misc_category').val('');
          $('#misc_description').val('');
          $('#misc_start_time').val('');
          $('#misc_end_time').val('');
          $('#misc_total_time').val('');
        } else {
          showAlert((response && response.message) ? response.message : 'An error occurred while saving.', 'error');
        }
      },
      error: function (xhr, status, error) {
        let errorMsg = 'Failed to submit form. Please try again.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
          errorMsg = xhr.responseJSON.message;
        }
        showAlert(errorMsg, 'error');
      },
      complete: function () {
        $submitBtn.prop('disabled', false).html(originalBtnContent);
      }
    });
  });

  // Handle Form Reset
  $('#btnMiscReset').on('click', function () {
    hideAlert();
    $('#misc_category').val('');
    $('#misc_description').val('');
    $('#misc_start_time').val('');
    $('#misc_end_time').val('');
    $('#misc_total_time').val('');
  });
});
