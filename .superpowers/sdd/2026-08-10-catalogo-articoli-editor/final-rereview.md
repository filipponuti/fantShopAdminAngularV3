1. ADDRESSED
2. ADDRESSED
3. ADDRESSED
4. ADDRESSED
5. ADDRESSED

New Critical/Important in fix diff:
- Important: Product search now executes both partial-SKU and title queries with `limit => -1`, materializes every matching `WC_Product`, deduplicates the full result set, and only then paginates in PHP. A common term can therefore make each debounced search consume time and memory proportional to the entire product catalog, risking slow requests or exhaustion on realistically large WooCommerce catalogs (`fant-admin-api/includes/class-faa-products.php:24-32,67-79`).

Verdict: open remain
