/* Admin behaviour: sidebar toggle, confirm dialogs, AJAX SEO audit, AI suggestions. */
(function () {
    'use strict';

    var csrf = (document.querySelector('input[name="_csrf"]') || {}).value || '';

    // Mobile sidebar
    var toggle = document.querySelector('.sidebar-toggle');
    var sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            var open = sidebar.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Confirmation before destructive actions: <form data-confirm="Delete this?">
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var message = form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            e.preventDefault();
        }
    });

    // SEO auditor: submit via fetch to show progress; the form still works without JS.
    var auditForm = document.getElementById('audit-form');
    if (auditForm) {
        auditForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var loading = document.getElementById('audit-loading');
            var error = document.getElementById('audit-error');
            var button = auditForm.querySelector('button[type="submit"]');
            error.hidden = true;
            loading.classList.add('is-active');
            button.disabled = true;
            fetch(auditForm.getAttribute('data-api'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ url: auditForm.querySelector('[name="url"]').value })
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  if (data.ok && data.redirect) { window.location.href = data.redirect; return; }
                  error.textContent = data.error || 'The audit could not be completed.';
                  error.hidden = false;
              })
              .catch(function () { error.textContent = 'Network error. Please try again.'; error.hidden = false; })
              .finally(function () { loading.classList.remove('is-active'); button.disabled = false; });
        });
    }

    // AI suggestions (optional feature): buttons with data-ai="meta" / "outline"
    document.querySelectorAll('[data-ai]').forEach(function (button) {
        button.addEventListener('click', function () {
            var kind = button.getAttribute('data-ai');
            var box = document.getElementById(button.getAttribute('aria-controls'));
            var fields = JSON.parse(button.getAttribute('data-fields') || '{}');
            var payload = { kind: kind };
            Object.keys(fields).forEach(function (k) {
                var el = document.getElementById(fields[k]);
                payload[k] = el ? el.value : '';
            });
            button.disabled = true;
            box.hidden = false;
            box.textContent = 'Thinking…';
            fetch(button.getAttribute('data-api'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify(payload)
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  box.textContent = '';
                  if (!data.ok) { box.textContent = data.error || 'No suggestion available.'; return; }
                  renderSuggestion(kind, data.data, box, fields);
              })
              .catch(function () { box.textContent = 'The AI assistant is unavailable.'; })
              .finally(function () { button.disabled = false; });
        });
    });

    function renderSuggestion(kind, data, box, fields) {
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
