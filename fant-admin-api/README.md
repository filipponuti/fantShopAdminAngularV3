# fantAdminApi

Plugin WordPress/WooCommerce per l'autenticazione della dashboard Angular
`fantShopAdmin`.

## API

- `GET /wp-json/fant-admin/v1/status`
- `POST /wp-json/fant-admin/v1/auth/login`
- `POST /wp-json/fant-admin/v1/auth/refresh`
- `POST /wp-json/fant-admin/v1/auth/logout`
- `GET /wp-json/fant-admin/v1/me`
- `GET|POST /wp-json/fant-admin/v1/catalogs`
- `GET|PUT|DELETE /wp-json/fant-admin/v1/catalogs/{codice}`
- `GET|POST /wp-json/fant-admin/v1/covers`
- `GET|PUT|DELETE /wp-json/fant-admin/v1/covers/{codice}`
- `POST /wp-json/fant-admin/v1/covers/{codice}/attachments`
- `GET /wp-json/fant-admin/v1/covers/{codice}/pdf`
- `GET|PUT /wp-json/fant-admin/v1/settings/ai`

Il login usa le credenziali WordPress e accetta esclusivamente utenti con
ruolo `administrator`. I token sono casuali e nel database vengono conservati
solo gli hash SHA-256. L'access token dura 15 minuti e il refresh token 30
giorni; entrambi i valori sono configurabili tramite opzioni WordPress.

Il plugin dichiara la compatibilita con HPOS. Non invia email e non richiede
plugin JWT esterni.

## Cataloghi

I cataloghi sono salvati come JSON in `wp-content/uploads/fant-admin-api/cataloghi/`.
Ogni codice identifica un file immutabile `{codice}.json`; `cataloghi-index.json`

## Copertine

I record JSON sono salvati in `wp-content/uploads/fant-admin-api/copertine/`, i PDF in
`copertine_pdf/` e gli allegati in `copertine_allegati/{codice}/`. Il codice è univoco
e immutabile; il PDF viene generato alla creazione e rigenerato alla modifica.
Il campo `nomeFilePdf` è modificabile, deve terminare con `.pdf` ed è univoco;
se non specificato assume il valore `{codice}.pdf`.

All'attivazione il plugin elimina le eventuali directory legacy `fant-admin-api*`,
mantenendo soltanto la cartella della versione corrente.
è un indice derivato e ricostruibile. Le scritture usano un file temporaneo e una
rinomina atomica.

## Impostazioni AI

Le configurazioni di Gemini, OpenAI e Claude sono salvate nelle opzioni
WordPress. Le chiavi API sono cifrate con una chiave derivata dai salt di
WordPress e non vengono mai incluse nelle risposte REST. La rotta è accessibile
soltanto agli utenti autenticati con ruolo `administrator`.
