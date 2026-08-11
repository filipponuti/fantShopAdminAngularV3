# Design: Refresh sezione da categoria + variazioni articolo

**Data:** 2026-08-11  
**Stato:** approvato  
**Approccio:** A — nested `variazioni[]` (confermato)  
**Estende:** `2026-08-10-catalogo-articoli-editor-design.md`

## 1. Obiettivo

Nella pagina **Articoli per il catalogo**:

1. **Refresh** sulle sezioni create da categoria: confrontare i prodotti Woo della categoria (con sottocategorie) con gli articoli già in sezione; se mancano elementi, chiedere conferma e mostrare una modale multi-selezione per aggiungerli.
2. **Variazioni** WooCommerce come **figli** dell’articolo padre (`variazioni[]`).
3. **Collapse / Expand** della lista figli: su sezione (articoli) e su articolo (variazioni), solo se ha figli.

Fuori scope: Settings/PDF, generazione PDF, sync inverso (rimuovere dalla sezione prodotti spariti da Woo), riordino drag delle sole variazioni tra sezioni.

## 2. Modello dati

### 2.1 Catalogo (JSON / API `contenuto`)

```ts
interface CatalogArticolo {
  productId: number;
  sku: string;
  nome: string;
  saltoPagina: boolean;
  variazioni?: CatalogArticolo[]; // max 1 livello; le variazioni non hanno a loro volta variazioni
}
```

- `numeroProdotti` lato API: somma degli articoli **top-level** + tutte le variazioni annidate.
- SKU unici **nella sezione** considerando anche le variazioni (stessa regola anti-duplicato).
- Cataloghi già salvati senza `variazioni` restano validi (`variazioni` assente o `[]`).

### 2.2 API prodotti

Estendere `map_product` / payload:

```ts
interface ProductItem {
  id: number;
  sku: string;
  name: string;
  code: string;
  type?: string; // simple | variable | variation | …
  variazioni?: ProductItem[]; // solo sui variabili; leaf senza nested
}
```

Regole `byCategory` / search:

- Restituire prodotti **padri** (simple / variable / altri non-variation).
- **Escludere** le variation come item top-level (sono solo dentro `variazioni` del padre).
- Per `variable`: popolare `variazioni` dalle children Woo pubblicate/private ammesse.
- Prodotti senza SKU: come oggi, esclusi all’inserimento in catalogo (padre e variazioni).

Bump plugin: **0.7.0** (cambio contratto prodotti + contenuto).

## 3. UX

### 3.1 Refresh sezione

- Visibile **solo** se la sezione ha `categoryId > 0` (sezioni da tab Categoria).
- Posizione: subito **dopo il nome** sezione (icona `ri-refresh-line`), con spinner mentre carica.
- Click:
  1. `GET products?categoryId=&includeChildren=1`
  2. Calcolare **mancanti** = prodotti/variazioni (con SKU) presenti in Woo ma assenti in sezione (match per `productId`; fallback SKU case-insensitive se serve).
     - Un padre mancante include le sue variazioni mancanti come sotto-albero da aggiungere.
     - Se il padre è già in sezione ma mancano variazioni → mancanti = solo quelle variazioni (da aggiungere sotto il padre esistente).
  3. Se **nessun mancante** → messaggio successo: *“La sezione è allineata alla categoria: nessun articolo nuovo.”*
  4. Se **ci sono mancanti** → `confirm`: *“Trovati N articoli/variazioni non presenti in questa sezione. Vuoi selezionarli da aggiungere?”*
     - No → stop  
     - Sì → aprire modale

### 3.2 Modale “Articoli mancanti”

- Titolo: es. *Articoli da aggiungere*
- Lista: checkbox multi-selezione; colonne **SKU** + **Descrizione (nome)**; variazioni indentate sotto il padre (o riga con prefisso che indica la parentela).
- Default: **tutti selezionati**.
- Pulsanti: **Annulla** | **Aggiungi** (disabilitato se selezione vuota).
- **Aggiungi**: appende i selezionati in coda alla sezione (padri nuovi in coda articoli; variazioni sotto il relativo padre, creando il padre solo se selezionato o già presente). Se si seleziona una variazione senza il padre e il padre non è in sezione → includere automaticamente il padre (senza altre variazioni non selezionate) oppure richiedere il padre: **regola scelta: auto-includere il padre** se manca.
- Chiude modale, `markDirty()`, messaggio *“Aggiunti X elementi.”*

Pattern UI: `NgbModal` + `ng-template` come in `fant-cataloghi` / `fant-categorie`.

### 3.3 Collapse / Expand

- **Sezione** con `articoli.length > 0`: due pulsanti (collapse tutti / expand tutti i figli diretti = nascondi/mostra lista articoli). Stato per-sezione in UI (`collapsedSectionIds`).
- **Articolo** con `variazioni.length > 0`: due pulsanti analoghi per le sue variazioni (`collapsedArticleKeys`, chiave `sectionIndex-productId`).
- Default: **espansi**.
- I due pulsanti sono icone distinte (es. `ri-fold-...` / `ri-unfold-...` o arrow up/down pair), non un solo toggle — come richiesto.

### 3.4 Main — rendering variazioni

- Sotto ogni articolo padre: lista variazioni indentata (SKU, nome, salto pagina, frecce, elimina, drag nella lista variazioni del padre).
- Aggiunta da tab **Articolo** (search): se il risultato è variable con variazioni, salvare padre + variazioni selezionabili in seguito via refresh; all’add da search per v1: aggiungere il padre e **tutte** le variazioni con SKU (coerente con add categoria).
- Add da **Categoria**: padre + tutte le variazioni con SKU.

## 4. Backend — dettagli

### `class-faa-products.php`

- In `by_category` e nelle liste search: skip `product_type === 'variation'` a top-level.
- Se `WC_Product_Variable`, mappare children in `variazioni`.
- `map_product`: aggiungere `type` e opzionale `variazioni`.

### `class-faa-catalogs.php` `normalize_prodotti`

- Accettare `variazioni` array opzionale su ogni articolo.
- Normalizzare ricorsivamente **un solo livello** (stessi campi; ignorare `variazioni` annidate più profonde).
- Dedup SKU nella sezione su padri + variazioni.
- `count_prodotti`: contare padri + variazioni.

## 5. Frontend — file toccati

- `fant-admin-api`: products + catalogs (+ version bump)
- `product.service.ts` (+ spec)
- `catalog.service.ts` (interfaccia)
- `fant-cataloghi-articoli.component.{ts,html,scss}` (+ spec)
- `fant.module.ts` se serve `NgbModalModule` (già usato altrove nel modulo fant)

## 6. Test

- PHP/unit o manual: variable product mappa variazioni; variation non compare top-level in byCategory.
- Angular: refresh senza diff → messaggio; con diff → confirm + modal add; collapse sezione/articolo; normalize/add non duplica SKU.

## 7. Rischi / note

- Cataloghi esistenti senza variazioni: OK.
- Plugin sul server shop va aggiornato a 0.7.0 prima del deploy FE che dipende da `variazioni`.
- Match refresh per `productId` primario (SKU fallback solo se id assente — non previsto in payload corrente).
