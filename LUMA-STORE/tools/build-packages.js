#!/usr/bin/env node
/**
 * Build the installable Luma packages from source.
 *
 * Each ZIP gets a single top-level folder (the layout WordPress expects when
 * installing a theme or plugin) and is built deterministically: file mtimes are
 * pinned, entries are sorted and extra platform attributes are stripped, so the
 * same source tree always produces the same SHA-256.
 *
 * Usage:
 *   node tools/build-packages.js            rebuild every package, print digests
 *   node tools/build-packages.js --check    fail if a committed ZIP is stale
 */
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const crypto = require('crypto');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const SOURCE = path.join(ROOT, 'source');
const PACKAGES = path.join(ROOT, 'packages');
const CHECK = process.argv.includes('--check');

/** Pinned mtime so repeated builds are byte-identical. */
const EPOCH = '2026-01-02T00:00:00Z';

const TARGETS = [
    { dir: 'luma-commerce-core', zip: 'luma-commerce-core.zip' },
    { dir: 'luma-commerce-theme', zip: 'luma-commerce-theme.zip' },
    { dir: 'luma-demo-kit', zip: 'luma-demo-kit.zip' },
];

const IGNORED = new Set(['node_modules', '.DS_Store']);

function entries(dir, base, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
        if (IGNORED.has(entry.name) || entry.name.startsWith('.')) continue;
        const abs = path.join(dir, entry.name);
        const rel = base + '/' + entry.name;
        if (entry.isDirectory()) {
            out.push(rel + '/');
            entries(abs, rel, out);
        } else {
            out.push(rel);
        }
    }
    return out;
}

function sha256(file) {
    return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
}

function build(target, destDir) {
    const srcDir = path.join(SOURCE, target.dir);
    if (!fs.existsSync(srcDir)) throw new Error('Missing source directory: ' + srcDir);

    const list = entries(srcDir, target.dir).sort();
    const out = path.join(destDir, target.zip);
    fs.mkdirSync(destDir, { recursive: true });
    if (fs.existsSync(out)) fs.unlinkSync(out);

    // Pin mtimes so the archive is reproducible.
    execFileSync('find', [srcDir, '-exec', 'touch', '-h', '-d', EPOCH, '{}', '+'], { stdio: 'ignore' });

    execFileSync('zip', ['-X', '-q', out, '-@'], {
        cwd: SOURCE,
        input: list.join('\n') + '\n',
        maxBuffer: 64 * 1024 * 1024,
    });
    return { path: out, digest: sha256(out), files: list.filter((e) => !e.endsWith('/')).length };
}

const tmp = CHECK ? fs.mkdtempSync(path.join(os.tmpdir(), 'luma-check-')) : PACKAGES;
const results = [];
let failed = false;

for (const target of TARGETS) {
    const built = build(target, tmp);
    if (CHECK) {
        const committed = path.join(PACKAGES, target.zip);
        if (!fs.existsSync(committed)) {
            console.log('MISSING  packages/' + target.zip);
            failed = true;
        } else if (sha256(committed) !== built.digest) {
            console.log('STALE    packages/' + target.zip);
            console.log('         committed ' + sha256(committed));
            console.log('         source    ' + built.digest);
            failed = true;
        } else {
            console.log('OK       packages/' + target.zip + '  ' + built.digest);
        }
        results.push({ zip: target.zip, ...built });
    } else {
        console.log('BUILT    packages/' + target.zip.padEnd(28) + built.files + ' files');
        console.log('         SHA-256 ' + built.digest);
        results.push({ zip: target.zip, ...built });
    }
}

if (CHECK) {
    fs.rmSync(tmp, { recursive: true, force: true });
} else {
    fs.writeFileSync(
        path.join(PACKAGES, 'SHA256SUMS.txt'),
        results.map((r) => r.digest + '  ' + r.zip).join('\n') + '\n'
    );
    console.log('WROTE    packages/SHA256SUMS.txt');
}

process.exit(failed ? 1 : 0);
