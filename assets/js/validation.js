/* Admin form helpers: live SEO character counters, slug generation and SERP preview.
   Server-side validation is authoritative; this only gives faster feedback. */
(function () {
    'use strict';

    function slugify(text) {
        return text.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
    }

    // Character counters: <span class="counter" data-count-for="f-meta_title" data-min="30" data-max="60">
    document.querySelectorAll('.counter[data-count-for]').forEach(function (counter) {
        var field = document.getElementById(counter.getAttribute('data-count-for'));
        if (!field) { return; }
        var min = +counter.getAttribute('data-min'), max = +counter.getAttribute('data-max');
        function update() {
            var n = field.value.trim().length;
            counter.textContent = n + ' / ' + max;
            counter.className = 'counter ' + (n === 0 ? '' : (n >= min && n <= max ? 'ok' : 'bad'));
        }
        field.addEventListener('input', update);
        update();
    });

    // Auto slug from title until the user edits the slug by hand.
    document.querySelectorAll('[data-slug-source]').forEach(function (slug) {
        var source = document.getElementById(slug.getAttribute('data-slug-source'));
        if (!source) { return; }
        var touched = slug.value !== '';
        slug.addEventListener('input', function () { touched = slug.value !== ''; });
        source.addEventListener('input', function () { if (!touched) { slug.value = slugify(source.value); slug.dispatchEvent(new Event('change')); } });
    });

    // Google-style snippet preview.
    var serp = document.querySelector('[data-serp]');
    if (serp) {
        var ids = JSON.parse(serp.getAttribute('data-serp'));
        var get = function (id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; };
        var render = function () {
            var title = get(ids.meta_title) || get(ids.title) || 'Page title';
            var desc = get(ids.meta_description) || get(ids.fallback_description) || 'Add a meta description to control how this page is summarised in search results.';
            serp.querySelector('.serp-title').textContent = title.length > 60 ? title.slice(0, 59) + '…' : title;
            serp.querySelector('.serp-desc').textContent = desc.length > 160 ? desc.slice(0, 159) + '…' : desc;
            serp.querySelector('.serp-url').textContent = ids.base + (get(ids.slug) || 'your-page-slug');
        };
        Object.keys(ids).forEach(function (k) {
            var el = document.getElementById(ids[k]);
            if (el) { el.addEventListener('input', render); el.addEventListener('change', render); }
        });
        render();
    }

    // Live on-page checklist for the editor (keyword in title / description / content, lengths).
    var checklist = document.querySelector('[data-seo-checklist]');
    if (checklist) {
        var cfg = JSON.parse(checklist.getAttribute('data-seo-checklist'));
        var val = function (id) { var el = document.getElementById(id); return el ? el.value.toLowerCase() : ''; };
        // Same rule as the server (keyword_in_text): every significant word present, any order.
        var stop = ['a', 'an', 'the', 'in', 'of', 'for', 'to', 'and', 'vs', 'is', 'how', 'what', 'best', 'on', 'with'];
        var hasKw = function (kw, text) {
            if (!kw || !text) { return false; }
            if (text.indexOf(kw) !== -1) { return true; }
            var words = kw.split(/\s+/).filter(function (w) { return w && stop.indexOf(w) === -1; });
            return words.length > 0 && words.every(function (w) {
                var stem = w.length > 4 ? w.replace(/s+$/, '') : w;
                return new RegExp('\\b' + stem.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).test(text);
            });
        };
        var runChecks = function () {
            var kw = val(cfg.keyword).trim();
            var title = val(cfg.meta_title) || val(cfg.title);
            var desc = val(cfg.meta_description);
            var body = val(cfg.content);
            var words = body.split(/\s+/).filter(Boolean).length;
            var checks = [
                ['Primary keyword set', !!kw],
                ['Keyword in title', hasKw(kw, title)],
                ['Keyword in meta description', hasKw(kw, desc)],
                ['Keyword used in content', hasKw(kw, body)],
                ['Title 30–60 characters', title.length >= 30 && title.length <= 60],
                ['Description 70–160 characters', desc.length >= 70 && desc.length <= 160],
                ['Content has H2 sections (## )', /^##\s/m.test(body)],
                ['At least 300 words (' + words + ')', words >= 300]
            ];
            checklist.innerHTML = '';
            checks.forEach(function (c) {
                var li = document.createElement('li');
                li.className = c[1] ? 'ok' : 'bad';
                li.textContent = c[0];
                checklist.appendChild(li);
            });
        };
        [cfg.keyword, cfg.title, cfg.meta_title, cfg.meta_description, cfg.content].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('input', runChecks); }
        });
        runChecks();
    }
})();
