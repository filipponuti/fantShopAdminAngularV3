# Final fix wave report

## Status

PASS — all requested final-review findings in scope were addressed. No commits were created.

## Changes

- Product search now builds a single ordered union of exact-SKU, partial-SKU, and title matches.
  Partial SKU matching uses WooCommerce's product data-store query filter to add a `_sku`
  `LIKE` meta query. Results are deduplicated by product ID before total calculation and
  pagination, so terms such as `T.PRI` match SKUs such as `T.PRI365-SF`.
- The catalog articles editor rejects save attempts with an empty catalog code.
- The component implements `OnDestroy` and clears its pending 300 ms search timer.
- Successful catalog-content mutations clear the previous save-success banner: category
  and product additions, page-break toggles, removals, arrow moves, and drag/drop moves.
- Catalog section names, article names, and article SKUs are normalized with
  `sanitize_text_field()` before validation and persistence.

## Files touched

- `default/src/app/pages/fant/fant-cataloghi-articoli/fant-cataloghi-articoli.component.ts`
- `default/src/app/pages/fant/fant-cataloghi-articoli/fant-cataloghi-articoli.component.spec.ts`
- `fant-admin-api/includes/class-faa-products.php`
- `fant-admin-api/includes/class-faa-catalogs.php`
- `.superpowers/sdd/2026-08-10-catalogo-articoli-editor/final-fix-report.md`

## Verification

- Focused Angular component suite:
  `npm test -- --watch=false --browsers=ChromeHeadless --include="src/app/pages/fant/fant-cataloghi-articoli/fant-cataloghi-articoli.component.spec.ts"`
  — 19/19 passing.
- IDE diagnostics for the changed Angular component and spec — no errors.
- `git diff --check` for the four changed source files — passed (only the repository's
  existing LF-to-CRLF checkout warning was emitted).
- PHP syntax lint was attempted, but the local environment does not have `php` available
  on `PATH`; no PHP test suite was added, per scope.
