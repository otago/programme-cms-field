// app/client/programmes/js/programmeid-dropdown/dom.js

(function (root, $) {
  root.OPProg = root.OPProg || {};

  let _counter = 0;

  function buildShell($holderOrSelect) {
    const $holder = $holderOrSelect.is('select')
      ? $holderOrSelect.closest('.form-group, .field, .programmeid-dd-wrap')
      : $holderOrSelect;

    const $select = $holder.is('select') ? $holder : $holder.find('select').first();
    if (!$select.length) return null;

    // Hide the native select — keep in DOM for SS server-side validation.
    $select.css({
      position: 'absolute',
      width: '1px',
      height: '1px',
      opacity: 0,
      pointerEvents: 'none',
    });

    const uid = 'pid-' + ++_counter;
    const listboxId = uid + '-listbox';

    const $insertTarget = $holder
      .find('.form__field-holder, .fieldholder-small, .middleColumn')
      .first();
    const $container = $insertTarget.length ? $insertTarget : $holder;
    let $wrap = $container.children('.programmeid-dd-wrap').first();
    if (!$wrap.length) {
      $wrap = $('<div class="programmeid-dd-wrap"></div>');
      $container.prepend($wrap);
    }

    if (!$wrap.find('.programmeid-dd-box').length) {
      const placeholder = String($select.data('placeholder') || 'Search programmes…');
      $wrap.append(`
        <div class="programmeid-dd-box">
          <div class="programmeid-dd-selected" hidden>
            <div class="programmeid-dd-selected-info">
              <span class="programmeid-dd-selected-name"></span>
              <span class="programmeid-dd-selected-meta">
                ID:&nbsp;<code class="programmeid-dd-id-value">—</code>
                <button type="button" class="programmeid-dd-copy" aria-label="Copy Programme ID">Copy ID</button>
              </span>
            </div>
            <div class="programmeid-dd-selected-actions">
              <button type="button" class="programmeid-dd-change">Change</button>
              <button type="button" class="programmeid-dd-clear">Clear</button>
            </div>
          </div>

          <div class="programmeid-dd-input-row">
            <input
              type="search"
              class="programmeid-dd-search"
              placeholder="${placeholder}"
              autocomplete="off"
              aria-label="Search programmes"
              role="combobox"
              aria-autocomplete="list"
              aria-expanded="false"
              aria-haspopup="listbox"
              aria-controls="${listboxId}">
            <span class="programmeid-dd-spinner" aria-hidden="true" hidden></span>
          </div>

          <ul class="programmeid-dd-listbox" id="${listboxId}" role="listbox" hidden></ul>
        </div>
        <div class="programmeid-dd-status" role="status" aria-live="polite"></div>
      `);
    }

    const $box = $wrap.find('.programmeid-dd-box');
    const $copyBtn = $box.find('.programmeid-dd-copy');
    const $idValue = $box.find('.programmeid-dd-id-value');

    $copyBtn.off('click').on('click', () => {
      const txt = String($idValue.text() || '').trim();
      if (!txt || txt === '—') return;
      const done = () => {
        $copyBtn.text('Copied!');
        setTimeout(() => $copyBtn.text('Copy ID'), 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(done).catch(done);
      } else {
        const tmp = document.createElement('textarea');
        tmp.value = txt;
        document.body.appendChild(tmp);
        tmp.select();
        try {
          document.execCommand('copy');
        } catch (_) {
          // execCommand may throw in some browsers
        }
        document.body.removeChild(tmp);
        done();
      }
    });

    return {
      uid,
      $holder,
      $select,
      $wrap,
      $box,
      $selected: $box.find('.programmeid-dd-selected'),
      $selectedName: $box.find('.programmeid-dd-selected-name'),
      $idValue,
      $search: $box.find('.programmeid-dd-search'),
      $inputRow: $box.find('.programmeid-dd-input-row'),
      $spinner: $box.find('.programmeid-dd-spinner'),
      $listbox: $box.find('.programmeid-dd-listbox'),
      $status: $wrap.find('.programmeid-dd-status'),
      $changeBtn: $box.find('.programmeid-dd-change'),
      $clearBtn: $box.find('.programmeid-dd-clear'),
    };
  }

  function showSelectedState(ui, label, id) {
    const { $selected, $selectedName, $idValue, $inputRow, $search } = ui;
    $inputRow.hide();
    closeListbox(ui);
    $selectedName.text(label || `Programme ID ${id}`);
    $idValue.text(id || '—');
    $selected.removeAttr('hidden');
    $search.attr('aria-expanded', 'false');
  }

  function showSearchState(ui) {
    const { $selected, $inputRow, $search } = ui;
    $selected.attr('hidden', 'hidden');
    $inputRow.show();
    $search.val('');
    setTimeout(() => $search.trigger('focus'), 0);
  }

  function renderListbox(ui, items, activeIdx, hasNext) {
    const { $listbox, $search } = ui;
    $listbox.empty();

    (items || []).forEach((item, i) => {
      const id = String(item.value || '').trim();
      const label = String(item.label || id);
      const optId = $listbox.attr('id') + '-' + i;
      const $li = $('<li>')
        .attr({ role: 'option', id: optId, tabindex: '-1', 'aria-selected': 'false' })
        .addClass('programmeid-dd-option')
        .data('pid-value', id)
        .data('pid-label', label);
      $li.append($('<span class="programmeid-dd-option-label">').text(label));
      $li.append($('<span class="programmeid-dd-option-id">').text('ID ' + id));
      if (i === activeIdx) {
        $li.addClass('is-active').attr('aria-selected', 'true');
        $search.attr('aria-activedescendant', optId);
      }
      $listbox.append($li);
    });

    if (hasNext) {
      $listbox.append(
        $('<li role="presentation" class="programmeid-dd-loadmore-item">').text(
          'Load more results…'
        )
      );
    }

    if (items && items.length) {
      $listbox.removeAttr('hidden');
      $search.attr('aria-expanded', 'true');
    } else {
      $listbox.attr('hidden', 'hidden');
      $search.attr('aria-expanded', 'false');
    }
  }

  function closeListbox(ui) {
    const { $listbox, $search } = ui;
    $listbox.attr('hidden', 'hidden').empty();
    $search.attr({ 'aria-expanded': 'false', 'aria-activedescendant': '' });
  }

  function setActiveOption(ui, idx) {
    const { $listbox, $search } = ui;
    const $opts = $listbox.find('.programmeid-dd-option');
    $opts.removeClass('is-active').attr('aria-selected', 'false');
    if (idx >= 0 && idx < $opts.length) {
      const $active = $opts.eq(idx);
      $active.addClass('is-active').attr('aria-selected', 'true');
      $search.attr('aria-activedescendant', $active.attr('id'));
      const el = $active[0];
      if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
    } else {
      $search.attr('aria-activedescendant', '');
    }
  }

  function setLoading(ui, isLoading) {
    if (isLoading) ui.$spinner.removeAttr('hidden');
    else ui.$spinner.attr('hidden', 'hidden');
  }

  function setStatus(ui, msg, type) {
    ui.$status.removeClass('is-ok is-warn is-err');
    if (type) ui.$status.addClass('is-' + type);
    ui.$status.text(msg || '');
  }

  function ensureSelectOption($select, value, label) {
    const val = String(value || '').trim();
    if (!val) return;
    const $opt = $select.find(`option[value="${val}"]`);
    if (!$opt.length) {
      $select.append(
        $('<option>')
          .attr('value', val)
          .text(label || `ID ${val}`)
      );
    } else if (label && $opt.text() !== label) {
      $opt.text(label);
    }
    $select.val(val);
    $select.trigger('chosen:updated').trigger('liszt:updated');
  }

  root.OPProg.prgDom = {
    buildShell,
    showSelectedState,
    showSearchState,
    renderListbox,
    closeListbox,
    setActiveOption,
    setLoading,
    setStatus,
    ensureSelectOption,
  };
})(window, jQuery);
