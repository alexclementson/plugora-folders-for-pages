(function(){
  if (!window.wp || !wp.apiFetch || !window.PlugoraFolders) return;
  var cfg = window.PlugoraFolders;

  // Wire nonce + root URL so REST calls authenticate properly.
  if (wp.apiFetch.createNonceMiddleware) {
    wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(cfg.nonce));
  }
  if (wp.apiFetch.createRootURLMiddleware) {
    wp.apiFetch.use(wp.apiFetch.createRootURLMiddleware(cfg.restRoot));
  }

  function api(path, opts) {
    return wp.apiFetch(Object.assign({ path: cfg.ns + path }, opts || {}));
  }

  // ---------- Pages list: inline folder assignment ----------
  function bindPagesList() {
    document.addEventListener('change', function(e) {
      var sel = e.target;
      if (!sel.classList || !sel.classList.contains('plugora-assign')) return;
      var pageId = parseInt(sel.dataset.page, 10);
      var folderId = sel.value === '' ? null : parseInt(sel.value, 10);
      var status = sel.parentNode.querySelector('.plugora-assign-status');
      if (status) { status.textContent = 'Saving…'; status.className = 'plugora-assign-status'; }
      api('/assign', { method: 'POST', data: { page_id: pageId, folder_id: folderId } })
        .then(function() {
          if (status) { status.textContent = 'Saved'; status.className = 'plugora-assign-status ok'; setTimeout(function(){ status.textContent=''; }, 1500); }
        })
        .catch(function(err) {
          if (status) { status.textContent = 'Error'; status.className = 'plugora-assign-status err'; }
          console.error('Plugora assign failed', err);
        });
    });
  }

  // Track the row currently being dragged so the sidebar drop handler can
  // do optimistic UI + revert on failure.
  var dragState = null; // { tr, pageId, prevFolderId, prevFolderLabel, dropped }

  function flashRow(tr, cls) {
    if (!tr) return;
    tr.classList.remove('plugora-flash-ok','plugora-flash-err','plugora-snap-back');
    // Force reflow so re-adding the class restarts the animation.
    void tr.offsetWidth;
    tr.classList.add(cls);
    setTimeout(function(){ tr.classList.remove(cls); }, 700);
  }

  function setRowFolder(tr, folderId, folderLabel) {
    if (!tr) return;
    var sel = tr.querySelector('select.plugora-assign');
    if (sel) sel.value = folderId == null ? '' : String(folderId);
  }

  // Make Pages list rows draggable so users can drop them on a folder in the sidebar.
  function bindPagesListDrag() {
    function pageIdFromRow(tr) {
      if (!tr || !tr.id) return 0;
      var m = tr.id.match(/^post-(\d+)$/);
      return m ? parseInt(m[1], 10) : 0;
    }
    function makeDraggable(tr) {
      if (!tr || tr.dataset.plugoraDraggable === '1') return;
      var id = pageIdFromRow(tr);
      if (!id) return;
      tr.dataset.plugoraDraggable = '1';
      tr.setAttribute('draggable', 'true');
      tr.classList.add('plugora-draggable-row');
      tr.addEventListener('dragstart', function(e) {
        if (!e.dataTransfer) return;
        var titleEl = tr.querySelector('.row-title');
        var label = titleEl ? titleEl.textContent : ('Page #' + id);
        var sel = tr.querySelector('select.plugora-assign');
        var prevFolderId = sel && sel.value !== '' ? parseInt(sel.value, 10) : null;
        var prevFolderLabel = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text : '';
        dragState = { tr: tr, pageId: id, prevFolderId: prevFolderId, prevFolderLabel: prevFolderLabel, dropped: false };
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plugora-page', String(id));
        e.dataTransfer.setData('text/plain', label);
        tr.classList.add('plugora-dragging');
        document.body.classList.add('plugora-dragging-active');
      });
      tr.addEventListener('dragend', function(e) {
        tr.classList.remove('plugora-dragging');
        document.body.classList.remove('plugora-dragging-active');
        // If the user released outside any folder target, gently snap the row back.
        var landed = dragState && dragState.dropped;
        var validDrop = e.dataTransfer && e.dataTransfer.dropEffect && e.dataTransfer.dropEffect !== 'none';
        if (!landed && !validDrop) {
          flashRow(tr, 'plugora-snap-back');
        }
        dragState = null;
      });
    }
    function scan() {
      document.querySelectorAll('table.wp-list-table tbody tr[id^="post-"]').forEach(makeDraggable);
    }
    scan();
    // Re-scan after WP quick-edit / async updates re-render rows.
    var obs = new MutationObserver(scan);
    var tbody = document.querySelector('table.wp-list-table tbody');
    if (tbody) obs.observe(tbody, { childList: true, subtree: false });
  }


  // ---------- Folders admin page ----------
  var root = document.getElementById('plugora-folders-app');
  var folders = [];
  var active = null;

  function load() {
    return api('/folders').then(function(rows) { folders = rows || []; render(); });
  }

  function createFolder() {
    var name = prompt('Folder name');
    if (!name) return;
    api('/folders', { method: 'POST', data: { name: name } }).then(load);
  }
  function renameFolder(f) {
    var name = prompt('Rename folder', f.name);
    if (!name) return;
    api('/folders/' + f.id, { method: 'PATCH', data: { name: name } }).then(load);
  }
  function removeFolder(f) {
    if (!confirm('Delete "' + f.name + '"?')) return;
    api('/folders/' + f.id, { method: 'DELETE' }).then(load);
  }

  function render() {
    if (!root) return;
    root.className = 'plugora-app';
    root.innerHTML = '';

    var toolbar = document.createElement('div');
    toolbar.className = 'plugora-toolbar';
    var add = document.createElement('button');
    add.className = 'plugora-btn';
    add.textContent = '+ New folder';
    add.onclick = createFolder;
    toolbar.appendChild(add);
    root.appendChild(toolbar);

    var grid = document.createElement('div');
    grid.className = 'plugora-grid';

    var left = document.createElement('div');
    if (!folders.length) {
      var e = document.createElement('div');
      e.className = 'plugora-empty';
      e.textContent = 'No folders yet';
      left.appendChild(e);
    }
    folders.forEach(function(f) {
      var row = document.createElement('div');
      row.className = 'plugora-folder' + (active === f.id ? ' active' : '');
      row.onclick = function() { active = f.id; render(); };
      var name = document.createElement('span'); name.textContent = f.name;
      var actions = document.createElement('span');
      var r = document.createElement('button');
      r.className = 'plugora-btn secondary'; r.textContent = 'Rename';
      r.onclick = function(ev) { ev.stopPropagation(); renameFolder(f); };
      var d = document.createElement('button');
      d.className = 'plugora-btn secondary'; d.style.marginLeft = '6px'; d.textContent = 'Delete';
      d.onclick = function(ev) { ev.stopPropagation(); removeFolder(f); };
      actions.appendChild(r); actions.appendChild(d);
      row.appendChild(name); row.appendChild(actions);
      left.appendChild(row);
    });
    grid.appendChild(left);

    var right = document.createElement('div');
    right.className = 'plugora-empty';
    right.innerHTML = 'Go to <strong>Pages → All Pages</strong>. Each row has a <em>Folder</em> dropdown — change it to assign instantly. Use <em>Bulk Edit</em> to move many pages at once, or the <em>All folders</em> filter to view a single folder.';
    grid.appendChild(right);

    root.appendChild(grid);
  }

  // ---------- Pages screen: folder tree sidebar ----------
  function getFilterParam() {
    var m = window.location.search.match(/[?&]plugora_folder=([^&]*)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  function buildFolderUrl(value) {
    var url = new URL(window.location.href);
    if (value === '' || value === null || typeof value === 'undefined') {
      url.searchParams.delete('plugora_folder');
    } else {
      url.searchParams.set('plugora_folder', String(value));
    }
    url.searchParams.delete('paged');
    return url.toString();
  }
  function buildSidebar(items) {
    var current = getFilterParam();
    var sidebar = document.createElement('aside');
    sidebar.className = 'plugora-pages-sidebar';

    // ---- Header ----
    var head = document.createElement('div');
    head.className = 'plugora-tree-head';
    head.innerHTML = '<span><span class="dashicons dashicons-portfolio"></span>Folders</span>';
    var headActions = document.createElement('span');
    headActions.className = 'plugora-head-actions';
    var addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'plugora-tree-add';
    addBtn.title = 'New folder';
    addBtn.setAttribute('aria-label', 'New folder');
    addBtn.innerHTML = '<span class="dashicons dashicons-plus-alt2"></span>';
    addBtn.addEventListener('click', function() {
      var name = prompt('Folder name');
      if (!name) return;
      api('/folders', { method: 'POST', data: { name: name } }).then(function() {
        sessionStorage.removeItem('plugora_sidebar_v1');
        window.location.reload();
      });
    });
    var collapseBtn = document.createElement('button');
    collapseBtn.type = 'button';
    collapseBtn.className = 'plugora-tree-iconbtn';
    collapseBtn.title = 'Collapse all';
    collapseBtn.setAttribute('aria-label', 'Collapse all');
    collapseBtn.innerHTML = '<span class="dashicons dashicons-menu-alt2"></span>';
    headActions.appendChild(addBtn);
    headActions.appendChild(collapseBtn);
    head.appendChild(headActions);
    sidebar.appendChild(head);

    // ---- Search ----
    var settings = (cfg.settings && typeof cfg.settings === 'object') ? cfg.settings : {};
    var showSearch  = settings.show_search  === undefined ? true : !!parseInt(settings.show_search, 10);
    var showCounts  = settings.show_counts  === undefined ? true : !!parseInt(settings.show_counts, 10);
    var showUnfiled = settings.show_unfiled === undefined ? true : !!parseInt(settings.show_unfiled, 10);

    var searchInput = null;
    if (showSearch) {
      var searchWrap = document.createElement('div');
      searchWrap.className = 'plugora-tree-search';
      searchInput = document.createElement('input');
      searchInput.type = 'search';
      searchInput.placeholder = 'Search folders…';
      searchWrap.appendChild(searchInput);
      sidebar.appendChild(searchWrap);
    }

    // ---- Scroll area ----
    var scroll = document.createElement('div');
    scroll.className = 'plugora-tree-scroll';
    sidebar.appendChild(scroll);

    // Group folders by parent for nested rendering.
    var folders = items.folders || [];
    var byParent = {};
    folders.forEach(function(f){
      var p = f.parent_id == null || f.parent_id === '0' || f.parent_id === 0 ? '__root__' : String(f.parent_id);
      (byParent[p] = byParent[p] || []).push(f);
    });

    // Persist expand state across reloads.
    var EXPAND_KEY = 'plugora_expanded_v1';
    var expanded = {};
    try { expanded = JSON.parse(sessionStorage.getItem(EXPAND_KEY) || '{}') || {}; } catch(e) {}
    function saveExpanded(){ try { sessionStorage.setItem(EXPAND_KEY, JSON.stringify(expanded)); } catch(e){} }

    function safe(s){ return String(s).replace(/[&<>"]/g, function(ch){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch];}); }

    function rowEl(opts){
      var li = document.createElement('li');
      li.dataset.filterText = (opts.filterText || opts.label || '').toLowerCase();
      var rowDiv = document.createElement('div');
      rowDiv.className = 'plugora-row' + (String(current) === String(opts.value) ? ' active' : '');
      rowDiv.style.paddingLeft = (opts.depth ? (opts.depth * 14) : 0) + 'px';

      var tw = document.createElement('button');
      tw.type = 'button';
      tw.className = 'plugora-twisty' + (opts.hasChildren ? '' : ' empty');
      var open = !!expanded[opts.id];
      tw.setAttribute('aria-expanded', open ? 'true' : 'false');
      tw.innerHTML = '<span class="dashicons dashicons-arrow-right"></span>';
      rowDiv.appendChild(tw);

      var a = document.createElement('a');
      a.href = buildFolderUrl(opts.value);
      var iconHtml = opts.color
        ? '<span class="plugora-cdot c-' + safe(opts.color) + '"></span>'
        : '<span class="dashicons dashicons-' + (opts.icon || 'category') + '"></span>';
      var countHtml = showCounts ? '<span class="plugora-count">' + (opts.count == null ? '' : opts.count) + '</span>' : '';
      a.innerHTML = '<span class="plugora-tree-label">' + iconHtml +
        '<span class="plugora-name">' + safe(opts.label) + '</span></span>' + countHtml;
      rowDiv.appendChild(a);
      li.appendChild(rowDiv);

      var isUnfiledTarget = opts.value === '-1';
      if ((opts.id && typeof opts.id === 'number') || isUnfiledTarget) {
        var dropFolderId = isUnfiledTarget ? null : opts.id;
        rowDiv.addEventListener('dragover', function(e){ if (e.dataTransfer && e.dataTransfer.types.indexOf('text/plugora-page') !== -1) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; rowDiv.classList.add('drop-target'); } });
        rowDiv.addEventListener('dragleave', function(){ rowDiv.classList.remove('drop-target'); });
        rowDiv.addEventListener('drop', function(e){
          rowDiv.classList.remove('drop-target');
          var pageId = e.dataTransfer && e.dataTransfer.getData('text/plugora-page');
          if (!pageId) return;
          e.preventDefault();
          var pid = parseInt(pageId, 10);
          var snap = dragState && dragState.pageId === pid ? dragState : null;
          if (snap) snap.dropped = true;

          // No-op: dropped on the same folder it was already in. Just give feedback.
          var samePlace = snap && ((snap.prevFolderId || null) === (dropFolderId || null));
          if (samePlace) { flashRow(snap.tr, 'plugora-snap-back'); return; }

          // Optimistic UI: update the row's select immediately.
          if (snap) setRowFolder(snap.tr, dropFolderId, opts.label);
          rowDiv.classList.add('drop-pending');

          api('/assign', { method: 'POST', data: { page_id: pid, folder_id: dropFolderId } })
            .then(function(){
              rowDiv.classList.remove('drop-pending');
              if (snap) flashRow(snap.tr, 'plugora-flash-ok');
              // Reload so counts + filtered view stay accurate.
              window.location.reload();
            })
            .catch(function(err){
              console.error('Plugora drop assign failed', err);
              rowDiv.classList.remove('drop-pending');
              // Revert: restore the row's previous folder and snap-back animate.
              if (snap) {
                setRowFolder(snap.tr, snap.prevFolderId, snap.prevFolderLabel);
                flashRow(snap.tr, 'plugora-flash-err');
              }
            });
        });
      }

      var childUl = null;
      if (opts.hasChildren) {
        childUl = document.createElement('ul');
        childUl.style.display = open ? 'block' : 'none';
        li.appendChild(childUl);
        tw.addEventListener('click', function(e){
          e.preventDefault(); e.stopPropagation();
          var nowOpen = childUl.style.display === 'none';
          childUl.style.display = nowOpen ? 'block' : 'none';
          tw.setAttribute('aria-expanded', nowOpen ? 'true' : 'false');
          expanded[opts.id] = nowOpen;
          saveExpanded();
        });
      }
      return { li: li, childUl: childUl };
    }

    var pinned = document.createElement('ul');
    pinned.className = 'plugora-tree plugora-pinned';
    pinned.appendChild(rowEl({label:'All pages',value:'',count:items.totals && items.totals.all,icon:'admin-page',depth:0,id:'__all__'}).li);
    if (showUnfiled) {
      pinned.appendChild(rowEl({label:'Unfiled',value:'-1',count:items.totals && items.totals.unfiled,icon:'inbox',depth:0,id:'__unfiled__'}).li);
    }
    scroll.appendChild(pinned);

    var rootUl = document.createElement('ul');
    rootUl.className = 'plugora-tree';
    scroll.appendChild(rootUl);

    function renderChildren(parentKey, parentUl, depth){
      (byParent[parentKey] || []).forEach(function(f){
        var children = byParent[String(f.id)] || [];
        var made = rowEl({
          label: f.name, value: f.id, count: f.count, icon: 'category',
          color: f.color, depth: depth, hasChildren: children.length > 0, id: f.id,
        });
        parentUl.appendChild(made.li);
        if (made.childUl) renderChildren(String(f.id), made.childUl, depth + 1);
      });
    }

    if (!folders.length) {
      var empty = document.createElement('div');
      empty.className = 'plugora-tree-empty';
      empty.textContent = 'No folders yet — click + to create one.';
      scroll.appendChild(empty);
    } else {
      renderChildren('__root__', rootUl, 0);
    }

    if (searchInput) {
      searchInput.addEventListener('input', function(){
        var q = searchInput.value.trim().toLowerCase();
        var lis = scroll.querySelectorAll('li');
        lis.forEach(function(li){
          if (!q) { li.style.display = ''; return; }
          var match = (li.dataset.filterText || '').indexOf(q) !== -1;
          li.style.display = match ? '' : 'none';
          if (match) {
            var p = li.parentElement;
            while (p && p !== scroll) {
              if (p.tagName === 'LI') p.style.display = '';
              if (p.tagName === 'UL') p.style.display = 'block';
              p = p.parentElement;
            }
          }
        });
      });
    }

    collapseBtn.addEventListener('click', function(){
      expanded = {}; saveExpanded();
      scroll.querySelectorAll('.plugora-twisty').forEach(function(t){ t.setAttribute('aria-expanded','false'); });
      scroll.querySelectorAll('li > ul').forEach(function(u){ u.style.display = 'none'; });
    });

    return sidebar;
  }

  function mountSidebar(items) {
    var wrap = document.querySelector('.wrap');
    if (!wrap || wrap.classList.contains('plugora-mounted')) return;
    wrap.classList.add('plugora-mounted');

    // Anchor: the standard wp-header-end <hr>. Everything after it is the list table area.
    var anchor = wrap.querySelector('.wp-header-end') || wrap.querySelector('hr.wp-header-end');
    if (!anchor) {
      var h1 = wrap.querySelector('h1.wp-heading-inline') || wrap.querySelector('h1');
      anchor = h1;
    }

    var layout = document.createElement('div');
    layout.className = 'plugora-pages-layout';
    var sidebar = buildSidebar(items);
    var main = document.createElement('div');
    main.className = 'plugora-pages-main';

    // Mobile drawer toggle button
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'plugora-drawer-toggle';
    toggle.innerHTML = '<span class="dashicons dashicons-category"></span><span>Folders</span>';
    var backdrop = document.createElement('div');
    backdrop.className = 'plugora-drawer-backdrop';
    var closeDrawer = function(){ sidebar.classList.remove('open'); backdrop.classList.remove('open'); };
    toggle.addEventListener('click', function(){
      var open = sidebar.classList.toggle('open');
      backdrop.classList.toggle('open', open);
    });
    backdrop.addEventListener('click', closeDrawer);
    sidebar.addEventListener('click', function(e){
      if (e.target.closest('a')) closeDrawer();
    });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeDrawer(); });

    var node = anchor.nextSibling;
    while (node) {
      var next = node.nextSibling;
      main.appendChild(node);
      node = next;
    }
    main.insertBefore(toggle, main.firstChild);
    layout.appendChild(sidebar);
    layout.appendChild(main);
    wrap.appendChild(layout);
    document.body.appendChild(backdrop);
  }

  function loadSidebar() {
    var CACHE_KEY = 'plugora_sidebar_v1';
    // 1) Instant paint from cache (no flash of un-styled list).
    try {
      var cached = sessionStorage.getItem(CACHE_KEY);
      if (cached) mountSidebar(JSON.parse(cached));
    } catch(e) {}
    // 2) Fetch fresh data and replace.
    api('/folders?with_counts=1').then(function(data) {
      var payload = Array.isArray(data) ? { folders: data, totals: {} } : (data || {});
      try { sessionStorage.setItem(CACHE_KEY, JSON.stringify(payload)); } catch(e) {}
      var wrap = document.querySelector('.wrap.plugora-mounted');
      if (wrap) {
        var oldSidebar = wrap.querySelector('.plugora-pages-sidebar');
        if (oldSidebar && oldSidebar.parentNode) {
          oldSidebar.parentNode.replaceChild(buildSidebar(payload), oldSidebar);
          return;
        }
      }
      mountSidebar(payload);
    }).catch(function(err) { console.error('Plugora sidebar failed', err); });
  }

  // ---------- Settings page: color picker ----------
  function bindColorPicker() {
    var pickers = document.querySelectorAll('.plugora-color-picker');
    pickers.forEach(function(picker){
      var addBtn = picker.querySelector('.plugora-color-add');
      var input  = picker.querySelector('.plugora-color-input');
      if (!addBtn || !input) return;
      var inputName = picker.getAttribute('data-input-name') || 'plugora_folders_settings[colors][]';

      function makeSwatch(hex){
        var span = document.createElement('span');
        span.className = 'plugora-color-swatch';
        span.style.background = hex;
        span.dataset.color = hex;
        span.title = hex;
        span.innerHTML = '<input type="hidden" name="' + inputName + '" value="' + hex + '" />' +
          '<button type="button" class="plugora-color-remove" aria-label="Remove">&times;</button>';
        picker.insertBefore(span, addBtn);
      }

      addBtn.addEventListener('click', function(){ input.click(); });
      input.addEventListener('change', function(){
        var v = input.value;
        if (v) makeSwatch(v);
      });
      picker.addEventListener('click', function(e){
        if (e.target.classList && e.target.classList.contains('plugora-color-remove')) {
          var sw = e.target.closest('.plugora-color-swatch');
          if (sw) sw.remove();
        }
      });
    });
  }

  if (cfg.context === 'pages') {
    bindPagesList();
    bindPagesListDrag();
    loadSidebar();
  } else if (root) {
    load();
  }
  // Always try to bind the color picker (cheap, no-op if not present).
  if (document.querySelector('.plugora-color-picker')) bindColorPicker();
})();
