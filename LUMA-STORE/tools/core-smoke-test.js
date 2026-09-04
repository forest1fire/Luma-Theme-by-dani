#!/usr/bin/env node
/**
 * Execute Luma Core's front-end script against real markup.
 *
 * core.js is written against jQuery, so this runs it on the jQuery double in
 * tools/jquery.js over the minimal DOM in tools/dom.js, using the live preview
 * page as its markup. AJAX is intercepted: every $.post is queued and resolved
 * by the test, so requests and responses can be asserted deterministically.
 *
 * Usage: node tools/core-smoke-test.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { loadHTML } = require('./dom');
const { createJQuery } = require('./jquery');

const ROOT = path.resolve(__dirname, '..');
const PREVIEW = path.join(ROOT, 'previews', 'luma-theme-preview.html');
const CORE_JS = path.join(ROOT, 'source', 'luma-commerce-core', 'assets', 'js', 'core.js');

const coreCode = fs.readFileSync(CORE_JS, 'utf8');
const previewHtml = fs.readFileSync(PREVIEW, 'utf8');

let passed = 0;
const failures = [];

function check(label, condition, detail) {
    if (condition) {
        passed++;
        console.log('  pass  ' + label);
    } else {
        failures.push(label + (detail !== undefined ? ' — got ' + detail : ''));
        console.log('  FAIL  ' + label + (detail !== undefined ? ' — got ' + detail : ''));
    }
}
function section(name) { console.log('\n' + name); }

const unhandled = [];
process.on('unhandledRejection', (reason) => unhandled.push(reason));

/**
 * Build a window with the preview loaded and jQuery available.
 * `withCore` controls whether the localized bundle exists at all.
 */
function boot(options) {
    const opts = options || {};
    const { window, document, scripts } = loadHTML(previewHtml);

    window.URL = URL;
    window.URLSearchParams = URLSearchParams;
    window.console = console;
    window._alerts = [];
    window.alert = function (message) { window._alerts.push(String(message)); };
    window.location.href = 'https://luma.test/shop/?paged=2';
    window.location.origin = 'https://luma.test';
    window.location.pathname = '/shop/';
    window.location.search = '?paged=2';
    window.location.reload = function () { window._reloaded = true; };
    window.navigator.share = undefined;
    window.navigator.clipboard = opts.clipboard === undefined
        ? { writeText: function () { return Promise.resolve(); } }
        : opts.clipboard;

    const jq = createJQuery(window);
    window.jQuery = jq.$;
    window.$ = jq.$;

    const sandbox = window;
    sandbox.window = sandbox;
    sandbox.self = sandbox;
    sandbox.globalThis = sandbox;
    sandbox.document = document;
    vm.createContext(sandbox);

    if (opts.withCore !== false) {
        const config = scripts.find((s) => /window\.lumaCore\s*=/.test(s.body));
        if (!config) throw new Error('preview no longer supplies the lumaCore config');
        vm.runInContext(config.body, sandbox, { filename: 'lumaCore-config' });
    }

    let error = null;
    try {
        vm.runInContext(coreCode, sandbox, { filename: 'core.js' });
    } catch (e) {
        error = e;
    }

    // Default AJAX responder; individual tests replace it.
    let responder = function () { return { success: true, data: {} }; };
    const flush = function () { return jq.flushAjax(function (data, call) { return responder(data, call); }); };

    return { window, document, scripts, jq, $: jq.$, sandbox, error, flush, setResponder: (fn) => { responder = fn; }, i18n: window.lumaCore ? window.lumaCore.i18n : {} };
}

/* ------------------------------------------------------------------- guard */

section('Load guard');
const guarded = boot({ withCore: false });
check('core.js does not throw when lumaCore is missing', guarded.error === null, guarded.error && guarded.error.message);
check('it binds nothing without the localized bundle', guarded.document.querySelectorAll('.luma-variation-swatches').length === 0);

/* -------------------------------------------------------------------- boot */

const env = boot();
const { window, document, $, jq } = env;

section('Boot');
check('core.js boots without throwing', env.error === null, env.error && env.error.stack);
if (env.error) {
    console.error('\nAborting: core.js failed to boot.');
    process.exit(1);
}
const i18n = env.i18n;
check('i18n bundle reached the script', typeof i18n === 'object' && !!i18n.addToWishlist);

/* ------------------------------------------------------- variation swatches */

section('Variation swatches (accessibility rewrite)');
const groups = document.querySelectorAll('.luma-variation-swatches');
check('a group is built for each variation select', groups.length === 2, groups.length);

const sizeSelect = document.querySelector('#pa_size');
const sizeGroup = sizeSelect.nextElementSibling;
check('group is placed directly after its select', sizeGroup && sizeGroup.classList.contains('luma-variation-swatches'));
check('group exposes role=group', sizeGroup.getAttribute('role') === 'group');
check('group is named from the visible label', sizeGroup.getAttribute('aria-label') === 'Size', sizeGroup.getAttribute('aria-label'));
const colourGroup = document.querySelector('#pa_colour').nextElementSibling;
check('second group is named from its own label', colourGroup.getAttribute('aria-label') === 'Colour', colourGroup.getAttribute('aria-label'));

const sizeButtons = sizeGroup.querySelectorAll('button');
check('the placeholder option produces no button', sizeButtons.length === 4, sizeButtons.length);
const buttonFor = (value) => sizeButtons.find((b) => b.getAttribute('data-value') === value);
check('preselected option starts selected', buttonFor('m').classList.contains('is-selected'));
check('preselected option is pressed', buttonFor('m').getAttribute('aria-pressed') === 'true');
check('unselected options are not pressed', buttonFor('s').getAttribute('aria-pressed') === 'false');
check('a disabled variation is disabled as a button', buttonFor('xl').hasAttribute('disabled'));

check('the native select is hidden', sizeSelect.hasAttribute('hidden'));
check('the native select leaves the a11y tree', sizeSelect.getAttribute('aria-hidden') === 'true');
check('the native select is removed from tab order', sizeSelect.getAttribute('tabindex') === '-1');

buttonFor('l').dispatchEvent({ type: 'click', bubbles: true });
check('clicking a swatch sets the select value', sizeSelect._jqValue === 'l', sizeSelect._jqValue);
check('aria-pressed follows the click', buttonFor('l').getAttribute('aria-pressed') === 'true');
check('the previous choice is released', buttonFor('m').getAttribute('aria-pressed') === 'false');
check('is-selected moves with the click', buttonFor('l').classList.contains('is-selected') && !buttonFor('m').classList.contains('is-selected'));

$(document.body).trigger('wc_fragment_refresh');
check('a WooCommerce refresh does not duplicate the swatches', document.querySelectorAll('.luma-variation-swatches').length === 2, document.querySelectorAll('.luma-variation-swatches').length);
check('selection survives the re-init', document.querySelector('#pa_size').nextElementSibling.querySelectorAll('button').find((b) => b.getAttribute('data-value') === 'l').getAttribute('aria-pressed') === 'true');

/* --------------------------------------------------------------- countdowns */

section('Offer countdown');
const countdowns = document.querySelectorAll('.luma-countdown');
check('both countdowns are present', countdowns.length === 2, countdowns.length);
const expired = countdowns.find((c) => /2026-01-01/.test(c.getAttribute('data-end')));
const live = countdowns.find((c) => /2026-12-31/.test(c.getAttribute('data-end')));
check('an elapsed countdown is marked expired', expired.classList.contains('is-expired'));
check('an elapsed countdown reads zero', expired.querySelector('[data-unit="days"]').textContent === '00');
check('a running countdown shows real time left', live.querySelector('[data-unit="days"]').textContent !== '00', live.querySelector('[data-unit="days"]').textContent);
check('only the running countdown arms a timer', window._pendingIntervals() === 1, window._pendingIntervals());
check('the expired countdown armed none', expired.getAttribute('data-luma-countdown-timer') === null || !expired._jqData || expired._jqData['luma-countdown-timer'] === undefined);
window._fireIntervals();
check('the running timer keeps ticking', window._pendingIntervals() === 1, window._pendingIntervals());
// Regression: re-initialisation used to stack a second interval on the same
// node and overwrite the stored id, so the older timer could never be cleared.
$(document.body).trigger('wc_fragment_refresh');
check('a fragment refresh does not stack countdown timers', window._pendingIntervals() === 1, window._pendingIntervals());

/* ------------------------------------------------- coupon + injection safety */

section('Coupon apply and injection safety');
const couponButton = document.querySelector('.luma-apply-coupon');
const couponStatus = document.querySelector('.luma-coupon-status');
couponButton.dispatchEvent({ type: 'click', bubbles: true });
check('the button enters a loading state', couponButton.classList.contains('is-loading'));
check('the button is disabled while in flight', couponButton.hasAttribute('disabled'));
check('the status copy is localized', couponStatus.textContent === i18n.checking, couponStatus.textContent);
check('a coupon request is sent', jq.lastCall('luma_apply_coupon') !== null);
check('the coupon code is forwarded', jq.lastCall('luma_apply_coupon').fields.coupon === 'LUMA10', jq.lastCall('luma_apply_coupon').fields.coupon);

const hostile = '<img src=x onerror=window.__pwned=1> Code applied';
env.setResponder(() => ({ success: true, data: { message: hostile, count: 3 } }));
env.flush();
check('the server message is shown verbatim as text', couponStatus.textContent === hostile, couponStatus.textContent);
check('no element is created from server text', couponStatus.querySelectorAll('img').length === 0);
check('the injection never executed', window.__pwned === undefined);
check('the button is re-enabled afterwards', !couponButton.hasAttribute('disabled'));
check('the applied label is localized', couponButton.querySelector('.luma-coupon-action').textContent.indexOf(i18n.applied) === 0, couponButton.querySelector('.luma-coupon-action').textContent);

/* --------------------------------------------------------- wishlist/compare */

section('Wish list and compare');
env.setResponder(() => ({ success: true, data: {} }));
const wishButton = document.querySelector('.luma-wishlist-toggle[data-wishlist-id="1"]');
check('a wish-list button starts unpressed', wishButton.getAttribute('aria-pressed') === 'false');
wishButton.dispatchEvent({ type: 'click', bubbles: true });
check('clicking marks it pressed', wishButton.getAttribute('aria-pressed') === 'true');
check('clicking adds the saved state class', wishButton.classList.contains('is-saved'));
check('the label switches to the localized removal copy', wishButton.getAttribute('aria-label') === i18n.removeFromWishlist, wishButton.getAttribute('aria-label'));
check('the choice is stored locally', JSON.parse(window.localStorage.getItem('lumaWishlist')).indexOf('1') > -1);
check('the visible count updates', document.querySelector('[data-luma-wishlist-count]').textContent === '1');
wishButton.dispatchEvent({ type: 'click', bubbles: true });
check('clicking again removes it', wishButton.getAttribute('aria-pressed') === 'false' && JSON.parse(window.localStorage.getItem('lumaWishlist')).length === 0);
check('the label returns to the localized add copy', wishButton.getAttribute('aria-label') === i18n.addToWishlist, wishButton.getAttribute('aria-label'));

const compareButtons = document.querySelectorAll('.luma-compare-toggle');
for (let i = 0; i < 4; i++) compareButtons[i].dispatchEvent({ type: 'click', bubbles: true });
check('four items can be compared', JSON.parse(window.localStorage.getItem('lumaCompare')).length === 4);
check('the compare tray becomes visible', document.querySelector('.luma-compare-tray').classList.contains('is-visible'));
check('the tray count is right', document.querySelector('[data-compare-count]').textContent === '4');
const before = window._alerts.length;
compareButtons[4] && compareButtons[4].dispatchEvent({ type: 'click', bubbles: true });
check('the fifth item is refused', JSON.parse(window.localStorage.getItem('lumaCompare')).length === 4);
check('the limit message is localized', window._alerts.length === before + 1 && window._alerts[before] === i18n.compareLimit, window._alerts[before]);

/* --------------------------------------------------------------- quick add */

section('Quick add');
const quickAdd = document.querySelector('.luma-quick-add[data-product-id="1"]');
const quickAddLabel = quickAdd.innerHTML;
quickAdd.dispatchEvent({ type: 'click', bubbles: true });
check('the button enters a loading state', quickAdd.classList.contains('is-loading') && quickAdd.hasAttribute('disabled'));
check('a quick-add request is sent', jq.lastCall('luma_quick_add') !== null);
check('the product id is forwarded', String(jq.lastCall('luma_quick_add').fields.product_id) === '1', jq.lastCall('luma_quick_add').fields.product_id);
env.setResponder(() => ({ success: true, data: { count: 3, html: '', subtotal: '$29.90' } }));
env.flush();
check('the confirmation label is localized', quickAdd.textContent.indexOf(i18n.addedToBag) === 0, quickAdd.textContent);
check('the cart badge updates from the response', document.querySelector('[data-luma-cart-count]').textContent === '3');
window._fireTimeouts();
check('the original label is restored', quickAdd.innerHTML === quickAddLabel, quickAdd.innerHTML);
check('the added state is cleared', !quickAdd.classList.contains('is-added') && !quickAdd.classList.contains('is-loading'));
check('the button is usable again', !quickAdd.hasAttribute('disabled'));

/* -------------------------------------------------------------- order bump */

section('Order bump');
const bump = document.querySelector('.luma-order-bump__toggle');
const bumpStatus = document.querySelector('.luma-order-bump__status');
bump._checked = true;
bump.setAttribute('checked', '');
bump.dispatchEvent({ type: 'change', bubbles: true });
check('the status copy is localized while adding', bumpStatus.textContent === i18n.adding, bumpStatus.textContent);
const bumpCall = jq.lastCall('luma_toggle_order_bump');
check('an order-bump request is sent', bumpCall !== null);
check('the product id comes from the wrapper', bumpCall && String(bumpCall.fields.product_id) === '5', bumpCall && bumpCall.fields.product_id);
check('the intent is sent as add=1', bumpCall && String(bumpCall.fields.add) === '1', bumpCall && bumpCall.fields.add);

section('Order bump — failure reverts the checkbox');
bump.dispatchEvent({ type: 'change', bubbles: true });
env.setResponder(() => { throw new Error('boom'); });
env.flush();
check('the checkbox is reverted on failure', bump._checked !== true || !bump.hasAttribute('checked'), bump.hasAttribute('checked'));
check('the failure copy is localized', bumpStatus.textContent === i18n.tryAgain, bumpStatus.textContent);
env.setResponder(() => ({ success: true, data: {} }));

/* ------------------------------------------------------------------ bundle */

section('Bundle');
const bundle = document.querySelector('[data-luma-bundle]');
const bundleButton = bundle.querySelector('.luma-add-bundle');
const bundleStatus = bundle.querySelector('.luma-bundle-status');
const boxes = bundle.querySelectorAll('input[type="checkbox"]');
boxes.forEach((b) => { b.removeAttribute('checked'); b._checked = false; });
bundleButton.dispatchEvent({ type: 'click', bubbles: true });
check('an empty bundle is refused locally', bundleStatus.textContent === i18n.choosePiece, bundleStatus.textContent);
check('an empty bundle sends no request', jq.lastCall('luma_add_bundle') === null);
boxes[0]._checked = true; boxes[0].setAttribute('checked', '');
boxes[1]._checked = true; boxes[1].setAttribute('checked', '');
bundleButton.dispatchEvent({ type: 'click', bubbles: true });
const bundleCall = jq.lastCall('luma_add_bundle');
check('a bundle request is sent', bundleCall !== null);
check('the status shows the localized adding copy', bundleStatus.textContent === i18n.addingBundle, bundleStatus.textContent);
env.flush();

/* ------------------------------------------------------------ share/clipboard */

section('Share');
const shareRejected = boot({ clipboard: { writeText: () => Promise.reject(new Error('NotAllowedError')) } });
const rejectedButton = shareRejected.document.querySelector('.luma-share-product');
rejectedButton.dispatchEvent({ type: 'click', bubbles: true });

const shareOk = boot();
const shareOkButton = shareOk.document.querySelector('.luma-share-product');
const originalShareLabel = shareOkButton.querySelector('span').textContent;
shareOkButton.dispatchEvent({ type: 'click', bubbles: true });

// Both clipboard promises settle on the microtask queue; assert on the next turn.
setTimeout(function () {
    section('Share — rejected clipboard');
    check('a rejected clipboard write is handled', unhandled.length === 0, unhandled.map(String).join('; '));
    check('the visitor is told to try again, in their language', rejectedButton.querySelector('span').textContent === shareRejected.i18n.tryAgain, rejectedButton.querySelector('span').textContent);

    section('Share — working clipboard');
    check('the button confirms the copy', shareOkButton.classList.contains('is-shared'));
    check('the confirmation is localized', shareOkButton.querySelector('span').textContent.indexOf(shareOk.i18n.copied) === 0, shareOkButton.querySelector('span').textContent);
    shareOk.window._fireTimeouts();
    check('the share label is restored', shareOkButton.querySelector('span').textContent === originalShareLabel, shareOkButton.querySelector('span').textContent);
    check('the shared state is cleared', !shareOkButton.classList.contains('is-shared'));
    finish();
}, 0);

/* ------------------------------------------------------- predictive search */

function predictiveSearchChecks() {
    section('Predictive search');
    const input = document.querySelector('.luma-predictive-input');
    const results = document.querySelector('.luma-predictive-results');
    input._jqValue = 'j';
    input.dispatchEvent({ type: 'input', bubbles: true });
    check('a single character sends no request', jq.lastCall('luma_predictive_search') === null);
    check('the combobox stays collapsed', input.getAttribute('aria-expanded') === 'false');
    input._jqValue = 'jack';
    input.dispatchEvent({ type: 'input', bubbles: true });
    window._fireTimeouts();
    check('a real term sends a request', jq.lastCall('luma_predictive_search') !== null);
    check('the term is forwarded', jq.lastCall('luma_predictive_search').fields.term === 'jack', jq.lastCall('luma_predictive_search').fields.term);
    check('the combobox announces activity', input.getAttribute('aria-expanded') === 'true' || input.getAttribute('aria-busy') === 'true');
    env.setResponder(() => ({ success: true, data: { html: '<a class="luma-predictive-item" href="#p1">Everyday Zip Jacket</a>' } }));
    env.flush();
    check('results are rendered into the listbox', results.querySelectorAll('a').length >= 1, results.innerHTML);
}

function filterChecks() {

    section('AJAX filtering');
    const filterForm = document.querySelector('[data-luma-filters]');
    const grid = document.querySelector('.woocommerce ul.products');
    filterForm.querySelector('select[name="product_cat"]')._jqValue = 'men';
    filterForm.dispatchEvent({ type: 'submit', bubbles: true });

    const filterCall = jq.lastCall('luma_filter_products');
    check('submitting the filters posts to the filter endpoint', filterCall !== null);
    check('the request carries the current page', filterCall && filterCall.fields.paged === '2', filterCall && filterCall.fields.paged);
    check('the request carries a base url', filterCall && !!filterCall.fields.base_url, filterCall && filterCall.fields.base_url);
    check('the base url is same-origin', filterCall && filterCall.fields.base_url.indexOf('https://luma.test/') === 0, filterCall && filterCall.fields.base_url);
    check('the chosen filter value is serialized', filterCall && filterCall.fields.product_cat === 'men', filterCall && filterCall.fields.product_cat);
    check('the grid is marked busy while loading', grid.getAttribute('aria-busy') === 'true');
    check('the grid gets a loading class', grid.classList.contains('is-loading'));

    const paginationMarkup = '<ul class="page-numbers"><li><span aria-current="page" class="page-numbers current">2</span></li></ul>';
    env.setResponder(() => ({
        success: true,
        data: { html: '<li class="product">Filtered result</li>', count: 12, pagination: paginationMarkup },
    }));
    env.flush();
    check('the response replaces the grid contents', grid.querySelectorAll('li.product').length === 1 && grid.textContent.indexOf('Filtered result') > -1);
    check('the result count uses the localized template', filterForm.querySelector('[data-luma-filter-count]').textContent === '12 pieces', filterForm.querySelector('[data-luma-filter-count]').textContent);
    check('server pagination replaces the stale markup', document.querySelector('.woocommerce-pagination').innerHTML.indexOf('aria-current="page"') > -1);
    check('the busy state is cleared', grid.getAttribute('aria-busy') === 'false' && !grid.classList.contains('is-loading'));
    check('the address bar is updated', window._historyEntries.length > 0 && /product_cat=men/.test(window._historyEntries[window._historyEntries.length - 1].url || ''), JSON.stringify(window._historyEntries.slice(-1)));

    section('AJAX filtering — failure path');
    filterForm.dispatchEvent({ type: 'submit', bubbles: true });
    env.setResponder(() => { throw new Error('network down'); });
    env.flush();
    const emptyState = grid.querySelector('.luma-filter-empty');
    check('a failed request renders an empty state', !!emptyState);
    check('the empty state is localized', emptyState && emptyState.textContent === i18n.error, emptyState && emptyState.textContent);
    check('the busy state is cleared on failure', grid.getAttribute('aria-busy') === 'false');

    section('AJAX filtering — hostile empty-state message');
    filterForm.dispatchEvent({ type: 'submit', bubbles: true });
    env.setResponder(() => ({ success: false, data: { message: '<script>window.__pwned2=1<\/script>No matches' } }));
    env.flush();
    const hostileState = grid.querySelector('.luma-filter-empty');
    check('the message is inserted as text', hostileState && hostileState.querySelectorAll('script').length === 0);
    check('the script never ran', window.__pwned2 === undefined);

}

function finish() {
    predictiveSearchChecks();
    // Runs last: the filter response replaces the product grid, which would
    // remove the loop buttons every earlier section depends on.
    filterChecks();
    console.log('\n' + '-'.repeat(58));
    if (failures.length) {
        console.log(failures.length + ' FAILED, ' + passed + ' passed');
        failures.forEach((f) => console.log('  - ' + f));
        process.exit(1);
    }
    console.log('All ' + passed + ' behaviour checks passed.');
    if (unhandled.length) {
        console.log('WARNING: ' + unhandled.length + ' unhandled promise rejection(s)');
        process.exit(1);
    }
}
