/**
 * uxknowdash.js v1.4.1
 */
console.log('[UXKnowDash] SCRIPT CHARGE, URL=', window.location.href);
(function () {
    'use strict';
    function getKbIdFromUrl() {
        const url = window.location.href;
        const m1 = url.match(/knowbaseitem\.form\.php.*[?&]id=(\d+)/i); if (m1) return parseInt(m1[1]);
        const m2 = url.match(/helpdesk\.faq\.php.*[?&]id=(\d+)/i);     if (m2) return parseInt(m2[1]);
        const m3 = url.match(/knowbaseitem\/(\d+)/i);                    if (m3) return parseInt(m3[1]);
        return null;
    }
    function getBase() { const b = document.querySelector('base'); return b ? b.href.replace(/\/$/, '') : ''; }

    function trackView(kbId, csrfToken) {
        const fd = new FormData();
        fd.append('knowbaseitems_id', kbId);
        fd.append('_glpi_csrf_token', csrfToken || '');
        fetch(getBase() + '/plugins/uxknowdash/ajax/track_kb_view.php', { method:'POST', body:fd, credentials:'same-origin' })
            .then(r => r.json())
            .then(d => console.log('[UXKnowDash] vue tracee:', d.ok))
            .catch(e => console.warn('[UXKnowDash] track error:', e));
    }

    function injectWidget(kbId) {
        const url = getBase() + '/plugins/uxknowdash/ajax/get_kb_widget.php?knowbaseitems_id=' + kbId;
        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.html) return;
                const selectors = ['.kb-article-footer','.kb-item-footer','div[data-itemtype=\"KnowbaseItem\"]','.read-only-content','.kb-answer','.kb_article','article','.container-fluid main','main'];
                let target = null;
                for (const sel of selectors) { target = document.querySelector(sel); if (target) break; }
                if (!target) target = document.body;
                const range = document.createRange();
                range.selectNode(target);
                target.appendChild(range.createContextualFragment(data.html));
                trackView(kbId, data.csrf_track || '');
            })
            .catch(e => console.warn('[UXKnowDash] widget error:', e));
    }

    function init() {
        const kbId = getKbIdFromUrl();
        console.log('[UXKnowDash] init kbId=', kbId);
        if (kbId) injectWidget(kbId);
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();