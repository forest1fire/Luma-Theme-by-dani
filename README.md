# Luma-Theme-by-dani

A WordPress + WooCommerce theme, its companion plugin, and a demo kit — built and
maintained as one workspace.

**Current releases:** Luma Theme **1.33.0** · Luma Core **1.30.0**

## Layout

Everything lives in [`LUMA-STORE/`](LUMA-STORE/):

| Path | Contents |
| --- | --- |
| `LUMA-STORE/source/luma-commerce-theme/` | Editable theme source |
| `LUMA-STORE/source/luma-commerce-core/` | Editable plugin source |
| `LUMA-STORE/source/luma-demo-kit/` | Demo products, Elementor templates, campaign assets |
| `LUMA-STORE/packages/` | Installable ZIPs plus `SHA256SUMS.txt` |
| `LUMA-STORE/docs/` | One upgrade report per release |
| `LUMA-STORE/previews/` | Static previews that load the real stylesheets |
| `LUMA-STORE/tools/` | Audit, translation, packaging and behaviour tooling |

`workspace-01a06c10-1783-7058-ac40-7af2b036c95f.zip` is the original distribution
this workspace was extracted from, kept as the pristine baseline.

## Installing

Upload `LUMA-STORE/packages/luma-commerce-theme.zip` under
**Appearance → Themes → Add New**, and `luma-commerce-core.zip` under
**Plugins → Add New**. Luma Core requires WooCommerce.

## Verifying a change

```bash
cd LUMA-STORE/tools && npm ci && npm run verify
```

That runs the static audit, checks the translation templates against source,
executes both `theme.js` and `core.js` against real markup, and confirms the
packaged ZIPs match the source tree. The same five gates run in CI via
[`.github/workflows/luma-checks.yml`](.github/workflows/luma-checks.yml).

## Seeing it

```bash
cd LUMA-STORE && python3 -m http.server 8080 --bind 0.0.0.0
```

Open `http://localhost:8080/` for the index, or
`http://localhost:8080/previews/luma-theme-preview.html` for the theme.

## What this release changed

See [`LUMA-STORE/docs/LUMA-v1.33.0-RELEASE-REPORT.md`](LUMA-STORE/docs/LUMA-v1.33.0-RELEASE-REPORT.md)
for the full list of bug fixes, the dark colour scheme, complete
internationalization of both packages, and the new quality gates.

## House rules

Every release preserves these:

- No fake sales, stock or urgency signals
- No tracking, no external fonts, no new runtime dependencies
- Privacy behaviour is only ever strengthened
- RTL, reduced-motion, Elementor and WooCommerce compatibility stay intact
- Versions are bumped everywhere for cache busting
