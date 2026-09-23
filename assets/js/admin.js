/* GrowthHub admin behaviour. Progressive enhancement: every screen works without JavaScript. */
(function () {
    'use strict';

    var csrf = (document.querySelector('input[name="_csrf"]') || {}).value || '';
    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    /* ------------------------------------------------ mobile sidebar drawer */
    var sidebar = document.getElementById('sidebar');
    var toggle = $('.sidebar-toggle');
    var backdrop = $('.sidebar-backdrop');
    function setDrawer(open) {
        if (!sidebar) { return; }
        sidebar.classList.toggle('is-open', open);
        if (backdrop) { backdrop.hidden = !open; }
        if (toggle) { toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); }
        if (open) { var first = sidebar.querySelector('a'); if (first) { first.focus(); } } else if (toggle) { toggle.focus(); }
    }
    if (toggle) { toggle.addEventListener('click', function () { setDrawer(true); }); }
    if (backdrop) { backdrop.addEventListener('click', function () { setDrawer(false); }); }
    var closeBtn = $('.sidebar-close');
    if (closeBtn) { closeBtn.addEventListener('click', function () { setDrawer(false); }); }

    /* ------------------------------------------------ dropdowns (<details>) */
    document.addEventListener('click', function (e) {
        $$('details.dropdown[open]').forEach(function (d) { if (!d.contains(e.target)) { d.removeAttribute('open'); } });
    });
    $$('details.dropdown').forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (d.open) { $$('details.dropdown[open]').forEach(function (o) { if (o !== d) { o.removeAttribute('open'); } }); }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            $$('details.dropdown[open]').forEach(function (d) { d.removeAttribute('open'); var s = d.querySelector('summary'); if (s) { s.focus(); } });
            if (sidebar && sidebar.classList.contains('is-open')) { setDrawer(false); }
        }
    });

    /* ------------------------------------------------ confirmation modal for destructive forms */
    var dialog = document.getElementById('confirm-dialog');
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute('data-confirm');
        if (!message || form.dataset.confirmed === '1') { return; }
        e.preventDefault();
        if (!dialog || typeof dialog.showModal !== 'function') {
            if (window.confirm(message)) { form.dataset.confirmed = '1'; form.submit(); }
            return;
        }
        $('#confirm-message', dialog).textContent = message;
        $('#confirm-ok', dialog).textContent = form.getAttribute('data-confirm-label') || 'Delete';
        dialog.returnValue = '';
        dialog.showModal();
        dialog.addEventListener('close', function onClose() {
            dialog.removeEventListener('close', onClose);
            if (dialog.returnValue === 'confirm') { form.dataset.confirmed = '1'; form.submit(); }
        });
    }, true);

    /* ------------------------------------------------ toasts */
    $$('.toast').forEach(function (t) {
        var close = function () { t.classList.add('is-leaving'); setTimeout(function () { t.remove(); }, 220); };
        var btn = t.querySelector('.toast-close');
        if (btn) { btn.addEventListener('click', close); }
        if (!t.classList.contains('toast-error')) { setTimeout(close, 6000); }
    });

    /* ------------------------------------------------ loading state on normal form submits */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || form.hasAttribute('data-no-loading')) { return; }
        var btn = e.submitter || form.querySelector('button[type="submit"]');
        if (btn && btn.classList.contains('btn') && !btn.classList.contains('btn-link')) {
            setTimeout(function () { btn.classList.add('is-loading'); }, 0);
        }
    });

    /* ------------------------------------------------ global search with typeahead */
    var searchInput = $('[data-admin-search]');
    var popover = document.getElementById('global-results');
    if (searchInput && popover) {
        var timer = null;
        var items = [];
        var active = -1;
        var render = function (data, q) {
            popover.innerHTML = '';
            items = [];
            active = -1;
            if (!data.results || !data.results.length) {
                var p = document.createElement('p');
                p.className = 'none';
                p.textContent = 'No results for “' + q + '”.';
                popover.appendChild(p);
            }
            (data.results || []).slice(0, 8).forEach(function (r) {
                var a = document.createElement('a');
                a.href = r.url;
                a.setAttribute('role', 'option');
                var t = document.createElement('span');
                t.textContent = r.title;
                var type = document.createElement('span');
                type.className = 'type';
                type.textContent = r.type;
                a.appendChild(t);
                a.appendChild(type);
                popover.appendChild(a);
                items.push(a);
            });
            var all = document.createElement('a');
            all.className = 'all';
            all.href = searchInput.form.action + '?q=' + encodeURIComponent(q);
            all.textContent = 'See all results';
            popover.appendChild(all);
            items.push(all);
            popover.hidden = false;
            searchInput.setAttribute('aria-expanded', 'true');
        };
        var close = function () { popover.hidden = true; searchInput.setAttribute('aria-expanded', 'false'); };
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            var q = searchInput.value.trim();
            if (q.length < 2) { close(); return; }
            timer = setTimeout(function () {
                fetch(searchInput.getAttribute('data-admin-search') + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function (d) { render(d, q); })
                    .catch(close);
            }, 180);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (popover.hidden || !items.length) { return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (active >= 0) { items[active].removeAttribute('aria-selected'); }
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items[active].setAttribute('aria-selected', 'true');
                items[active].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && active >= 0) {
                e.preventDefault();
                window.location.href = items[active].href;
            } else if (e.key === 'Escape') { close(); }
        });
        document.addEventListener('click', function (e) { if (!searchInput.form.contains(e.target)) { close(); } });
        document.addEventListener('keydown', function (e) {
            var tag = (document.activeElement || {}).tagName;
            if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') { e.preventDefault(); searchInput.focus(); }
        });
    }

    /* ------------------------------------------------ SEO auditor (AJAX with skeleton) */
    var auditForm = document.getElementById('audit-form');
    if (auditForm) {
        auditForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var loading = document.getElementById('audit-loading');
            var skeletonBox = document.getElementById('audit-skeleton');
            var error = document.getElementById('audit-error');
            var button = auditForm.querySelector('button[type="submit"]');
            error.hidden = true;
            loading.classList.add('is-active');
            if (skeletonBox) { skeletonBox.hidden = false; }
            button.classList.add('is-loading');
            fetch(auditForm.getAttribute('data-api'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ url: auditForm.querySelector('[name="url"]').value })
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  if (data.ok && data.redirect) { window.location.href = data.redirect; return; }
                  error.textContent = data.error || 'The audit could not be completed.';
                  error.hidden = false;
              })
              .catch(function () { error.textContent = 'Network error. Please try again.'; error.hidden = false; })
              .finally(function () {
                  loading.classList.remove('is-active');
                  if (skeletonBox) { skeletonBox.hidden = true; }
                  button.classList.remove('is-loading');
              });
        });
    }

    /* ------------------------------------------------ copy to clipboard */
    $$('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.getAttribute('data-copy'));
            if (!target || !navigator.clipboard) { return; }
            navigator.clipboard.writeText(target.textContent).then(function () {
                var old = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = old; }, 1500);
            });
        });
    });

    /* ------------------------------------------------ AI suggestions (optional feature) */
    $$('[data-ai]').forEach(function (button) {
        button.addEventListener('click', function () {
            var kind = button.getAttribute('data-ai');
            var box = document.getElementById(button.getAttribute('aria-controls'));
            var fields = JSON.parse(button.getAttribute('data-fields') || '{}');
            var payload = { kind: kind };
            Object.keys(fields).forEach(function (k) {
                var el = document.getElementById(fields[k]);
                payload[k] = el ? el.value : '';
            });
            button.classList.add('is-loading');
            box.hidden = false;
            box.textContent = 'Thinking…';
            fetch(button.getAttribute('data-api'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify(payload)
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  box.textContent = '';
                  if (!data.ok) { box.textContent = data.error || 'No suggestion available.'; return; }
                  renderSuggestion(kind, data.data, box);
              })
              .catch(function () { box.textContent = 'The AI assistant is unavailable.'; })
              .finally(function () { button.classList.remove('is-loading'); });
        });
    });

    function renderSuggestion(kind, data, box) {
        var note = document.createElement('p');
        note.className = 'small muted';
        note.textContent = 'AI draft – review and edit before saving.';
        if (kind === 'meta') {
            [['meta_title', 'Meta title'], ['meta_description', 'Meta description']].forEach(function (pair) {
                var p = document.createElement('p');
                var strong = document.createElement('strong');
                strong.textContent = pair[1] + ': ';
                p.appendChild(strong);
                p.appendChild(document.createTextNode(data[pair[0]] || ''));
                box.appendChild(p);
            });
            var apply = document.createElement('button');
            apply.type = 'button';
            apply.className = 'btn btn-sm btn-outline';
            apply.textContent = 'Use these';
            apply.addEventListener('click', function () {
                ['meta_title', 'meta_description'].forEach(function (k) {
                    var el = document.getElementById('f-' + k);
                    if (el && data[k]) { el.value = data[k]; el.dispatchEvent(new Event('input')); }
                });
            });
            box.appendChild(apply);
        } else {
            var h = document.createElement('p');
            var s = document.createElement('strong');
            s.textContent = data.title || '';
            h.appendChild(s);
            box.appendChild(h);
            var ol = document.createElement('ol');
            (data.sections || []).forEach(function (t) { var li = document.createElement('li'); li.textContent = t; ol.appendChild(li); });
            box.appendChild(ol);
            if ((data.faqs || []).length) {
                var fq = document.createElement('p');
                fq.textContent = 'FAQ ideas: ' + data.faqs.join(' · ');
                box.appendChild(fq);
            }
        }
        box.appendChild(note);
    }
})();
