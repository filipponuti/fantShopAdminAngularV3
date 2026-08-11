# Task 1 Report — API prodotti (`fant-admin/v1/products`)

**Date:** 2026-08-10  
**Status:** DONE  
**Commits:** none (per plan constraint)

## Summary

Implemented the WooCommerce products REST endpoint for the catalog articles editor backend slice. The plugin version was bumped to `0.6.0`.

## Files changed

| File | Action |
|------|--------|
| `fant-admin-api/includes/class-faa-products.php` | Created |
| `fant-admin-api/fant-admin-api.php` | Modified — require + version bump |
| `fant-admin-api/includes/class-faa-api.php` | Modified — route + handler |

## Implementation details

### `Fant_Admin_API_V4_Products`

- **`search( string $search, int $page, int $per_page )`**
  - Uses `wc_get_products()` with pagination (`paginate => true`).
  - Clamps `$page` to ≥ 1 and `$per_page` to 1–100.
  - Filters `publish` and `private` products, ordered by title ASC.
  - Optional title search via `'s'` when `$search` is non-empty after trim.
  - Enriches results with exact SKU lookup via `wc_get_product_id_by_sku()` when not already present (prepended to `items`).
  - Returns wrapper: `{ items, page, perPage, total }` where each item is `{ id, sku, name, code }`.

- **`by_category( int $category_id, bool $include_children )`**
  - Collects term IDs: category + descendants via `get_term_children()` when `$include_children` is true.
  - Uses **`WC_Product_Query`** with **`tax_query`** on `product_cat` **`term_id`** IN `$term_ids` (preferred over multi-slug approach per brief).
  - Returns plain array of `{ id, sku, name, code }`, deduplicated by product ID, ordered by `menu_order` ASC.

- **`map_product( WC_Product $product )`**
  - Maps `id`, `sku`, `name`, and `code` (`_sku` meta, falling back to SKU).

### REST route

- **Route:** `GET /wp-json/fant-admin/v1/products`
- **Registration:** `self::route( '/products', WP_REST_Server::READABLE, 'products' );` in `register_routes()`.
- **Authorization:** Default `authorized` permission callback (JWT admin required), consistent with other protected routes.
- **Handler `products()`:**
  - If `categoryId > 0` → `by_category( categoryId, includeChildren )` — plain array response.
  - Else → `search( search, page, perPage )` — paginated wrapper response.
  - `includeChildren` treated as false only for `'0'` or `'false'` (case-insensitive).

### Plugin bootstrap

- Added `require_once` for `class-faa-products.php` before `class-faa-api.php`.
- Bumped plugin header `Version` and `FANT_ADMIN_API_V4_VERSION` to **`0.6.0`**.

## Brief compliance checklist

| Requirement | Met |
|-------------|-----|
| Create `class-faa-products.php` | ✅ |
| Modify `fant-admin-api.php` (require + version) | ✅ |
| Modify `class-faa-api.php` (route + handler) | ✅ |
| `search` returns `{items,page,perPage,total}` | ✅ |
| `by_category` returns plain array `{id,sku,name,code}` | ✅ |
| Prefer `WC_Product_Query` + `tax_query` on `term_id` | ✅ |
| Version `0.6.0` | ✅ |
| No git commit | ✅ |

## Verification

### PHP syntax (`php -l`)

**Not run — `php` CLI not available on this machine (command not found in PATH).**

Manual review performed:
- All three PHP files follow existing plugin conventions (`defined( 'ABSPATH' ) || exit`, final class, spacing).
- Handler signature and route registration match plan Step 2.
- No obvious syntax errors.

### Live API test

**Not performed** — no JWT / remote WordPress site available in this session.

Recommended manual verification on test site:

```bash
# search (paginated wrapper)
curl -s -H "Authorization: Bearer $TOKEN" \
  "$SITE/wp-json/fant-admin/v1/products?search=T.&perPage=5"

# by category (plain array)
curl -s -H "Authorization: Bearer $TOKEN" \
  "$SITE/wp-json/fant-admin/v1/products?categoryId=197&includeChildren=1"
```

## Concerns / notes

1. **`php -l` unavailable locally** — syntax should be verified on deploy target or CI.
2. **SKU enrichment in search** — when an exact SKU match is prepended to `items`, `total` from WooCommerce pagination is unchanged (matches plan reference implementation; client should not rely on `total` including the extra SKU row).
3. **`code` field** — uses `_sku` post meta with fallback to `get_sku()`; if business logic expects a different meta key, that would be a follow-up.

## Self-review

Implementation matches Task 1 plan steps 1–2. Deliberate deviation from plan sample: `by_category` uses `WC_Product_Query` + `tax_query` on `term_id` instead of slug-based `category` arg, per global task constraint. Route wiring and authorization follow existing `class-faa-api.php` patterns.

---

## Fix round 1/5 (review findings)

**Date:** 2026-08-10  
**Status:** DONE  
**Commits:** none

### Findings addressed

| # | Finding | Fix |
|---|---------|-----|
| 1 | Exact-SKU enrichment broke pagination (`items` > perPage, `total` stale, repeat on later pages) | SKU fallback now runs only on page 1, only when the product is absent from title-search results (`include` + `s` probe); prepends then `array_slice` to `perPage`; increments `total` by 1 when added |
| 2 | Exact-SKU lookup ignored `publish`/`private` status filter | Added `is_allowed_status()`; SKU product included only when status is `publish` or `private` |

### Code changed

- `fant-admin-api/includes/class-faa-products.php` — `search()` SKU enrichment block + `is_allowed_status()` helper

### Verification

| Command | Result |
|---------|--------|
| `php -l fant-admin-api/includes/class-faa-products.php` | Not run — PHP CLI not on PATH |
| `git diff --check -- fant-admin-api/includes/class-faa-products.php` | Passed (exit 0) |
| Manual logic review | `count(items) ≤ perPage`; SKU-only match yields `total ≥ 1`; draft/trash SKU rejected; page 2+ no duplicate SKU prepend |

---

## Fix round 2/5 (pagination merge)

**Date:** 2026-08-10  
**Status:** DONE  
**Commits:** none

### Finding addressed

| # | Finding | Fix |
|---|---------|-----|
| 1 | Prepend+slice dropped last title hit from page 1; page 2 never returned it; `total` inconsistent across pages | Replaced post-hoc prepend with exclude+offset merge: exact SKU (case-insensitive, publish/private) pinned via `resolve_sku_extra()`; title query uses `exclude => [sku_id]`; `total = title_total + 1` on every page; page 1 fetches `perPage - 1` title rows + SKU; page ≥ 2 uses `offset = (page-1)*perPage - 1` |

### Code changed

- `fant-admin-api/includes/class-faa-products.php` — refactored `search()` into `search_title_only()`, `resolve_sku_extra()`, and SKU-merge pagination branch

### Verification

| Command | Result |
|---------|--------|
| `php -l fant-admin-api/includes/class-faa-products.php` | Not run — PHP CLI not on PATH |
| `git diff --check -- fant-admin-api/includes/class-faa-products.php` | Passed (exit 0) |
| Manual pagination walkthrough | Virtual list `[sku, title₀…]` — page 1 = 1 SKU + (perPage−1) titles; page 2 offset skips SKU slot; same `total` on all pages; no slice discard |

---

## Fix round 3/5 (perPage=1 edge case)

**Date:** 2026-08-10  
**Status:** DONE  
**Commits:** none

### Finding addressed

| # | Finding | Fix |
|---|---------|-----|
| 1 | `perPage === 1` with `$sku_extra` passed `limit = 0` to WordPress, which does not mean “zero results” | Page 1 with `perPage === 1` returns `[sku_extra]` only (no title query); `title_total` still from paginate count query; page ≥ 2 unchanged (`offset = page - 2`, `limit = 1`); `perPage > 1` branch unchanged (`limit = perPage - 1`) |

### Code changed

- `fant-admin-api/includes/class-faa-products.php` — guard page-1 title fetch behind `$per_page > 1`

### Verification

| Command | Result |
|---------|--------|
| `php -l fant-admin-api/includes/class-faa-products.php` | Not run — PHP CLI not on PATH |
| `git diff --check -- fant-admin-api/includes/class-faa-products.php` | Passed (exit 0) |
| Manual edge-case review | `perPage=1` page 1 → 1 SKU item, no `limit=0` query; page 2 → first title row at offset 0 |
