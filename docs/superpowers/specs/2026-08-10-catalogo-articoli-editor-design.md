# Design: Editor articoli catalogo (sezioni + salti pagina)

**Data:** 2026-08-10  
**Stato:** approvato  
**Approccio:** A — JSON nativo in `fant-admin-api` (confermato)

## 1. Obiettivo

Dalla lista **Cataloghi → Cataloghi**, per ogni catalogo aprire una pagina di edit del contenuto: sezioni (categorie Woo) e articoli (SKU), con salti pagina a livello sezione e SKU, rimozione e riordino (drag & drop + frecce), Salva / Annulla.

Fuori scope di questa iterazione: generazione PDF, migrazione automatica dei `.cfg` legacy, hide prodotti/sezioni del vecchio modello.

## 2. Contesto attuale

- Lista: `fant-cataloghi` (`CatalogService.list/create/update/delete`).
- Storage catalogo: JSON in `wp-content/uploads/fant-admin-api/cataloghi/{codice}.json` con `testata` + `prodotti: []`.
- `PUT /catalogs/{code}` aggiorna solo `nome`.
- Nessuna API prodotti in `fant-admin/v1`.
- Legacy `catalogo_v1` `.cfg` ha già il concetto di salto sezione (`newpage.sections`) e salto SKU (`sez-{id}.salti`).

## 3. UX

### 3.1 Lista cataloghi

- Nuovo pulsante per riga (es. icona elenco / “Articoli”) accanto a Modifica / Elimina.
- Navigazione a `/fant-cataloghi/:codice`.

### 3.2 Pagina edit

- **Titolo:** `Articoli per il catalogo: {nome}`
- **Layout:** sidebar sinistra “Aggiungi” | main destra elenco sezioni/articoli
- **Footer fisso/in basso:** `Annulla` | `Salva`
  - Annulla → torna a `/fant-cataloghi` senza salvare
  - Salva → persiste il contenuto e resta in pagina (toast successo/errore) oppure torna alla lista (default: resta in pagina con feedback)

### 3.3 Sidebar — titolo “Aggiungi”

**Tab Categoria**

- Albero categorie Woo (riuso pattern da `fant-categorie`, in sola selezione).
- Selezione singola + bottone **Aggiungi**.
- Effetto: crea una **nuova Sezione** nel main e **carica subito** tutti i prodotti della categoria **incluse le sottocategorie**.
- Toggle salto pagina sezione: default `false` alla creazione (l’utente lo attiva esplicitamente).

**Tab Articolo**

- Campo **Cerca** su codice / SKU / descrizione.
- Lista risultati con multi-selezione + **Aggiungi**.
- Destinazione: **sezione selezionata** nel main.
- Se nessuna sezione selezionata: messaggio di avviso, non aggiungere.

### 3.4 Main

- Elenco sezioni ordinate.
- Per ogni sezione:
  - nome, toggle **salto pagina**, frecce su/giù, drag handle, elimina
  - click sulla riga sezione → diventa **sezione selezionata** (evidenziata)
- Dentro ogni sezione: elenco articoli (SKU) con:
  - codice/SKU, nome, toggle **salto pagina**, frecce su/giù, drag handle, elimina
- Riordino: drag & drop **e** frecce (stesso range: sezioni tra sezioni; articoli dentro la propria sezione).
- Evitare duplicati SKU nella stessa sezione all’aggiunta (skip o toast “già presente”).

## 4. Modello dati JSON

Sostituire/reinterpretare `prodotti` come array di sezioni (non più array vuoto senza schema).

```json
{
  "schemaVersion": 2,
  "testata": {
    "codice": "nutrizione",
    "nome": "Nutrizione",
    "createdAt": "...",
    "updatedAt": "..."
  },
  "prodotti": [
    {
      "categoryId": 197,
      "nome": "Hobby Universali",
      "saltoPagina": true,
      "articoli": [
        {
          "productId": 12345,
          "sku": "T.PRI365-SF",
          "nome": "Prodotto esempio",
          "saltoPagina": true
        }
      ]
    }
  ]
}
```

### Regole

| Campo | Note |
|-------|------|
| `schemaVersion` | `2` per cataloghi con sezioni tipizzate |
| `prodotti[]` | sezioni ordinate |
| `categoryId` | term_id Woo `product_cat` |
| `nome` sezione | snapshot nome categoria al momento dell’aggiunta |
| `saltoPagina` sezione | boolean |
| `articoli[]` | prodotti ordinati nella sezione |
| `productId` | ID Woo prodotto |
| `sku` | SKU Woo (chiave operativa salti, come legacy) |
| `nome` articolo | snapshot al momento dell’aggiunta |
| `numeroProdotti` (summary) | somma di tutti gli `articoli` nelle sezioni |

Compatibilità: cataloghi esistenti con `prodotti: []` restano validi; lettura tratta array vuoto come nessuna sezione.

## 5. API (`fant-admin/v1`)

### 5.1 Prodotti (nuove)

| Metodo | Path | Descrizione |
|--------|------|-------------|
| GET | `/products` | Query `search` (codice/SKU/nome/descrizione), paginazione `page`, `perPage` |
| GET | `/products` | Query `categoryId` + `includeChildren=1` per caricare prodotti categoria+sottocategorie |

Response item minimo:

```json
{
  "id": 12345,
  "sku": "T.PRI365-SF",
  "name": "...",
  "code": "..."
}
```

(`code` = meta/codice articolo se esiste; altrimenti uguale a SKU o campo disponibile.)

Auth: stesso JWT admin di `fant-admin/v1`.

### 5.2 Cataloghi (estensioni)

| Metodo | Path | Descrizione |
|--------|------|-------------|
| GET | `/catalogs/{catalogCode}` | già presente lato PHP; usarlo dall’Angular |
| PUT | `/catalogs/{catalogCode}/contenuto` | body `{ "prodotti": [ sezioni... ] }` — sostituisce l’intero array sezioni |

`PUT /catalogs/{code}` resta per aggiornare solo `nome` (lista/modale attuale).

Validazione contenuto:

- struttura sezioni/articoli come sopra
- `categoryId` / `productId` numerici
- `sku` stringa non vuota per articoli
- dedupe SKU per sezione lato server (opzionale ma consigliato)

## 6. Angular

### 6.1 Routing

```ts
{ path: 'fant-cataloghi/:codice', component: FantCataloghiArticoliComponent }
{ path: 'fant-cataloghi', component: FantCataloghiComponent }
```

Ordine: route parametrica **dopo** o comunque non ambigua rispetto alla lista (lista path esatto, edit con param).

### 6.2 Service

- `CatalogService.get(code)`
- `CatalogService.updateContenuto(code, prodotti)`
- `ProductService.search(...)` / `ProductService.byCategory(categoryId, includeChildren)`

### 6.3 Componenti

- `FantCataloghiArticoliComponent` (pagina edit)
- Eventuale sotto-componente albero categorie read-only/select (estratto da pattern `fant-categorie`)
- Lista sezioni/articoli con CDK drag-drop (già usato in categorie)

### 6.4 Lista

- Bottone riga → `router.navigate(['/fant-cataloghi', catalog.codice])`

## 7. Mapping concettuale legacy → nuovo

| Legacy `.cfg` | Nuovo JSON |
|---------------|------------|
| `[newpage] sections` | sezioni con `saltoPagina: true` |
| `[sez-{id}] salti` | articoli con `saltoPagina: true` |
| `[newpage] products` | derivabile come flatten degli SKU con salto (non obbligatorio salvare duplicato) |
| slug categoria = file cfg | `testata.codice` catalogo JSON |

Nessuna lettura/scrittura `.cfg` in questa fase.

## 8. Criteri di accettazione

1. Dalla lista, bottone apre `/fant-cataloghi/{codice}` con titolo corretto.
2. Aggiungi categoria → nuova sezione con prodotti (anche sottocategorie).
3. Aggiungi articoli → solo se sezione selezionata; finiscono in quella sezione.
4. Toggle salto su sezione e SKU visibili e persistiti al Salva.
5. Rimuovi sezione/articolo funziona in bozza e dopo Salva.
6. Riordino con drag e frecce funziona per sezioni e per articoli nella sezione.
7. Annulla non persiste modifiche.
8. Ricaricando la pagina dopo Salva si vedono sezioni/articoli/salti/ordine salvati.
9. `numeroProdotti` in lista riflette il conteggio articoli.

## 9. Rischi / note

- Categorie grandi: `includeChildren=1` può restituire molti prodotti → paginare o limite alto documentato; UI con loading.
- Snapshot `nome` può diventare stale se Woo cambia i nomi → accettabile; eventuale refresh futuro.
- PDF: consumer futuro leggerà questo JSON (non parte di questa delivery).
