### Task 3: Angular CatalogService + ProductService

Read full Task 3 from plan:
`docs/superpowers/plans/2026-08-10-catalogo-articoli-editor.md` — Task 3 only.

**Files:**
- Create: `default/src/app/core/services/product.service.ts`
- Create: `default/src/app/core/services/product.service.spec.ts`
- Modify: `default/src/app/core/services/catalog.service.ts`
- Create/Modify: `default/src/app/core/services/catalog.service.spec.ts`

**Produces interfaces (exact names):**
- CatalogArticolo, CatalogSezione, CatalogDetail
- ProductItem, ProductSearchResult
- CatalogService.get(code), CatalogService.updateContenuto(code, prodotti)
- ProductService.search(...), ProductService.byCategory(...)

API base uses `environment.siteUrl` + `environment.apiNamespace` like WooCategoryService (strip trailing slash).

Normalize get(): if prodotti missing/not array → [].

**DO NOT commit.**

Run focused ng tests if karma works; otherwise `npx ng build --configuration development` as compile check and note in report.
