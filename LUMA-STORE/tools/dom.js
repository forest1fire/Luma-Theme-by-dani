#!/usr/bin/env node
/**
 * Minimal DOM used by the smoke tests.
 *
 * Just enough of the browser to run the theme's dependency-free scripts against
 * real markup: a forgiving HTML parser, element/attribute handling, classList,
 * a descendant selector engine, event registration and dispatch, plus the
 * window APIs the scripts touch (localStorage, matchMedia, requestAnimationFrame,
 * MutationObserver). It is a test double, not a browser — nothing here ships.
 */
'use strict';

const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
const RAW = new Set(['script', 'style']);

/* ------------------------------------------------------------------ parser */

function tokenize(html) {
    const tokens = [];
    let i = 0;
    while (i < html.length) {
        if (html.startsWith('<!--', i)) {
            const end = html.indexOf('-->', i + 4);
            i = end === -1 ? html.length : end + 3;
            continue;
        }
        if (html.startsWith('<!', i)) {
            const end = html.indexOf('>', i);
            i = end === -1 ? html.length : end + 1;
            continue;
        }
        if (html[i] === '<') {
            const close = html[i + 1] === '/';
            const m = /^<\/?([a-zA-Z][-a-zA-Z0-9:]*)/.exec(html.slice(i));
            if (!m) { i++; continue; }
            let j = i + m[0].length;
            const attrs = {};
            for (;;) {
                while (j < html.length && /\s/.test(html[j])) j++;
                if (html[j] === '>' || html[j] === '/' || j >= html.length) break;
                const am = /^([^\s=/>]+)/.exec(html.slice(j));
                if (!am) { j++; continue; }
                const name = am[1].toLowerCase();
                j += am[1].length;
                let k = j;
                while (k < html.length && /\s/.test(html[k])) k++;
                if (html[k] === '=') {
                    k++;
                    while (k < html.length && /\s/.test(html[k])) k++;
                    const quote = html[k];
                    if (quote === '"' || quote === "'") {
                        const end = html.indexOf(quote, k + 1);
                        attrs[name] = html.slice(k + 1, end === -1 ? html.length : end);
                        j = end === -1 ? html.length : end + 1;
                    } else {
                        const vm = /^[^\s>]*/.exec(html.slice(k));
                        attrs[name] = vm[0];
                        j = k + vm[0].length;
                    }
                } else {
                    attrs[name] = '';
                    j = k;
                }
            }
            const selfClose = html[j] === '/';
            const end = html.indexOf('>', j);
            i = end === -1 ? html.length : end + 1;
            tokens.push({ type: close ? 'end' : 'start', name: m[1].toLowerCase(), attrs, selfClose, raw: true });
            continue;
        }
        const next = html.indexOf('<', i);
        const text = html.slice(i, next === -1 ? html.length : next);
        if (text.trim()) tokens.push({ type: 'text', value: text });
        i = next === -1 ? html.length : next;
    }
    return tokens;
}

/* ------------------------------------------------------------------- nodes */

class ClassList {
    constructor(el) { this.el = el; }
    get set() {
        const value = this.el.attributes.get('class') || '';
        return new Set(value.split(/\s+/).filter(Boolean));
    }
    write(set) { this.el.attributes.set('class', [...set].join(' ')); }
    add(...names) { const s = this.set; names.forEach((n) => n && s.add(n)); this.write(s); }
    remove(...names) { const s = this.set; names.forEach((n) => s.delete(n)); this.write(s); }
    contains(name) { return this.set.has(name); }
    toggle(name, force) {
        const s = this.set;
        const on = force === undefined ? !s.has(name) : !!force;
        if (on) s.add(name); else s.delete(name);
        this.write(s);
        return on;
    }
}

class Node {
    constructor(ownerDocument, tagName) {
        this.ownerDocument = ownerDocument;
        this.tagName = tagName ? tagName.toUpperCase() : null;
        this.localName = tagName || null;
        this.attributes = new Map();
        this.childNodes = [];
        this.parentNode = null;
        this.listeners = new Map();
        this._text = null;
        this.focused = false;
    }

    get nodeType() { return this.localName ? 1 : 3; }
    get children() { return this.childNodes.filter((n) => n.localName); }
    get firstElementChild() { return this.children[0] || null; }
    get nextElementSibling() {
        if (!this.parentNode) return null;
        const sibs = this.parentNode.children;
        return sibs[sibs.indexOf(this) + 1] || null;
    }

    appendChild(child) {
        if (child.parentNode) child.parentNode.removeChild(child);
        child.parentNode = this;
        this.childNodes.push(child);
        if (this.ownerDocument) this.ownerDocument._notify(this, child);
        return child;
    }
    insertBefore(child, ref) {
        const at = ref ? this.childNodes.indexOf(ref) : this.childNodes.length;
        if (child.parentNode) child.parentNode.removeChild(child);
        child.parentNode = this;
        this.childNodes.splice(at < 0 ? this.childNodes.length : at, 0, child);
        if (this.ownerDocument) this.ownerDocument._notify(this, child);
        return child;
    }
    removeChild(child) {
        const at = this.childNodes.indexOf(child);
        if (at > -1) this.childNodes.splice(at, 1);
        child.parentNode = null;
        return child;
    }
    remove() { if (this.parentNode) this.parentNode.removeChild(this); }

    get textContent() {
        if (this._rawText !== undefined) return this._rawText;
        return this.childNodes.map((n) => n.textContent).join('');
    }
    set textContent(value) {
        this.childNodes = [];
        this._rawText = undefined;
        const str = String(value);
        if (str === '') return;
        const text = new Node(this.ownerDocument, null);
        text._rawText = str;
        text.parentNode = this;
        this.childNodes.push(text);
        if (this.ownerDocument) this.ownerDocument._notify(this, text);
    }

    get innerHTML() { return this.childNodes.map((n) => (n._rawText !== undefined ? n._rawText : n.outerHTML)).join(''); }
    set innerHTML(html) {
        this.childNodes = [];
        parseInto(this.ownerDocument, this, String(html));
        if (this.ownerDocument) this.ownerDocument._notify(this, null);
    }
    get outerHTML() {
        const name = this.localName;
        const attrs = [...this.attributes].map(([k, v]) => (v === '' ? ' ' + k : ' ' + k + '="' + v + '"')).join('');
        if (VOID.has(name)) return '<' + name + attrs + '>';
        return '<' + name + attrs + '>' + this.innerHTML + '</' + name + '>';
    }

    getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
    setAttribute(name, value) { this.attributes.set(String(name).toLowerCase(), String(value)); }
    hasAttribute(name) { return this.attributes.has(String(name).toLowerCase()); }
    removeAttribute(name) { this.attributes.delete(String(name).toLowerCase()); }

    get className() { return this.attributes.get('class') || ''; }
    set className(value) { this.attributes.set('class', String(value)); }
    get classList() { if (!this._classList) this._classList = new ClassList(this); return this._classList; }
    get id() { return this.attributes.get('id') || ''; }
    get hidden() { return this.hasAttribute('hidden'); }
    set hidden(value) { if (value) this.setAttribute('hidden', ''); else this.removeAttribute('hidden'); }
    get style() {
        const self = this;
        if (!this._style) {
            this._style = {
                setProperty(prop, value) {
                    const current = self.getAttribute('style') || '';
                    const cleaned = current.replace(new RegExp('(^|;)\\s*' + prop + '\\s*:[^;]*;?'), '$1').replace(/^;|;$/g, '');
                    self.setAttribute('style', (cleaned ? cleaned + '; ' : '') + prop + ': ' + value);
                },
                get width() { return /width:\s*([^;]+)/.exec(self.getAttribute('style') || '')?.[1] || ''; },
                set width(value) { this.setProperty('width', value); },
            };
        }
        return this._style;
    }

    focus() { this.focused = true; if (this.ownerDocument) this.ownerDocument.activeElement = this; }
    blur() { this.focused = false; }
    click() { this.dispatchEvent({ type: 'click', target: this, bubbles: true }); }

    addEventListener(type, fn) {
        if (!this.listeners.has(type)) this.listeners.set(type, []);
        this.listeners.get(type).push(fn);
    }
    removeEventListener(type, fn) {
        const list = this.listeners.get(type);
        if (list) this.listeners.set(type, list.filter((f) => f !== fn));
    }
    dispatchEvent(event) {
        event.target = event.target || this;
        event.preventDefault = event.preventDefault || function () { event.defaultPrevented = true; };
        event.stopPropagation = event.stopPropagation || function () { event.cancelBubble = true; };
        let node = this;
        while (node) {
            event.currentTarget = node;
            for (const fn of (node.listeners.get(event.type) || []).slice()) {
                fn.call(node, event);
                if (event.cancelBubble) return !event.defaultPrevented;
            }
            if (!event.bubbles) break;
            node = node.parentNode;
        }
        return !event.defaultPrevented;
    }

    contains(other) {
        if (!other) return false;
        let node = other;
        while (node) {
            if (node === this) return true;
            node = node.parentNode;
        }
        return false;
    }

    closest(selector) {
        let node = this;
        while (node && node.localName) {
            if (matches(node, selector)) return node;
            node = node.parentNode;
        }
        return null;
    }

    querySelector(selector) { return queryAll(this, selector)[0] || null; }
    querySelectorAll(selector) { return queryAll(this, selector); }
    getElementsByTagName(name) { return descendants(this).filter((n) => n.localName === String(name).toLowerCase()); }
    getElementsByClassName(name) { return descendants(this).filter((n) => n.classList.contains(name)); }

    getBoundingClientRect() {
        return { top: 0, left: 0, right: 0, bottom: 0, width: 1200, height: 80, x: 0, y: 0 };
    }
    get offsetTop() { return 0; }
    get offsetLeft() { return 0; }
    get offsetHeight() { return 80; }
    get offsetWidth() { return 1200; }
    get scrollHeight() { return 5000; }
    get clientHeight() { return 800; }
    scrollIntoView() {}
}

function descendants(root, out = []) {
    for (const child of root.childNodes) {
        if (child.localName) { out.push(child); descendants(child, out); }
    }
    return out;
}

/* ---------------------------------------------------------------- selectors */

function parseSimple(text) {
    const sel = { tag: null, id: null, classes: [], attrs: [], nots: [], pseudos: [] };
    let rest = text;
    const notRe = /:not\(([^)]*)\)/g;
    let m;
    while ((m = notRe.exec(rest))) sel.nots.push(parseSimple(m[1]));
    rest = rest.replace(notRe, '');
    // Remaining pseudo-classes must be lifted out before the tag name is read,
    // otherwise `:checked` would be mistaken for an element type.
    const pseudoRe = /:([a-z-]+)/gi;
    while ((m = pseudoRe.exec(rest))) sel.pseudos.push(m[1].toLowerCase());
    rest = rest.replace(pseudoRe, '');
    const attrRe = /\[([^\]=]+)(?:=["']?([^\]"']*)["']?)?\]/g;
    while ((m = attrRe.exec(rest))) sel.attrs.push({ name: m[1].toLowerCase(), value: m[2] === undefined ? null : m[2] });
    rest = rest.replace(attrRe, '');
    const idRe = /#([\w-]+)/g;
    while ((m = idRe.exec(rest))) sel.id = m[1];
    rest = rest.replace(idRe, '');
    const classRe = /\.([\w-]+)/g;
    while ((m = classRe.exec(rest))) sel.classes.push(m[1]);
    rest = rest.replace(classRe, '');
    const tag = rest.trim();
    if (tag && tag !== '*') sel.tag = tag.toLowerCase();
    return sel;
}

function matchesSimple(el, sel) {
    if (!el || !el.localName) return false;
    if (sel.tag && el.localName !== sel.tag) return false;
    if (sel.id && el.id !== sel.id) return false;
    for (const c of sel.classes) if (!el.classList.contains(c)) return false;
    for (const a of sel.attrs) {
        if (!el.hasAttribute(a.name)) return false;
        if (a.value !== null && el.getAttribute(a.name) !== a.value) return false;
    }
    for (const n of sel.nots) if (matchesSimple(el, n)) return false;
    for (const p of sel.pseudos) {
        switch (p) {
            case 'checked':
                if (el._checked !== true && !el.hasAttribute('checked')) return false;
                break;
            case 'selected':
                if (el._selected !== true && !el.hasAttribute('selected')) return false;
                break;
            case 'disabled':
                if (el._disabled !== true && !el.hasAttribute('disabled')) return false;
                break;
            case 'enabled':
                if (el._disabled === true || el.hasAttribute('disabled')) return false;
                break;
            case 'visible':
                // Approximation: the stub has no layout, so treat anything not
                // explicitly hidden as visible.
                if (el.hasAttribute('hidden')) return false;
                break;
            case 'hidden':
                if (!el.hasAttribute('hidden')) return false;
                break;
            case 'empty':
                if (el.childNodes.length || (el.textContent || '').trim()) return false;
                break;
            case 'first-child':
                if (!el.parentNode || el.parentNode.children[0] !== el) return false;
                break;
            default:
                return false;
        }
    }
    return true;
}

function matches(el, selector) {
    return String(selector).split(',').some((group) => {
        const parts = group.trim().split(/\s+/).map(parseSimple);
        if (!matchesSimple(el, parts[parts.length - 1])) return false;
        let node = el.parentNode;
        for (let i = parts.length - 2; i >= 0; i--) {
            let found = false;
            while (node && node.localName) {
                if (matchesSimple(node, parts[i])) { node = node.parentNode; found = true; break; }
                node = node.parentNode;
            }
            if (!found) return false;
        }
        return true;
    });
}

function queryAll(root, selector) {
    return descendants(root).filter((el) => matches(el, selector));
}

/* ----------------------------------------------------------------- document */

class Document extends Node {
    constructor() {
        super(null, null);
        this.localName = '#document';
        this.ownerDocument = this;
        this.observers = [];
        this.readyState = 'loading';
        this.documentElement = this.createElement('html');
        this.appendChild(this.documentElement);
        this.head = this.createElement('head');
        this.body = this.createElement('body');
        this.documentElement.appendChild(this.head);
        this.documentElement.appendChild(this.body);
        this.activeElement = this.body;
        this.observers = [];
    }
    createElement(tag) { const el = new Node(this, tag); return el; }
    createTextNode(value) { const n = new Node(this, null); n._rawText = String(value); return n; }
    getElementById(id) { return descendants(this).find((el) => el.id === id) || null; }
    querySelector(selector) { return queryAll(this, selector)[0] || null; }
    querySelectorAll(selector) { return queryAll(this, selector); }
    _notify(node, child) { for (const obs of (this.observers || [])) obs._record(node, child); }
}

/* -------------------------------------------------------------------- window */

function createWindow() {
    const document = new Document();
    const store = new Map();

    const window = {
        document,
        navigator: { userAgent: 'luma-smoke-test', clipboard: null },
        location: { href: 'https://luma.test/shop/', origin: 'https://luma.test', pathname: '/shop/', search: '', hash: '' },
        innerWidth: 1440,
        innerHeight: 900,
        pageYOffset: 0,
        scrollY: 0,
        scrollTo() {},
        timers: [],
    };
    // window gets its own listener registry, backed by a detached element node.
    const host = new Node(document, 'window-host');
    window.addEventListener = host.addEventListener.bind(host);
    window.removeEventListener = host.removeEventListener.bind(host);
    window.dispatchEvent = host.dispatchEvent.bind(host);
    window._host = host;

    function makeStorage() {
        const map = new Map();
        return {
            getItem: (k) => (map.has(k) ? map.get(k) : null),
            setItem: (k, v) => map.set(k, String(v)),
            removeItem: (k) => map.delete(k),
            clear: () => map.clear(),
            get length() { return map.size; },
            key: (i) => [...map.keys()][i] || null,
        };
    }
    window.localStorage = makeStorage();
    window.sessionStorage = makeStorage();

    const historyEntries = [];
    window.history = {
        length: 1,
        state: null,
        pushState: function (state, title, url) { historyEntries.push({ type: 'push', state: state, url: url }); this.state = state; this.length++; },
        replaceState: function (state, title, url) { historyEntries.push({ type: 'replace', state: state, url: url }); this.state = state; },
        back: function () {},
    };
    window._historyEntries = historyEntries;

    let darkPreferred = false;
    const mediaListeners = [];
    window.matchMedia = function (query) {
        const isDark = /prefers-color-scheme:\s*dark/.test(query);
        const isMobile = /max-width:\s*(\d+)/.exec(query);
        return {
            media: query,
            matches: isDark ? darkPreferred : isMobile ? window.innerWidth <= parseInt(isDark ? 0 : isMobile[1], 10) : false,
            addEventListener(type, fn) { if (isDark) mediaListeners.push(fn); },
            removeEventListener() {},
            addListener(fn) { if (isDark) mediaListeners.push(fn); },
        };
    };
    window._setDarkPreferred = function (value) {
        darkPreferred = value;
        mediaListeners.forEach((fn) => fn({ matches: value }));
    };

    let rafQueue = [];
    window.requestAnimationFrame = function (fn) { rafQueue.push(fn); return rafQueue.length; };
    window._flushRaf = function () { const q = rafQueue; rafQueue = []; q.forEach((fn) => fn(window.scrollY)); };
    window.cancelAnimationFrame = function () {};

    // Timeouts and intervals are tracked separately so a test can assert that
    // an interval was actually cleared rather than merely overwritten.
    let timerSeq = 0;
    const timeouts = new Map();
    const intervals = new Map();
    window.timers = [];

    window.setTimeout = function (fn, ms) {
        const id = ++timerSeq;
        timeouts.set(id, { fn: fn, ms: ms || 0 });
        return id;
    };
    window.clearTimeout = function (id) { timeouts.delete(id); };
    window.setInterval = function (fn, ms) {
        const id = ++timerSeq;
        intervals.set(id, { fn: fn, ms: ms || 0 });
        return id;
    };
    window.clearInterval = function (id) { intervals.delete(id); };

    /** Run every due timeout once, and every interval once. */
    window._fireTimers = function () {
        const due = [...timeouts.entries()];
        timeouts.clear();
        for (const [, t] of due) t.fn();
        for (const [, t] of [...intervals.entries()]) t.fn();
        return due.length;
    };
    /** Run only the intervals, leaving them registered (as a browser would). */
    window._fireIntervals = function () {
        for (const [, t] of [...intervals.entries()]) t.fn();
        return intervals.size;
    };
    /** Run only the queued timeouts, once each. */
    window._fireTimeouts = function () {
        const due = [...timeouts.entries()];
        timeouts.clear();
        for (const [, t] of due) t.fn();
        return due.length;
    };
    window._pendingTimeouts = function () { return timeouts.size; };
    window._pendingIntervals = function () { return intervals.size; };
    window._timers = { timeouts: timeouts, intervals: intervals };

    window.MutationObserver = class {
        constructor(callback) { this.callback = callback; this.targets = []; document.observers.push(this); }
        observe(target) { this.targets.push(target); }
        disconnect() { this.targets = []; }
        _record(node) {
            for (const target of this.targets) {
                if (target === node || target.childNodes.includes(node) || (node && node.parentNode === target)) {
                    this.callback([{ target: node }], this);
                    return;
                }
            }
        }
    };

    window.getComputedStyle = function (el) {
        return { getPropertyValue: () => '', width: '1200px', display: el && el.hidden ? 'none' : 'block' };
    };
    window.fetch = function () { return Promise.reject(new Error('fetch is not available in the smoke-test window')); };
    window.alert = function () {};
    window.console = console;

    return window;
}

/* ------------------------------------------------------------- HTML loading */

function parseInto(document, root, html) {
    const tokens = tokenize(html);
    const stack = [root];
    for (const token of tokens) {
        const top = stack[stack.length - 1];
        if (token.type === 'text') {
            const text = new Node(document, null);
            text._rawText = token.value;
            text.parentNode = top;
            top.childNodes.push(text);
            continue;
        }
        if (token.type === 'end') {
            for (let i = stack.length - 1; i > 0; i--) {
                if (stack[i].localName === token.name) { stack.length = i; break; }
            }
            continue;
        }
        const el = new Node(document, token.name);
        for (const [k, v] of Object.entries(token.attrs)) el.attributes.set(k, v);
        el.parentNode = top;
        top.childNodes.push(el);
        if (VOID.has(token.name) || token.selfClose) continue;
        if (RAW.has(token.name)) { el._rawText = ''; stack.push(el); continue; }
        stack.push(el);
    }
    return root;
}

/**
 * Build a window whose document.body contains the given markup.
 * Raw <script> bodies are collected (not executed) so a test can choose what to run.
 */
function loadHTML(html) {
    const window = createWindow();
    const document = window.document;
    const bodyMatch = /<body[^>]*>([\s\S]*)<\/body>/i.exec(html);
    const headMatch = /<head[^>]*>([\s\S]*?)<\/head>/i.exec(html);
    const htmlMatch = /<html([^>]*)>/i.exec(html);

    if (htmlMatch) {
        const attrs = tokenize('<x ' + htmlMatch[1] + '>')[0].attrs;
        for (const [k, v] of Object.entries(attrs)) document.documentElement.attributes.set(k, v);
    }

    const scripts = [];

    /** Parse a fragment into `target`, lifting <script> bodies out first. */
    function ingest(fragment, target) {
        if (!fragment) return;
        const cleaned = fragment.replace(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi, function (whole, attrs, body) {
            scripts.push({ attrs, body, owner: target === document.head ? 'head' : 'body' });
            return '';
        });
        parseInto(document, target, cleaned);
    }

    ingest(headMatch && headMatch[1], document.head);
    ingest(bodyMatch && bodyMatch[1], document.body);

    document.readyState = 'complete';
    return { window, document, scripts };
}

/**
 * Parse an HTML fragment into detached top-level nodes.
 * Used by the jQuery double so `$('<div><span>x</span></div>')` keeps its
 * children instead of collapsing to a single empty element.
 */
function fragment(document, html) {
    const holder = new Node(document, 'fragment-host');
    parseInto(document, holder, String(html));
    return holder.childNodes.filter((n) => n.localName);
}

module.exports = { loadHTML, createWindow, Node, Document, matches, fragment };
