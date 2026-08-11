### Task 7: Main — toggle, elimina, frecce, drag & drop, Salva

Read Task 7 from plan:
`docs/superpowers/plans/2026-08-10-catalogo-articoli-editor.md` — Task 7 only.

**Modify:** fant-cataloghi-articoli.component.ts/html/scss

**Must implement:**
1. selectSection + visual highlight (already partly there — keep)
2. Toggle saltoPagina on section and each article (form-switch)
3. removeSection / removeArticolo with selectedSectionIndex fixups
4. moveSection / moveArticolo with arrows (delta ±1)
5. CDK drag-drop for sections and for articles within a section (DragDropModule already in fant.module)
6. save() → CatalogService.updateContenuto(codice, sezioni); stay on page; success/error alerts
7. Annulla already navigates away (keep)

Match UI quality of fant-categorie for arrows/drag where practical.

**DO NOT commit.**

Acceptance mentally: toggles, remove, reorder arrows+dnd, Salva persist via API client.
