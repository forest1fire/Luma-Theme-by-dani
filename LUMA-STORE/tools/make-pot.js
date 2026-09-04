#!/usr/bin/env node
/**
 * Generate gettext translation templates for the Luma theme and Luma Core.
 *
 * Extracts every translatable call from the PHP source — __(), _e(), _x(),
 * _n(), esc_html__(), esc_attr__() and their echo variants — together with any
 * `translators:` comment placed directly above the call, and writes a valid
 * .pot file per text domain.
 *
 * Usage: node tools/make-pot.js [--check]
 *   --check  exit non-zero if a committed .pot is out of date
 */
'use strict';

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const ROOT = path.resolve(__dirname, '..');
const SOURCE = path.join(ROOT, 'source');
const CHECK = process.argv.includes('--check');

const PACKAGES = [
    { dir: 'luma-commerce-theme', domain: 'luma-commerce', name: 'Luma', pot: 'languages/luma-commerce.pot' },
    { dir: 'luma-commerce-core', domain: 'luma-commerce-core', name: 'Luma Core', pot: 'languages/luma-commerce-core.pot' },
];

const FN = String.raw`(?:__|_e|_x|_ex|_n|_nx|esc_html__|esc_html_e|esc_attr__|esc_attr_e|translate)`;
const CALL = new RegExp(String.raw`${FN}\s*\(`, 'g');

/** Read a single-quoted PHP string starting at `i` (the opening quote). */
function phpString(src, i) {
    if (src[i] !== "'") return null;
    let out = '';
    let j = i + 1;
    while (j < src.length) {
        const ch = src[j];
        if (ch === '\\') {
            const nx = src[j + 1];
            out += nx === "'" || nx === '\\' ? nx : ch + nx;
            j += 2;
            continue;
        }
        if (ch === "'") return { value: out, end: j + 1 };
        out += ch;
        j++;
    }
    return null;
}

function skipSpace(src, i) {
    while (i < src.length && /\s/.test(src[i])) i++;
    return i;
}

function lineOf(src, index) {
    return src.slice(0, index).split('\n').length;
}

/** Collect the nearest preceding `/* translators: ... *\/` comment. */
function translatorsComment(src, index) {
    const before = src.slice(0, index);
    const m = /\/\*\s*translators:\s*([\s\S]*?)\*\/\s*$/i.exec(before);
    if (!m) return null;
    return m[1].replace(/\s*\n\s*\*?\s*/g, ' ').replace(/\s+/g, ' ').trim();
}

function extract(file, domain) {
    const src = fs.readFileSync(file, 'utf8');
    const entries = [];
    CALL.lastIndex = 0;
    let m;
    while ((m = CALL.exec(src))) {
        const fn = m[0].replace(/\s*\($/, '');
        let i = skipSpace(src, m.index + m[0].length);
        const singular = phpString(src, i);
        if (!singular) continue;
        i = skipSpace(src, singular.end);

        let plural = null;
        if (fn === '_n' || fn === '_nx') {
            if (src[i] !== ',') continue;
            i = skipSpace(src, i + 1);
            const p = phpString(src, i);
            if (!p) continue;
            plural = p.value;
            i = skipSpace(src, p.end);
        }
        if (src[i] !== ',') continue;
        i = skipSpace(src, i + 1);

        // _x()/_nx() carry a context string before the domain.
        let context = null;
        if (fn === '_x' || fn === '_ex' || fn === '_nx') {
            const c = phpString(src, i);
            if (!c) continue;
            context = c.value;
            i = skipSpace(src, c.end);
            if (src[i] !== ',') continue;
            i = skipSpace(src, i + 1);
        }
        if (fn === '_n' || fn === '_nx') {
            // count argument — skip to the next comma
            let depth = 0;
            while (i < src.length) {
                if (src[i] === '(' || src[i] === '[') depth++;
                else if (src[i] === ')' || src[i] === ']') { if (depth === 0) break; depth--; }
                else if (src[i] === ',' && depth === 0) break;
                i++;
            }
            i = skipSpace(src, i + 1);
        }

        const dom = phpString(src, i);
        if (!dom || dom.value !== domain) continue;

        entries.push({
            file: path.relative(path.join(SOURCE, PACKAGES.find((p) => p.domain === domain).dir), file).split(path.sep).join('/'),
            line: lineOf(src, m.index),
            singular: singular.value,
            plural,
            context,
            comment: translatorsComment(src, m.index),
        });
    }
    return entries;
}

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        if (entry.name === 'node_modules' || entry.name === 'languages') continue;
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, out);
        else if (full.endsWith('.php')) out.push(full);
    }
    return out;
}

function quote(value) {
    return '"' + value.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\t/g, '\\t') + '"';
}

function buildPot(pkg, entries) {
    const seen = new Map();
    for (const e of entries) {
        const key = (e.context || '') + '\u0004' + e.singular + '\u0004' + (e.plural || '');
        if (!seen.has(key)) seen.set(key, { ...e, refs: [] });
        seen.get(key).refs.push(e.file + ':' + e.line);
        if (e.comment && !seen.get(key).comment) seen.get(key).comment = e.comment;
    }

    const now = new Date().toISOString().replace(/:\d\d\.\d+Z$/, '+0000');
    const out = [];
    out.push('# Translation template for ' + pkg.name + '.');
    out.push('# Copyright (C) CodeWithDani');
    out.push('# This file is distributed under the GPL-2.0-or-later license.');
    out.push('msgid ""');
    out.push('msgstr ""');
    out.push('"Project-Id-Version: ' + pkg.name + '\\n"');
    out.push('"Report-Msgid-Bugs-To: https://example.com/luma-commerce\\n"');
    out.push('"POT-Creation-Date: ' + now + '\\n"');
    out.push('"MIME-Version: 1.0\\n"');
    out.push('"Content-Type: text/plain; charset=UTF-8\\n"');
    out.push('"Content-Transfer-Encoding: 8bit\\n"');
    out.push('"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"');
    out.push('"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"');
    out.push('"Language-Team: LANGUAGE <LL@li.org>\\n"');
    out.push('"X-Domain: ' + pkg.domain + '\\n"');
    out.push('');

    const sorted = [...seen.values()].sort((a, b) => a.refs[0].localeCompare(b.refs[0]));
    for (const e of sorted) {
        for (const ref of [...new Set(e.refs)].sort()) out.push('#: ' + ref);
        if (e.comment) out.push('#. translators: ' + e.comment);
        if (e.context) out.push('msgctxt ' + quote(e.context));
        out.push('msgid ' + quote(e.singular));
        if (e.plural) {
            out.push('msgid_plural ' + quote(e.plural));
            out.push('msgstr[0] ""');
            out.push('msgstr[1] ""');
        } else {
            out.push('msgstr ""');
        }
        out.push('');
    }
    return out.join('\n');
}

let failed = false;
for (const pkg of PACKAGES) {
    const dir = path.join(SOURCE, pkg.dir);
    const files = walk(dir).sort();
    const entries = files.flatMap((f) => extract(f, pkg.domain));
    const pot = buildPot(pkg, entries);
    const target = path.join(dir, pkg.pot);
    fs.mkdirSync(path.dirname(target), { recursive: true });

    const digest = (text) => crypto.createHash('sha256').update(text.replace(/^"POT-Creation-Date.*$/m, '')).digest('hex');
    const existing = fs.existsSync(target) ? fs.readFileSync(target, 'utf8') : null;

    if (CHECK) {
        if (existing === null) {
            console.log('MISSING  ' + path.relative(ROOT, target));
            failed = true;
        } else if (digest(existing) !== digest(pot)) {
            console.log('STALE    ' + path.relative(ROOT, target));
            failed = true;
        } else {
            console.log('OK       ' + path.relative(ROOT, target) + '  (' + entries.length + ' strings)');
        }
    } else {
        fs.writeFileSync(target, pot);
        console.log('WROTE    ' + path.relative(ROOT, target) + '  (' + entries.length + ' strings, ' + new Set(entries.map((e) => e.singular)).size + ' unique)');
    }
}
process.exit(failed ? 1 : 0);
