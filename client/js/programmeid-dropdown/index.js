// app/client/programmes/js/programmeid-dropdown/index.js

(function ($) {
  $.entwine('ss', function ($) {
    const { prgConstants, prgState, prgApi, prgDom } = window.OPProg || {};
    if (!prgConstants || !prgState || !prgApi || !prgDom) return;

    const { DEBOUNCE_MS } = prgConstants;
    const { ensure, set, get } = prgState;
    const { fetchJSON } = prgApi;
    const {
      buildShell,
      showSelectedState,
      showSearchState,
      renderListbox,
      closeListbox,
      setActiveOption,
      setLoading,
      setStatus,
      ensureSelectOption,
    } = prgDom;

    $('select.js-programmeid-dropdown').entwine({
      onmatch: function () {
        const $host = this;
        if ($host.data('programmeid-init')) {
          this._super();
          return;
        }

        const ui = buildShell($host);
        if (!ui) {
          this._super();
          return;
        }

        const { uid, $holder, $select, $search, $changeBtn, $clearBtn, $listbox } = ui;

        ensure($holder);
        set(
          $holder,
          'endpoint',
          String($select.data('remote-endpoint') || '/admin/programme-options/list').trim()
        );
        set($holder, 'pageSize', parseInt(String($select.data('page-size') || '25'), 10) || 25);
        set($holder, 'q', '');
        set($holder, 'offset', 0);
        set($holder, 'hasNext', false);
        set($holder, 'busy', false);
        set($holder, 'items', []);
        set($holder, 'activeIdx', -1);

        const hiddenName = String($select.data('hidden-field') || 'ProgrammeID');
        const $hidden = $select.closest('form').find(`[name="${hiddenName}"]`);

        // Load existing value — treat "0" as empty (SilverStripe Int columns default to 0)
        const existingId = String($hidden.val() || $select.val() || '').trim();
        if (existingId && existingId !== '0') {
          setLoading(ui, true);
          fetchJSON(get($holder, 'endpoint'), { id: existingId })
            .done((resp) => {
              const item = resp && resp.items && resp.items[0];
              const label = item ? item.label : null;
              ensureSelectOption($select, existingId, label);
              showSelectedState(ui, label || `Programme ID ${existingId}`, existingId);
            })
            .fail(() => {
              showSelectedState(ui, `Programme ID ${existingId}`, existingId);
            })
            .always(() => setLoading(ui, false));
        } else {
          setStatus(ui, 'Search or browse to pick a programme');
        }

        // Search input — load on focus if empty
        $search.on('focus', () => {
          if (!get($holder, 'items').length && !get($holder, 'busy')) {
            doSearch($holder, ui, $hidden, '', 0, false);
          } else if (get($holder, 'items').length && $listbox.attr('hidden')) {
            renderListbox(ui, get($holder, 'items'), -1, get($holder, 'hasNext'));
          }
        });

        let debounceId = null;
        $search.on('input', () => {
          const q = String($search.val() || '').trim();
          set($holder, 'q', q);
          set($holder, 'offset', 0);
          set($holder, 'activeIdx', -1);
          if (debounceId) clearTimeout(debounceId);
          debounceId = setTimeout(() => doSearch($holder, ui, $hidden, q, 0, false), DEBOUNCE_MS);
        });

        $search.on('keydown', (e) => {
          const items = get($holder, 'items');
          let activeIdx = get($holder, 'activeIdx');
          const isOpen = !$listbox.attr('hidden');

          if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!isOpen && items.length) {
              renderListbox(ui, items, 0);
              set($holder, 'activeIdx', 0);
            } else {
              const next = Math.min(activeIdx + 1, items.length - 1);
              set($holder, 'activeIdx', next);
              setActiveOption(ui, next);
            }
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prev = Math.max(activeIdx - 1, 0);
            set($holder, 'activeIdx', prev);
            setActiveOption(ui, prev);
          } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIdx >= 0 && items[activeIdx]) {
              selectItem($holder, ui, $hidden, items[activeIdx]);
            } else {
              if (debounceId) clearTimeout(debounceId);
              doSearch($holder, ui, $hidden, String($search.val() || '').trim(), 0, false);
            }
          } else if (e.key === 'Escape') {
            e.preventDefault();
            closeListbox(ui);
            set($holder, 'activeIdx', -1);
          }
        });

        // Click on a result
        $listbox.on('click', '.programmeid-dd-option', function () {
          const $li = $(this);
          selectItem($holder, ui, $hidden, {
            value: $li.data('pid-value'),
            label: $li.data('pid-label'),
          });
        });

        // Load more (rendered as last <li> inside the listbox)
        $listbox.on('click', '.programmeid-dd-loadmore-item', () => {
          const nextOffset = (get($holder, 'offset') || 0) + get($holder, 'pageSize');
          doSearch($holder, ui, $hidden, get($holder, 'q'), nextOffset, true);
        });

        // Change / Clear
        $changeBtn.on('click', () => {
          const items = get($holder, 'items');
          const currentId = String($hidden.val() || '');
          const currentItem = items.find((it) => String(it.value) === currentId);
          showSearchState(ui);
          if (currentItem) {
            $search.val(currentItem.label);
            doSearch($holder, ui, $hidden, currentItem.label, 0, false);
          } else {
            doSearch($holder, ui, $hidden, '', 0, false);
          }
        });

        $clearBtn.on('click', () => {
          $hidden.val('');
          $select.val('').trigger('chosen:updated').trigger('liszt:updated');
          set($holder, 'items', []);
          set($holder, 'q', '');
          set($holder, 'activeIdx', -1);
          showSearchState(ui);
          closeListbox(ui);
          setStatus(ui, 'Search or browse to pick a programme');
        });

        // Click outside closes the listbox
        $(document).on(`click.${uid}`, (e) => {
          if (!$(e.target).closest(ui.$wrap).length) {
            closeListbox(ui);
          }
        });

        // Form submit — ensure select has the current value for SS validation
        $select.closest('form').on('submit.programmeid', () => {
          const val = $hidden.val();
          if (val) ensureSelectOption($select, val);
        });

        $host.data('programmeid-init', true);
        this._super();
      },

      onunmatch: function () {
        const $host = this;
        const uid = $host.data('programmeid-uid');
        if (uid) $(document).off(`.${uid}`);
        $host.closest('form').off('submit.programmeid');
        this._super();
      },
    });

    function doSearch($holder, ui, $hidden, q, offset, append) {
      if (get($holder, 'busy')) return;
      const endpoint = get($holder, 'endpoint');
      const limit = get($holder, 'pageSize');

      set($holder, 'busy', true);
      setLoading(ui, true);
      setStatus(ui, q ? 'Searching…' : 'Loading programmes…');

      fetchJSON(endpoint, { q, limit, offset })
        .done((resp) => {
          const newItems = Array.isArray(resp.items) ? resp.items : [];
          const hasNext = !!resp.hasNextPage;
          const prior = append ? get($holder, 'items') || [] : [];
          const items = prior.concat(newItems);

          set($holder, 'items', items);
          set($holder, 'hasNext', hasNext);
          set($holder, 'offset', offset);
          set($holder, 'activeIdx', -1);

          renderListbox(ui, items, -1, hasNext);

          if (items.length === 0) {
            setStatus(
              ui,
              q ? 'No programmes match your search' : 'No programmes available',
              'warn'
            );
          } else {
            const total = items.length + (hasNext ? '+' : '');
            setStatus(
              ui,
              `${total} programme${items.length === 1 ? '' : 's'}${q ? ' matching' : ''}`
            );
          }
        })
        .fail((xhr) => {
          const msg =
            xhr && xhr.responseJSON && xhr.responseJSON.error
              ? xhr.responseJSON.error
              : 'Request failed';
          setStatus(ui, msg, 'err');
          closeListbox(ui);
        })
        .always(() => {
          setLoading(ui, false);
          set($holder, 'busy', false);
        });
    }

    function selectItem($holder, ui, $hidden, item) {
      const value = String(item.value || '').trim();
      const label = String(item.label || `Programme ID ${value}`);
      if (!value) return;

      $hidden.val(value);
      ensureSelectOption(ui.$select, value, label);
      showSelectedState(ui, label, value);
      setStatus(ui, '');
    }
  });
})(jQuery);
