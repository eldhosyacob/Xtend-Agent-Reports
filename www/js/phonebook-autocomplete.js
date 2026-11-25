(function($) {
  'use strict';

  $(function() {
    const fieldConfigs = [
      {
        key: 'email',
        $input: $('#email'),
        $suggestions: $('#emailSuggestions'),
        metaKeys: ['contact_details', 'phone']
      },
      {
        key: 'phone',
        $input: $('#phone'),
        $suggestions: $('#phoneSuggestions'),
        metaKeys: ['contact_details', 'email']
      },
      {
        key: 'contact_details',
        $input: $('#contact_details'),
        $suggestions: $('#contactDetailsSuggestions'),
        metaKeys: ['phone', 'email']
      }
    ];

    // Only initialize when the phonebook form fields are present
    if (!fieldConfigs.some(cfg => cfg.$input.length && cfg.$suggestions.length)) {
      return;
    }

    const $addPhonebookContainer = $('#addPhonebookCheckboxContainer');
    const $addPhonebookCheckbox = $('#add_to_phonebook');
    let matchCheckTimer = null;
    let matchCheckRequest = null;

    function hideAddPhonebookCheckbox() {
      $addPhonebookContainer.removeClass('is-visible');
      $addPhonebookCheckbox.prop('checked', false);
    }

    function showAddPhonebookCheckbox() {
      if ($addPhonebookCheckbox.is(':disabled')) {
        return;
      }
      $addPhonebookContainer.addClass('is-visible');
    }

    function hasEditablePhonebookInput() {
      return fieldConfigs.some(cfg =>
        cfg.$input.length && !cfg.$input.is(':disabled') && !cfg.$input.prop('readonly')
      );
    }

    function schedulePhonebookMatchCheck(immediate) {
      if (!$addPhonebookContainer.length || !$addPhonebookCheckbox.length) {
        return;
      }

      if (matchCheckTimer) {
        clearTimeout(matchCheckTimer);
        matchCheckTimer = null;
      }

      if (matchCheckRequest && matchCheckRequest.readyState !== 4) {
        matchCheckRequest.abort();
        matchCheckRequest = null;
      }

      if (!hasEditablePhonebookInput()) {
        hideAddPhonebookCheckbox();
        return;
      }

      const contactVal = ($('#contact_details').val() || '').trim();
      const phoneVal = ($('#phone').val() || '').trim();
      const emailVal = ($('#email').val() || '').trim();

      if (!contactVal && !phoneVal && !emailVal) {
        hideAddPhonebookCheckbox();
        return;
      }

      const runCheck = function() {
        matchCheckRequest = $.ajax({
          url: 'api/get-phonebook.php',
          type: 'GET',
          data: {
            check: 1,
            contact_details: contactVal,
            phone: phoneVal,
            email: emailVal
          },
          dataType: 'json',
          success: function(response) {
            const currentContact = ($('#contact_details').val() || '').trim();
            const currentPhone = ($('#phone').val() || '').trim();
            const currentEmail = ($('#email').val() || '').trim();

            if (currentContact !== contactVal || currentPhone !== phoneVal || currentEmail !== emailVal) {
              return;
            }

            if (response && response.success && response.exists === false) {
              showAddPhonebookCheckbox();
            } else {
              hideAddPhonebookCheckbox();
            }
          }
        });
      };

      if (immediate) {
        runCheck();
      } else {
        matchCheckTimer = setTimeout(runCheck, 150);
      }
    }

    function escapeHtml(text) {
      return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function escapeRegex(text) {
      return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function highlightMatch(text, query) {
      const safeStr = String(text ?? '');
      if (!query) {
        return escapeHtml(safeStr);
      }

      const regex = new RegExp(escapeRegex(query), 'gi');
      let result = '';
      let lastIndex = 0;
      let match;

      while ((match = regex.exec(safeStr)) !== null) {
        if (match.index > lastIndex) {
          result += escapeHtml(safeStr.slice(lastIndex, match.index));
        }
        result += '<strong>' + escapeHtml(match[0]) + '</strong>';
        lastIndex = regex.lastIndex;
        if (match[0].length === 0) {
          regex.lastIndex++;
        }
      }

      if (lastIndex < safeStr.length) {
        result += escapeHtml(safeStr.slice(lastIndex));
      }

      return result;
    }

    function closeAllSuggestions() {
      fieldConfigs.forEach(cfg => {
        cfg.$suggestions.empty().hide();
        cfg.currentFocus = -1;
      });
    }

    function applyPhonebookEntry(entry, onlyEmptyFields) {
      if (!entry) return;

      fieldConfigs.forEach(cfg => {
        if (!cfg.$input.length || cfg.$input.is(':disabled') || cfg.$input.prop('readonly')) {
          return;
        }

        const value = entry[cfg.key] != null ? String(entry[cfg.key]).trim() : '';

        if (onlyEmptyFields) {
          if (!value || cfg.$input.val().trim() !== '') {
            return;
          }
        }

        cfg.$input.val(value);
      });
    }

    function scrollActiveIntoView($container, $item) {
      if (!$container.length || !$item.length) return;
      const containerEl = $container[0];
      const itemEl = $item[0];

      if (itemEl.offsetTop < containerEl.scrollTop) {
        containerEl.scrollTop = itemEl.offsetTop;
      } else if (itemEl.offsetTop + itemEl.clientHeight > containerEl.scrollTop + containerEl.clientHeight) {
        containerEl.scrollTop = itemEl.offsetTop + itemEl.clientHeight - containerEl.clientHeight;
      }
    }

    function setActive(cfg, $items) {
      $items.removeClass('active');
      if (cfg.currentFocus >= $items.length) cfg.currentFocus = 0;
      if (cfg.currentFocus < 0) cfg.currentFocus = $items.length - 1;

      const $activeItem = $items.eq(cfg.currentFocus).addClass('active');
      scrollActiveIntoView(cfg.$suggestions, $activeItem);
    }

    function renderSuggestions(cfg, entries, query) {
      cfg.$suggestions.empty().hide();
      cfg.currentFocus = -1;

      if (!Array.isArray(entries) || entries.length === 0) {
        return;
      }

      entries.forEach((entry, index) => {
        const primaryVal = entry[cfg.key] != null ? String(entry[cfg.key]).trim() : '';
        if (!primaryVal) return;

        const metaParts = cfg.metaKeys
          .map(k => (entry[k] != null ? String(entry[k]).trim() : ''))
          .filter(Boolean);

        const $primary = $('<div class="phonebook-suggestion-primary"></div>')
          .html(highlightMatch(primaryVal, query));

        const $suggestion = $('<div class="autocomplete-suggestion"></div>')
          .attr('data-index', index)
          .append($primary);

        if (metaParts.length > 0) {
          const $meta = $('<div class="phonebook-suggestion-meta"></div>')
            .text(metaParts.join(' • '));
          $suggestion.append($meta);
        }

        // Use mousedown to select before blur closes the dropdown
        $suggestion.on('mousedown', function(e) {
          e.preventDefault();
          applyPhonebookEntry(entry, false);
          closeAllSuggestions();
          hideAddPhonebookCheckbox();
        });

        cfg.$suggestions.append($suggestion);
      });

      if (cfg.$suggestions.children().length > 0) {
        cfg.$suggestions.show();
      }
    }

    function hasEmptyCompanionField(activeCfg) {
      return fieldConfigs.some(cfg =>
        cfg.key !== activeCfg.key &&
        cfg.$input.length &&
        !cfg.$input.is(':disabled') &&
        !cfg.$input.prop('readonly') &&
        cfg.$input.val().trim() === ''
      );
    }

    fieldConfigs.forEach(cfg => {
      cfg.currentFocus = -1;
      cfg.debounceTimer = null;
      cfg.activeRequest = null;
      cfg.lastQuery = '';
      cfg.lastResults = [];

      if (!cfg.$input.length || !cfg.$suggestions.length) {
        return;
      }

      cfg.$input.on('input', function() {
        if (cfg.$input.is(':disabled') || cfg.$input.prop('readonly')) {
          return;
        }

        const val = this.value.trim();

        schedulePhonebookMatchCheck(false);

        if (cfg.debounceTimer) {
          clearTimeout(cfg.debounceTimer);
          cfg.debounceTimer = null;
        }

        if (cfg.activeRequest && cfg.activeRequest.readyState !== 4) {
          cfg.activeRequest.abort();
          cfg.activeRequest = null;
        }

        if (!val) {
          cfg.lastQuery = '';
          cfg.lastResults = [];
          cfg.$suggestions.empty().hide();
          cfg.currentFocus = -1;
          return;
        }

        cfg.debounceTimer = setTimeout(function() {
          cfg.activeRequest = $.ajax({
            url: 'api/get-phonebook.php',
            type: 'GET',
            data: { field: cfg.key, q: val },
            dataType: 'json',
            success: function(response) {
              if (cfg.$input.val().trim() !== val) {
                return;
              }
              if (response && response.success && Array.isArray(response.data)) {
                cfg.lastQuery = val;
                cfg.lastResults = response.data;
                renderSuggestions(cfg, response.data, val);
              } else {
                cfg.$suggestions.empty().hide();
              }
            },
            error: function(xhr, status) {
              if (status !== 'abort') {
                cfg.$suggestions.empty().hide();
              }
            }
          });
        }, 150);
      });

      cfg.$input.on('keydown', function(e) {
        const $items = cfg.$suggestions.find('.autocomplete-suggestion');
        if (!$items.length) return;

        if (e.keyCode === 40) {
          cfg.currentFocus++;
          setActive(cfg, $items);
          e.preventDefault();
        } else if (e.keyCode === 38) {
          cfg.currentFocus--;
          setActive(cfg, $items);
          e.preventDefault();
        } else if (e.keyCode === 13) {
          if (cfg.currentFocus > -1) {
            const $selected = $items.eq(cfg.currentFocus);
            if ($selected.length) {
              $selected.trigger('mousedown');
              e.preventDefault();
            }
          }
        } else if (e.keyCode === 27) {
          cfg.$suggestions.empty().hide();
          cfg.currentFocus = -1;
        }
      });

      // On blur, auto-fill empty companion fields if there is a unique exact match
      cfg.$input.on('blur', function() {
        if (cfg.$input.is(':disabled') || cfg.$input.prop('readonly')) {
          return;
        }

        const val = this.value.trim();
        cfg.$suggestions.empty().hide();
        cfg.currentFocus = -1;

        if (!val || !hasEmptyCompanionField(cfg)) {
          return;
        }

        if (cfg.lastQuery === val && cfg.lastResults.length > 0) {
          const exactMatches = cfg.lastResults.filter(entry =>
            entry[cfg.key] != null && String(entry[cfg.key]).trim().toLowerCase() === val.toLowerCase()
          );
          if (exactMatches.length === 1) {
            applyPhonebookEntry(exactMatches[0], true);
            schedulePhonebookMatchCheck(true);
          }
          return;
        }

        $.ajax({
          url: 'api/get-phonebook.php',
          type: 'GET',
          data: { field: cfg.key, q: val, exact: 1 },
          dataType: 'json',
          success: function(response) {
            if (cfg.$input.val().trim() !== val) {
              return;
            }
            if (response && response.success && Array.isArray(response.data) && response.data.length === 1) {
              applyPhonebookEntry(response.data[0], true);
              schedulePhonebookMatchCheck(true);
            }
          }
        });
      });
    });

    $(document).on('click', function(e) {
      fieldConfigs.forEach(cfg => {
        if (!$(e.target).closest(cfg.$input.parent()).length) {
          cfg.$suggestions.empty().hide();
          cfg.currentFocus = -1;
        }
      });
    });

    $('#newRecordForm, #editRecordForm').on('reset', function() {
      closeAllSuggestions();
      hideAddPhonebookCheckbox();
    });

    // Check pre-populated values on initial page load (e.g., edit-record.php)
    schedulePhonebookMatchCheck(true);
  });
})(jQuery);

