export function defaultLayoutHeaderHtml(codice: string): string {
  switch (codice) {
    case 'articolo-semplice':
      return ARTICOLO_SEMPLICE_HEADER_HTML;
    default:
      return '<p>Intestazione: da definire.</p>';
  }
}

const ARTICOLO_SEMPLICE_HEADER_HTML = `<div class="art-semplice-intestazione" role="row">
  <span>Foto</span>
  <span>Codice</span>
  <span>Articolo</span>
  <span>Prezzo</span>
  <span>Note</span>
  <span>Iva</span>
</div>
`;

/** HTML di default per Ripristina / primo carico senza htmlPreview salvato. */
export function defaultLayoutHtml(codice: string): string {
  switch (codice) {
    case 'articolo-semplice':
      return ARTICOLO_SEMPLICE_DEFAULT_HTML;
    default:
      return '<p>Template layout: da definire.</p>';
  }
}

const ARTICOLO_SEMPLICE_DEFAULT_HTML = `<article class="art-semplice">
  <div class="art-semplice-riga">
    <div class="art-semplice-foto"><span class="art-semplice-foto-box"></span></div>
    <div class="art-semplice-codice">{{SKU}}</div>
    <div class="art-semplice-nome">{{NOME}}</div>
    <div class="art-semplice-prezzo">{{PREZZO}}</div>
    <div class="art-semplice-note">{{NOTE}}</div>
    <div class="art-semplice-iva">{{IVA}}</div>
  </div>
  <div class="art-semplice-specchio">
    <div class="art-semplice-specchio-titoli">
      <span>Codice</span>
      <span>Prezzo</span>
      <span>Misure Esterne</span>
      <span>Colore</span>
      <span>Iva</span>
    </div>
    <div class="art-semplice-specchio-riga">
      <span>{{VAR_SKU}}</span>
      <span>{{VAR_PREZZO}}</span>
      <span>{{VAR_MISURE}}</span>
      <span>{{VAR_COLORE}}</span>
      <span>{{VAR_IVA}}</span>
    </div>
    <div class="art-semplice-specchio-vuota"></div>
    <div class="art-semplice-specchio-vuota"></div>
    <div class="art-semplice-specchio-vuota"></div>
    <div class="art-semplice-specchio-vuota"></div>
    <div class="art-semplice-specchio-vuota"></div>
    <div class="art-semplice-specchio-vuota"></div>
  </div>
</article>
`;
