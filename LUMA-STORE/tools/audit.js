#!/usr/bin/env node
/**
 * Luma workspace static audit.
 *
 * Validates PHP, CSS, JS and JSON syntax across the theme, the Core plugin and
 * the demo kit, then runs a set of WordPress-specific quality checks that
 * cannot be caught by a linter alone (duplicate DOM ids, wrong text domains,
 * unescaped `echo`, undefined theme helpers, version drift).
 *
 * Usage: node tools/audit.js [--json]
 * Exits non-zero when an error-level problem is found.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const SOURCE = path.join(ROOT, 'source');
const AS_JSON = process.argv.includes('--json');

/* ------------------------------------------------------------------ collect */

const IGNORED_DIRS = new Set(['node_modules', '.git', '.tools']);

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.name.startsWith('.') && entry.name !== '.') {
            if (IGNORED_DIRS.has(entry.name)) continue;
        }
        if (IGNORED_DIRS.has(entry.name)) continue;
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, out);
        else out.push(full);
    }
    return out;
}

const files = walk(SOURCE);
const byExt = (ext) => files.filter((f) => f.toLowerCase().endsWith(ext));

const phpFiles = byExt('.php');
const cssFiles = byExt('.css');
const jsFiles = byExt('.js');
const jsonFiles = byExt('.json');

const problems = [];
function report(level, file, message, line) {
    problems.push({
        level,
        file: file ? path.relative(ROOT, file) : '(workspace)',
        line: line || null,
        message,
    });
}

/* ------------------------------------------------------------- module lookup */

/**
 * Resolve a dev dependency from the local tools folder, falling back to a
 * shared install so the audit also runs on machines without `npm install`.
 */
function resolveTool(name) {
    const candidates = [
        path.join(__dirname, 'node_modules', name),
        process.env.TOOLS_DIR ? path.join(process.env.TOOLS_DIR, 'node_modules', name) : null,
        name,
    ].filter(Boolean);
    for (const candidate of candidates) {
        try {
            return require(candidate);
        } catch (err) {
            /* try the next candidate */
        }
    }
    throw new Error(`Cannot resolve ${name}. Run "npm install" inside tools/.`);
}

/* --------------------------------------------------------------- php parser */

let parser = null;
try {
    const Engine = resolveTool('php-parser');
    parser = new Engine({
        parser: { extractDoc: false, php7: true, locations: true, suppressErrors: false },
        ast: { withPositions: true },
    });
} catch (err) {
    report('warn', null, 'php-parser unavailable: ' + err.message);
}

function phpSyntax(file) {
    if (!parser) return;
    const code = fs.readFileSync(file, 'utf8');
    try {
        parser.parseCode(code, path.basename(file));
    } catch (err) {
        const line = err && err.loc && err.loc.start ? err.loc.start.line : (err.lineNumber || null);
        report('error', file, 'PHP syntax error: ' + (err.message || String(err)), line);
    }
}

/* -------------------------------------------------------------- css parsing */

let cssTree = null;
try {
    cssTree = resolveTool('css-tree');
} catch (err) {
    report('warn', null, 'css-tree unavailable: ' + err.message);
}

function cssSyntax(file) {
    const code = fs.readFileSync(file, 'utf8');
    if (cssTree) {
        try {
            cssTree.parse(code, {
                filename: path.basename(file),
                positions: true,
                onParseError(error) {
                    report('error', file, 'CSS parse error: ' + error.message, error.line);
                },
            });
        } catch (err) {
            report('error', file, 'CSS parse failure: ' + err.message);
        }
    }
    // Brace balance is a cheap safety net even when css-tree is present.
    let depth = 0;
    let line = 1;
    let inComment = false;
    for (let i = 0; i < code.length; i++) {
        const ch = code[i];
        const next = code[i + 1];
        if (ch === '\n') line++;
        if (inComment) {
            if (ch === '*' && next === '/') { inComment = false; i++; }
            continue;
        }
        if (ch === '/' && next === '*') { inComment = true; i++; continue; }
        if (ch === '{') depth++;
        if (ch === '}') {
            depth--;
            if (depth < 0) report('error', file, 'Unbalanced CSS: extra closing brace', line);
            depth = Math.max(depth, 0);
        }
    }
    if (depth !== 0) report('error', file, `Unbalanced CSS: ${depth} unclosed block(s)`);
    if (inComment) report('error', file, 'Unterminated CSS comment');
}

/* --------------------------------------------------------------- js parsing */

function jsSyntax(file) {
    try {
        execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
    } catch (err) {
        const msg = String(err.stderr || err.message).split('\n').slice(0, 4).join(' | ');
        report('error', file, 'JS syntax error: ' + msg);
    }
}

/* ------------------------------------------------------------- json parsing */

function jsonSyntax(file) {
    try {
        JSON.parse(fs.readFileSync(file, 'utf8'));
    } catch (err) {
        const m = /position (\d+)/.exec(err.message);
        let line = null;
        if (m) line = fs.readFileSync(file, 'utf8').slice(0, Number(m[1])).split('\n').length;
        report('error', file, 'Invalid JSON: ' + err.message, line);
    }
}

/* --------------------------------------------------- php comment stripping */

/**
 * Blank out PHP comments while preserving every newline, so reported line
 * numbers stay correct. Strings (single, double and heredoc-free) are scanned
 * first so `https://example.com` and `#fragment` inside them are not mistaken
 * for `//` or `#` comments.
 */
function stripPhpComments(code) {
    const out = code.split('');
    let i = 0;
    let quote = null;
    while (i < code.length) {
        const ch = code[i];
        const next = code[i + 1];
        if (quote) {
            if (ch === '\\') { i += 2; continue; }
            if (ch === quote) quote = null;
            i++;
            continue;
        }
        if (ch === '"' || ch === "'") { quote = ch; i++; continue; }
        if (ch === '/' && next === '*') {
            while (i < code.length && !(code[i] === '*' && code[i + 1] === '/')) {
                if (out[i] !== '\n') out[i] = ' ';
                i++;
            }
            if (i < code.length) { out[i] = ' '; out[i + 1] = ' '; i += 2; }
            continue;
        }
        if ((ch === '/' && next === '/') || (ch === '#' && next !== '[')) {
            // Only a comment when it starts a token, not inside a URL.
            const prev = i > 0 ? code[i - 1] : '\n';
            const looksLikeUrl = ch === '/' && prev !== '\n' && !/\s/.test(prev);
            if (!looksLikeUrl) {
                while (i < code.length && code[i] !== '\n') { out[i] = ' '; i++; }
                continue;
            }
        }
        i++;
    }
    return out.join('');
}

/* --------------------------------------------------------- wordpress checks */

const THEME_DOMAIN = 'luma-commerce';
const CORE_DOMAIN = 'luma-commerce-core';

function themeDir(file) {
    const rel = path.relative(SOURCE, file);
    return rel.split(path.sep)[0];
}

function expectedDomain(file) {
    const dir = themeDir(file);
    if (dir === 'luma-commerce-core') return CORE_DOMAIN;
    if (dir === 'luma-commerce-theme') return THEME_DOMAIN;
    return null;
}

/** Collect every `__( 'x', 'domain' )` style call and flag foreign domains. */
function checkTextDomains(file) {
    const code = fs.readFileSync(file, 'utf8');
    const expected = expectedDomain(file);
    if (!expected) return;
    const re = /\b(?:__|_e|_x|_n|_nx|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\(([\s\S]{0,220}?)\)/g;
    let match;
    while ((match = re.exec(code))) {
        const args = match[1];
        const domains = [...args.matchAll(/['"]([a-z0-9\-_/]+)['"]\s*(?:,|\)|$)/gi)].map((m) => m[1]);
        const literal = [...args.matchAll(/,\s*['"]([a-z0-9\-_/]+)['"]\s*\)/gi)].map((m) => m[1]);
        for (const domain of literal.length ? literal : domains) {
            if (/^(luma|woocommerce)/.test(domain) && domain !== expected && domain !== 'woocommerce') {
                const line = code.slice(0, match.index).split('\n').length;
                report('error', file, `Wrong text domain "${domain}" (expected "${expected}")`, line);
            }
        }
    }
    // Translatable string passed through a variable: `__( $label, 'domain' )`.
    const varRe = /\b(?:__|_e|esc_html__|esc_attr__)\s*\(\s*\$/g;
    if (varRe.test(code)) {
        const line = code.slice(0, code.search(varRe)).split('\n').length;
        report('warn', file, 'Translation function called with a variable string (breaks .pot extraction)', line);
    }
}

/** Flag `echo $var` / `echo func(...)` that bypasses an escaping helper. */
function checkEchoEscaping(file) {
    const code = stripPhpComments(fs.readFileSync(file, 'utf8'));
    const lines = code.split('\n');
    const safeFn = /^(esc_html|esc_attr|esc_url|esc_js|wp_kses_post|wp_kses|absint|intval|floatval|number_format_i18n|wp_json_encode|tag_escape|wp_date|the_permalink|comment_text|the_content|the_title|dynamic_sidebar|wp_nav_menu|do_shortcode|the_post_thumbnail|get_avatar|the_archive_title|the_archive_description|the_posts_pagination|the_post_navigation|the_posts_navigation|wp_link_pages|comment_form|wp_list_comments|the_comments_navigation|woocommerce_[a-z_]+|luma_commerce_cart_link|luma_commerce_fallback_menu|luma_commerce_fallback_footer_menu|language_attributes|body_class|post_class|comment_class|bloginfo|wp_head|wp_footer|wp_body_open|the_custom_logo|search_form|get_search_form|get_header|get_footer|get_template_part|have_posts|the_post|add_query_arg|wp_nonce_field|submit_button|settings_fields|do_settings_sections|checked|selected|disabled|the_ID|comment_ID|paginate_links|wc_get_template_part|the_widget|is_active_sidebar|luma_core_render_[a-z_]+)$/;
    lines.forEach((line, i) => {
        const re = /<\?php\s+echo\s+([^;]+);/g;
        let m;
        while ((m = re.exec(line))) {
            const expr = m[1].trim();
            if (/^(esc_|wp_kses|absint|intval|floatval|number_format_i18n|wp_json_encode|tag_escape|sanitize_)/.test(expr)) continue;
            if (/^['"]/.test(expr)) continue; // literal HTML string
            const fnMatch = /^([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/.exec(expr);
            if (fnMatch && safeFn.test(fnMatch[1])) continue;
            // A ternary is safe when every branch is escaped or a literal.
            if (expr.includes('?') && expr.includes(':')) {
                const branches = expr.split('?').slice(1).join('?').split(':');
                const allSafe = branches.length >= 2 && branches.every((branch) => {
                    const b = branch.trim();
                    return /^['"]/.test(b) || /^(esc_|wp_kses|absint|intval|floatval|number_format_i18n|tag_escape|sanitize_)/.test(b);
                });
                if (allSafe) continue;
            }
            if (/^\$/.test(expr) && !/\.\s*\$/.test(expr)) {
                report('warn', file, `Unescaped variable output: echo ${expr.slice(0, 60)}`, i + 1);
            }
        }
    });
}

/**
 * Duplicate element ids that can co-render on one request.
 *
 * WordPress resolves exactly one top-level template per request, so ids shared
 * between (say) 404.php and single.php are fine. Ids are only a defect when
 * they appear twice inside a set of files that can render together: the shared
 * chrome (header, footer, includes, partials) plus one page template.
 *
 * Partial templates such as searchform.php and template-parts/* are included
 * more than once per request, so they must not emit static ids at all.
 */
const PAGE_TEMPLATES = new Set([
    '404.php', 'archive.php', 'front-page.php', 'home.php', 'index.php',
    'page.php', 'search.php', 'single.php', 'singular.php',
]);
const REPEATABLE_PARTIALS = ['searchform.php', 'template-parts/'];

function checkDuplicateIds() {
    const themeFiles = phpFiles.filter((f) => themeDir(f) === 'luma-commerce-theme');
    const collect = (file) => {
        const code = stripPhpComments(fs.readFileSync(file, 'utf8'));
        const rel = path.relative(ROOT, file);
        return [...code.matchAll(/\bid="([a-zA-Z0-9_\-]+)"/g)].map((m) => ({
            id: m[1],
            file: rel,
            line: code.slice(0, m.index).split('\n').length,
        }));
    };

    // (a) static ids inside a partial that can be included repeatedly.
    for (const file of themeFiles) {
        const rel = path.relative(path.join(SOURCE, 'luma-commerce-theme'), file).split(path.sep).join('/');
        if (!REPEATABLE_PARTIALS.some((prefix) => rel === prefix || rel.startsWith(prefix))) continue;
        for (const hit of collect(file)) {
            report('error', file, `Static id "${hit.id}" in a repeatable partial; use wp_unique_id() so it stays unique per render`, hit.line);
        }
    }

    // (b) ids shared between the always-rendered chrome and a page template,
    //     or between two chrome files.
    /*
     * An id emitted from inside a function body is a conditional provider, not
     * unconditional chrome. `inc/woocommerce.php` supplies <main id="primary">
     * only for the WooCommerce templates, which never co-render with page.php
     * or single.php, so it must not be reported as a collision.
     */
    const functionSpans = (code) => {
        const spans = [];
        for (const m of code.matchAll(/\bfunction\s+[a-zA-Z_][a-zA-Z0-9_]*\s*\([^)]*\)\s*\{/g)) {
            let depth = 1;
            let j = m.index + m[0].length;
            while (j < code.length && depth > 0) {
                if (code[j] === '{') depth++;
                else if (code[j] === '}') depth--;
                j++;
            }
            spans.push([m.index, j]);
        }
        return spans;
    };
    const collectChrome = (file) => {
        const code = stripPhpComments(fs.readFileSync(file, 'utf8'));
        const spans = functionSpans(code);
        const rel = path.relative(ROOT, file);
        return [...code.matchAll(/\bid="([a-zA-Z0-9_\-]+)"/g)]
            .filter((m) => !spans.some(([start, end]) => m.index > start && m.index < end))
            .map((m) => ({ id: m[1], file: rel, line: code.slice(0, m.index).split('\n').length }));
    };

    const chrome = themeFiles.filter((f) => !PAGE_TEMPLATES.has(path.basename(f)));
    const pages = themeFiles.filter((f) => PAGE_TEMPLATES.has(path.basename(f)));
    const chromeIds = new Map();
    for (const file of chrome) {
        for (const hit of collectChrome(file)) {
            if (!chromeIds.has(hit.id)) chromeIds.set(hit.id, []);
            chromeIds.get(hit.id).push(hit);
        }
    }
    for (const [id, owners] of chromeIds) {
        const distinctFiles = new Set(owners.map((o) => o.file));
        if (distinctFiles.size > 1) {
            report('error', null, `Duplicate static DOM id "${id}" in shared chrome: ` + owners.map((o) => `${o.file}:${o.line}`).join(', '));
        }
    }
    for (const file of pages) {
        for (const hit of collect(file)) {
            if (chromeIds.has(hit.id)) {
                report('error', file, `Static id "${hit.id}" collides with shared chrome at ` + chromeIds.get(hit.id).map((o) => `${o.file}:${o.line}`).join(', '), hit.line);
            }
        }
    }
}

/** Helpers referenced by templates but never defined anywhere in the source. */
function checkUndefinedHelpers() {
    const defined = new Set();
    const wpCore = new Set();
    for (const file of phpFiles) {
        const code = fs.readFileSync(file, 'utf8');
        for (const m of code.matchAll(/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g)) defined.add(m[1]);
    }
    const called = new Map();
    for (const file of phpFiles) {
        const code = fs.readFileSync(file, 'utf8');
        for (const m of code.matchAll(/\b(luma_[a-z0-9_]+)\s*\(/g)) {
            const name = m[1];
            if (defined.has(name)) continue;
            if (!called.has(name)) called.set(name, []);
            called.get(name).push(path.relative(ROOT, file) + ':' + code.slice(0, m.index).split('\n').length);
        }
    }
    for (const [name, sites] of called) {
        report('error', null, `Call to undefined helper ${name}() from ${[...new Set(sites)].slice(0, 4).join(', ')}`);
    }
}

/** Version strings must agree across style.css, bootstrap and readme. */
function checkVersions() {
    const styleCss = fs.readFileSync(path.join(SOURCE, 'luma-commerce-theme', 'style.css'), 'utf8');
    const themeVersion = (styleCss.match(/^Version:\s*([\d.]+)/m) || [])[1];
    const bootstrap = fs.readFileSync(path.join(SOURCE, 'luma-commerce-theme', 'functions.php'), 'utf8');
    const constant = (bootstrap.match(/LUMA_COMMERCE_VERSION',\s*'([\d.]+)'/) || [])[1];
    if (themeVersion && constant && themeVersion !== constant) {
        report('error', null, `Theme version drift: style.css=${themeVersion} functions.php=${constant}`);
    }
    const themeReadme = fs.readFileSync(path.join(SOURCE, 'luma-commerce-theme', 'readme.txt'), 'utf8');
    const readmeVersion = (themeReadme.match(/Stable tag:\s*([\d.]+)/i) || [])[1];
    if (themeVersion && readmeVersion && themeVersion !== readmeVersion) {
        report('warn', null, `Theme readme.txt stable tag (${readmeVersion}) differs from style.css (${themeVersion})`);
    }
    const plugin = fs.readFileSync(path.join(SOURCE, 'luma-commerce-core', 'luma-commerce-core.php'), 'utf8');
    const pluginHeader = (plugin.match(/^Version:\s*([\d.]+)/m) || [])[1];
    const pluginConst = (plugin.match(/LUMA_CORE_VERSION',\s*'([\d.]+)'/) || [])[1];
    if (pluginHeader && pluginConst && pluginHeader !== pluginConst) {
        report('error', null, `Core version drift: header=${pluginHeader} constant=${pluginConst}`);
    }
    const coreReadme = fs.readFileSync(path.join(SOURCE, 'luma-commerce-core', 'readme.txt'), 'utf8');
    const coreReadmeVersion = (coreReadme.match(/Stable tag:\s*([\d.]+)/i) || [])[1];
    if (pluginHeader && coreReadmeVersion && pluginHeader !== coreReadmeVersion) {
        report('warn', null, `Core readme.txt stable tag (${coreReadmeVersion}) differs from plugin header (${pluginHeader})`);
    }
    return { themeVersion, pluginHeader };
}

/**
 * WordPress and WooCommerce read compatibility data from file headers, not from
 * the prose in a readme. A missing `Tested up to` in style.css means the theme
 * details screen shows nothing; a missing `WC tested up to` means WooCommerce
 * treats the extension as untested and nags on every update. Verify the headers
 * exist, agree with each other, and agree with the readme.
 */
function headerValue(text, name) {
    const m = new RegExp('^\\s*\\*?\\s*' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ':\\s*(.+?)\\s*$', 'im');
    return m.test(text) ? m.exec(text)[1] : null;
}

function checkHeaders() {
    const themeDir = path.join(SOURCE, 'luma-commerce-theme');
    const coreDir = path.join(SOURCE, 'luma-commerce-core');

    const specs = [
        {
            label: 'theme style.css',
            file: path.join(themeDir, 'style.css'),
            required: ['Theme Name', 'Version', 'Requires at least', 'Tested up to', 'Requires PHP', 'License', 'License URI', 'Text Domain'],
        },
        {
            label: 'theme readme.txt',
            file: path.join(themeDir, 'readme.txt'),
            required: ['Stable tag', 'Tested up to', 'Requires at least', 'Requires PHP', 'License'],
        },
        {
            label: 'Core plugin header',
            file: path.join(coreDir, 'luma-commerce-core.php'),
            required: ['Plugin Name', 'Version', 'Requires at least', 'Tested up to', 'Requires PHP', 'License', 'License URI', 'Text Domain'],
        },
        {
            label: 'Core readme.txt',
            file: path.join(coreDir, 'readme.txt'),
            required: ['Stable tag', 'Tested up to', 'Requires at least', 'Requires PHP', 'License'],
        },
    ];

    const texts = {};
    for (const spec of specs) {
        if (!fs.existsSync(spec.file)) {
            report('error', null, `Missing metadata file: ${spec.label}`);
            continue;
        }
        const text = fs.readFileSync(spec.file, 'utf8');
        texts[spec.label] = text;
        for (const name of spec.required) {
            if (!headerValue(text, name)) {
                report('error', spec.file, `${spec.label} is missing the "${name}" header`);
            }
        }
    }

    // Each package must not contradict itself between its header and its readme.
    const pairs = [
        ['theme style.css', 'theme readme.txt', 'theme'],
        ['Core plugin header', 'Core readme.txt', 'Luma Core'],
    ];
    for (const [headerKey, readmeKey, label] of pairs) {
        if (!texts[headerKey] || !texts[readmeKey]) continue;
        for (const field of ['Tested up to', 'Requires at least', 'Requires PHP']) {
            const a = headerValue(texts[headerKey], field);
            const b = headerValue(texts[readmeKey], field);
            if (a && b && a !== b) {
                report('error', null, `${label} "${field}" disagrees: header=${a} readme=${b}`);
            }
        }
    }

    // A distributable readme must carry a changelog, and the version being
    // shipped must appear in it — otherwise customers upgrading cannot tell
    // what changed.
    for (const [label, readmeKey, version] of [
        ['theme', 'theme readme.txt', versions.themeVersion],
        ['Luma Core', 'Core readme.txt', versions.pluginHeader],
    ]) {
        const text = texts[readmeKey];
        if (!text) continue;
        if (!/^==\s*Changelog\s*==\s*$/im.test(text)) {
            report('error', null, `${label} readme.txt has no "== Changelog ==" section`);
            continue;
        }
        if (version && !new RegExp('^=\\s*' + version.replace(/\./g, '\\.') + '\\s*=\\s*$', 'im').test(text)) {
            report('error', null, `${label} readme.txt changelog has no entry for the shipping version ${version}`);
        }
    }

    // WooCommerce compatibility is declared separately and is easy to forget.
    // Reported as a warning because the correct value depends on real testing
    // against a running store, which no static check can perform.
    const corePlugin = texts['Core plugin header'];
    if (corePlugin) {
        const wcTested = headerValue(corePlugin, 'WC tested up to');
        const wcRequires = headerValue(corePlugin, 'WC requires at least');
        if (!wcTested || !wcRequires) {
            report(
                'warn',
                path.join(coreDir, 'luma-commerce-core.php'),
                'Luma Core declares no "WC tested up to" / "WC requires at least" header, so WooCommerce ' +
                'lists it as untested and warns before every WooCommerce update. Set both after testing ' +
                'against a live store.'
            );
        }
    }

    const themeTested = headerValue(texts['theme style.css'] || '', 'Tested up to');
    const coreTested = headerValue(texts['Core plugin header'] || '', 'Tested up to');
    if (themeTested && coreTested && themeTested !== coreTested) {
        report('warn', null, `Theme and plugin declare different WordPress compatibility: theme=${themeTested} plugin=${coreTested}`);
    }

    return { themeTested, coreTested };
}

/**
 * Every key a script looks up in its localized bundle must actually be provided
 * by the matching wp_localize_script() call. A typo or a forgotten key does not
 * throw — the helper quietly returns the English fallback, so a translated site
 * shows one stray English string and nothing looks wrong.
 */
function phpI18nKeys(text) {
    const start = text.indexOf("'i18n'");
    if (start === -1) return null;
    const open = text.indexOf('array(', start);
    if (open === -1) return null;
    let depth = 0;
    let end = -1;
    for (let i = open + 'array'.length; i < text.length; i++) {
        if (text[i] === '(') depth++;
        else if (text[i] === ')') {
            depth--;
            if (depth === 0) { end = i; break; }
        }
    }
    if (end === -1) return null;
    const block = text.slice(open, end);
    const keys = new Set();
    for (const m of block.matchAll(/'([a-zA-Z0-9_]+)'\s*=>/g)) keys.add(m[1]);
    return keys;
}

function jsI18nKeys(text, accessor) {
    const keys = new Set();
    // Direct lookups: t('key', …) / tt('key', …)
    for (const m of text.matchAll(new RegExp('\\b' + accessor + '\\(\\s*[\'"]([a-zA-Z0-9_]+)[\'"]', 'g'))) keys.add(m[1]);
    // Keys held in a table and passed to the accessor indirectly. The theme's
    // count-label targets do this: { base: t('viewBag'), one: 'oneItemInBag',
    // many: 'itemsInBag' }. Only one/many are indirect — `base` is already a
    // direct call, and `key:` is used for unrelated descriptors in core.js.
    for (const m of text.matchAll(/\b(?:one|many)\s*:\s*'([a-zA-Z0-9_]+)'/g)) keys.add(m[1]);
    return keys;
}

function checkI18nKeys() {
    const pairs = [
        {
            label: 'theme',
            php: path.join(SOURCE, 'luma-commerce-theme', 'inc', 'core.php'),
            js: path.join(SOURCE, 'luma-commerce-theme', 'assets', 'js', 'theme.js'),
            accessor: 't',
        },
        {
            label: 'Luma Core',
            php: path.join(SOURCE, 'luma-commerce-core', 'luma-commerce-core.php'),
            js: path.join(SOURCE, 'luma-commerce-core', 'assets', 'js', 'core.js'),
            accessor: 'tt',
        },
    ];

    for (const pair of pairs) {
        if (!fs.existsSync(pair.php) || !fs.existsSync(pair.js)) continue;
        const provided = phpI18nKeys(fs.readFileSync(pair.php, 'utf8'));
        if (!provided) {
            report('warn', pair.php, `${pair.label}: could not locate the wp_localize_script i18n array`);
            continue;
        }
        const used = jsI18nKeys(fs.readFileSync(pair.js, 'utf8'), pair.accessor);
        const missing = [...used].filter((key) => !provided.has(key)).sort();
        if (missing.length) {
            report(
                'error',
                pair.js,
                `${pair.label} script looks up i18n key(s) the PHP bundle never provides: ${missing.join(', ')}. ` +
                'They silently fall back to English on translated sites.'
            );
        }
    }
}



/**
 * WooCommerce calls that are reachable while WooCommerce is inactive.
 *
 * Luma Core already fataled once this way: the offer and size-guide popups
 * called WooCommerce functions with no guard, so any site without the plugin
 * active hit a fatal error. A guard only counts if it sits between the entry
 * point and the call, so this walks the AST instead of grepping.
 *
 * A function is treated as safe when any of these hold:
 *   - some enclosing `if` / ternary tests for WooCommerce (function_exists,
 *     class_exists, method_exists, or a Luma availability helper);
 *   - its own body contains such a test — the usual
 *     `if ( ! class_exists( 'WooCommerce' ) ) return;` early exit;
 *   - it is only reached from WooCommerce's own hooks, which cannot fire when
 *     WooCommerce is inactive;
 *   - every call site is itself safe, so the guard lives in the caller. That
 *     last rule is what makes helpers like luma_core_cart_payload() correct:
 *     they hold no guard, but each AJAX handler that calls them checks
 *     luma_core_cart_available() first.
 *
 * WooCommerce's own template overrides are skipped: WordPress only loads them
 * through WooCommerce.
 */
const WC_CALL = /^(WC|wc_[a-z0-9_]+|is_cart|is_checkout|is_checkout_pay_page|is_product|is_product_category|is_product_tag|is_product_taxonomy|is_shop|is_woocommerce|is_account_page|is_wc_endpoint_url|is_store_notice_showing)$/;
const WC_CLASS = /^WC_/;
const GUARD_RE = /class_exists|function_exists|method_exists|is_plugin_active|luma_core_wc_active|luma_core_cart_available|luma_core_session_available|luma_core_woocommerce|shortcode_exists/;
const WC_HOOK = /^(woocommerce_|wc_|woocommerce$)/;

function astWalk(node, visit, ancestors) {
    if (!node || typeof node !== 'object') return;
    if (Array.isArray(node)) {
        for (const child of node) astWalk(child, visit, ancestors);
        return;
    }
    if (typeof node.kind !== 'string') return;
    visit(node, ancestors);
    ancestors.push(node);
    for (const key of Object.keys(node)) {
        if (key === 'loc' || key === 'position' || key === 'offset') continue;
        const value = node[key];
        if (value && typeof value === 'object') astWalk(value, visit, ancestors);
    }
    ancestors.pop();
}

function nodeSource(node, lines) {
    if (!node || !node.loc) return '';
    const start = node.loc.start.line;
    const end = node.loc.end ? node.loc.end.line : start;
    return lines.slice(start - 1, end).join('\n');
}

function nodeName(node) {
    if (!node) return null;
    if (node.kind === 'name') return node.name;
    if (node.kind === 'string') return node.value;
    return null;
}

function checkWooCommerceGuards(file) {
    if (!parser) return;
    // WooCommerce template overrides are only ever loaded by WooCommerce.
    if (/(^|\/)woocommerce\/[^/]+\.php$/.test(file.split(path.sep).join('/'))) return;

    const code = fs.readFileSync(file, 'utf8');
    const lines = code.split('\n');
    let ast;
    try {
        ast = parser.parseCode(code, path.basename(file));
    } catch (err) {
        return; // syntax errors are reported by phpSyntax()
    }

    const functions = new Map();   // name -> { name, body, hooks: [], wcCalls: [] }
    const callSites = new Map();   // callee -> [{ caller, guarded, line }]
    const nodeToFn = new Map();

    function enclosingFn(ancestors) {
        for (let i = ancestors.length - 1; i >= 0; i--) {
            const a = ancestors[i];
            if (a.kind === 'function' || a.kind === 'method' || a.kind === 'closure') {
                if (nodeToFn.has(a)) return nodeToFn.get(a);
            }
        }
        return null;
    }

    /** Is any enclosing conditional testing for WooCommerce? */
    function insideGuard(ancestors) {
        return ancestors.some((a) => {
            if (a.kind !== 'if' && a.kind !== 'retif' && a.kind !== 'while') return false;
            return GUARD_RE.test(nodeSource(a.test, lines));
        });
    }

    // Pass 1 — register every function declaration.
    astWalk(ast, function (node) {
        if (node.kind !== 'function' && node.kind !== 'method') return;
        const name = (node.name && (node.name.name || node.name)) || null;
        if (!name) return;
        const record = {
            name: name,
            node: node,
            body: nodeSource(node, lines),
            hooks: [],
            wcCalls: [],
            line: node.loc && node.loc.start ? node.loc.start.line : 0,
        };
        functions.set(name, record);
        nodeToFn.set(node, record);
    }, []);

    // Pass 2 — hook registrations, WooCommerce calls and internal call sites.
    astWalk(ast, function (node, ancestors) {
        if (node.kind !== 'call' && node.kind !== 'new') return;
        const name = node.kind === 'new' ? nodeName(node.what) : nodeName(node.what);
        if (!name) return;
        const caller = enclosingFn(ancestors);
        const guarded = insideGuard(ancestors);
        const line = node.loc && node.loc.start ? node.loc.start.line : 0;

        if (node.kind === 'call' && (name === 'add_action' || name === 'add_filter')) {
            const args = node.arguments || [];
            const hookName = nodeName(args[0]);
            let cbName = nodeName(args[1]);
            if (!cbName && args[1] && args[1].kind === 'closure') cbName = null;
            if (hookName && cbName && functions.has(cbName)) functions.get(cbName).hooks.push(hookName);
            return;
        }

        const isWc = node.kind === 'new' ? WC_CLASS.test(name) : (WC_CALL.test(name) || WC_CLASS.test(name));
        if (isWc) {
            const label = node.kind === 'new' ? 'new ' + name : name;
            if (caller) caller.wcCalls.push({ name: label, line: line, guarded: guarded });
            else if (!guarded) {
                // Top-level template code with no conditional around it.
                report(
                    'warn', file,
                    `Unguarded ${label} at template top level. This fatals when WooCommerce is inactive.`,
                    line
                );
            }
            return;
        }

        // Internal call: record it so caller safety can propagate.
        if (functions.has(name)) {
            if (!callSites.has(name)) callSites.set(name, []);
            callSites.get(name).push({ caller: caller ? caller.name : null, guarded: guarded, line: line });
        }
    }, []);

    // Pass 3 — decide which functions are safe, propagating through callers.
    const safe = new Map();
    function ownGuard(f) {
        return GUARD_RE.test(f.body) || (f.hooks.length > 0 && f.hooks.every((h) => WC_HOOK.test(h)));
    }
    for (const f of functions.values()) safe.set(f.name, ownGuard(f));

    for (let round = 0; round < 6; round++) {
        let changed = false;
        for (const f of functions.values()) {
            if (safe.get(f.name) || !f.wcCalls.length) continue;
            const sites = callSites.get(f.name) || [];
            if (!sites.length) continue; // unreachable: nothing calls it
            const allSafe = sites.every((site) => {
                if (site.guarded) return true;
                return site.caller !== null && safe.get(site.caller) === true;
            });
            if (allSafe) { safe.set(f.name, true); changed = true; }
        }
        if (!changed) break;
    }

    // Pass 4 — report.
    for (const f of functions.values()) {
        if (!f.wcCalls.length || safe.get(f.name)) continue;
        const unguardedSites = f.wcCalls.filter((c) => !c.guarded);
        if (!unguardedSites.length) continue;
        const names = [...new Set(unguardedSites.map((c) => c.name))].slice(0, 4).join(', ');
        const sites = callSites.get(f.name) || [];
        const reach = f.hooks.length
            ? 'hooked to ' + [...new Set(f.hooks)].join(', ')
            : sites.length
                ? 'called from ' + [...new Set(sites.map((s) => s.caller || 'template top level'))].slice(0, 3).join(', ')
                : 'no caller found';
        report(
            'warn', file,
            `${f.name}() calls ${names} with no WooCommerce guard (${unguardedSites.length} unguarded site${unguardedSites.length === 1 ? '' : 's'}; ${reach}). ` +
            'This fatals when WooCommerce is inactive.',
            unguardedSites[0].line
        );
    }
}


/**
 * Classes that JavaScript creates must have CSS.
 *
 * The variation swatches shipped with markup and no stylesheet at all: the
 * feature was invisible, and no template preview would ever show it because the
 * nodes only exist at runtime. This is deliberately narrow — only element
 * factories, and only the project's own `luma-` namespace. A broad "every class
 * used must be defined" rule is not usable here, because plenty of classes are
 * styled through a parent flex/grid rule, a descendant selector, or a second
 * class on the same element, and all of those read as false positives.
 */
function collectCssClasses() {
    const defined = new Set();
    for (const file of cssFiles) {
        const text = fs.readFileSync(file, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
        for (const m of text.matchAll(/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/g)) defined.add(m[1]);
    }
    return defined;
}

function checkJsCreatedClasses() {
    const defined = collectCssClasses();
    const created = new Map();

    for (const file of jsFiles) {
        const lines = fs.readFileSync(file, 'utf8').split('\n');
        lines.forEach(function (line, index) {
            const where = file + ':' + (index + 1);
            const patterns = [
                // $('<div class="luma-x"></div>')
                /\$\(\s*'<[^']*?\bclass\s*=\s*(["'])([^"']+)\1/g,
                // $("<div class='luma-x'></div>")
                /\$\(\s*"<[^"]*?\bclass\s*=\s*(')([^"]+)\1/g,
                // $('<li>', { 'class': 'luma-x' })
                /\$\(\s*'<[a-zA-Z][^']*>'\s*,\s*\{\s*'?class'?\s*:\s*'([^']+)'/g,
                // element.className = 'luma-x'
                /className\s*=\s*'([^']+)'/g,
            ];
            for (const re of patterns) {
                for (const m of line.matchAll(re)) {
                    const value = m[2] !== undefined ? m[2] : m[1];
                    for (const cls of String(value).split(/\s+/)) {
                        if (!cls.startsWith('luma-')) continue;
                        if (!created.has(cls)) created.set(cls, []);
                        created.get(cls).push(where);
                    }
                }
            }
        });
    }

    for (const [cls, sites] of [...created].sort()) {
        if (defined.has(cls)) continue;
        report(
            'error',
            sites[0].split(':')[0],
            `JavaScript creates class "${cls}" but no stylesheet defines it, so the element renders unstyled. ` +
            'Created at ' + sites.slice(0, 3).map((s) => s.split('/').slice(-2).join('/')).join(', ') + '.',
            parseInt(sites[0].split(':').pop(), 10)
        );
    }
    return created.size;
}


/**
 * Accessibility invariants that can be decided from markup alone.
 *
 * style.css advertises the `accessibility-ready` tag, which is a public claim,
 * so it is worth verifying mechanically. Only rules with no legitimate
 * exception are checked:
 *
 *   - every <img> carries an alt attribute (alt="" is fine for decorative);
 *   - every <iframe> carries a title;
 *   - a hidden honeypot field is removed from both the accessibility tree and
 *     the tab order, because a field that is invisible but focusable traps
 *     keyboard users and confuses screen-reader users.
 *
 * Labelling of ordinary form controls is deliberately not checked: a wrapping
 * <label> is valid and common here, and deciding it correctly needs real
 * label-association analysis. Guessing would produce noise, and the theme's
 * controls were reviewed by hand instead.
 */
/**
 * Replace PHP blocks with a placeholder containing no angle brackets.
 *
 * Templates interleave PHP inside attribute values, so a tag scanner that stops
 * at the first `>` never reaches the end of the tag: an image written as
 * `<img src="<?php … ?>" alt="…">` looks like a truncated `<img>` carrying no
 * alt at all. Without this the accessibility checks silently parse no images
 * and report a clean bill of health for markup they never actually saw.
 * Newlines inside the block are preserved so reported line numbers stay right.
 */
function stripPhpBlocks(text) {
    return text.replace(/<\?(?:php|=)?[\s\S]*?\?>/g, function (block) {
        return 'lumaPhp' + block.replace(/[^\n]/g, '');
    });
}

function htmlTagsIn(text) {
    const out = [];
    for (const m of text.matchAll(/<([a-zA-Z][-a-zA-Z0-9]*)((?:[^<>]*?))\/?>/g)) {
        out.push({
            tag: m[1].toLowerCase(),
            attrs: m[2],
            line: text.slice(0, m.index).split('\n').length,
        });
    }
    return out;
}

function checkAccessibilityMarkup(file) {
    if (!file.endsWith('.php')) return;
    // PHP blocks are replaced first: their `>` characters otherwise truncate
    // every tag they appear in, which made these checks vacuous.
    const text = stripPhpBlocks(fs.readFileSync(file, 'utf8'));

    for (const t of htmlTagsIn(text)) {
        if (t.tag === 'img' && !/\balt\s*=/.test(t.attrs)) {
            report('error', file, '<img> with no alt attribute. Decorative images need an explicit alt="".', t.line);
        }
        if (t.tag === 'iframe' && !/\btitle\s*=/.test(t.attrs)) {
            report('error', file, '<iframe> with no title attribute.', t.line);
        }
        if (t.tag === 'input') {
            const type = (/\btype\s*=\s*["']?([a-z]+)/i.exec(t.attrs) || [])[1] || 'text';
            if (type === 'hidden') continue;
            // A field pushed off-screen but still in the tab order or the a11y
            // tree is worse than no field at all.
            const offscreen = /clip|position:\s*absolute|-9999|sr-only|screen-reader/.test(t.attrs)
                || /form-trap|honeypot/i.test(t.attrs);
            const ariaHidden = /aria-hidden\s*=\s*(?:["']?true|["']?1)/i.test(t.attrs)
                || /aria-hidden\s*=\s*["']?\s*(?:true|1)/i.test(t.attrs)
                || /aria-hidden=\\?"true\\?"/i.test(t.attrs);
            const outOfTabOrder = /tabindex\s*=\s*["']?-1/i.test(t.attrs);
            if (offscreen || (/luma-form-trap/.test(t.attrs))) {
                if (!/aria-hidden/.test(t.attrs)) {
                    report('error', file, 'Honeypot field is not aria-hidden, so screen readers announce a field sighted users never see.', t.line);
                }
                if (!outOfTabOrder) {
                    report('error', file, 'Honeypot field has no tabindex="-1", so keyboard users tab into an invisible field.', t.line);
                }
            } else if (/aria-hidden/.test(t.attrs) && !outOfTabOrder && type !== 'hidden') {
                // aria-hidden on a focusable control is an ARIA violation:
                // the element can receive focus while being hidden from AT.
                report('error', file, `A focusable <input type="${type}"> is aria-hidden but still in the tab order.`, t.line);
            }
        }
    }
}

/* --------------------------------------------------------------------- main */

phpFiles.forEach(phpSyntax);
cssFiles.forEach(cssSyntax);
jsFiles.forEach(jsSyntax);
jsonFiles.forEach(jsonSyntax);
phpFiles.forEach(checkTextDomains);
phpFiles.forEach(checkEchoEscaping);
checkDuplicateIds();
checkUndefinedHelpers();
const versions = checkVersions();
const headers = checkHeaders();
checkI18nKeys();
for (const file of phpFiles) checkWooCommerceGuards(file);
checkJsCreatedClasses();
phpFiles.forEach(checkAccessibilityMarkup);

const errors = problems.filter((p) => p.level === 'error');
const warns = problems.filter((p) => p.level === 'warn');

if (AS_JSON) {
    process.stdout.write(JSON.stringify({ errors: errors.length, warnings: warns.length, problems }, null, 2) + '\n');
} else {
    const grouped = new Map();
    for (const p of problems) {
        const key = p.level === 'error' ? 'ERROR' : 'WARN';
        if (!grouped.has(key)) grouped.set(key, []);
        grouped.get(key).push(p);
    }
    for (const [key, list] of grouped) {
        process.stdout.write(`\n=== ${key} (${list.length}) ===\n`);
        for (const p of list) {
            process.stdout.write(`${p.file}${p.line ? ':' + p.line : ''} — ${p.message}\n`);
        }
    }
    process.stdout.write(
        `\nChecked ${phpFiles.length} PHP, ${cssFiles.length} CSS, ${jsFiles.length} JS, ${jsonFiles.length} JSON files.\n` +
            `${errors.length} error(s), ${warns.length} warning(s).\n`
    );
}

process.exit(errors.length ? 1 : 0);
