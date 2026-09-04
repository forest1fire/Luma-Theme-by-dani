/**
 * Luma theme front-end behaviour.
 *
 * Dependency-free. Every string written into the DOM comes from the localized
 * `lumaTheme.i18n` object so translated sites do not fall back to English.
 *
 * @package LumaCommerce
 */
(function () {
    'use strict';

    var config = window.lumaTheme || {};
    var i18n = config.i18n || {};
    var VIEW_STORAGE_KEY = 'lumaShopView';
    var SCHEME_STORAGE_KEY = 'lumaScheme';

    function t(key, fallback) {
        return typeof i18n[key] === 'string' && i18n[key] ? i18n[key] : fallback;
    }

    function safeFocus(element) {
        if (element && typeof element.focus === 'function') element.focus();
    }

    function storageGet(key) {
        try { return window.localStorage.getItem(key); } catch (error) { return null; }
    }

    function storageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (error) { /* private mode */ }
    }

    function isMobileLayout() {
        return window.matchMedia && window.matchMedia('(max-width: 1024px)').matches;
    }

    /* ---------------------------------------------------------- grid / list */

    function initShopViewToggle() {
        var toggle = document.querySelector('.luma-shop-view-toggle');
        var products = document.querySelector('.woocommerce ul.products');
        if (!toggle || !products) return;
        var buttons = toggle.querySelectorAll('[data-luma-view]');

        function setView(view) {
            var listView = 'list' === view;
            products.classList.toggle('luma-products--list', listView);
            Array.prototype.forEach.call(buttons, function (button) {
                var active = button.getAttribute('data-luma-view') === view;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
                button.setAttribute('aria-label', active
                    ? t(listView ? 'listView' : 'gridView', listView ? 'List view' : 'Grid view')
                    : t(listView ? 'gridView' : 'listView', listView ? 'Grid view' : 'List view'));
            });
            storageSet(VIEW_STORAGE_KEY, view);
        }

        Array.prototype.forEach.call(buttons, function (button) {
            button.addEventListener('click', function () { setView(button.getAttribute('data-luma-view')); });
        });

        var saved = 'list' === storageGet(VIEW_STORAGE_KEY) ? 'list' : 'grid';
        setView(saved);
    }

    /* -------------------------------------------------------- color scheme */

    /** Resolve the scheme currently in effect, including the OS preference. */
    function activeScheme() {
        var explicit = document.documentElement.getAttribute('data-luma-theme');
        if ('dark' === explicit || 'light' === explicit) return explicit;
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function initSchemeToggle() {
        var button = document.querySelector('[data-luma-scheme-toggle]');
        if (!button) return;

        function render() {
            var isDark = 'dark' === activeScheme();
            button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            button.setAttribute('aria-label', isDark
                ? t('lightMode', 'Switch to light theme')
                : t('darkMode', 'Switch to dark theme'));
        }

        button.addEventListener('click', function () {
            var next = 'dark' === activeScheme() ? 'light' : 'dark';
            document.documentElement.setAttribute('data-luma-theme', next);
            storageSet(SCHEME_STORAGE_KEY, next);
            render();
        });

        // Keep the label correct when the OS preference changes underneath us
        // and the visitor has not made an explicit choice.
        if (window.matchMedia && !storageGet(SCHEME_STORAGE_KEY)) {
            var query = window.matchMedia('(prefers-color-scheme: dark)');
            var listener = function () { render(); };
            if (typeof query.addEventListener === 'function') query.addEventListener('change', listener);
            else if (typeof query.addListener === 'function') query.addListener(listener);
        }

        render();
    }

    /* -------------------------------------------------------- page chrome */

    function initBackToTop() {
        var link = document.querySelector('[data-luma-to-top]');
        if (!link) return;
        var threshold = 480;
        var ticking = false;

        function update() {
            link.classList.toggle('is-visible', window.scrollY > threshold);
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        }, { passive: true });
        // The href="#top" fragment is resolved natively by the browser and
        // `scroll-behavior: smooth` in theme.css animates it, so no click
        // handler is needed — the control keeps working without JavaScript.
        update();
    }

    function initReadingProgress() {
        var bar = document.querySelector('[data-luma-progress]');
        if (!bar) return;
        var article = document.querySelector('.luma-article') || document.querySelector('main');
        if (!article) return;
        var ticking = false;

        function update() {
            var start = article.offsetTop;
            var span = article.offsetHeight - window.innerHeight;
            var ratio = span > 0 ? (window.scrollY - start) / span : 1;
            bar.style.width = Math.max(0, Math.min(100, ratio * 100)) + '%';
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        }, { passive: true });
        window.addEventListener('resize', update, { passive: true });
        update();
    }

    function initStickyHeader() {
        var header = document.querySelector('.luma-header--sticky');
        if (!header) return;
        var lastY = window.scrollY;
        var ticking = false;

        function update() {
            var y = window.scrollY;
            // Only hide once the visitor is past the header and moving down,
            // and never while a disclosure (menu/search) is open.
            var menuOpen = document.body.classList.contains('luma-menu-open');
            var searchOpen = document.querySelector('#luma-search-panel:not([hidden])');
            if (y > 220 && y > lastY + 6 && !menuOpen && !searchOpen) header.classList.add('is-hidden');
            else if (y < lastY - 6 || y <= 220) header.classList.remove('is-hidden');
            lastY = y;
            ticking = false;
        }
        window.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        }, { passive: true });
    }

    /* -------------------------------------------------- count badges (a11y) */

    /**
     * The bag and wish-list links use aria-label, which hides their visible
     * count from assistive technology. Watch the badge text and keep the label
     * in step, whatever script updated the number.
     */
    function initCountLabels() {
        var targets = [
            { badge: '[data-luma-cart-count]', base: t('viewBag', 'View shopping bag'), one: 'oneItemInBag', many: 'itemsInBag' },
            { badge: '[data-luma-wishlist-count]', base: t('viewWishlist', 'Wishlist'), one: 'oneItemSaved', many: 'itemsSaved' },
        ];

        targets.forEach(function (target) {
            var badge = document.querySelector(target.badge);
            if (!badge) return;
            var link = badge.closest('a');
            if (!link) return;

            function sync() {
                var count = parseInt(badge.textContent, 10) || 0;
                var template = count === 1 ? t(target.one, '1 item') : t(target.many, '%d items');
                link.setAttribute('aria-label', count
                    ? template.replace('%d', String(count))
                    : target.base + ' (' + t('noItems', 'empty') + ')');
            }

            sync();
            if (typeof window.MutationObserver === 'function') {
                new window.MutationObserver(sync).observe(badge, { childList: true, characterData: true, subtree: true });
            }
        });
    }

    /* -------------------------------------------------------------- panels */

    function initMobileMenu() {
        var menuButton = document.querySelector('.luma-menu-toggle');
        var nav = document.querySelector('#primary-navigation');
        if (!menuButton || !nav) return;

        var menuLabel = menuButton.querySelector('.screen-reader-text');

        function closeMenu() {
            nav.classList.remove('is-open');
            menuButton.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('luma-menu-open');
            if (menuLabel) menuLabel.textContent = t('openMenu', 'Open menu');
        }

        function openMenu() {
            nav.classList.add('is-open');
            menuButton.setAttribute('aria-expanded', 'true');
            document.body.classList.add('luma-menu-open');
            if (menuLabel) menuLabel.textContent = t('closeMenu', 'Close menu');
            safeFocus(nav.querySelector('a'));
        }

        menuButton.addEventListener('click', function () {
            if (nav.classList.contains('is-open')) closeMenu(); else openMenu();
        });
        nav.addEventListener('click', function (event) {
            if (event.target.closest && event.target.closest('a')) closeMenu();
        });
        document.addEventListener('click', function (event) {
            if (!nav.classList.contains('is-open') || nav.contains(event.target) || menuButton.contains(event.target)) return;
            closeMenu();
        });
        document.addEventListener('keyup', function (event) {
            if ('Escape' === event.key && nav.classList.contains('is-open')) {
                closeMenu();
                safeFocus(menuButton);
            }
        });
        window.addEventListener('resize', function () {
            if (!isMobileLayout()) closeMenu();
        }, { passive: true });

        if (menuLabel) menuLabel.textContent = t('openMenu', 'Open menu');
    }

    function initSearchPanel() {
        var searchButton = document.querySelector('.luma-search-toggle');
        var searchPanel = document.querySelector('#luma-search-panel');
        if (!searchButton || !searchPanel) return;

        var baseLabel = searchButton.getAttribute('aria-label') || t('openSearch', 'Search');

        function closeSearch() {
            searchPanel.setAttribute('hidden', 'hidden');
            searchButton.setAttribute('aria-expanded', 'false');
            searchButton.setAttribute('aria-label', baseLabel);
        }

        searchButton.addEventListener('click', function () {
            var closed = searchPanel.hasAttribute('hidden');
            if (closed) {
                searchPanel.removeAttribute('hidden');
                searchButton.setAttribute('aria-expanded', 'true');
                searchButton.setAttribute('aria-label', t('closeSearch', 'Close search'));
                safeFocus(searchPanel.querySelector('input'));
            } else {
                closeSearch();
                safeFocus(searchButton);
            }
        });
        document.addEventListener('click', function (event) {
            if (searchPanel.hasAttribute('hidden') || searchPanel.contains(event.target) || searchButton.contains(event.target)) return;
            closeSearch();
        });
        document.addEventListener('keyup', function (event) {
            if ('Escape' === event.key && !searchPanel.hasAttribute('hidden')) {
                closeSearch();
                safeFocus(searchButton);
            }
        });
    }

    /* ---------------------------------------------------------------- boot */

    function boot() {
        initShopViewToggle();
        initSchemeToggle();
        initMobileMenu();
        initSearchPanel();
        initBackToTop();
        initReadingProgress();
        initStickyHeader();
        initCountLabels();
    }

    if ('loading' === document.readyState) document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());
