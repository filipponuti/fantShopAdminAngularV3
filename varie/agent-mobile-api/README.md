# Agent Mobile API

Plugin WordPress/WooCommerce che implementa il contratto
`docs/openapi.yaml` per l'app mobile degli agenti.

## Requisiti

- WordPress 6.9 o successivo
- WooCommerce 10.7 o successivo
- PHP 8.1 o successivo
- WooCommerce B2B
- WooCommerce B2B Sales Agents
- Advanced Custom Fields

Compatibile con HPOS attivo e disattivo tramite WooCommerce CRUD.

## Installazione

1. Copiare `agent-mobile-api` in `wp-content/plugins/`.
2. Attivare `Agent Mobile API` dall'amministrazione WordPress.
3. Verificare `GET /wp-json/agent-mobile/v1/settings` con un token agente.

L'attivazione crea esclusivamente le tabelle:

- `{prefix}ama_sessions`
- `{prefix}ama_idempotency`

## Email

Per sicurezza le email API sono disabilitate per default alla prima
attivazione tramite l'opzione `ama_disable_emails = yes`.

In produzione dovra essere impostata esplicitamente a `no` dopo il collaudo.
Il blocco viene applicato soltanto durante creazione clienti e documenti via
API.

## Autenticazione

Il plugin usa token opachi casuali:

- i token completi vengono restituiti soltanto al client;
- nel database vengono conservati hash SHA-256;
- l'access token dura 15 minuti per default;
- il refresh token dura 30 giorni e viene ruotato a ogni refresh.

Non sono necessari plugin JWT esterni.

## Listini

- `L0`: prezzo zero
- `L1`: campo ACF `l1_conf`
- `L2`: campo ACF `l2_conf`
- `L3`: campo ACF `l3_conf`

Per compatibilita con l'ambiente di test sono riconosciuti anche gli alias
ACF `l1c`, `l2c` e `l3c`. I candidati possono essere personalizzati tramite
il filtro `ama_price_field_candidates`.

Il server risolve sempre il prezzo corrente e salva sulla riga ordine livello,
prezzo base, prezzo manuale, sconti e prezzo finale.

## Estensioni

L'hook `ama_before_calculate_document` permette di aggiungere righe di
spedizione o altre regole prima del calcolo finale WooCommerce.
