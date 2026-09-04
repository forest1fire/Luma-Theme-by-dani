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

/* --------------------------------------------------------------------- main */

phpFiles.forEach(phpSyntax);
cssFiles.forEach(cssSyntax);
jsFiles.forEach(jsSyntax);
jsonFiles.forEach(jsonSyntax);
phpFiles.forEach(checkTextDomains);
phpFiles.forEach(checkEchoEscaping);
checkDuplicateIds();
checkUndefinedHelpers();
checkVersions();

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
