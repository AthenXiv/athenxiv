/* ==========================================================================
   Athenaeum — progressive enhancement only. The site works without JS.
   ========================================================================== */
(function () {
  'use strict';

  var config = window.ATHENAEUM || {};

  /* ---------------------------------------------------------- confirmations */
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) { return; }
    var message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  /* ------------------------------------------------------- repeatable rows */
  function reindex(container) {
    // author_corresponding[] is keyed by row index; renumber after add/remove.
    var rows = container.querySelectorAll('[data-repeat-row]');
    Array.prototype.forEach.call(rows, function (row, index) {
      var box = row.querySelector('input[name^="author_corresponding"]');
      if (box) { box.name = 'author_corresponding[' + index + ']'; }
    });
  }

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest('[data-repeat-add]');
    if (addButton) {
      var key = addButton.getAttribute('data-repeat-add');
      var container = document.querySelector('[data-repeat="' + key + '"]');
      if (!container) { return; }
      var rows = container.querySelectorAll('[data-repeat-row]');
      if (!rows.length) { return; }
      var clone = rows[rows.length - 1].cloneNode(true);
      Array.prototype.forEach.call(clone.querySelectorAll('input, select, textarea'), function (field) {
        if (field.type === 'checkbox' || field.type === 'radio') {
          field.checked = false;
        } else {
          field.value = '';
        }
      });
      container.appendChild(clone);
      reindex(container);
      return;
    }

    var removeButton = event.target.closest('[data-repeat-remove]');
    if (removeButton) {
      var row = removeButton.closest('[data-repeat-row]');
      var parent = row ? row.parentNode : null;
      if (row && parent) {
        // Keep at least one row so the form stays usable.
        if (parent.querySelectorAll('[data-repeat-row]').length > 1) {
          parent.removeChild(row);
          reindex(parent);
        } else {
          Array.prototype.forEach.call(row.querySelectorAll('input, textarea'), function (field) {
            if (field.type !== 'checkbox') { field.value = ''; }
          });
        }
      }
    }
  });

  /* ------------------------------------------------------- file size hints */
  document.addEventListener('change', function (event) {
    var input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') { return; }
    var wrapper = input.parentNode;
    var note = wrapper ? wrapper.querySelector('.file-note') : null;
    if (!note) {
      note = document.createElement('p');
      note.className = 'help file-note';
      if (wrapper) { wrapper.appendChild(note); }
    }
    var files = input.files || [];
    if (!files.length) { note.textContent = ''; return; }
    var total = 0;
    Array.prototype.forEach.call(files, function (file) { total += file.size; });
    note.textContent = files.length + ' × ' + formatSize(total);
  });

  function formatSize(bytes) {
    var units = ['B', 'KB', 'MB', 'GB'];
    var index = 0;
    while (bytes >= 1024 && index < units.length - 1) { bytes /= 1024; index++; }
    return (index === 0 ? bytes : bytes.toFixed(1)) + ' ' + units[index];
  }

  /* --------------------------------------------------- markdown live preview */
  var source = document.querySelector('[data-markdown-source]');
  var target = document.querySelector('[data-markdown-target]');
  if (source && target) {
    var holder = target.closest('[data-markdown-preview]');
    var render = function () {
      var text = source.value;
      if (!text.trim()) {
        if (holder) { holder.hidden = true; }
        target.innerHTML = '';
        return;
      }
      if (holder) { holder.hidden = false; }
      target.innerHTML = miniMarkdown(text);
    };
    var timer = null;
    source.addEventListener('input', function () {
      window.clearTimeout(timer);
      timer = window.setTimeout(render, 250);
    });
    render();
  }

  /**
   * Deliberately tiny: this is a preview, the authoritative renderer is the
   * server (Parsedown in safe mode). HTML is escaped here too.
   */
  function miniMarkdown(text) {
    var escaped = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    var lines = escaped.split(/\r?\n/);
    var html = [];
    var inList = false;
    var inCode = false;

    lines.forEach(function (line) {
      if (/^```/.test(line)) {
        html.push(inCode ? '</code></pre>' : '<pre><code>');
        inCode = !inCode;
        return;
      }
      if (inCode) { html.push(line); return; }

      var heading = line.match(/^(#{1,6})\s+(.*)$/);
      if (heading) {
        if (inList) { html.push('</ul>'); inList = false; }
        var level = heading[1].length;
        html.push('<h' + level + '>' + inline(heading[2]) + '</h' + level + '>');
        return;
      }
      var item = line.match(/^\s*[-*+]\s+(.*)$/);
      if (item) {
        if (!inList) { html.push('<ul>'); inList = true; }
        html.push('<li>' + inline(item[1]) + '</li>');
        return;
      }
      if (!line.trim()) {
        if (inList) { html.push('</ul>'); inList = false; }
        html.push('');
        return;
      }
      if (inList) { html.push('</ul>'); inList = false; }
      html.push('<p>' + inline(line) + '</p>');
    });
    if (inList) { html.push('</ul>'); }
    if (inCode) { html.push('</code></pre>'); }
    return html.join('\n');
  }

  function inline(text) {
    return text
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>')
      .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" rel="nofollow noopener">$1</a>');
  }

  /* -------------------------------------------------------- announcements */
  document.addEventListener('click', function (event) {
    var close = event.target.closest('[data-notice-close]');
    if (!close) { return; }
    var banner = close.closest('[data-notice]');
    if (!banner) { return; }
    var revision = banner.getAttribute('data-notice-revision') || '';
    if (revision) {
      // One year, same path; the server also checks this cookie so the banner
      // never even renders again for this revision.
      document.cookie = 'athenaeum_notice=' + encodeURIComponent(revision)
        + '; path=/; max-age=31536000; samesite=lax';
    }
    banner.parentNode.removeChild(banner);
  });

  /* ------------------------------------------------ linked-account picker */
  var linkForm = document.querySelector('[data-link-form]');
  if (linkForm) {
    var rowsHolder = linkForm.querySelector('[data-link-rows]');
    var panel = linkForm.querySelector('[data-link-panel]');
    var platformSelect = linkForm.querySelector('[data-link-platform]');
    var valueInput = linkForm.querySelector('[data-link-value]');
    var hint = linkForm.querySelector('[data-link-hint]');
    var template = linkForm.querySelector('[data-link-template]');

    var placeholders = {};
    var hints = {};
    Array.prototype.forEach.call(platformSelect ? platformSelect.options : [], function (option) {
      placeholders[option.value] = option.getAttribute('data-placeholder') || '';
      hints[option.value] = option.getAttribute('data-hint') || '';
    });

    var syncHint = function () {
      var key = platformSelect ? platformSelect.value : '';
      if (valueInput) { valueInput.placeholder = placeholders[key] || ''; }
      if (hint) { hint.textContent = hints[key] || ''; }
    };
    if (platformSelect) {
      platformSelect.addEventListener('change', syncHint);
      syncHint();
    }

    var openPanel = function () {
      if (panel) { panel.hidden = false; }
      if (valueInput) { valueInput.focus(); }
    };

    document.addEventListener('click', function (event) {
      if (event.target.closest('[data-link-open]')) { openPanel(); return; }
      if (event.target.closest('[data-link-close]')) {
        if (panel) { panel.hidden = true; }
        return;
      }

      var remove = event.target.closest('[data-link-remove]');
      if (remove && linkForm.contains(remove)) {
        var row = remove.closest('[data-link-row]');
        if (row) { row.parentNode.removeChild(row); }
        var empty = linkForm.querySelector('[data-link-empty]');
        if (empty) { empty.hidden = false; }
        return;
      }

      if (event.target.closest('[data-link-add]')) {
        var key = platformSelect ? platformSelect.value : '';
        var value = valueInput ? valueInput.value.trim() : '';
        if (!key || !value) {
          if (valueInput) { valueInput.focus(); }
          return;
        }
        // One row per platform: replace an existing one instead of duplicating.
        var existing = linkForm.querySelector('[data-link-row][data-platform="' + key + '"]');
        if (existing) {
          var field = existing.querySelector('input');
          if (field) { field.value = value; }
        } else if (template) {
          var fragment = template.content.cloneNode(true);
          var newRow = fragment.querySelector('[data-link-row]');
          newRow.setAttribute('data-platform', key);
          var label = newRow.querySelector('[data-link-label]');
          var option = platformSelect ? platformSelect.options[platformSelect.selectedIndex] : null;
          label.textContent = option ? option.textContent : key;
          var input = newRow.querySelector('[data-link-input]');
          input.name = 'link_' + key;
          input.value = value;
          rowsHolder.appendChild(fragment);
        }
        var placeholder = linkForm.querySelector('[data-link-empty]');
        if (placeholder) { placeholder.hidden = true; }
        if (valueInput) { valueInput.value = ''; }
        if (panel) { panel.hidden = true; }
      }
    });
  }

  /* -------------------------------------- language "other" free-text field */
  var languageSelect = document.querySelector('[data-language-select]');
  if (languageSelect) {
    var customRow = document.querySelector('[data-language-custom]');
    var customInput = customRow ? customRow.querySelector('input') : null;
    var syncLanguage = function () {
      var isOther = customRow && languageSelect.value === languageSelect.getAttribute('data-other-value');
      if (customRow) { customRow.hidden = !isOther; }
      if (customInput) { customInput.required = !!isOther; }
    };
    languageSelect.addEventListener('change', syncLanguage);
    syncLanguage();
  }

  /* ---------------------------------------------- admin batch selection UI */
  document.addEventListener('click', function (event) {
    var all = event.target.closest('[data-select-all]');
    var invert = event.target.closest('[data-select-invert]');
    if (!all && !invert) { return; }
    var scope = (all || invert).getAttribute('data-select-all') || (all || invert).getAttribute('data-select-invert');
    var boxes = document.querySelectorAll('input[name="' + (scope || 'paper_ids') + '[]"]');
    if (!boxes.length) { boxes = document.querySelectorAll('input[name="' + (scope || 'paper_ids') + '"]'); }
    Array.prototype.forEach.call(boxes, function (box) {
      box.checked = all ? true : !box.checked;
    });
  });

  /* --------------------------------------------- announcement colour picker */
  var colorSelect = document.querySelector('[data-notice-color]');
  if (colorSelect) {
    var customRow = document.querySelector('[data-notice-custom]');
    var customInput = document.querySelector('[data-notice-color-input]');
    var customValue = document.querySelector('[data-notice-color-value]');
    var syncColor = function () {
      var isCustom = colorSelect.value === 'custom';
      if (customRow) { customRow.hidden = !isCustom; }
      if (customInput) { customInput.name = isCustom ? 'notice_color_custom' : ''; }
      if (customValue) { customValue.textContent = isCustom && customInput ? customInput.value : ''; }
    };
    colorSelect.addEventListener('change', syncColor);
    if (customInput) { customInput.addEventListener('input', syncColor); }
    syncColor();
  }

  /* --------------------------------------------------- searchable pickers */
  /* A picker is a search box plus the native <select> it drives: the select
     stays the single source of truth (so the form still works without JS), the
     box filters it and offers a clickable shortlist. Used for the paper
     language and the subject area, both of which have hundreds of entries. */
  var normalise = function (value) {
    return (value || '').toString().toLowerCase();
  };

  Array.prototype.forEach.call(document.querySelectorAll('[data-picker]'), function (picker) {
    var form = picker.closest('form') || document;
    var search = picker.querySelector('[data-picker-search]');
    var list = picker.querySelector('[data-picker-list]');
    // A form may hold several pickers: the search box names its select target.
    var target = search ? search.getAttribute('aria-controls') : null;
    var select = target ? form.querySelector('#' + target) : form.querySelector('[data-picker-select]');
    if (!search || !list || !select) { return; }

    var options = Array.prototype.slice.call(select.options);
    var selectedLabel = function () {
      var option = select.options[select.selectedIndex];
      return option ? option.textContent.trim() : '';
    };

    var render = function (query) {
      var needle = normalise(query).trim();
      var shown = 0;
      list.innerHTML = '';
      for (var i = 0; i < options.length; i++) {
        var option = options[i];
        if (option.disabled || option.hidden) { continue; }
        var haystack = normalise(option.getAttribute('data-search') || option.textContent);
        if (needle !== '' && haystack.indexOf(needle) === -1) { continue; }
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'picker__option' + (option.selected ? ' is-selected' : '');
        button.setAttribute('data-value', option.value);
        button.textContent = option.textContent.trim();
        list.appendChild(button);
        shown++;
        if (shown >= 60) { break; }
      }
      if (shown === 0) {
        var empty = document.createElement('p');
        empty.className = 'picker__empty muted small';
        empty.textContent = (config.i18n && config.i18n.no_results) || '—';
        list.appendChild(empty);
      }
      list.hidden = false;
    };

    search.addEventListener('focus', function () { render(search.value); });
    search.addEventListener('input', function () { render(search.value); });
    search.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { list.hidden = true; return; }
      if (event.key === 'Enter') {
        var first = list.querySelector('.picker__option');
        if (first) {
          event.preventDefault();
          first.click();
        }
      }
    });

    list.addEventListener('click', function (event) {
      var button = event.target.closest('.picker__option');
      if (!button) { return; }
      select.value = button.getAttribute('data-value');
      // Let dependent widgets (the free-text "other" fields) react.
      select.dispatchEvent(new Event('change', { bubbles: true }));
      list.hidden = true;
      search.value = '';
      search.blur();
    });

    document.addEventListener('click', function (event) {
      if (!picker.contains(event.target) && !list.hidden) { list.hidden = true; }
    });

    select.addEventListener('change', function () {
      search.placeholder = selectedLabel() || search.placeholder;
    });
    search.placeholder = search.placeholder || selectedLabel();
  });

  /* ------------------------------------- "Other / not listed" area field */
  var areaSelect = document.querySelector('[data-area-select]');
  if (areaSelect) {
    var areaOtherRow = document.querySelector('[data-area-other]');
    var areaOtherInput = areaOtherRow ? areaOtherRow.querySelector('input') : null;
    var syncArea = function () {
      var isOther = areaSelect.value === areaSelect.getAttribute('data-other-value');
      if (areaOtherRow) { areaOtherRow.hidden = !isOther; }
      if (areaOtherInput) { areaOtherInput.required = !!isOther; }
    };
    areaSelect.addEventListener('change', syncArea);
    syncArea();
  }

  /* ----------------------------------- searchable multi-area filter list */
  // Drop empty filter fields before submitting so the URL stays readable
  // (an empty language select used to produce "…&language=").
  var filterForm = document.querySelector('[data-filters]');
  if (filterForm) {
    filterForm.addEventListener('submit', function () {
      Array.prototype.forEach.call(filterForm.querySelectorAll('input, select'), function (field) {
        if (field.value === '' && !field.disabled) {
          field.disabled = true; // disabled controls are not submitted
        }
      });
    });
  }

  var areaFilter = document.querySelector('[data-area-filter]');
  if (areaFilter) {
    var areaSearch = areaFilter.querySelector('[data-area-search]');
    var areaOptions = Array.prototype.slice.call(areaFilter.querySelectorAll('[data-area-option]'));
    var areaEmpty = areaFilter.querySelector('[data-area-empty]');
    var areaSummary = areaFilter.querySelector('[data-area-summary]');
    var checkboxes = Array.prototype.slice.call(areaFilter.querySelectorAll('input[type=checkbox]'));
    var total = checkboxes.length;
    var template = (areaSummary && areaSummary.getAttribute('data-template')) || ':count';

    var updateSummary = function () {
      if (!areaSummary) { return; }
      var chosen = checkboxes.filter(function (box) { return box.checked; }).length;
      areaSummary.textContent = template
        .replace(':count', chosen)
        .replace(':total', total);
    };

    if (areaSearch) {
      areaSearch.addEventListener('input', function () {
        var needle = normalise(areaSearch.value).trim();
        var shown = 0;
        areaOptions.forEach(function (option) {
          var haystack = normalise(option.getAttribute('data-name'));
          var match = needle === '' || haystack.indexOf(needle) !== -1;
          option.hidden = !match;
          if (match) { shown++; }
        });
        if (areaEmpty) { areaEmpty.hidden = shown !== 0; }
      });
    }

    checkboxes.forEach(function (box) { box.addEventListener('change', updateSummary); });
    updateSummary();
  }

  /* ------------------------------- fill an untranslated page from English */
  var fillButton = document.querySelector('[data-fill-from-fallback]');
  if (fillButton) {
    var sourceTitle = document.querySelector('[data-fallback-title]');
    var sourceContent = document.querySelector('[data-fallback-content]');
    fillButton.addEventListener('click', function () {
      var title = document.getElementById('title');
      var content = document.getElementById('content');
      if (title && sourceTitle && title.value.trim() === '') { title.value = sourceTitle.value; }
      if (content && sourceContent && content.value.trim() === '') { content.value = sourceContent.value; }
      if (content) {
        content.dispatchEvent(new Event('input', { bubbles: true }));
        content.focus();
      }
      fillButton.disabled = true;
    });
  }

  /* ----------------------------------------------- e-mail verification code */
  // The block is the *container*, not the <form>: a page may keep the code and
  // the new password in one form, and then the request has to go somewhere else.
  // Everything the handler needs — the button, the status line, the address —
  // must live inside this element, so the lookup is scoped to it.
  var codeBlock = document.querySelector('[data-email-code-block]');
  if (codeBlock) {    var codeButton = codeBlock.querySelector('[data-send-code]');
    var codeStatus = codeBlock.querySelector('[data-code-status]');
    // The address may sit inside the block (recovery page) or earlier in the
    // same form (registration, where the code block comes after it).
    var emailField = codeBlock.querySelector('input[name="email"]') || document.querySelector('input[name="email"]');
    var endpoint = codeBlock.getAttribute('data-endpoint');
    var cooldown = 0;
    var timer = null;

    var say = function (message, ok) {
      if (!codeStatus) {
        // Without a place to print, a silent failure is worse than a loud one.
        window.alert(message);
        return;
      }
      codeStatus.hidden = false;
      codeStatus.textContent = message;
      codeStatus.className = 'small ' + (ok ? 'ok' : 'danger');
    };

    var tick = function () {
      if (cooldown <= 0) {
        codeButton.disabled = false;
        codeButton.textContent = codeButton.getAttribute('data-label') || codeButton.textContent;
        window.clearInterval(timer);
        return;
      }
      cooldown -= 1;
      codeButton.disabled = true;
      codeButton.textContent = (codeButton.getAttribute('data-label') || '') + ' (' + cooldown + ')';
    };

    if (codeButton) {
      codeButton.setAttribute('data-label', codeButton.textContent.trim());
      codeButton.addEventListener('click', function () {
        var email = emailField ? emailField.value.trim() : '';
        if (!email) {
          say((config.i18n && config.i18n.email_required) || 'e-mail required', false);
          if (emailField) { emailField.focus(); }
          return;
        }
        codeButton.disabled = true;
        var body = new URLSearchParams();
        body.set('_token', codeBlock.getAttribute('data-csrf') || '');
        body.set('email', email);
        // No locale is sent: the server writes the message in the language of
        // this request's session, which is the one the visitor is reading.

        fetch(endpoint, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString(),
          credentials: 'same-origin'
        }).then(function (response) {
          return response.json().catch(function () { return { ok: false, error: 'unexpected response' }; });
        }).then(function (data) {
          say(data.message || data.error || '…', !!data.ok);
          if (data.ok) {
            cooldown = 60;
            tick();
            timer = window.setInterval(tick, 1000);
          } else {
            codeButton.disabled = false;
          }
        }).catch(function () {
          say((config.i18n && config.i18n.error) || 'error', false);
          codeButton.disabled = false;
        });
      });
    }
  }

  /* ------------------------------------------------------------ copy hashes */
  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-copy]');
    if (!trigger || !navigator.clipboard) { return; }
    var value = trigger.getAttribute('data-copy') || '';
    navigator.clipboard.writeText(value).then(function () {
      var original = trigger.textContent;
      trigger.textContent = (config.i18n && config.i18n.copied) || 'copied';
      window.setTimeout(function () { trigger.textContent = original; }, 1200);
    });
  });
})();
