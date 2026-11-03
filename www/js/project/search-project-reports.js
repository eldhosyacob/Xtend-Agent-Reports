$(document).ready(function() {
  // Set maximum date for calendars to today
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  const todayStr = `${yyyy}-${mm}-${dd}`;

  $('#from_date').attr('max', todayStr);
  $('#to_date').attr('max', todayStr);

  // Date range validation on change
  $('#from_date, #to_date').on('change', function() {
    const fromDate = $('#from_date').val();
    const toDate = $('#to_date').val();

    if (fromDate && toDate) {
      if (new Date(fromDate) > new Date(toDate)) {
        alert('From Date cannot be greater than To Date. Also, To Date should be greater than or equal to From Date.');
        $(this).val('');
      }
    }
  });

  // Autocomplete setup for Client Name
  const clientList = window.searchProjectClients || [];
  const $clientInput = $('#client_name');
  const $clientSuggestions = $('#clientSuggestions');
  let currentFocus = -1;

  if ($clientInput.length && $clientSuggestions.length) {
    $clientInput.on('input', function() {
      const val = this.value.trim().toLowerCase();
      $clientSuggestions.empty().hide();
      currentFocus = -1;

      if (!val) return;

      const matches = clientList.filter(client => client.toLowerCase().includes(val)).slice(0, 10);

      if (matches.length > 0) {
        matches.forEach((match, index) => {
          const $suggestion = $('<div class="autocomplete-suggestion"></div>')
            .text(match)
            .attr('data-index', index)
            .on('click', function() {
              $clientInput.val(match);
              $clientSuggestions.empty().hide();
            });
          $clientSuggestions.append($suggestion);
        });
        $clientSuggestions.show();
      }
    });

    // Keyboard navigation
    $clientInput.on('keydown', function(e) {
      const $items = $clientSuggestions.find('.autocomplete-suggestion');
      if (!$items.length) return;

      if (e.keyCode === 40) { // Arrow Down
        currentFocus++;
        setActive($items);
        e.preventDefault();
      } else if (e.keyCode === 38) { // Arrow Up
        currentFocus--;
        setActive($items);
        e.preventDefault();
      } else if (e.keyCode === 13) { // Enter
        if (currentFocus > -1) {
          if ($items.eq(currentFocus).length) {
            $items.eq(currentFocus).trigger('click');
            e.preventDefault();
          }
        }
      } else if (e.keyCode === 27) { // Escape
        $clientSuggestions.empty().hide();
        currentFocus = -1;
      }
    });

    function setActive($items) {
      $items.removeClass('active');
      if (currentFocus >= $items.length) currentFocus = 0;
      if (currentFocus < 0) currentFocus = $items.length - 1;
      const $activeItem = $items.eq(currentFocus);
      $activeItem.addClass('active');

      const containerHeight = $clientSuggestions.height();
      const itemHeight = $activeItem.outerHeight();
      const itemTop = $activeItem.position().top;

      if (itemTop + itemHeight > containerHeight) {
        $clientSuggestions.scrollTop($clientSuggestions.scrollTop() + itemTop + itemHeight - containerHeight);
      } else if (itemTop < 0) {
        $clientSuggestions.scrollTop($clientSuggestions.scrollTop() + itemTop);
      }
    }

    // Close autocomplete when clicking outside
    $(document).on('click', function(e) {
      if (!$(e.target).closest('#client_name').parent().length) {
        $clientSuggestions.empty().hide();
      }
    });
  }

  // Reset button logic
  $('#resetBtn').on('click', function() {
    if (confirm('Are you sure you want to clear the search criteria?')) {
      $('#searchProjectReportsForm')[0].reset();
      $clientSuggestions.empty().hide();
    }
  });

  // Toast notification helper
  function showNotification(message, type = 'success') {
    $('.toast-notification').remove();

    const icon = type === 'success' 
      ? '<i class="fa-solid fa-circle-check toast-icon" style="color: #10b981;"></i>' 
      : '<i class="fa-solid fa-circle-exclamation toast-icon" style="color: #ef4444;"></i>';

    const $toast = $('<div class="toast-notification"></div>')
      .addClass('toast-' + type)
      .html(icon + '<span>' + message + '</span>')
      .appendTo('body');

    setTimeout(() => $toast.addClass('show'), 50);

    setTimeout(() => {
      $toast.removeClass('show');
      setTimeout(() => $toast.remove(), 400);
    }, 4000);
  }

  // Form submission validation: Ensure at least one search criterion is entered
  $('#searchProjectReportsForm').on('submit', function(e) {
    const agent = $('#agent_name').val() ? $('#agent_name').val().trim() : '';
    const fromDate = $('#from_date').val() ? $('#from_date').val().trim() : '';
    const toDate = $('#to_date').val() ? $('#to_date').val().trim() : '';
    const clientName = $('#client_name').val() ? $('#client_name').val().trim() : '';
    const productName = $('#product_name').val() ? $('#product_name').val().trim() : '';
    const productId = $('#product_id').val() ? $('#product_id').val().trim() : '';
    const softwareVersion = $('#software_version').val() ? $('#software_version').val().trim() : '';
    const lcdId = $('#lcd_id').val() ? $('#lcd_id').val().trim() : '';
    const hardwareId = $('#hardware_id').val() ? $('#hardware_id').val().trim() : '';
    const haspId = $('#hasp_id').val() ? $('#hasp_id').val().trim() : '';

    if (!agent && !fromDate && !toDate && !clientName && !productName && !productId && !softwareVersion && !lcdId && !hardwareId && !haspId) {
      e.preventDefault();
      showNotification('Please enter at least one search criteria to search.', 'error');
      return;
    }

    if (fromDate && toDate && new Date(fromDate) > new Date(toDate)) {
      e.preventDefault();
      alert('From Date cannot be greater than To Date. Also, To Date should be greater than or equal to From Date.');
      return;
    }
  });
});

