# fantAdminApi

Plugin WordPress/WooCommerce per l'autenticazione della dashboard Angular
`fantShopAdmin`.

## API

- `GET /wp-json/fant-admin/v1/status`
- `POST /wp-json/fant-admin/v1/auth/login`
- `POST /wp-json/fant-admin/v1/auth/refresh`
- `POST /wp-json/fant-admin/v1/auth/logout`
- `GET /wp-json/fant-admin/v1/me`

Il login usa le credenziali WordPress e accetta esclusivamente utenti con
ruolo `administrator`. I token sono casuali e nel database vengono conservati
solo gli hash SHA-256. L'access token dura 15 minuti e il refresh token 30
giorni; entrambi i valori sono configurabili tramite opzioni WordPress.

Il plugin dichiara la compatibilita con HPOS. Non invia email e non richiede
plugin JWT esterni.
