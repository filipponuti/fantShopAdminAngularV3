# Task 1 Re-review — Fix Round 2

## Verdict

### Finding 1: NOT ADDRESSED

The exclude-and-offset merge is consistent for `perPage >= 2`: it models the exact-SKU item as the first entry, excludes that product from title results, reports `title_total + 1` on every page, and shifts later title offsets by one, so title hits are not dropped.

However, the supported `perPage = 1` case still breaks the required page-size and pagination semantics. On page 1, line 45 passes `limit => 0` to `wc_get_products()`. WooCommerce maps `limit` to WordPress's `posts_per_page`; WordPress treats an empty/zero `posts_per_page` as the site's configured default (and ultimately never as “return zero rows”). Consequently, page 1 can contain the pinned SKU plus one or more title matches, exceeding `perPage`. Later pages use one title per page starting at title offset `0`, so title rows already emitted on page 1 can also be repeated. The fix must special-case `perPage === 1` and avoid issuing the title query on page 1.

Affected code: `fant-admin-api/includes/class-faa-products.php:40-54`.

## New Critical / Important breakage

None. The `perPage = 1` failure is an unresolved edge of the original Finding 1, not a separate regression.

## Verification

- Compared the fix-round-2 review package with the actual `class-faa-products.php`; they match.
- Walked the merged virtual list across page boundaries for exact-SKU searches.
- Checked the minimum allowed page size (`perPage = 1`) against the `wc_get_products()` / `WP_Query` limit behavior.
- No executable WooCommerce test environment is present, so this conclusion is based on direct control-flow and upstream query-semantics review.

## Overall verdict

Open findings remain. Finding 1 is addressed for `perPage >= 2`, but not for the explicitly accepted minimum value of `1`.
