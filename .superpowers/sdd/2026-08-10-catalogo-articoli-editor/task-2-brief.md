### Task 2: API contenuto catalogo + recount `numeroProdotti`

Read full Task 2 from plan (steps 1–4 with code):
`docs/superpowers/plans/2026-08-10-catalogo-articoli-editor.md` — Task 2 only.

**Files:**
- Modify: `fant-admin-api/includes/class-faa-catalogs.php`
- Modify: `fant-admin-api/includes/class-faa-api.php`

**Must produce:**
- `Fant_Admin_API_V4_Catalogs::update_contenuto( string $code, $prodotti )`
- `Fant_Admin_API_V4_Catalogs::normalize_prodotti( $prodotti )` with correct `'saltoPagina' => ! empty( $articolo['saltoPagina'] )` (NOT the buggy plan line that references section)
- `summary()` counts nested `articoli`; retrocompat for flat legacy prodotti
- REST `PUT /catalogs/(?P<catalogCode>...)/contenuto` registered (prefer before generic code routes if order matters)
- Handler `update_catalog_contenuto`

**DO NOT commit.**

Interfaces already live from Task 1: products API exists; leave it untouched unless conflict.
