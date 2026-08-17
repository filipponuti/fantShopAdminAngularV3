# Design: Layout Articoli (registry per PDF cataloghi)

**Data:** 2026-08-11  
**Stato:** approvato  
**Approccio:** A — Registry JSON seedato (confermato)

## 1. Obiettivo

Sotto **Cataloghi**, nuova gestione **Layout Articoli**: registry dei tipi di layout articolo usati in seguito per generare i PDF catalogo.

v1: lista + dettaglio **sola lettura** (placeholder template). Niente create/delete. Niente generazione PDF in questa iterazione.

## 2. Contesto

- Menu Cataloghi oggi: Copertine/Retri → Cataloghi.
- Pattern storage/API riusabile: Copertine (`uploads/fant-admin-api/copertine/{codice}.json`).
- Layout PDF legacy: rami PHP in `catalogo_v1/.../catalogo.php` (5 tipi già distinti).
- Tab PDF nell’editor catalogo Angular: ancora vuoto.

## 3. UX

### 3.1 Menu

Ordine sottomenu **Cataloghi**:

1. Layout Articoli → `/fant-layout-articoli`
2. Copertine/Retri → `/fant-copertine`
3. Cataloghi → `/fant-cataloghi`

### 3.2 Lista `/fant-layout-articoli`

- Titolo / breadcrumb: Cataloghi → Layout Articoli
- Tabella: codice, nome (eventualmente famiglia)
- Cerca su codice/nome (pattern Copertine)
- **Unica azione per riga:** Dettaglio (icona eye / link)
- **Niente** “Nuovo”, **niente** Elimina, **niente** Modifica in lista

### 3.3 Dettaglio `/fant-layout-articoli/:codice`

- Titolo = nome layout
- Breadcrumb: Cataloghi → Layout Articoli → {nome}
- Freccia indietro verso lista (come articoli catalogo)
- Scheda read-only: codice, nome, famiglia / flag (`showVariationImages`, `bancali`, …)
- Area placeholder: “Template layout: da definire”
- Annulla / Chiudi → torna alla lista (opzionale un solo bottone Indietro)

## 4. Record seed (5, immutabili come insieme)

| codice | nome | family | showVariationImages | bancali |
|--------|------|--------|---------------------|---------|
| `articolo-semplice` | Articolo Semplice | `semplice` | false | false |
| `articolo-variazioni-no-img` | Articolo Con Variazioni Senza immagini | `variazioni` | false | false |
| `articolo-variazioni-img` | Articolo Con Variazioni Con immagini | `variazioni` | true | false |
| `nutrizione-sfuso` | Nutrizione Sfuso | `nutrizione` | false | false |
| `nutrizione-bancali` | Nutrizione con bancali | `nutrizione` | false | true |

- Non eliminabili via API/UI
- Non creabili via API/UI in v1
- Seed automatico se directory vuota / file mancanti (activate o primo `GET /layouts`)

## 5. Modello dati JSON

Path: `wp-content/uploads/fant-admin-api/layout-articoli/{codice}.json`

```json
{
  "schemaVersion": 1,
  "testata": {
    "codice": "articolo-semplice",
    "nome": "Articolo Semplice",
    "createdAt": "...",
    "updatedAt": "..."
  },
  "layout": {
    "family": "semplice",
    "showVariationImages": false,
    "bancali": false,
    "cssClass": "",
    "labels": {
      "confezione": "Confezione da {n} pz.",
      "set": "Set da {n} pz."
    },
    "htmlPreview": null,
    "notes": ""
  }
}
```

Nota: `htmlPreview` / template ricco restano `null` in v1; edit strutturato o HTML in iterazioni successive.

## 6. API (`fant-admin/v1`)

| Method | Route | Comportamento |
|--------|-------|---------------|
| GET | `/layouts` | Lista summary; seed se necessario |
| GET | `/layouts/{code}` | Dettaglio; 404 se codice sconosciuto |

- Auth: stesso gate delle altre route fant-admin
- **Niente** POST/PUT/DELETE in v1 (o 405 se qualcuno chiama)
- Plugin bump minore (es. 0.8.0)

Summary list item: `{ codice, nome, family, showVariationImages, bancali, updatedAt }`

## 7. Frontend Angular

| Pezzo | Path |
|-------|------|
| Menu | `layouts/sidebar/menu.ts` |
| Route list + detail | `fant-routing.module.ts` |
| Module | `fant.module.ts` |
| Service | `core/services/layout.service.ts` |
| Lista | `pages/fant/fant-layout-articoli/` |
| Dettaglio | `pages/fant/fant-layout-articoli-dettaglio/` (o stessa cartella con due component) |
| i18n IT | label menu se usata |

Pattern UI: tabella Copertine semplificata; navigazione dettaglio come cataloghi articoli.

## 8. Fuori scope v1

- Editor template / CKEditor / salvataggio HTML
- Generazione PDF catalogo
- Associazione automatica prodotto→layout in Woo
- Migrazione `.cfg` legacy

## 9. Rischi / note

- I 5 layout esistono già come rami in `catalogo.php`: il registry admin li rende espliciti per il futuro motore PDF.
- Finché non c’è PUT, `labels`/`notes` sono solo informativi in UI.
- Dopo v1, PUT potrà aggiornare campi layout senza permettere delete dei 5 seed.
