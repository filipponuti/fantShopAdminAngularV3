/** HTML di default per Ripristina / primo carico senza htmlPreview salvato. */
export function defaultLayoutHtml(codice: string): string {
  switch (codice) {
    case 'articolo-semplice':
      return ARTICOLO_SEMPLICE_DEFAULT_HTML;
    default:
      return '<p>Template layout: da definire.</p>';
  }
}

const ARTICOLO_SEMPLICE_DEFAULT_HTML = `<div class="fantini-catalogo-sezione">
  <div class="fantini-catalogo-sezione-titolo">{{SEZIONE}}</div>
  <div class="fantini-catalogo-sezione-articoli">
    <div class="fantini-catalogo-riga-articolo titolo">
      <div class="fantini-catalogo-articolo-col titolo fant-col-20">Foto</div>
      <div class="fantini-catalogo-articolo-col titolo fant-col-15">Codice</div>
      <div class="fantini-catalogo-articolo-col titolo fant-col-30">Articolo</div>
      <div class="fantini-catalogo-articolo-col titolo fant-col-10">Prezzo</div>
      <div class="fantini-catalogo-articolo-col titolo fant-col-20">Note</div>
      <div class="fantini-catalogo-articolo-col titolo fant-col-5">Iva</div>
    </div>
    <div class="fantini-catalogo-riga-articolo variable" data-sku="{{SKU}}">
      <div class="fantini-catalogo-articolo-col fant-col-20">
        <div class="wrap-img img-variable"><img src="{{FOTO}}" alt="{{NOME}}" /></div>
      </div>
      <div class="fantini-catalogo-articolo-col fant-col-15">{{SKU}}</div>
      <div class="fantini-catalogo-articolo-col nome fant-col-48">{{NOME}}</div>
      <div class="fantini-catalogo-articolo-col note fant-col-0"></div>
      <div class="fantini-catalogo-articolo-col dx prezzo fant-col-15 campo-iva"></div>
    </div>
    <div class="fantini-catalogo-riga-articolo-variazioni titolo">
      <div class="fantini-catalogo-articolo-col titolo variazione fant-col-20">Codice</div>
      <div class="fantini-catalogo-articolo-col titolo variazione fant-col-10">Prezzo</div>
      <div class="fantini-catalogo-articolo-col titolo variazione fant-col-30">Misure Esterne</div>
      <div class="fantini-catalogo-articolo-col titolo variazione fant-col-20">Colore</div>
      <div class="fantini-catalogo-articolo-col titolo variazione fant-col-5">Iva</div>
    </div>
    <div class="fantini-catalogo-riga-articolo">
      <div class="fantini-catalogo-articolo-col variazione fant-col-20">{{VAR_SKU}}</div>
      <div class="fantini-catalogo-articolo-col variazione fant-col-10">{{VAR_PREZZO}}</div>
      <div class="fantini-catalogo-articolo-col variazione fant-col-30">{{VAR_MISURE}}</div>
      <div class="fantini-catalogo-articolo-col variazione fant-col-20">{{VAR_COLORE}}</div>
      <div class="fantini-catalogo-articolo-col variazione fant-col-5">{{VAR_IVA}}</div>
    </div>
  </div>
</div>
`;
