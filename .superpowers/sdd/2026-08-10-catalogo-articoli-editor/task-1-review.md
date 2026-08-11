# Task 1 Review — API prodotti

## Gate

- **Spec compliance:** ✅
- **Task quality:** Changes requested

The implementation creates the requested product-query class, registers the protected `GET /fant-admin/v1/products` route, uses the existing `authorized` JWT-administrator permission callback, returns the required response shapes, uses `WC_Product_Query` with a `term_id` `tax_query` for category loading, and bumps both plugin version declarations to `0.6.0`. `HEAD` remains at the supplied base commit, so the no-commit constraint is also satisfied.

## Critical / Important findings

### [P2] Keep exact-SKU enrichment consistent with pagination — `fant-admin-api/includes/class-faa-products.php:26-48`

When an exact SKU is not in the title-search page, the implementation prepends it to `items` but leaves `total` unchanged. A SKU-only match can therefore return one item with `total: 0`; a full title page can return `perPage + 1` items; and the same SKU fallback can be repeated on later pages. That makes the required paginated wrapper internally inconsistent for clients using `total` or page size. The exact-SKU match should be incorporated into the pagination/count semantics rather than appended outside them.

### [P2] Apply the requested product-status filter to exact-SKU matches — `fant-admin-api/includes/class-faa-products.php:27-40`

The main query only includes `publish` and `private`, but `wc_get_product_id_by_sku()` followed by `wc_get_product()` can add a product in another status, such as draft, pending, or trash. Thus entering an exact SKU bypasses the availability constraint used by every other search result. Check the loaded product's status before adding it, or fold the SKU match into a query that enforces the same constraints.

## Minor findings

### [P3] Add executable coverage for the two query branches

No automated or live API verification covers response shape, category descendants, authorization, exact-SKU fallback, or pagination. PHP CLI is unavailable in this environment, so syntax linting could not be executed; `git diff --check` did pass. This is a residual regression risk rather than a requirements failure because the brief explicitly allowed a coherence review when PHP/JWT access was unavailable.

## Verification performed

- Inspected the complete supplied review package and all three task files.
- Confirmed route wiring uses the same default `authorized` callback as other protected `fant-admin/v1` routes.
- Confirmed `HEAD` is `18100830a566ebe59c2aba594564774ae27221a9`.
- Ran `git diff --check` for the task paths successfully.
- Attempted `php -l`; PHP is not installed or available on `PATH`.
