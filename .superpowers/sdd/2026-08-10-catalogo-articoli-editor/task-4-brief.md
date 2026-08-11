### Task 4: Scaffold pagina edit + rotta + bottone lista

Read Task 4 from plan:
`docs/superpowers/plans/2026-08-10-catalogo-articoli-editor.md` — Task 4 only.

**Create:**
- `default/src/app/pages/fant/fant-cataloghi-articoli/fant-cataloghi-articoli.component.ts`
- `...html`, `...scss`

**Modify:**
- `fant-routing.module.ts` — add `{ path: 'fant-cataloghi/:codice', component: FantCataloghiArticoliComponent }` AND keep `{ path: 'fant-cataloghi', ... }`. Put parametric route in a way Angular resolves correctly (both are fine as sibling routes with different specificity).
- `fant.module.ts` — declare component; add `FormsModule` if using ngModel
- `fant-cataloghi.component.ts/html` — Articoli button + `openArticoli` + inject Router

**Page must have:**
- Title via breadcrumbs: `Articoli per il catalogo: ` + nome
- Layout shells: sidebar + main (can be empty placeholders for Tasks 5–7)
- Footer Annulla / Salva (Salva can be stub/no-op or call empty save for now)
- load() via CatalogService.get(codice) from route param
- cancel() → navigate `/fant-cataloghi`

**DO NOT commit.**

Reuse CatalogService types from Task 3. Match UI patterns of fant-cataloghi (alerts, buttons btn-light / btn-success).
