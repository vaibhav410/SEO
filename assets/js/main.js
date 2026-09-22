/* SYSCOM GrowthHub - public site behaviour. Progressive enhancement only: every feature works without JS. */
(function () {
    'use strict';

    document.documentElement.classList.remove('no-js');

    // Mobile navigation toggle
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
    }

    // Live search suggestions on the blog search box (falls back to the normal GET search)
    var searchInput = document.querySelector('[data-search-suggest]');
    var list = document.getElementById('search-suggestions');
    if (searchInput && list) {
        var timer = null;
        var endpoint = searchInput.getAttribute('data-search-suggest');
        searchInput.addEventListener('input', function () {
            clearTimeout(timer);
            var q = searchInput.value.trim();
            if (q.length < 2) { list.innerHTML = ''; return; }
            timer = setTimeout(function () {
                fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function (data) {
                        list.innerHTML = '';
                        (data.results || []).forEach(function (item) {
                            var li = document.createElement('li');
                            var a = document.createElement('a');
                            a.href = item.url;
                            a.textContent = item.title;
                            var small = document.createElement('small');
                            small.textContent = item.type;
                            a.appendChild(small);
                            li.appendChild(a);
                            list.appendChild(li);
                        });
                    })
                    .catch(function () { list.innerHTML = ''; });
            }, 200);
        });
    }

    // Lead form: light client-side checks before the (authoritative) server validation
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var firstInvalid = null;
            form.querySelectorAll('[required], [type="email"]').forEach(function (field) {
                var valid = field.checkValidity();
                field.setAttribute('aria-invalid', valid ? 'false' : 'true');
                if (!valid && !firstInvalid) { firstInvalid = field; }
            });
            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.focus();
                firstInvalid.reportValidity();
                return;
            }
            var button = form.querySelector('button[type="submit"]');
            if (button) { button.disabled = true; button.textContent = 'Sending…'; }
        });
    });
})();
