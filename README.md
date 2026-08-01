# fantShopAdminAngularV3

## Build

```bash
# sviluppo
ng build --base-href=/pippo/

# deploy test su Apache (Serverplan) — optimization=false riduce falsi positivi antivirus
ng build --configuration production --base-href=/test/ --optimization=false

# production standard
ng build --configuration production
```

## Deploy su Serverplan (Apache) — base `/test/`

La build di deploy è già pronta in:

- **Cartella FTP (consigliata):** `deploy-test/`
- **TAR completo (no zip):** `varie/deploy-test.tar`
- **Split anti-AV:** `varie/deploy-test-static.zip` (senza JS) + `varie/deploy-test-js.tar` (solo JS)

### Perché l'antivirus blocca lo zip

Su Serverplan ClamAV usa firme **Sanesecurity Foxhole** che spesso segnalano come virus gli archivi **ZIP/RAR che contengono file `.js`** (falso positivo tipico delle build Angular). Non è un virus reale.

### Metodo consigliato: upload FTP della cartella

1. Con FileZilla / FTP carica **tutto** il contenuto di `deploy-test/` dentro `public_html/test/` (o la docroot equivalente).
2. Verifica che ci sia `.htaccess` nella root di `/test/`.
3. Apri `https://TUO-DOMINIO/test/`.

Non caricare uno zip unico con i JS dentro: l'AV lo rifiuta.

### Alternativa split (se serve un archivio)

1. Carica e scompatta `varie/deploy-test-static.zip` in `/test/` (niente JS → di solito passa l'AV).
2. Carica `varie/deploy-test-js.tar` e scompatta nella stessa cartella `/test/` (oppure carica i `.js` via FTP uno a uno dalla cartella `deploy-test/`).

### Alternativa TAR unico

Carica `varie/deploy-test.tar` e scompatta in `/test/`. Il TAR non compresso spesso evita la firma `Foxhole.JS_Zip_*` (che colpisce soprattutto gli ZIP).

### Se l'AV blocca comunque

Apri un ticket Serverplan chiedendo di whitelistare il falso positivo (firma tipo `Sanesecurity.Foxhole.JS_Zip_*`) oppure di ripristinare i file dalla quarantena cPanel → Sicurezza → Quarantena Malware.

### Se vedi 403 Forbidden

1. In `public_html/test/` devono esserci **direttamente** `index.html` e `.htaccess` (non dentro una sottocartella `deploy-test/`).
2. Permessi: cartelle **755**, file **644** (su FileZilla: tasto destro → File permissions).
3. Ricarica il `.htaccess` aggiornato da `deploy-test/.htaccess`.
4. Prova direttamente `https://TUO-DOMINIO/test/index.html` — se apre, il problema è solo il rewrite; se dà 403, è permessi o file mancante/quarantena.
5. Controlla cPanel → Sicurezza → Quarantena Malware: l'AV può aver messo i file in quarantena dopo l'upload.




npm --prefix .\default run build -- --define "SITE_URL='https://www.filipponuti.it/agenti'"


ng build --configuration production --base-href=/test/ --optimization=false --define "SITE_URL='https://www.filipponuti.it/agenti'"
