# Final whole-branch review — Editor articoli catalogo

**Date:** 2026-08-11
**Base:** `18100830a566ebe59c2aba594564774ae27221a9` (working tree, uncommitted)
**Spec:** `docs/superpowers/specs/2026-08-10-catalogo-articoli-editor-design.md`
**Plan:** `docs/superpowers/plans/2026-08-10-catalogo-articoli-editor.md`
**Verdict:** **Changes requested** (no critical defects; 2 important items + merge hygiene)

---

## 0. How this review was done

- `final-review-package.md` is corrupted: it mixes UTF-8 and UTF-16LE segments (BOM `EF BB BF` followed by 18.780 NUL bytes), so large parts of it are unreadable mojibake. It was decoded best-effort and then **the review was performed directly on the working-tree files and on `git diff <base>`**, which is authoritative anyway.
- Verifications actually executed:
  - `npx ng test --watch=false --browsers=ChromeHeadless --include=<3 feature specs>` → **TOTAL: 22 SUCCESS**.
  - `npx ng build --configuration development` → **bundle generation complete, 0 errors** (so the new template type-checks under AOT and the module/route wiring compiles).
  - Full `npx ng test` (whole repo) → 28 failures + browser DISCONNECT at 82/142. **All failures are pre-existing Velzon template specs** (CheckoutComponent, BuySellComponent, CalendarComponent, …); none of them belong to this feature, and the feature specs never got to run in that pass. Not a regression from this branch, but worth knowing that "the suite is green" cannot be claimed repo-wide.
  - PHP: no `php` binary on this machine, so **no syntax lint and no runtime verification of the PHP layer was possible**.

---

## 1. Spec / acceptance-criteria coverage

| Spec §8 acceptance criterion | Status |
|---|---|
| 1. Bottone lista → `/fant-cataloghi/{codice}` + titolo | ✅ `openArticoli()` + route + `'Articoli per il catalogo: ' + nome` |
| 2. Aggiungi categoria → sezione + prodotti (con sottocategorie) | ✅ `addCategorySection()` + `by_category()` con `get_term_children` |
| 3. Aggiungi articoli solo con sezione selezionata | ✅ `addSelectedProducts()` + messaggio d'errore, testato |
| 4. Toggle salto sezione/SKU, persistiti | ✅ UI switch + `normalize_prodotti` conserva `saltoPagina` |
| 5. Rimuovi sezione/articolo | ✅ con riallineamento di `selectedSectionIndex` |
| 6. Riordino drag + frecce (sezioni e articoli) | ✅ codice presente e unit-testato; **integrazione CDK nel DOM mai provata** (vedi §4) |
| 7. Annulla non persiste | ✅ `cancel()` → navigate |
| 8. Reload dopo Salva mostra lo stato | ⚠️ plausibile ma **mai verificato end-to-end** (PUT reale mai eseguito) |
| 9. `numeroProdotti` = somma articoli | ✅ `summary()` con conteggio annidato + fallback legacy |
| §3.3 "Cerca su codice / SKU / descrizione" | ❌ **parziale** — vedi Important #1 |

Vincoli globali del plan rispettati: nessuna scrittura `.cfg`, nessun auto-commit, default `saltoPagina = false`, rotta parametrica prima della lista, `DragDropModule` già importato in `fant.module.ts`.

Il baco noto del plan (`'saltoPagina' => ! empty( $section['articoli'] ) && ! empty( $articolo['saltoPagina'] )`) **è stato corretto** in implementazione (`class-faa-catalogs.php:186`): usa la forma giusta. Bene.

---

## 2. Critical

Nessuno.

---

## 3. Important

### I1 — La ricerca articoli non copre SKU/codice parziale (requisito di spec §3.3 / §5.1)

`Fant_Admin_API_V4_Products::search()` costruisce la query con `'s' => $search`, che in `WC_Product_Query` finisce dritto in `WP_Query` e cerca **solo** su `post_title` / `post_content` / `post_excerpt`. L'unico contributo SKU è `resolve_sku_extra()`, che usa `wc_get_product_id_by_sku()` + `strcasecmp()` e quindi richiede lo **SKU esatto e completo**.

Conseguenza pratica: digitando `T.PRI` (prefisso SKU, caso d'uso tipico del catalogo Fantini) non compare nulla, a meno che quella stringa non sia anche nel titolo/descrizione. La spec chiede esplicitamente ricerca su "codice / SKU / descrizione".

WooCommerce espone già la variante corretta: `wc_get_products( array( 'sku' => $term ) )` viene tradotto dal data store in una `meta_query` su `_sku` con `compare => 'LIKE'`. Fix suggerito: eseguire una seconda query per SKU LIKE e fondere/dedupare i risultati, oppure sostituire il ramo `resolve_sku_extra` con la ricerca `sku` LIKE (che copre anche il match esatto e semplifica parecchio la paginazione custom).

Se invece il comportamento attuale è accettato dal prodotto, va messo per iscritto nella spec (è una riduzione di scope, non un dettaglio).

### I2 — Il working tree contiene modifiche estranee alla feature, non incluse nel review package

`git diff` sul base mostra file che **non compaiono nello status del package** e non hanno nulla a che vedere con l'editor articoli:

- `default/src/app/layouts/sidebar/menu.ts`: le label sono state sostituite da chiavi i18n (`MENUITEMS.FANT.*`) a **stringhe hardcoded** (`'Home'`, `'Cataloghi'`, `'Settings'`, `'AI'`, nuovo titolo `'General'`). È una regressione di localizzazione, per giunta in contraddizione con…
- `default/src/assets/i18n/*.json` (8 file): aggiunta di `MENUITEMS.FANT.GENERAL` e, per ar/ch/de/es/fr/ru, dell'intero blocco `MENUITEMS.FANT.*` — cioè chiavi che `menu.ts` ora non usa più. Da notare che `it.json`/`en.json` hanno `"GENERAL": "General"`, non tradotto in italiano.
- `default/src/app/app.module.ts`: `enforceLoading: true` nel `TRANSLATE_HTTP_LOADER_CONFIG`.
- `fantShopAdminAngularV3.prompt.txt`: +63 righe di log di prompt.

Non è codice di questa feature e non è stato rivisto in nessun task. Poiché tutto il branch è uncommitted, un `git commit -a` li porterebbe dentro insieme all'editor. **Prima del merge: separare in un commit distinto (o scartare), e decidere se il de-i18n di `menu.ts` è voluto** — al momento le due modifiche (hardcode + nuove chiavi i18n) si annullano a vicenda.

Nota correlata, severità bassa: `default/src/app/pages/fant/fant-categorie/fant-categorie.component.html` sposta icona+label prima del chevron di espansione. È *dentro* il package quindi è stato visto, ma non è tracciato da spec né plan: confermare che sia una modifica voluta.

---

## 4. Minor — da sistemare prima del merge (costo basso, rischio concreto)

### M1 — `save()` non protegge il caso `codice` vuoto
`load()` intercetta `!this.codice` e mostra l'errore, ma `save()` no: con URL malformato il bottone Salva spara `PUT /catalogs//contenuto` (404/route non trovata) e mostra "Salvataggio fallito." Aggiungere la stessa guardia di `load()` (2 righe).
`fant-cataloghi-articoli.component.ts:370`

### M2 — Timer di debounce non annullato alla distruzione del component
`searchTimer` viene creato in `onSearchInput()` ma non esiste `ngOnDestroy`. Uscendo dalla pagina entro 300 ms dall'ultima digitazione parte comunque la chiamata `/products` e la callback scrive su un component distrutto. Aggiungere `ngOnDestroy` con `clearTimeout` (e, volendo, `takeUntil`).
`fant-cataloghi-articoli.component.ts:48,174`

### M3 — Il messaggio di successo resta appeso dopo nuove modifiche
Dopo un Salva riuscito, "Catalogo salvato." rimane visibile mentre l'utente continua a modificare la bozza: il banner afferma qualcosa che non è più vero. Azzerare `success` sulle mutazioni (add/remove/move/toggle) o almeno al primo cambiamento successivo al salvataggio.

### M4 — Nomi di sezione/articolo non sanitizzati lato PHP
`normalize_prodotti()` fa solo `trim()`, mentre `create()`/`update()` passano da `valid_name()` → `sanitize_text_field()`. Il contenuto finisce in un JSON destinato a essere consumato da un futuro generatore PDF, che non avrà l'escaping automatico di Angular. Allineare con `sanitize_text_field()` e valutare un cap di lunghezza (e un tetto al numero di sezioni/articoli per richiesta: oggi il payload è illimitato).
`class-faa-catalogs.php:153,171-172`

### M5 — Verifica manuale mai eseguita su API e drag & drop
Sono gli step del plan **Task 1 Step 3**, **Task 2 Step 4**, **Task 7 Step 6**, tutti mai eseguiti. Il codice PHP più delicato non è mai stato girato nemmeno una volta:
- `WC_Product_Query` con `tax_query` custom su `term_id` (il plan stesso la indicava come fallback "se lo slug approach fallisce");
- la paginazione custom quando lo SKU esatto viene messo in testa (`offset = ($page-1)*$per_page - 1`, `exclude`, `total = title_total + 1`);
- il round-trip `PUT /contenuto` → `GET /catalogs/{code}` → `numeroProdotti` in lista;
- il comportamento delle `cdkDropList` **annidate** (lista articoli dentro l'item sezione, che è a sua volta un `cdkDrag`).

Su questa macchina non c'è PHP, quindi nemmeno un `php -l` è stato possibile. Uno smoke test manuale sul sito di test (i curl già scritti nel plan + un giro completo in UI) è il requisito realistico prima del merge.

---

## 5. Minor — OK da rimandare

- **M6** — `moveArticolo()` sostituisce l'oggetto sezione (`this.sezioni[si] = { ...section, articoli }`) mentre il template fa `@for (section of sezioni; track section)`, cioè track per identità: ogni click sulla freccia distrugge e ricrea l'intero sottoalbero DOM della sezione (perdita del focus dal bottone, flicker, drop list ricreata). `removeArticolo`/`dropArticolo` invece mutano in place. Uniformare mutando `section.articoli` anche qui.
- **M7** — Ricerca con termine vuoto: cancellando l'input parte comunque `search('', 1, 30)`, che restituisce i primi 30 prodotti del catalogo Woo senza filtro. (Ledger Task 6.) Guardia a una riga, ma innocua.
- **M8** — `map_product()`: `code` è di fatto sempre uguale a `sku`, perché `WC_Data::get_meta('_sku')` non restituisce le meta interne. Il campo `code` non porta informazione. Rimuoverlo o mapparlo sul vero meta "codice articolo" quando sarà noto.
- **M9** — `schemaVersion` scritto come letterale `2` in `update_contenuto()` mentre la classe ha `const SCHEMA_VERSION = 1`, ancora usata da `rebuild_index()`. Funziona, ma sono due schemi con lo stesso nome: introdurre una costante dedicata evita il drift.
- **M10** — `by_category()` usa `'limit' => -1` senza tetto. Rischio già dichiarato nella spec §9 e mitigato dallo spinner in UI; da rivedere quando/se una categoria supererà qualche migliaio di prodotti.
- **M11** — A11y: l'item sezione è `<section role="button" tabindex="0">` e contiene bottoni e switch (contenuto interattivo annidato dentro un elemento interattivo, non valido); la compensazione è `stopPropagation()` su click e keydown. Funziona ma il ruolo semantico è discutibile: preferibile un bottone/`role="option"` dedicato per la selezione.
- **M12** — Modifiche possibili mentre il salvataggio è in volo (solo i bottoni del footer sono disabilitati): il "Catalogo salvato." può riferirsi a uno stato diverso da quello a schermo. (Ledger Task 7.)
- **M13** — Nessun test sulla risposta "stale" della ricerca: la guardia `searchRequestId` esiste ed è corretta, ma il test copre solo il debounce, non l'ordine di arrivo invertito. (Ledger Task 6.)
- **M14** — Nessuna copertura automatica PHP/API: nel repo non esiste alcuna infrastruttura di test PHP, quindi introdurla non è in scope di questa delivery. (Ledger Task 1.) Resta però M5, che è la verifica manuale.
- **M15** — Il tooling che genera i review package produce file a codifica mista (UTF-8 + UTF-16LE), illeggibili dagli strumenti standard. È un problema di processo, non di prodotto: da correggere nello script che fa `>>` da PowerShell. (Ledger Task 2.)

---

## 6. Triage dei minor differiti nel ledger

| Voce ledger | Esito |
|---|---|
| Task 1 — nessuna copertura automatica PHP/API | **OK differire** (nessun harness PHP nel repo) — ma la verifica manuale diventa M5, bloccante |
| Task 2 — encoding del review package | **OK differire** (solo tooling/documentazione) → M15 |
| Task 2 — nessun test API live | **Da fare prima del merge** → M5 |
| Task 6 — test risposta stale | **OK differire** → M13 |
| Task 6 — chiamata con search vuota | **OK differire** → M7 |
| Task 6 — UX del debounce | **OK differire** (300 ms è ragionevole e testato) |
| Task 7 — integrazione template CDK non testata | **Da fare prima del merge**, come QA manuale → M5 |
| Task 7 — modifiche durante il salvataggio | **OK differire** → M12 |

---

## 7. Cosa è fatto bene (per non perderlo nel rumore)

- La normalizzazione lato server non si fida del client: valida tipi, richiede `categoryId`/`nome`/`productId`/`sku`, dedupa gli SKU case-insensitive per sezione e ricostruisce l'array in forma canonica. È la scelta giusta per un endpoint "replace all".
- `summary()` conta correttamente il nuovo schema annidato e ha un fallback esplicito per i cataloghi legacy piatti, con la guardia giusta sul caso "sezioni tutte vuote" (che resta 0, non ricade nel legacy).
- La route `/contenuto` è registrata prima della route generica ed è protetta dallo stesso `permission_callback` admin delle altre; `rest_ensure_response()` propaga i `WP_Error` con il loro status.
- Il debounce con `searchRequestId` gestisce sia il ritardo sia le risposte fuori ordine, e il `finalize` è protetto dallo stesso token.
- Le 22 spec della feature sono realmente significative (dedupe, SKU vuoti, selezione dopo remove/move, keyboard, errori API), non test di facciata.
- `CatalogService.get()` normalizza `prodotti` non-array a `[]`, con test dedicati: protegge dai cataloghi legacy.
