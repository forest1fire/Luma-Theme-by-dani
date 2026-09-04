# Luma Store workspace

Current releases: **Luma Theme 1.33.0** · **Luma Core 1.30.0**

## Where everything is

- `source/luma-commerce-core/` — editable Luma Core WooCommerce plugin source.
- `source/luma-commerce-theme/` — editable Luma WordPress theme source.
- `source/luma-demo-kit/` — editable demo products, Elementor templates and campaign assets.
- `packages/luma-commerce-core.zip` — installable Core plugin.
- `packages/luma-commerce-theme.zip` — installable Luma theme.
- `packages/luma-demo-kit.zip` — demo kit package.
- `packages/SHA256SUMS.txt` — digests for the three ZIPs above.
- `docs/LUMA-v1.33.0-RELEASE-REPORT.md` — latest release: bug fixes, internationalization, quality gates.
- `previews/luma-theme-preview.html` — live preview that loads the real shipped CSS and `theme.js`.
- `previews/luma-commerce-preview.html` — standalone visual preview from earlier releases.
- `tools/` — audit, translation, packaging and behaviour tooling (see below).

## Quality gates

Run everything at once:

```bash
cd LUMA-STORE/tools && npm ci && npm run verify
```

| Command | What it proves |
| --- | --- |
| `node tools/audit.js` | Escaping, duplicate ids, brace balance, version drift, header metadata, changelog presence, i18n key coverage, WooCommerce guard reachability, JS-created class coverage, accessibility markup, dead code, template hierarchy |
| `node tools/make-pot.js [--check]` | Regenerate or verify the `.pot` translation templates |
| `node tools/smoke-test.js` | `theme.js` executes against real markup and behaves correctly |
| `node tools/core-smoke-test.js` | `core.js` executes on a jQuery double with AJAX intercepted |
| `node tools/build-packages.js [--check]` | Rebuild the ZIPs, or verify they match the source tree |

`.github/workflows/luma-checks.yml` runs all five on every push and pull request.

## Local preview

The preview page links the actual stylesheets and scripts from `source/`, so serve
the workspace root rather than opening the file directly:

```bash
cd LUMA-STORE && python3 -m http.server 8080 --bind 0.0.0.0
```

Then open `http://localhost:8080/` for the index, or
`http://localhost:8080/previews/luma-theme-preview.html` for the theme.

The root workspace is intentionally kept clean. Canonical images live inside the source packages; duplicate root-level copies and superseded audit reports were removed.
