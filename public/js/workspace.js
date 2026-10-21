/* CommerceChat Batch 13 workspace interactions: progressive enhancement only. */
(() => {
    'use strict';
    const nav = document.getElementById('workspace-sidebar');
    const open = document.querySelector('[data-sidebar-open]');
    const dismiss = document.querySelectorAll('[data-sidebar-close]');
    const theme = document.querySelector('[data-theme-toggle]');
    if (!nav) return;
    const mobile = window.matchMedia('(max-width: 860px)');
    let returnFocus = null;
    const setOpen = (enabled) => {
        document.body.classList.toggle('ws-sidebar-open', enabled);
        if (open) open.setAttribute('aria-expanded', enabled ? 'true' : 'false');
        nav.inert = mobile.matches && !enabled;
        if (enabled) {
            returnFocus = document.activeElement;
            const firstLink = nav.querySelector('.ws-nav-link.is-active') || nav.querySelector('.ws-nav-link');
            if (firstLink) firstLink.focus();
        } else if (returnFocus && mobile.matches) {
            returnFocus.focus();
            returnFocus = null;
        }
    };
    const syncViewport = () => {
        document.body.classList.remove('ws-sidebar-open');
        if (open) open.setAttribute('aria-expanded', 'false');
        nav.inert = mobile.matches;
    };
    syncViewport();
    if (open) open.addEventListener('click', () => setOpen(true));
    dismiss.forEach((button) => button.addEventListener('click', () => setOpen(false)));
    nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        if (mobile.matches) setOpen(false);
    }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('ws-sidebar-open')) setOpen(false);
    });
    if (typeof mobile.addEventListener === 'function') mobile.addEventListener('change', syncViewport);
    else mobile.addListener(syncViewport);
    const setThemeLabel = () => {
        if (!theme) return;
        const dark = document.documentElement.dataset.theme === 'dark';
        theme.setAttribute('aria-label', dark ? 'Use light theme' : 'Use dark theme');
        theme.setAttribute('title', dark ? 'Use light theme' : 'Use dark theme');
        theme.setAttribute('aria-pressed', dark ? 'true' : 'false');
    };
    setThemeLabel();
    if (theme) theme.addEventListener('click', () => {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('commerce-theme', next); } catch (_) { /* private browsing */ }
        setThemeLabel();
    });
})();
