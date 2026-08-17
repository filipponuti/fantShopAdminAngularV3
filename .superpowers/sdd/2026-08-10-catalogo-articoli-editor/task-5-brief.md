### Task 5: Sidebar tab Categoria → aggiungi sezione con prodotti

Read Task 5 from plan:
`docs/superpowers/plans/2026-08-10-catalogo-articoli-editor.md` — Task 5 only.

**Modify:** `fant-cataloghi-articoli.component.ts/html/scss`

**Behavior:**
- Tabs UI: Categoria | Articolo (Articolo tab can stay placeholder until Task 6)
- Load Woo categories via WooCategoryService.list(); build tree (copy minimal buildTree/expand from fant-categorie — read-only select, NO drag reorder/edit)
- Click node → selectedCategoryId
- Aggiungi → ProductService.byCategory(id, true) → append CatalogSezione with saltoPagina false, articoli mapped with saltoPagina false; select new section as selectedSectionIndex
- Show loading/error states
- Main should list sections already (at least name + article count) so Aggiungi is visible; full toggles/dnd wait for Task 7 but showing sections + selecting them is needed for Task 6

**DO NOT commit.**

Inspect existing scaffold first and extend it — do not rewrite the whole page unnecessarily.
