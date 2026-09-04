(function ($) {
    'use strict';

    /*
     * Every handler below dereferences `lumaCore`. If the localized bundle is
     * missing the script used to throw on the first interaction, so bail out
     * cleanly and leave the native WooCommerce behaviour intact.
     */
    if (!window.lumaCore || !window.lumaCore.ajaxUrl) return;

    var i18n = window.lumaCore.i18n || {};
    function tt(key, fallback) {
        return typeof i18n[key] === 'string' && i18n[key] ? i18n[key] : fallback;
    }

    /**
     * Insert server-supplied copy as text, never as HTML. Several handlers
     * passed a message straight into .html(), which is an injection sink.
     */
    function safeText(target, message) {
        if (target && target.length) target.text(message || '');
    }

    var storage = {
        get: function (key) { try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { return []; } },
        set: function (key, value) { try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) {} }
    };

    function setCartCount(count) { $('.luma-cart-count').text(count); }
    function setCollectionCount(selector, count) { $(selector).text(count); }
    function lumaEvent(name, data) {
        if (!window.lumaCore || !lumaCore.analyticsEnabled || !lumaCore.analyticsConsent) return;
        window.dataLayer = window.dataLayer || [];
        data = data || {}; data.event = name; data.luma_attribution = data.luma_attribution || {};
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'].forEach(function (key) { var value = new URLSearchParams(window.location.search).get(key); if (value) data.luma_attribution[key] = value.slice(0, 120); });
        window.dataLayer.push(data);
    }

    var lumaLastFocus = null;
    function refreshDrawer(open) {
        var drawer = $('#luma-cart-drawer');
        if (!drawer.length) return;
        if (open && !drawer.hasClass('is-open')) lumaLastFocus = document.activeElement;
        $.post(lumaCore.ajaxUrl, { action: 'luma_cart_contents', nonce: lumaCore.nonce }).done(function (response) {
            if (response.success) applyDrawerData(response);
        });
        if (open) { drawer.addClass('is-open').attr('aria-hidden', 'false'); window.setTimeout(function () { drawer.find('.luma-cart-drawer__close').trigger('focus'); }, 0); }
    }
    function closeDrawer() { var drawer = $('#luma-cart-drawer'); drawer.removeClass('is-open').attr('aria-hidden', 'true'); if (lumaLastFocus && typeof lumaLastFocus.focus === 'function') { lumaLastFocus.focus(); lumaLastFocus = null; } }
    function applyDrawerData(response) {
        var drawer = $('#luma-cart-drawer');
        if (!drawer.length || !response || !response.success) return false;
        drawer.find('[data-cart-body]').html(response.data.html || '');
        drawer.find('[data-cart-meter]').html(response.data.meter || '');
        drawer.find('[data-cart-recommendations]').html(response.data.recommendations || '');
        drawer.find('[data-cart-saved]').html(response.data.saved || '');
        drawer.find('[data-cart-notices]').html(response.data.notices || '');
        drawer.find('[data-cart-subtotal]').html(response.data.subtotal || '');
        setCartCount(response.data.count || 0);
        return true;
    }

    var lumaModalLastFocus = null;
    var lumaOfferLastFocus = null;
    function openQuickView(html) {
        lumaModalLastFocus = document.activeElement;
        var modal = $('#luma-quick-view-modal');
        if (!modal.length) {
            $('body').append('<div id="luma-quick-view-modal" class="luma-modal" aria-hidden="true"><div class="luma-modal__backdrop"></div><div class="luma-modal__dialog" role="dialog" aria-modal="true"><button class="luma-modal__close" type="button" aria-label="Close">×</button><div class="luma-modal__inner"></div></div></div>');
            modal = $('#luma-quick-view-modal');
        }
        modal.find('.luma-modal__inner').html(html);
        modal.addClass('is-open').attr('aria-hidden', 'false');
        window.setTimeout(function () { modal.find('.luma-modal__close').trigger('focus'); }, 0);
    }
    function closeModal() { var modal = $('#luma-quick-view-modal'); modal.removeClass('is-open').attr('aria-hidden', 'true'); if (lumaModalLastFocus && typeof lumaModalLastFocus.focus === 'function') { lumaModalLastFocus.focus(); lumaModalLastFocus = null; } }
    function trapDialogFocus(event, dialog) {
        if ('Tab' !== event.key || !dialog.length) return;
        var focusable = dialog.find('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])').filter(':visible');
        if (!focusable.length) return;
        var first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
    $(document).on('keydown.lumaDialogs', function (event) {
        if ('Escape' === event.key) { if ($('#luma-cart-drawer').hasClass('is-open')) closeDrawer(); else if ($('#luma-quick-view-modal').hasClass('is-open')) closeModal(); else if ($('#luma-offer-popup').hasClass('is-open')) $('#luma-offer-popup .luma-offer-popup__close').trigger('click'); else if ($('.luma-size-guide__modal:not([hidden])').length) $('.luma-size-guide__modal:not([hidden]) .luma-size-guide__close').trigger('click'); }
        if ($('#luma-cart-drawer').hasClass('is-open')) trapDialogFocus(event, $('#luma-cart-drawer'));
        else if ($('#luma-quick-view-modal').hasClass('is-open')) trapDialogFocus(event, $('#luma-quick-view-modal'));
        else if ($('#luma-offer-popup').hasClass('is-open')) trapDialogFocus(event, $('#luma-offer-popup'));
        else if ($('.luma-size-guide__modal:not([hidden])').length) trapDialogFocus(event, $('.luma-size-guide__modal:not([hidden])'));
    });

    function updateLocalButton(button, saved) {
        button.toggleClass('is-saved', saved).attr('aria-pressed', saved ? 'true' : 'false');
        if (button.hasClass('luma-wishlist-toggle')) {
            button.attr('aria-label', saved ? tt('removeFromWishlist', 'Remove from wish list') : tt('addToWishlist', 'Add to wish list'));
        } else if (button.hasClass('luma-compare-toggle')) {
            button.attr('aria-label', saved ? tt('removeFromCompare', 'Remove from compare') : tt('addToCompare', 'Add to compare'));
        }
    }
    function syncCollection(name, ids) {
        if (!lumaCore.account || !lumaCore.account.loggedIn) return;
        $.post(lumaCore.ajaxUrl, { action: 'luma_sync_collection', nonce: lumaCore.nonce, collection: name, ids: ids });
    }
    function updateWishlistCount() { setCollectionCount('.luma-wishlist-count', storage.get('lumaWishlist').length); }
    function updateCompareCount() {
        var ids = storage.get('lumaCompare');
        setCollectionCount('[data-compare-count]', ids.length);
        $('.luma-compare-tray').toggleClass('is-visible', ids.length > 0);
        $('.luma-compare-toggle').each(function () { updateLocalButton($(this), ids.indexOf(String($(this).data('compare-id'))) !== -1); });
    }

    function loadCollection(type) {
        var selector = '[data-luma-collection="' + type + '"]';
        var ids = storage.get(type === 'recently_viewed' ? 'lumaRecentlyViewed' : type === 'wishlist' ? 'lumaWishlist' : 'lumaCompare');
        $(selector).each(function () {
            var collection = $(this);
            if (!ids.length) { collection.hide(); return; }
            $.post(lumaCore.ajaxUrl, { action: 'luma_local_collection', nonce: lumaCore.nonce, ids: ids, collection: type }).done(function (response) {
                if (response.success) collection.find('[data-luma-collection-grid]').html(response.data.html);
            });
        });
    }

    function initCountdowns() {
        $('.luma-countdown').each(function () {
            var block = $(this), end = new Date(String(block.data('end')).replace(' ', 'T'));
            if (isNaN(end.getTime())) return;
            var tick = function () {
                var seconds = Math.max(0, Math.floor((end.getTime() - Date.now()) / 1000));
                if (seconds <= 0) { block.data('luma-expired', true); block.addClass('is-expired'); }
                var days = Math.floor(seconds / 86400); seconds %= 86400;
                var hours = Math.floor(seconds / 3600); seconds %= 3600;
                var minutes = Math.floor(seconds / 60); seconds %= 60;
                block.find('[data-unit="days"]').text(String(days).padStart(2, '0'));
                block.find('[data-unit="hours"]').text(String(hours).padStart(2, '0'));
                block.find('[data-unit="minutes"]').text(String(minutes).padStart(2, '0'));
                block.find('[data-unit="seconds"]').text(String(seconds).padStart(2, '0'));
            };
            tick();
            // Stop the timer at zero: previously it kept running forever, and
            // re-running init would stack a second interval on the same node.
            var timer = window.setInterval(function () {
                tick();
                if (block.data('luma-expired')) { window.clearInterval(timer); }
            }, 1000);
            block.data('luma-countdown-timer', timer);
        });
    }

    /**
     * Clickable variation swatches.
     *
     * The native <select> stays the source of truth for WooCommerce's variation
     * matching, but it is removed from the accessibility tree and replaced by a
     * named button group that reports its own pressed state. Previously the
     * group had role="group" with no accessible name and no state, and a
     * display:none select, so assistive technology users lost the labelled
     * attribute control entirely.
     */
    function initVariationSwatches() {
        $('.variations select').each(function () {
            var select = $(this);
            if (select.data('luma-swatches') || select.find('option').length < 2) return;

            var row = select.closest('tr, .variation-wrap, div');
            var labelText = '';
            var labelFor = select.attr('id');
            if (labelFor) labelText = $('label[for="' + labelFor + '"]').first().text();
            if (!labelText) labelText = row.find('th, .label, label').first().text();
            labelText = String(labelText).replace(/[:\s]+$/, '').trim();

            var swatches = $('<div class="luma-variation-swatches"></div>')
                .attr('role', 'group')
                .attr('aria-label', labelText || select.attr('name') || tt('chooseOption', 'Choose an option'));

            function markSelected(value) {
                swatches.find('button').each(function () {
                    var button = $(this);
                    var on = button.attr('data-value') === String(value);
                    button.toggleClass('is-selected', on).attr('aria-pressed', on ? 'true' : 'false');
                });
            }

            select.find('option').each(function () {
                var option = $(this), value = option.attr('value');
                if (!value) return;
                var button = $('<button type="button"></button>')
                    .text(option.text())
                    .attr({ 'data-value': value, 'aria-pressed': 'false' });
                if (option.prop('disabled')) button.prop('disabled', true);
                button.on('click', function () {
                    select.val(value).trigger('change');
                    markSelected(value);
                });
                swatches.append(button);
            });

            // Keep the swatches in step when WooCommerce clears or resolves a
            // variation (attribute resets, gallery reloads, form resets).
            var form = select.closest('form.variations_form');
            if (form.length) {
                form.on('reset_data.lumaSwatches wc_variation_form.lumaSwatches', function () { markSelected(select.val() || ''); });
                form.on('show_variation.lumaSwatches', function () { markSelected(select.val() || ''); });
            }
            select.on('change.lumaSwatches', function () { markSelected(select.val() || ''); });

            select.hide().attr({ 'aria-hidden': 'true', 'tabindex': '-1' }).after(swatches).data('luma-swatches', true);
            markSelected(select.val() || '');
        });
    }

    $(document).on('click', '.luma-bag-link', function (event) {
        if ($('#luma-cart-drawer').length) { event.preventDefault(); refreshDrawer(true); }
    });
    $(document).on('click', '.luma-cart-drawer__close, .luma-cart-drawer__backdrop', closeDrawer);
    $(document).on('click', '.luma-mini-cart__controls [data-cart-action]', function () {
        var button = $(this), item = button.closest('[data-cart-key]'), key = item.data('cart-key'), quantity = parseInt(item.find('.luma-mini-cart__controls > span').text(), 10) || 0, action = button.data('cart-action');
        if ('plus' === action) quantity += 1; if ('minus' === action) quantity = Math.max(0, quantity - 1); if ('remove' === action) quantity = 0;
        button.prop('disabled', true);
        $.post(lumaCore.ajaxUrl, { action: 'luma_update_cart_item', nonce: lumaCore.nonce, cart_key: key, quantity: quantity }).done(function (response) { if (response.success) { applyDrawerData(response); } else { window.alert(response.data.message); } }).always(function () { button.prop('disabled', false); });
    });
    $(document).on('click', '.luma-quick-view', function () {
        var button = $(this); button.addClass('is-loading');
        $.post(lumaCore.ajaxUrl, { action: 'luma_quick_view', nonce: lumaCore.nonce, product_id: button.data('product-id') }).done(function (response) { if (response.success) openQuickView(response.data.html); else window.alert(response.data.message); }).always(function () { button.removeClass('is-loading'); });
    });
    $(document).on('click', '.luma-modal__close, .luma-modal__backdrop', closeModal);

    $(document).on('click', '.luma-quick-add', function () {
        var button = $(this); button.addClass('is-loading').prop('disabled', true);
        var original = button.html();
        $.post(lumaCore.ajaxUrl, { action: 'luma_quick_add', nonce: lumaCore.nonce, product_id: button.data('product-id') }).done(function (response) {
            if (response.success) {
                applyDrawerData(response);
                button.html($('<span>').text(tt('addedToBag', 'Added') + ' \u2713')).addClass('is-added');
                refreshDrawer(true);
                // The label was previously replaced permanently, so a second
                // click still read "Added".
                window.setTimeout(function () { button.removeClass('is-added').html(original); }, 2200);
            } else { safeText(button.closest('.luma-mini-product').find('.luma-bundle-status'), response.data && response.data.message); }
        }).fail(function (xhr) {
            window.alert(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : tt('error', 'Please choose an option or try again.'));
        }).always(function () { button.removeClass('is-loading').prop('disabled', false); });
    });

    $(document).on('click', '.luma-buy-now', function () {
        var button = $(this), form = button.closest('form.cart');
        if (!form.length) return;
        if (!form.find('[name="luma_buy_now"]').length) form.append('<input type="hidden" name="luma_buy_now" value="1">');
        form.find('button.single_add_to_cart_button').first().trigger('click');
    });
    $(document).on('click', '.luma-share-product', function () {
        var button = $(this), title = button.data('share-title') || document.title, share = { title: title, text: tt('shareText', 'Take a look at this piece.'), url: window.location.href };
        if (navigator.share) { navigator.share(share).catch(function () { /* dismissed */ }); }
        else if (navigator.clipboard && navigator.clipboard.writeText) {
            var label = button.find('span');
            var restore = label.text();
            // writeText rejects on insecure origins; the unhandled rejection
            // used to surface as a console error with no user feedback.
            navigator.clipboard.writeText(window.location.href).then(function () {
                button.addClass('is-shared'); label.text(tt('copied', 'Copied') + ' \u2713');
                window.setTimeout(function () { button.removeClass('is-shared'); label.text(restore); }, 2200);
            }).catch(function () { safeText(label, tt('tryAgain', 'Please try again.')); });
        }
    });
    $(document).on('click', '.luma-apply-coupon', function () {
        var button = $(this), status = button.closest('.luma-coupon-booster').find('.luma-coupon-status');
        button.addClass('is-loading').prop('disabled', true); safeText(status, tt('checking', 'Checking\u2026'));
        $.post(lumaCore.ajaxUrl, { action: 'luma_apply_coupon', nonce: lumaCore.nonce, coupon: button.data('coupon') }).done(function (response) { safeText(status, response.data && response.data.message); if (response.success) { button.addClass('is-applied').find('.luma-coupon-action').text('Applied ✓'); lumaEvent('apply_promotion', { promotion_id: String(button.data('coupon')) }); refreshDrawer(false); } }).fail(function (xhr) { status.text(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : lumaCore.i18n.error); }).always(function () { button.removeClass('is-loading').prop('disabled', false); });
    });

    $(document).on('click', '.luma-add-bundle', function () {
        var button = $(this), bundle = button.closest('[data-luma-bundle]'), ids = [];
        bundle.find('input[type="checkbox"]:checked').each(function () { ids.push($(this).val()); });
        var status = bundle.find('.luma-bundle-status');
        if (!ids.length) { safeText(status, tt('choosePiece', 'Select at least one piece.')); return; }
        button.prop('disabled', true); safeText(status, tt('addingBundle', 'Adding the edit\u2026'));
        $.post(lumaCore.ajaxUrl, { action: 'luma_add_bundle', nonce: lumaCore.nonce, product_ids: ids }).done(function (response) { if (response.success) { applyDrawerData(response); status.text(response.data.message); lumaEvent('luma_bundle_add', { items: ids }); refreshDrawer(true); } else status.text(response.data.message); }).fail(function (xhr) { status.text(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : tt('tryAgain', 'Please try again.')); }).always(function () { button.prop('disabled', false); });
    });
    $(document).on('change', '.luma-order-bump__toggle', function () {
        var toggle = $(this), box = toggle.closest('[data-luma-order-bump]'), status = box.find('.luma-order-bump__status'), add = toggle.is(':checked');
        toggle.prop('disabled', true); safeText(status, add ? tt('adding', 'Adding\u2026') : tt('removing', 'Removing\u2026'));
        $.post(lumaCore.ajaxUrl, { action: 'luma_toggle_order_bump', nonce: lumaCore.nonce, product_id: box.data('product-id'), add: add ? 1 : 0 }).done(function (response) { safeText(status, response.data && response.data.message); if (response.success) { setCartCount(response.data.count); lumaEvent(add ? 'add_to_cart' : 'remove_from_cart', { item_id: String(box.data('product-id')), item_list_name: 'checkout order bump' }); $(document.body).trigger('update_checkout'); } else toggle.prop('checked', !add); }).fail(function () { toggle.prop('checked', !add); status.text('Please try again.'); }).always(function () { toggle.prop('disabled', false); });
    });
    $(document).on('click', '.luma-wishlist-toggle', function () {
        var button = $(this), id = String(button.data('wishlist-id')), ids = storage.get('lumaWishlist'), index = ids.indexOf(id);
        if (index === -1) ids.push(id); else ids.splice(index, 1);
        storage.set('lumaWishlist', ids); syncCollection('wishlist', ids); updateLocalButton(button, index === -1); updateWishlistCount(); if (index !== -1 && button.closest('[data-luma-collection="wishlist"]').length) button.closest('.luma-mini-product').fadeOut(180, function () { var card = $(this), collection = card.closest('[data-luma-collection]'); card.remove(); if (!collection.find('.luma-mini-product').length) collection.find('[data-luma-collection-grid]').html('<p class="luma-collection-empty">Nothing saved here yet.</p>'); }); lumaEvent(index === -1 ? 'add_to_wishlist' : 'remove_from_wishlist', { item_id: id });
    });
    $(document).on('click', '.luma-compare-toggle', function () {
        var button = $(this), id = String(button.data('compare-id')), ids = storage.get('lumaCompare'), index = ids.indexOf(id);
        if (index === -1) { if (ids.length >= 4) { window.alert(tt('compareLimit', 'Compare up to four pieces at a time.')); return; } ids.push(id); } else ids.splice(index, 1);
        storage.set('lumaCompare', ids); syncCollection('compare', ids); updateCompareCount(); if (index !== -1 && button.closest('[data-luma-collection="compare"]').length) { var collection = button.closest('[data-luma-collection="compare"]'), table = button.closest('.luma-compare-table'); if (table.length) { table.find('[data-compare-product="' + id + '"]').remove(); if (!table.find('thead th[data-compare-product]').length) collection.find('[data-luma-collection-grid]').html('<p class="luma-collection-empty">Nothing saved here yet.</p>'); } else button.closest('.luma-mini-product').fadeOut(180, function () { var card = $(this); card.remove(); if (!collection.find('.luma-mini-product').length) collection.find('[data-luma-collection-grid]').html('<p class="luma-collection-empty">Nothing saved here yet.</p>'); }); }
    });

    $(document).on('input', '.luma-predictive-input', function () {
        if (lumaCore.searchEnabled === false) return;
        var input = $(this), term = input.val(), results = input.closest('.luma-search-form').find('.luma-predictive-results'), requestId = (input.data('searchRequestId') || 0) + 1, previous = input.data('searchXhr');
        input.data('searchRequestId', requestId); window.clearTimeout(input.data('searchTimer')); if (previous && previous.abort) previous.abort();
        if (term.length < 2) { results.empty().removeClass('is-visible'); input.attr({ 'aria-expanded': 'false', 'aria-busy': 'false' }); return; }
        results.html('<p class="luma-predictive-empty">' + lumaCore.i18n.searching + '</p>').addClass('is-visible'); input.attr({ 'aria-expanded': 'true', 'aria-busy': 'true' });
        input.data('searchTimer', window.setTimeout(function () {
            var xhr = $.post(lumaCore.ajaxUrl, { action: 'luma_predictive_search', nonce: lumaCore.nonce, term: term }); input.data('searchXhr', xhr);
            xhr.done(function (response) { if (requestId !== input.data('searchRequestId')) return; if (response.success) results.html(response.data.html).addClass('is-visible'); else results.html('<p class="luma-predictive-empty">' + (lumaCore.i18n.noResults || 'No products found.') + '</p>').addClass('is-visible'); }).fail(function () { if (requestId === input.data('searchRequestId')) results.html('<p class="luma-predictive-empty">' + (lumaCore.i18n.noResults || 'No products found.') + '</p>').addClass('is-visible'); }).always(function () { if (requestId === input.data('searchRequestId')) input.attr('aria-busy', 'false'); });
        }, 260));
    });
    $(document).on('keydown', '.luma-predictive-input', function (event) {
        if (lumaCore.searchEnabled === false) return;
        var input = $(this), results = input.closest('.luma-search-form').find('.luma-predictive-results'), options = results.find('.luma-predictive-result');
        if (event.key === 'Escape') { results.empty().removeClass('is-visible'); input.attr('aria-expanded', 'false'); return; }
        if (!options.length || (event.key !== 'ArrowDown' && event.key !== 'ArrowUp' && event.key !== 'Enter')) return;
        var current = options.index(options.filter('.is-active')), next = event.key === 'ArrowDown' ? current + 1 : event.key === 'ArrowUp' ? current - 1 : current;
        if (event.key === 'Enter' && current >= 0) { event.preventDefault(); window.location.href = options.eq(current).attr('href'); return; }
        event.preventDefault(); if (next >= options.length) next = 0; if (next < 0) next = options.length - 1; options.removeClass('is-active').eq(next).addClass('is-active');
    });
    function updateFilterSummary(form) {
        var summary = form.find('[data-luma-active-filters]'), chips = [];
        if (!summary.length) return;
        var category = form.find('[name="luma_cat"] option:selected');
        if (category.val()) chips.push({ key: 'category', label: category.text() });
        if (form.find('[name="min_price"]').val()) chips.push({ key: 'min', label: (lumaCore.i18n.min || 'Min') + ' ' + form.find('[name="min_price"]').val() });
        if (form.find('[name="max_price"]').val()) chips.push({ key: 'max', label: (lumaCore.i18n.max || 'Max') + ' ' + form.find('[name="max_price"]').val() });
        if (form.find('[name="stock_status"]:checked').length) chips.push({ key: 'stock', label: lumaCore.i18n.inStock || 'In stock' });
        if (form.find('[name="on_sale"]:checked').length) chips.push({ key: 'sale', label: lumaCore.i18n.onSale || 'On sale' });
        form.find('[name^="luma_attr"]:checked').each(function () { chips.push({ key: 'attribute', label: $(this).closest('label').text().trim() }); });
        if (!chips.length) { summary.empty(); return; }
        var html = '<span class="luma-active-filters__label">' + $('<span>').text(lumaCore.i18n.active || 'Active:').html() + '</span>';
        chips.forEach(function (chip) { html += '<button type="button" class="luma-filter-chip" data-luma-clear-filter="' + chip.key + '">' + $('<span>').text(chip.label).html() + ' <span aria-hidden="true">×</span></button>'; });
        html += '<button type="button" class="luma-filter-clear-all" data-luma-clear-filter="all">' + $('<span>').text(lumaCore.i18n.clearAll || 'Clear all').html() + '</button>';
        summary.html(html);
    }

    var lumaFilterLastFocus = null;
    function closeMobileFilters(form) {
        form.find('[data-luma-filter-panel]').removeClass('is-open').attr('aria-hidden', 'true');
        form.find('[data-luma-filter-toggle]').attr('aria-expanded', 'false');
        form.find('[data-luma-filter-backdrop]').attr('hidden', 'hidden');
        $('body').removeClass('luma-filter-open');
        if (lumaFilterLastFocus && typeof lumaFilterLastFocus.focus === 'function') { lumaFilterLastFocus.focus(); lumaFilterLastFocus = null; }
    }

    function syncFilterPagination(form) {
        var fields = form.serializeArray();
        $('.woocommerce-pagination a').each(function () {
            var link = new URL(this.href, window.location.href);
            ['luma_cat', 'luma_context_cat', 'min_price', 'max_price', 'stock_status', 'on_sale', 'luma_orderby', 'luma_attr'].forEach(function (name) { link.searchParams.delete(name); });
            Array.from(link.searchParams.keys()).forEach(function (name) { if (name.indexOf('luma_attr[') === 0) link.searchParams.delete(name); });
            fields.forEach(function (field) { if (field.name !== 'luma_context_cat' || field.value) link.searchParams.append(field.name, field.value); });
            this.href = link.toString();
        });
    }
    /** Empty/error state built with .text() so a server message can never be
     *  interpreted as markup. */
    function filterEmptyState(message) {
        var li = $('<li>', { 'class': 'luma-filter-empty' });
        li.text(message || tt('noResults', 'No products found.'));
        return li;
    }

    /**
     * Swap in pagination that matches the filtered result set.
     *
     * Previously the pagination rendered for the unfiltered query stayed on the
     * page, so a filtered view offered page numbers that no longer existed.
     */
    function applyFilterPagination(response) {
        var nav = $('.woocommerce-pagination').first();
        if (!nav.length) return;
        if (response.pagination) nav.html(response.pagination);
        else nav.remove();
    }

    function lumaApplyFilters(form, pushState) {
        var list = $('.woocommerce ul.products').first();
        if (!list.length) return;
        list.addClass('is-loading').attr('aria-busy', 'true');
        var url = new URL(window.location.href);
        var payload = form.serialize()
            + '&action=luma_filter_products'
            + '&nonce=' + encodeURIComponent(lumaCore.nonce)
            + '&paged=' + encodeURIComponent(url.searchParams.get('paged') || url.searchParams.get('product-page') || '1')
            + '&base_url=' + encodeURIComponent(url.origin + url.pathname + url.search);
        $.post(lumaCore.ajaxUrl, payload).done(function (response) {
            if (!response.success || !response.data) {
                list.empty().append(filterEmptyState((response.data && response.data.message) || tt('error', 'Please choose an option or try again.')));
                return;
            }
            if (response.data.html) list.html(response.data.html);
            else list.empty().append(filterEmptyState(response.data.message || tt('noResults', 'No products found.')));

            var count = Number(response.data.count) || 0;
            var template = tt('resultsCount', '%d pieces');
            form.find('[data-luma-filter-count]').text(template.indexOf('%d') !== -1 ? template.replace('%d', String(count)) : count + ' ' + tt('pieces', 'pieces'));

            if (pushState) {
                var next = new URL(window.location.href);
                next.search = '';
                form.serializeArray().forEach(function (field) { next.searchParams.append(field.name, field.value); });
                window.history.pushState({ lumaFilters: true }, '', next.toString());
            }
            if (response.data.pagination !== undefined) applyFilterPagination(response.data);
            else syncFilterPagination(form);
            updateFilterSummary(form);
            if (window.innerWidth <= 760) closeMobileFilters(form);
        }).fail(function () {
            list.empty().append(filterEmptyState(tt('error', 'Please choose an option or try again.')));
        }).always(function () { list.removeClass('is-loading').attr('aria-busy', 'false'); });
    }
    $(document).on('submit', '[data-luma-filters]', function (event) { event.preventDefault(); lumaApplyFilters($(this), true); });
    $(document).on('click', '[data-luma-filter-toggle]', function () { var button = $(this), form = button.closest('[data-luma-filters]'), panel = form.find('[data-luma-filter-panel]'), open = panel.toggleClass('is-open').hasClass('is-open'); panel.attr('aria-hidden', open ? 'false' : 'true'); button.attr('aria-expanded', open ? 'true' : 'false'); form.find('[data-luma-filter-backdrop]').prop('hidden', !open); $('body').toggleClass('luma-filter-open', open); if (open) { lumaFilterLastFocus = button[0]; setTimeout(function () { form.find('[data-luma-filter-close]').trigger('focus'); }, 0); } else { lumaFilterLastFocus = null; } });
    $(document).on('click', '[data-luma-filter-close], [data-luma-filter-backdrop]', function () { closeMobileFilters($(this).closest('[data-luma-filters]')); });
    $(document).on('click', '[data-luma-clear-filter]', function () {
        var button = $(this), form = button.closest('[data-luma-filters]'), key = button.data('luma-clear-filter');
        if ('all' === key || 'category' === key) form.find('[name="luma_cat"]').val('');
        if ('all' === key || 'min' === key) form.find('[name="min_price"]').val('');
        if ('all' === key || 'max' === key) form.find('[name="max_price"]').val('');
        if ('all' === key || 'stock' === key) form.find('[name="stock_status"]').prop('checked', false);
        if ('all' === key || 'sale' === key) form.find('[name="on_sale"]').prop('checked', false);
        if ('all' === key || 'attribute' === key) form.find('[name^="luma_attr"]').prop('checked', false);
        updateFilterSummary(form);
        lumaApplyFilters(form, true);
    });
    $(document).on('keydown', function (event) { if ('Escape' === event.key) $('[data-luma-filters]').each(function () { closeMobileFilters($(this)); }); });
    $(document).on('change', '[data-luma-filters] select, [data-luma-filters] input', function () { updateFilterSummary($(this).closest('[data-luma-filters]')); });
    $('[data-luma-filters]').each(function () { updateFilterSummary($(this)); });
    $(window).on('resize.lumaFilters', function () { if (window.innerWidth > 760) $('[data-luma-filters]').each(function () { closeMobileFilters($(this)); $(this).find('[data-luma-filter-panel]').attr('aria-hidden', 'false'); }); });
    window.addEventListener('popstate', function () { if ($('[data-luma-filters]').length) window.location.reload(); });

    $(document).on('click', '.luma-sticky-atc__button', function () {
        var form = $('form.cart').first(); if (form.length) { $('html, body').animate({ scrollTop: form.offset().top - 120 }, 350); form.find('button.single_add_to_cart_button').first().trigger('focus'); }
    });
    $(document).on('click', '.luma-save-for-later', function () {
        var button = $(this), cartKey = button.data('cart-key'), originalLabel = button.text();
        button.prop('disabled', true).text(tt('saving', 'Saving\u2026'));
        $.post(lumaCore.ajaxUrl, { action: 'luma_save_for_later', nonce: lumaCore.nonce, cart_key: cartKey }).done(function (response) { if (response.success) applyDrawerData(response); else window.alert(response.data.message); }).fail(function () { window.alert(tt('saveFailed', 'This item could not be saved.')); }).always(function () { button.prop('disabled', false).text(originalLabel); });
    });
    $(document).on('click', '.luma-saved-action[data-saved-action]', function () {
        var button = $(this), action = button.data('saved-action'), savedKey = button.data('saved-key');
        button.prop('disabled', true);
        $.post(lumaCore.ajaxUrl, { action: action === 'move' ? 'luma_move_saved_to_cart' : 'luma_remove_saved_item', nonce: lumaCore.nonce, saved_key: savedKey }).done(function (response) { if (response.success) { if (action === 'move') applyDrawerData(response); else $('#luma-cart-drawer [data-cart-saved]').html(response.data.saved || ''); } else window.alert(response.data.message); }).fail(function () { window.alert(tt('savedFailed', 'This saved item could not be updated.')); }).always(function () { button.prop('disabled', false); });
    });

    $(document).on('click', '.luma-sticky-buy-now', function () {
        var form = $('form.cart').first(); if (!form.length) return;
        if (!form.find('[name="luma_buy_now"]').length) form.append('<input type="hidden" name="luma_buy_now" value="1">');
        form.find('button.single_add_to_cart_button').first().trigger('click');
    });
    $(document).on('click', '.luma-size-guide__trigger', function () { var trigger = $(this), modal = trigger.siblings('.luma-size-guide__modal'); modal.data('luma-trigger', this).removeAttr('hidden'); trigger.attr('aria-expanded', 'true'); window.setTimeout(function () { modal.find('.luma-size-guide__close').trigger('focus'); }, 0); });
    $(document).on('click', '.luma-size-guide__close, .luma-size-guide__backdrop', function () { var modal = $(this).closest('.luma-size-guide__modal'), trigger = modal.data('luma-trigger'); modal.attr('hidden', true); if (trigger) $(trigger).attr('aria-expanded', 'false').trigger('focus'); });
    $(document).on('submit', '[data-luma-track-form]', function (event) {
        event.preventDefault(); var form = $(this), button = form.find('.luma-track-submit'), status = form.find('.luma-track-status'); button.prop('disabled', true); safeText(status, tt('checking', 'Checking\u2026'));
        $.post(lumaCore.ajaxUrl, { action: 'luma_track_order', nonce: lumaCore.nonce, order_id: form.find('[name="order_id"]').val(), email: form.find('[name="email"]').val() }).done(function (response) { status.text(response.success ? response.data.message + (response.data.date ? ' Placed ' + response.data.date + '.' : '') : response.data.message); }).fail(function (xhr) { status.text(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : tt('orderNotFound', 'We could not find that order.')); }).always(function () { button.prop('disabled', false); });
    });
    $(document).on('click', '.luma-offer-popup__close, .luma-offer-popup__backdrop', function () { var popup = $('#luma-offer-popup'); popup.removeClass('is-open').attr('aria-hidden', 'true'); try { sessionStorage.setItem('lumaOfferSeen', '1'); } catch (e) {} if (lumaOfferLastFocus && typeof lumaOfferLastFocus.focus === 'function') { lumaOfferLastFocus.focus(); lumaOfferLastFocus = null; } });
    $(document).on('click', '[data-luma-analytics]', function () {
        var state = $(this).data('luma-analytics'), secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = 'luma_analytics_consent=' + (state === 'accept' ? '1' : '0') + '; max-age=31536000; path=/; SameSite=Lax' + secure;
        if (state === 'accept') window.location.reload(); else $('#luma-analytics-consent').remove();
    });
    $(document).on('click', '.luma-copy-offer', function () {
        var button = $(this), code = button.data('offer-code'), restore = button.text();
        if (!navigator.clipboard || !navigator.clipboard.writeText) return;
        navigator.clipboard.writeText(String(code)).then(function () {
            button.text(tt('copied', 'Copied') + ' \u2713');
            window.setTimeout(function () { button.text(restore); }, 2200);
        }).catch(function () { button.text(tt('tryAgain', 'Please try again.')); });
    });
    $(document).on('submit', '[data-luma-lead]', function (event) {
        event.preventDefault(); var form = $(this), button = form.find('button[type="submit"]'), status = form.find('.luma-lead-status'); button.prop('disabled', true); safeText(status, tt('saving', 'Saving\u2026'));
        $.post(lumaCore.ajaxUrl, { action: 'luma_lead_capture', nonce: lumaCore.nonce, email: form.find('[name="email"]').val(), luma_website: form.find('[name="luma_website"]').val() }).done(function (response) { status.text(response.success ? response.data.message : response.data.message); if (response.success) { try { sessionStorage.setItem('lumaOfferSeen', '1'); } catch (e) {} } }).fail(function (xhr) { status.text(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : tt('tryAgain', 'Please try again.')); }).always(function () { button.prop('disabled', false); });
    });
    $(document).on('submit', '[data-luma-waitlist]', function (event) {
        event.preventDefault(); var form = $(this), button = form.find('button'), status = form.find('.luma-waitlist-status'); button.prop('disabled', true); safeText(status, tt('saving', 'Saving\u2026'));
        $.post(lumaCore.ajaxUrl, { action: 'luma_waitlist_signup', nonce: lumaCore.nonce, product_id: form.find('[name="product_id"]').val(), email: form.find('[name="email"]').val() }).done(function (response) { status.text(response.success ? response.data.message : response.data.message); if (response.success) form.find('input[type="email"]').val(''); }).fail(function (xhr) { status.text(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : tt('tryAgain', 'Please try again.')); }).always(function () { button.prop('disabled', false); });
    });

    $(document).on('submit', 'form.cart', function () { var id = $('.luma-current-product').data('luma-current-product'); lumaEvent('add_to_cart_intent', { item_id: id ? String(id) : '', item_list_name: 'product page' }); });
    $(document).on('click', '.luma-drawer-checkout, .checkout-button', function () { lumaEvent('begin_checkout', { item_list_name: 'bag' }); });
    $(document).on('click', '.luma-recommendation a, .luma-mini-product a', function () { var card = $(this).closest('[data-product-id]'), id = card.data('product-id'); if ( id ) lumaEvent('select_item', { item_id: String(id), item_list_name: 'recommendation' }); });
    $(document.body).on('added_to_cart', function () { lumaEvent('add_to_cart', { item_list_name: 'shop' }); refreshDrawer(true); });
    if ($('.luma-sticky-atc').length) { var toggleSticky = function () { $('body').toggleClass('is-scrolled-product', window.scrollY > 360); }; $(window).on('scroll', toggleSticky); toggleSticky(); }

    $(function () {
        lumaEvent('page_view', { page_type: document.body.className.indexOf('single-product') !== -1 ? 'product' : (document.body.className.indexOf('woocommerce') !== -1 ? 'commerce' : 'content') });
        if (lumaCore.account && lumaCore.account.loggedIn) {
            var serverWishlist = lumaCore.account.wishlist || [], localWishlist = storage.get('lumaWishlist');
            var serverCompare = lumaCore.account.compare || [], localCompare = storage.get('lumaCompare');
            storage.set('lumaWishlist', serverWishlist.concat(localWishlist.filter(function (id) { return serverWishlist.indexOf(String(id)) === -1; })).slice(0, 50));
            storage.set('lumaCompare', serverCompare.concat(localCompare.filter(function (id) { return serverCompare.indexOf(String(id)) === -1; })).slice(0, 4));
        }
        var current = $('.luma-current-product').data('luma-current-product');
        if (current) { var ids = storage.get('lumaRecentlyViewed').filter(function (id) { return String(id) !== String(current); }); ids.unshift(String(current)); storage.set('lumaRecentlyViewed', ids.slice(0, 12)); lumaEvent('view_item', { item_id: String(current), item_list_name: 'product page' }); }
        $('.luma-wishlist-toggle').each(function () { var button = $(this); updateLocalButton(button, storage.get('lumaWishlist').indexOf(String(button.data('wishlist-id'))) !== -1); });
        updateWishlistCount(); updateCompareCount(); loadCollection('wishlist'); loadCollection('compare'); loadCollection('recently_viewed'); initCountdowns(); initVariationSwatches();
        // Variation forms and product grids can be replaced after an AJAX
        // refresh, so re-run the progressive enhancements on those events.
        $(document.body).on('wc_fragment_refresh updated_wc_div added_to_cart removed_from_cart', function () { initVariationSwatches(); initCountdowns(); });
        var popup = $('#luma-offer-popup');
        if (popup.length) {
            var showPopup = function () { var seen = false; try { seen = sessionStorage.getItem('lumaOfferSeen'); } catch (e) {} if (!seen) { lumaOfferLastFocus = document.activeElement; popup.addClass('is-open').attr('aria-hidden', 'false'); window.setTimeout(function () { popup.find('.luma-offer-popup__close').trigger('focus'); }, 0); } };
            var delay = parseInt(popup.data('delay'), 10) || 8;
            if (window.innerWidth <= 760) delay = Math.max(delay, 12);
            window.setTimeout(showPopup, delay * 1000);
            if (window.innerWidth > 760) $(document).on('mouseleave.lumaOffer', function (event) { if (event.clientY <= 0) { showPopup(); $(document).off('mouseleave.lumaOffer'); } });
        }
    });
}(jQuery));
