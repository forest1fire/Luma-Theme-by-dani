#!/usr/bin/env node
/**
 * A jQuery double for the smoke tests.
 *
 * Luma Core's front-end script is written against jQuery, so testing it means
 * providing one. This implements only the surface core.js actually touches —
 * selection, traversal, attributes, classes, delegated events, fragments and
 * $.post — on top of the minimal DOM in dom.js.
 *
 * AJAX is deliberately not real. $.post queues the call and the test flushes it
 * with a responder, so assertions stay synchronous and deterministic instead of
 * racing microtasks.
 */
'use strict';

const { matches, fragment } = require('./dom');

/** Parse `<div class="x">…` into a tag name and attribute map. */
function parseFragment(html) {
    const m = /^\s*<([a-zA-Z][-a-zA-Z0-9]*)((?:\s+[^\s=>]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]*))?)*)\s*\/?>/.exec(html);
    if (!m) return null;
    const attrs = {};
    const attrRe = /([^\s=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]*)))?/g;
    let a;
    while ((a = attrRe.exec(m[2]))) {
        attrs[a[1].toLowerCase()] = a[2] !== undefined ? a[2] : a[3] !== undefined ? a[3] : a[4] !== undefined ? a[4] : '';
    }
    return { tag: m[1].toLowerCase(), attrs };
}

function dashToCamel(name) {
    return name.replace(/-([a-z])/g, function (_, c) { return c.toUpperCase(); });
}

class JQ {
    constructor(nodes) {
        const list = nodes || [];
        for (let i = 0; i < list.length; i++) this[i] = list[i];
        this.length = list.length;
    }

    get nodes() {
        const out = [];
        for (let i = 0; i < this.length; i++) out.push(this[i]);
        return out;
    }

    each(fn) { this.nodes.forEach((el, i) => fn.call(el, i, el)); return this; }
    first() { return new JQ(this.length ? [this[0]] : []); }
    last() { return new JQ(this.length ? [this[this.length - 1]] : []); }
    eq(i) { const el = this[i < 0 ? this.length + i : i]; return new JQ(el ? [el] : []); }
    get(i) { return i === undefined ? this.nodes : this[i]; }

    find(selector) {
        const out = [];
        this.each(function () {
            for (const el of this.querySelectorAll(selector)) if (!out.includes(el)) out.push(el);
        });
        return new JQ(out);
    }

    closest(selector) {
        const out = [];
        this.each(function () {
            const hit = this.closest(selector);
            if (hit && !out.includes(hit)) out.push(hit);
        });
        return new JQ(out);
    }

    filter(arg) {
        const out = this.nodes.filter((el) => (typeof arg === 'function' ? arg.call(el, 0, el) : matches(el, arg)));
        return new JQ(out);
    }

    children(selector) {
        const out = [];
        this.each(function () {
            for (const el of this.children) if (!selector || matches(el, selector)) out.push(el);
        });
        return new JQ(out);
    }

    add(other) {
        const out = this.nodes.slice();
        new JQ(other && other.nodes ? other.nodes : [other]).each(function () { if (!out.includes(this)) out.push(this); });
        return new JQ(out);
    }

    /* ------------------------------------------------------- attributes */

    attr(name, value) {
        if (value === undefined && typeof name === 'object') {
            this.each(function () { for (const [k, v] of Object.entries(name)) this.setAttribute(k, v); });
            return this;
        }
        if (value === undefined) {
            const el = this[0];
            return el && el.hasAttribute(name) ? el.getAttribute(name) : undefined;
        }
        this.each(function () {
            if (value === null) this.removeAttribute(name);
            else this.setAttribute(name, typeof value === 'function' ? value(0, this.getAttribute(name)) : value);
        });
        return this;
    }

    removeAttr(name) { this.each(function () { this.removeAttribute(name); }); return this; }
    hasAttr(name) { const el = this[0]; return !!(el && el.hasAttribute(name)); }

    prop(name, value) {
        if (value === undefined) {
            const el = this[0];
            if (!el) return undefined;
            if (name === 'checked' || name === 'disabled' || name === 'selected') return el['_' + name] === true || el.hasAttribute(name);
            return el[name] !== undefined ? el[name] : el.getAttribute(name);
        }
        this.each(function () {
            if (name === 'checked' || name === 'disabled' || name === 'selected') {
                this['_' + name] = !!value;
                if (value) this.setAttribute(name, ''); else this.removeAttribute(name);
            } else {
                this[name] = value;
            }
        });
        return this;
    }

    val(value) {
        if (value === undefined) {
            const el = this[0];
            if (!el) return undefined;
            if (el._jqValue !== undefined) return el._jqValue;
            if (el.localName === 'select') {
                const sel = el.querySelectorAll('option').find((o) => o.hasAttribute('selected'));
                return sel ? (sel.getAttribute('value') || sel.textContent.trim()) : '';
            }
            return el.hasAttribute('value') ? el.getAttribute('value') : '';
        }
        this.each(function () {
            this._jqValue = String(value);
            this.setAttribute('value', String(value));
        });
        return this;
    }

    data(key, value) {
        if (value === undefined && typeof key === 'object') {
            this.each(function () { for (const [k, v] of Object.entries(key)) { this._jqData = this._jqData || {}; this._jqData[k] = v; } });
            return this;
        }
        if (value === undefined) {
            const el = this[0];
            if (!el) return undefined;
            if (el._jqData && el._jqData[key] !== undefined) return el._jqData[key];
            const raw = el.getAttribute('data-' + key.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase()));
            if (raw === null) return undefined;
            if (raw === 'true') return true;
            if (raw === 'false') return false;
            if (raw !== '' && !isNaN(Number(raw))) return Number(raw);
            return raw;
        }
        this.each(function () {
            this._jqData = this._jqData || {};
            this._jqData[key] = value;
        });
        return this;
    }

    removeData(key) { this.each(function () { if (this._jqData) delete this._jqData[key]; }); return this; }

    /* ------------------------------------------------------------ text */

    text(value) {
        if (value === undefined) return this.nodes.map((el) => el.textContent).join('');
        this.each(function () { this.textContent = value === null ? '' : String(value); });
        return this;
    }

    html(value) {
        if (value === undefined) return this.length ? this[0].innerHTML : '';
        this.each(function () {
            if (value === null || value === '') { this.innerHTML = ''; return; }
            // jQuery accepts nodes and wrapped sets, not just markup strings.
            if (value instanceof JQ || (value && value.localName)) {
                this.childNodes = [];
                for (const node of (value instanceof JQ ? value.nodes : [value])) this.appendChild(node);
                return;
            }
            this.innerHTML = String(value);
        });
        return this;
    }

    /* ---------------------------------------------------------- classes */

    addClass(names) { this.each(function () { this.classList.add(...String(names).split(/\s+/)); }); return this; }
    removeClass(names) {
        this.each(function () {
            if (!names) this.setAttribute('class', '');
            else this.classList.remove(...String(names).split(/\s+/));
        });
        return this;
    }
    hasClass(name) { return this.nodes.some((el) => el.classList.contains(name)); }
    toggleClass(name, force) {
        this.each(function () {
            String(name).split(/\s+/).forEach((n) => { if (n) this.classList.toggle(n, force); });
        });
        return this;
    }

    /* ------------------------------------------------------ DOM editing */

    append(arg) {
        const self = this;
        this.each(function () {
            const target = this;
            new JQ(resolveArg(self._factory, arg, target)).each(function () { target.appendChild(this); });
        });
        return this;
    }

    appendTo(arg) {
        const target = new JQ(resolveArg(this._factory, arg, null));
        const self = this;
        target.each(function () {
            const parent = this;
            self.each(function () { parent.appendChild(this); });
        });
        return this;
    }

    prepend(arg) {
        const self = this;
        this.each(function () {
            const parent = this;
            const kids = new JQ(resolveArg(self._factory, arg, parent));
            for (let i = kids.length - 1; i >= 0; i--) parent.insertBefore(kids[i], parent.firstElementChild);
        });
        return this;
    }

    remove() { this.each(function () { this.remove(); }); return this; }
    empty() { this.each(function () { this.childNodes = []; }); return this; }

    after(arg) {
        const self = this;
        this.each(function () {
            const node = this;
            const parent = node.parentNode;
            if (!parent) return;
            const kids = new JQ(resolveArg(self._factory, arg, parent));
            let ref = node.nextElementSibling;
            kids.each(function () { parent.insertBefore(this, ref); });
        });
        return this;
    }

    before(arg) {
        const self = this;
        this.each(function () {
            const node = this;
            const parent = node.parentNode;
            if (!parent) return;
            new JQ(resolveArg(self._factory, arg, parent)).each(function () { parent.insertBefore(this, node); });
        });
        return this;
    }

    /** Form fields as [{name, value}], matching jQuery's rules. */
    serializeArray() {
        const out = [];
        this.each(function () {
            const scope = this.localName === 'form' ? [this, ...this.querySelectorAll('input, select, textarea')] : [];
            for (const field of scope) {
                if (!field.localName || !['input', 'select', 'textarea'].includes(field.localName)) continue;
                const name = field.getAttribute('name');
                if (!name) continue;
                if (field.hasAttribute('disabled')) continue;
                const type = field.getAttribute('type');
                if ((type === 'checkbox' || type === 'radio') && !field.hasAttribute('checked') && field._checked !== true) continue;
                if (type === 'submit' || type === 'button') continue;
                let value;
                if (field.localName === 'select') {
                    const selected = field.querySelectorAll('option').find((o) => o.hasAttribute('selected'));
                    value = field._jqValue !== undefined ? field._jqValue : selected ? (selected.getAttribute('value') || selected.textContent.trim()) : '';
                } else {
                    value = field._jqValue !== undefined ? field._jqValue : (field.getAttribute('value') || '');
                }
                out.push({ name: name, value: String(value) });
            }
        });
        return out;
    }

    serialize() {
        return this.serializeArray()
            .map((f) => encodeURIComponent(f.name) + '=' + encodeURIComponent(f.value))
            .join('&');
    }

    /* ------------------------------------------------------- positioning */

    offset() { return { top: 0, left: 0 }; }
    scrollTop() { return 0; }
    animate(props) {
        if (props && typeof props.scrollTop === 'number' && this._window) this._window.scrollY = props.scrollTop;
        return this;
    }
    hide() { this.each(function () { this.setAttribute('hidden', ''); }); return this; }
    show() { this.each(function () { this.removeAttribute('hidden'); }); return this; }
    fadeOut(duration, callback) {
        this.hide();
        if (typeof duration === 'function') duration();
        else if (typeof callback === 'function') callback();
        return this;
    }
    fadeIn() { return this.show(); }
    focus() { this.each(function () { if (typeof this.focus === 'function') this.focus(); }); return this; }
    blur() { this.each(function () { if (typeof this.blur === 'function') this.blur(); }); return this; }
    is(selector) { return this.nodes.some((el) => matches(el, selector)); }

    /* ---------------------------------------------------------- events */

    on(types, selectorOrData, maybeHandler) {
        const handler = typeof selectorOrData === 'function' ? selectorOrData : maybeHandler;
        const selector = typeof selectorOrData === 'string' ? selectorOrData : null;
        if (typeof handler !== 'function') return this;

        String(types).split(/\s+/).forEach(function (rawType) {
            if (!rawType) return;
            const type = rawType.split('.')[0]; // strip jQuery namespace
            this.each(function () {
                const node = this;
                node.addEventListener(type, function (event) {
                    if (!selector) {
                        handler.call(node, decorate(event, node));
                        return;
                    }
                    const origin = event.target && event.target.localName ? event.target : node;
                    const hit = origin.closest(selector);
                    if (hit && node.contains(hit)) handler.call(hit, decorate(event, hit));
                });
            });
        }, this);
        return this;
    }

    off() { return this; }

    one(types, selector, handler) { return this.on(types, selector, handler); }

    trigger(type, extra) {
        const name = String(type).split('.')[0];
        this.each(function () {
            if (name === 'focus') { if (typeof this.focus === 'function') this.focus(); return; }
            if (name === 'blur') { if (typeof this.blur === 'function') this.blur(); return; }
            const event = { type: name, bubbles: true, target: this, detail: extra };
            if (name === 'change' || name === 'input' || name === 'submit') event.preventDefault = function () { event.defaultPrevented = true; };
            this.dispatchEvent(event);
        });
        return this;
    }

    submit() { return this.trigger('submit'); }
    click() { return this.trigger('click'); }
}

function decorate(event, currentTarget) {
    event.currentTarget = currentTarget;
    if (!event.preventDefault) event.preventDefault = function () { event.defaultPrevented = true; };
    if (!event.stopPropagation) event.stopPropagation = function () { event.cancelBubble = true; };
    if (event.which === undefined) event.which = 0;
    return event;
}

/** Turn a $.append()-style argument into a list of nodes. */
function resolveArg(factory, arg, context) {
    if (arg instanceof JQ) return arg.nodes;
    if (arg && arg.localName) return [arg];
    if (Array.isArray(arg)) return arg;
    if (typeof arg === 'string') {
        if (arg.trim().charAt(0) === '<') return factory.fragment(arg);
        return factory.query(arg, context);
    }
    return [];
}

/**
 * Build a jQuery-like `$` bound to a fake window.
 * Returns { $, flushAjax, ajaxCalls }.
 */
function createJQuery(window) {
    const document = window.document;
    const ajaxCalls = [];
    const ajaxHistory = [];

    function makeFragment(spec) {
        const el = document.createElement(spec.tag);
        for (const [k, v] of Object.entries(spec.attrs)) el.setAttribute(k, v);
        return el;
    }
    makeFragment.fragment = function (html) { return fragment(document, html); };

    function query(selector, context) {
        const root = context || document;
        const out = [];
        String(selector).split(',').forEach(function (part) {
            const trimmed = part.trim();
            if (!trimmed) return;
            for (const el of root.querySelectorAll(trimmed)) if (!out.includes(el)) out.push(el);
        });
        return out;
    }

    function build(nodes) {
        const jq = new JQ(nodes);
        jq._factory = makeFragment;
        jq._window = window;
        return jq;
    }

    function $(arg, context) {
        if (arg instanceof JQ) return arg;
        if (!arg) return build([]);
        if (arg === window) return build([window._host || document.documentElement]);
        if (arg === document || arg === document.documentElement) return build([arg]);
        if (arg.localName || arg.nodeType) return build([arg]);
        if (Array.isArray(arg)) return build(arg);
        if (typeof arg === 'function') { arg($); return build([]); }
        if (typeof arg === 'string') {
            if (arg.trim().charAt(0) === '<') {
                const created = build(fragment(document, arg));
                // jQuery's two-argument form: $('<li>', { class: 'x', text: 'y' })
                if (context && typeof context === 'object' && !context.localName && !(context instanceof JQ)) {
                    for (const [key, value] of Object.entries(context)) {
                        if (key === 'text') created.text(value);
                        else if (key === 'html') created.html(value);
                        else if (key === 'class') created.addClass(value);
                        else if (typeof value === 'function') created.on(key, value);
                        else created.attr(key, value);
                    }
                }
                return created;
            }
            if (arg === 'body') return build([document.body]);
            if (arg === 'html') return build([document.documentElement]);
            if (arg === 'html, body') return build([document.documentElement, document.body]);
            return build(query(arg, context && (context.localName ? context : context[0])));
        }
        return build([]);
    }

    /* -------------------------------------------------------------- ajax */

    function post(url, data) {
        const call = { url: url, data: data || {}, done: [], fail: [], always: [] };
        // core.js posts either an object or a pre-serialized query string
        // (the filter form does the latter). Expose both shapes so a test can
        // assert on fields without caring which one was used.
        if (typeof call.data === 'string') {
            call.raw = call.data;
            call.fields = {};
            for (const [k, v] of new URLSearchParams(call.data)) call.fields[k] = v;
        } else {
            call.fields = { ...call.data };
            call.raw = new URLSearchParams(call.fields).toString();
        }
        call.action = call.fields.action || null;
        ajaxCalls.push(call);
        ajaxHistory.push(call);

        const jqXHR = {
            done: function (fn) { call.done.push(fn); return jqXHR; },
            fail: function (fn) { call.fail.push(fn); return jqXHR; },
            always: function (fn) { call.always.push(fn); return jqXHR; },
            then: function (fn) { call.done.push(fn); return jqXHR; },
            catch: function (fn) { call.fail.push(fn); return jqXHR; },
            abort: function () { call.aborted = true; },
        };
        return jqXHR;
    }

    /**
     * Resolve every queued $.post through `responder(data)`.
     * Return a normal `{ success, data }` object for a successful response, or
     * throw to simulate a failed request.
     */
    function flushAjax(responder) {
        const queue = ajaxCalls.splice(0, ajaxCalls.length);
        let handled = 0;
        for (const call of queue) {
            if (call.aborted) continue;
            handled++;
            let response;
            let ok = true;
            try {
                response = responder ? responder(call.data, call) : { success: true, data: {} };
                if (response === undefined) response = { success: true, data: {} };
            } catch (error) {
                ok = false;
                response = { success: false, data: { message: error.message } };
            }
            const list = ok ? call.done : call.fail;
            for (const fn of list) fn(response, ok ? 'success' : 'error', jqXHRFor(call));
            for (const fn of call.always) fn(response, ok ? 'success' : 'error');
        }
        return handled;
    }

    function jqXHRFor() { return { status: 200, responseText: '' }; }

    /** Most recent request for an action, from the full history. */
    function lastCall(action) {
        for (let i = ajaxHistory.length - 1; i >= 0; i--) {
            if (!action || ajaxHistory[i].action === action) return ajaxHistory[i];
        }
        return null;
    }

    /** Every request for an action, oldest first. */
    function callsFor(action) {
        return ajaxHistory.filter((call) => !action || call.action === action);
    }

    function resetAjax() { ajaxCalls.length = 0; ajaxHistory.length = 0; }

    $.post = post;
    $.get = post;
    $.ajax = function (options) { return post(options && options.url, options && options.data); };
    $.each = function (obj, fn) { Object.keys(obj).forEach((k, i) => fn(i, obj[k])); };
    $.trim = function (s) { return String(s == null ? '' : s).trim(); };
    $.fn = JQ.prototype;
    $.expr = { pseudos: {} };

    return {
        $: $,
        flushAjax: flushAjax,
        ajaxCalls: ajaxCalls,
        ajaxHistory: ajaxHistory,
        lastCall: lastCall,
        callsFor: callsFor,
        resetAjax: resetAjax,
    };
}

module.exports = { createJQuery, JQ, parseFragment };
