#!/usr/bin/env node
/**
 * Execute the shipped front-end scripts against real markup.
 *
 * Static analysis catches syntax and escaping problems; it cannot tell you that
 * a click handler throws. This runs theme.js inside a minimal DOM built from the
 * live preview page and asserts the observable behaviour of every feature the
 * script is responsible for.
 *
 * Usage: node tools/smoke-test.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { loadHTML } = require('./dom');

const ROOT = path.resolve(__dirname, '..');
const PREVIEW = path.join(ROOT, 'previews', 'luma-theme-preview.html');
const THEME_JS = path.join(ROOT, 'source', 'luma-commerce-theme', 'assets', 'js', 'theme.js');

let passed = 0;
const failures = [];

function check(label, condition, detail) {
    if (condition) {
        passed++;
        console.log('  pass  ' + label);
    } else {
        failures.push(label + (detail ? ' — ' + detail : ''));
        console.log('  FAIL  ' + label + (detail ? ' — ' + detail : ''));
    }
}

function section(name) { console.log('\n' + name); }

/* ------------------------------------------------------------------ harness */

const html = fs.readFileSync(PREVIEW, 'utf8');
const { window, document, scripts } = loadHTML(html);

const sandbox = window;
sandbox.window = sandbox;
sandbox.self = sandbox;
sandbox.globalThis = sandbox;
sandbox.document = document;
vm.createContext(sandbox);

/** Run a script body in the fake window, surfacing any thrown error. */
function run(label, code) {
    try {
        vm.runInContext(code, sandbox, { filename: label });
        return null;
    } catch (error) {
        return error;
    }
}

section('Script execution');

const bootScript = scripts.find((s) => /luma-scheme-bootstrap/.test(s.attrs));
check('preview carries the no-flash scheme bootstrap', !!bootScript);
if (bootScript) {
    check('bootstrap runs without throwing', run('bootstrap', bootScript.body) === null);
}
check('bootstrap marks the document scheme-ready', document.documentElement.classList.contains('luma-scheme-ready'));

// The preview inlines the same object WordPress passes via wp_localize_script().
const configScript = scripts.find((s) => /window\.lumaTheme\s*=/.test(s.body));
check('preview supplies the lumaTheme config', !!configScript);
if (configScript) check('lumaTheme config runs without throwing', run('config', configScript.body) === null);

const themeCode = fs.readFileSync(THEME_JS, 'utf8');
const bootError = run('theme.js', themeCode);
check('theme.js boots without throwing', bootError === null, bootError && bootError.message);
if (bootError) {
    console.error('\nAborting: theme.js failed to boot.\n' + bootError.stack);
    process.exit(1);
}

const docEl = document.documentElement;
// The preview now carries demonstration markup as well as the live grid, so
// compare against the count at boot rather than a hardcoded number.
const productCountAtBoot = document.querySelectorAll('.product').length;

section('Colour scheme toggle');
const schemeButton = document.querySelector('[data-luma-scheme-toggle]');
check('toggle is present', !!schemeButton);
check('toggle starts pressed=false in light mode', schemeButton.getAttribute('aria-pressed') === 'false', schemeButton.getAttribute('aria-pressed'));
check('toggle advertises dark mode', /dark/i.test(schemeButton.getAttribute('aria-label')), schemeButton.getAttribute('aria-label'));
schemeButton.dispatchEvent({ type: 'click', bubbles: true });
check('click switches the document to dark', docEl.getAttribute('data-luma-theme') === 'dark', docEl.getAttribute('data-luma-theme'));
check('choice is persisted to localStorage', window.localStorage.getItem('lumaScheme') === 'dark');
check('toggle becomes pressed=true', schemeButton.getAttribute('aria-pressed') === 'true');
check('label flips to light mode', /light/i.test(schemeButton.getAttribute('aria-label')), schemeButton.getAttribute('aria-label'));
schemeButton.dispatchEvent({ type: 'click', bubbles: true });
check('second click returns to light', docEl.getAttribute('data-luma-theme') === 'light');
check('label is localized, not hardcoded English', schemeButton.getAttribute('aria-label') === window.lumaTheme.i18n.darkMode);

section('Shop grid / list view');
const viewButtons = document.querySelectorAll('[data-luma-view]');
const products = document.querySelector('.woocommerce ul.products');
check('view toggle and product grid are present', viewButtons.length === 2 && !!products);
const listButton = document.querySelector('[data-luma-view="list"]');
listButton.dispatchEvent({ type: 'click', bubbles: true });
check('list view applies the grid modifier', products.classList.contains('luma-products--list'));
check('list button becomes pressed', listButton.getAttribute('aria-pressed') === 'true');
check('grid button is released', document.querySelector('[data-luma-view="grid"]').getAttribute('aria-pressed') === 'false');
check('view choice persists', window.localStorage.getItem('lumaShopView') === 'list');
check('active button label comes from i18n', listButton.getAttribute('aria-label') === window.lumaTheme.i18n.listView, listButton.getAttribute('aria-label'));
document.querySelector('[data-luma-view="grid"]').dispatchEvent({ type: 'click', bubbles: true });
check('grid view removes the modifier', !products.classList.contains('luma-products--list'));

section('Accessible count labels');
const cartBadge = document.querySelector('[data-luma-cart-count]');
const cartLink = cartBadge.closest('a');
check('bag badge is inside a link', !!cartLink);
check('initial label reports the real count', cartLink.getAttribute('aria-label') === '2 items in bag', cartLink.getAttribute('aria-label'));
cartBadge.textContent = '1';
check('singular label is used for one item', cartLink.getAttribute('aria-label') === '1 item in bag', cartLink.getAttribute('aria-label'));
cartBadge.textContent = '0';
check('empty label is used at zero', cartLink.getAttribute('aria-label') === 'View shopping bag (empty)', cartLink.getAttribute('aria-label'));
cartBadge.textContent = '7';
check('MutationObserver keeps the label in step', cartLink.getAttribute('aria-label') === '7 items in bag', cartLink.getAttribute('aria-label'));

const wishBadge = document.querySelector('[data-luma-wishlist-count]');
const wishLink = wishBadge.closest('a');
check('wishlist label reports empty', /empty/i.test(wishLink.getAttribute('aria-label')), wishLink.getAttribute('aria-label'));

section('Mobile navigation');
const menuButton = document.querySelector('.luma-menu-toggle');
const nav = document.querySelector('#primary-navigation');
const menuLabel = menuButton.querySelector('.screen-reader-text');
check('menu starts collapsed', menuButton.getAttribute('aria-expanded') === 'false');
menuButton.dispatchEvent({ type: 'click', bubbles: true });
check('click opens the nav', nav.classList.contains('is-open'));
check('aria-expanded follows state', menuButton.getAttribute('aria-expanded') === 'true');
check('body is locked while open', document.body.classList.contains('luma-menu-open'));
check('button text switches to Close menu', menuLabel.textContent === 'Close menu', menuLabel.textContent);
check('focus moves into the nav', nav.querySelector('a').focused === true);
document.dispatchEvent({ type: 'keyup', key: 'Escape', bubbles: true });
check('Escape closes the nav', !nav.classList.contains('is-open') && menuButton.getAttribute('aria-expanded') === 'false');
check('focus returns to the toggle', menuButton.focused === true);

section('Search panel');
const searchButton = document.querySelector('.luma-search-toggle');
const searchPanel = document.querySelector('#luma-search-panel');
check('panel starts hidden', searchPanel.hidden === true);
searchButton.dispatchEvent({ type: 'click', bubbles: true });
check('click reveals the panel', searchPanel.hidden === false);
check('search button is expanded', searchButton.getAttribute('aria-expanded') === 'true');
check('search label switches to close', /close/i.test(searchButton.getAttribute('aria-label')), searchButton.getAttribute('aria-label'));
check('focus lands in the field', searchPanel.querySelector('input').focused === true);
document.dispatchEvent({ type: 'keyup', key: 'Escape', bubbles: true });
check('Escape closes the panel', searchPanel.hidden === true);

section('Scroll-driven chrome');
const toTop = document.querySelector('[data-luma-to-top]');
const header = document.querySelector('.luma-header--sticky');
const progress = document.querySelector('[data-luma-progress]');
check('back-to-top starts hidden', !toTop.classList.contains('is-visible'));
window.scrollY = 900;
window._host.dispatchEvent({ type: 'scroll', bubbles: false });
window._flushRaf();
check('back-to-top appears past the threshold', toTop.classList.contains('is-visible'));
check('sticky header hides on scroll down', header.classList.contains('is-hidden'));
const lastY = window.scrollY;
window.scrollY = lastY - 60;
window._host.dispatchEvent({ type: 'scroll', bubbles: false });
window._flushRaf();
check('sticky header returns on scroll up', !header.classList.contains('is-hidden'));
window.scrollY = 0;
window._host.dispatchEvent({ type: 'scroll', bubbles: false });
window._flushRaf();
check('back-to-top hides again at the top', !toTop.classList.contains('is-visible'));

section('Reading progress');
const article = document.querySelector('.luma-article');
check('article is present', !!article);
// The stub reports a fixed 80px height for every element; give the article a
// realistic one so the ratio maths is actually exercised.
Object.defineProperty(article, 'offsetHeight', { value: 4000, configurable: true });
window.scrollY = 0;
window._host.dispatchEvent({ type: 'scroll', bubbles: false });
window._flushRaf();
check('progress starts at 0%', progress.style.width === '0%', progress.style.width);
window.scrollY = 1550; // halfway through the 4000 - 900 = 3100px scroll span
window._host.dispatchEvent({ type: 'scroll', bubbles: false });
window._flushRaf();
check('progress tracks scroll position', progress.style.width === '50%', progress.style.width);
window.scrollY = 99999;
window._host.dispatchEvent({ type: 'scroll', bubbles: false });
window._flushRaf();
check('progress is clamped at 100%', progress.style.width === '100%', progress.style.width);

section('Progressive enhancement');
check('no script writes raw English into aria labels', !/aria-label="(Search|Bag|Account)"[^>]*data-luma-scheme/.test(document.body.outerHTML));
check('document survives the whole run', document.querySelectorAll('.product').length === productCountAtBoot, document.querySelectorAll('.product').length + ' vs ' + productCountAtBoot + ' at boot');

console.log('\n' + '-'.repeat(58));
if (failures.length) {
    console.log(failures.length + ' FAILED, ' + passed + ' passed');
    failures.forEach((f) => console.log('  - ' + f));
    process.exit(1);
}
console.log('All ' + passed + ' behaviour checks passed.');
