### Task 6: Sidebar tab Articolo → multi-select + aggiungi a sezione selezionata

Read Task 6 from plan:
`docs/superpowers/plans/2026-08-10-catalogo-articoli-editor.md` — Task 6 only.

**Modify:** fant-cataloghi-articoli.component.ts/html (scss if needed)

**Behavior:**
- Search field → ProductService.search (debounce ~300ms)
- Results list with multi-select checkboxes
- Aggiungi → requires selectedSectionIndex; else error message in Italian
- Append to that section skipping duplicate SKU (case-insensitive) and empty sku
- Clear selection after add
- saltoPagina false on new articles

**DO NOT commit.** Extend existing component from Task 5.
