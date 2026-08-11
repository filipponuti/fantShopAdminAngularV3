# Plan: Refresh sezione + variazioni catalogo

> **Spec:** `docs/superpowers/specs/2026-08-11-catalogo-refresh-variazioni-design.md`

**Goal:** Refresh sezioni da categoria con modale multi-select; variazioni nested; collapse/expand.

## File map

| File | Change |
|------|--------|
| `fant-admin-api/includes/class-faa-products.php` | `type`, `variazioni`, skip variation top-level |
| `fant-admin-api/includes/class-faa-catalogs.php` | normalize + count variazioni |
| `fant-admin-api/fant-admin-api.php` | version 0.7.0 |
| `default/.../product.service.ts` (+spec) | ProductItem.variazioni |
| `default/.../catalog.service.ts` | CatalogArticolo.variazioni |
| `default/.../fant-cataloghi-articoli/*` | refresh, modal, collapse, UI variazioni |
| `default/.../fant.module.ts` | NgbModalModule if needed |

## Tasks

- [x] Task 1: PHP products map with variations
- [x] Task 2: PHP catalogs normalize/count
- [x] Task 3: Angular services
- [x] Task 4: Component refresh + modal + collapse + variation rows
- [x] Task 5: Unit tests + version bump

No commits unless requested.
