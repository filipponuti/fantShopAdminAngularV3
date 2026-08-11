### Task 1: API prodotti (`fant-admin/v1/products`)

**Files:**
- Create: `fant-admin-api/includes/class-faa-products.php`
- Modify: `fant-admin-api/fant-admin-api.php`
- Modify: `fant-admin-api/includes/class-faa-api.php`

**Interfaces:**
- Produces: `Fant_Admin_API_V4_Products::search( string $search, int $page, int $per_page ): array` con shape `{items,page,perPage,total}`
- Produces: `Fant_Admin_API_V4_Products::by_category( int $category_id, bool $include_children ): array` di item `{ id, sku, name, code }`
- Produces: REST `GET /wp-json/fant-admin/v1/products?search=&page=&perPage=` e `?categoryId=&includeChildren=1`

Preferire `WC_Product_Query` + `tax_query` su `term_id` per `by_category` (più affidabile dei multi-slug).

Implementare come da plan steps 1–3 (codice completo nel plan Task 1). Bump version a `0.6.0`.

**NON fare commit** (vincolo globale del plan).

Verifica: se non hai JWT/sito remoto, verifica almeno che i file PHP siano coerenti (syntax check `php -l` se disponibile) e che route/require siano registrati.
