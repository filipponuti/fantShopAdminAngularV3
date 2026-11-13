/** Altezza foglio A4 a 96dpi, piede escluso dal flusso articoli. */
const PAGE_WIDTH_PX = 794;
const PAGE_HEIGHT_PX = 1123;
const PAGE_FOOTER_PX = 36;
const PAGE_PADDING_TOP_PX = 28;
const PAGE_CONTENT_PX = PAGE_HEIGHT_PX - PAGE_FOOTER_PX - PAGE_PADDING_TOP_PX;

/** Spazio utile di una pagina, lo stesso usato dall'indicatore in Sezioni e articoli. */
export const CATALOG_PAGE_CONTENT_PX = PAGE_CONTENT_PX;

export interface CatalogPdfArticle {
  sku: string;
  nome: string;
  saltoPagina: boolean;
  layout: string;
  height: number;
  prezzo?: string;
  iva?: string;
  foto?: string;
  varPrezzo?: string;
  varMisure?: string;
  varColore?: string;
  varIva?: string;
}

export interface CatalogPdfSection {
  nome: string;
  saltoPagina: boolean;
  articoli: CatalogPdfArticle[];
}

export interface CatalogPdfTheme {
  backgroundColor: string;
  textColor: string;
  fontSize: number;
}

export interface CatalogPdfDocumentInput {
  titolo: string;
  codice: string;
  theme: CatalogPdfTheme;
  sezioni: CatalogPdfSection[];
  layoutHtml: ReadonlyMap<string, string>;
  layoutHeaderHtml?: ReadonlyMap<string, string>;
  /** Altezza usata se il layout non ne dichiara una. */
  fallbackHeight?: number;
}

export function buildCatalogPdfDocument(input: CatalogPdfDocumentInput): string {
  const pages = paginate(input);
  const body = pages
    .map((page, index) => {
      const ribbon = index === 0
        ? ''
        : `<div class="catalogo-salto">${escapeHtml(page.breakLabel)}</div>`;
      return `${ribbon}<section class="catalogo-pagina">
        <div class="catalogo-pagina-corpo">${page.html}</div>
        <footer class="catalogo-pagina-piede">${escapeHtml(input.titolo)} · Pagina ${index + 1} di ${pages.length}</footer>
      </section>`;
    })
    .join('');

  return `<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(input.titolo)}</title>
  <style>${documentCss(input.theme)}</style>
</head>
<body>
${body}
</body>
</html>`;
}

export function catalogPdfPageCount(html: string): number {
  return html.split('class="catalogo-pagina"').length - 1;
}

export function catalogPdfDocumentForPrint(html: string): string {
  const script = '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},300)});</script>';
  return html.replace('</body>', `${script}</body>`);
}

/** Anteprima del template layout: lo stesso HTML dell'editor, con l'articolo di esempio. */
export function buildLayoutHtmlPreview(
  templateHtml: string,
  example?: { sku: string; nome: string; foto?: string },
): string {
  const sku = example?.sku?.trim() || 'TF8100';
  const nome = example?.nome?.trim() || 'FONTANA CON ROSONE';
  const filled = fillTemplate(templateHtml, {
    SEZIONE: 'FATTE A MANO >> Fontane',
    SKU: sku,
    NOME: nome,
    FOTO: '',
    VAR_SKU: example?.sku?.trim() ? sku : 'TATITF8100',
    VAR_PREZZO: '€ 76,70',
    VAR_MISURE: 'cm. 45 x 26 - h. 70',
    VAR_COLORE: '',
    VAR_IVA: '22%',
  });
  const body = applyProductPhoto(filled.replace(
    /<img\b[^>]*src=(?:"\s*"|'\s*'|\s*)[^>]*>/gi,
    '<span class="catalogo-foto-ph"></span>',
  ), example?.foto ?? '');
  const theme: CatalogPdfTheme = { backgroundColor: '#C6B2B3', textColor: '#333333', fontSize: 15 };
  return `<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <style>${documentCss(theme)}
    body { background: #fff; padding: 16px; }
  </style>
</head>
<body>${body}</body>
</html>`;
}

interface PdfPage {
  breakLabel: string;
  html: string;
}

function paginate(input: CatalogPdfDocumentInput): PdfPage[] {
  const fallbackHeight = input.fallbackHeight && input.fallbackHeight > 0 ? input.fallbackHeight : 220;
  const sectionHeight = Math.max(34, Math.round(input.theme.fontSize) + 18);
  const pages: PdfPage[] = [];
  let chunks: string[] = [];
  let used = 0;
  let breakLabel = '';
  let sectionOnPage = false;
  let headerOnPage = false;
  const headerHeight = 36;

  const flush = (nextLabel: string): void => {
    if (!chunks.length) {
      return;
    }
    pages.push({ breakLabel, html: chunks.join('') });
    chunks = [];
    used = 0;
    breakLabel = nextLabel;
    sectionOnPage = false;
    headerOnPage = false;
  };

  const place = (html: string, height: number): void => {
    const slot = Math.max(1, Math.min(height, PAGE_CONTENT_PX));
    chunks.push(html);
    used += slot;
  };

  const placeSection = (section: CatalogPdfSection): void => {
    if (sectionOnPage) {
      return;
    }
    place(sectionBar(section.nome, sectionHeight, input.theme), sectionHeight);
    sectionOnPage = true;
  };

  for (const section of input.sezioni) {
    if (section.saltoPagina && used > 0) {
      flush('Salto pagina');
    }
    if (!section.articoli.length) {
      placeSection(section);
      continue;
    }
    for (const articolo of section.articoli) {
      const height = articolo.height > 0 ? articolo.height : fallbackHeight;
      const header = headerFor(input, articolo.layout);
      if (articolo.saltoPagina && used > 0) {
        flush('Salto pagina');
      }
      placeSection(section);
      if (!headerOnPage && header) {
        place(header, headerHeight);
        headerOnPage = true;
      }
      const layout = input.layoutHtml.get(articolo.layout) || '<p>Template layout: da definire.</p>';
      place(articleBlock(layout, section.nome, articolo, height), height);
    }
  }

  if (!chunks.length) {
    chunks.push('<p class="catalogo-vuoto">Nessun articolo in questo catalogo.</p>');
  }
  pages.push({ breakLabel, html: chunks.join('') });
  return pages;
}

function headerFor(input: CatalogPdfDocumentInput, layout: string): string {
  const html = input.layoutHeaderHtml?.get(layout)?.trim() || '';
  if (!html || html.includes('Intestazione: da definire')) {
    return '';
  }
  return html;
}

function articleBlock(layoutHtml: string, sezione: string, articolo: CatalogPdfArticle, height: number): string {
  const filled = fillTemplate(layoutHtml, {
    SEZIONE: sezione,
    SKU: articolo.sku,
    CODICE: articolo.sku,
    NOME: articolo.nome,
    PREZZO: articolo.prezzo ?? '',
    IVA: articolo.iva ?? '',
    FOTO: '',
    VAR_SKU: articolo.sku,
    VAR_PREZZO: articolo.varPrezzo ?? '',
    VAR_MISURE: articolo.varMisure ?? '',
    VAR_COLORE: articolo.varColore ?? '',
    VAR_IVA: articolo.varIva ?? '',
  });
  const body = filled.replace(/<div class="fantini-catalogo-sezione-titolo">[\s\S]*?<\/div>/gi, '');
  const withoutEmptyImage = body.replace(
    /<img\b[^>]*src=(?:"\s*"|'\s*'|\s*)[^>]*>/gi,
    '<span class="catalogo-foto-ph"></span>',
  );
  const withPhoto = applyProductPhoto(withoutEmptyImage, articolo.foto ?? '');
  return `<div class="catalogo-articolo" style="height:${Math.round(height)}px">${withPhoto}</div>`;
}

function applyProductPhoto(html: string, foto: string): string {
  const url = /^https?:\/\/[^\s"'<>]+$/i.test(foto.trim()) ? foto.trim() : '';
  if (!url) {
    return html;
  }
  const img = `<img class="art-semplice-foto-box" src="${escapeHtml(url)}" alt="">`;
  return html.replace(/<span\b[^>]*class="[^"]*\bart-semplice-foto-box\b[^"]*"[^>]*>\s*<\/span>/gi, img);
}

function sectionBar(nome: string, height: number, theme: CatalogPdfTheme): string {
  const background = cssColor(theme.backgroundColor, '#C6B2B3');
  const color = cssColor(theme.textColor, '#333333');
  const fontSize = Math.min(24, Math.max(10, Math.round(theme.fontSize) || 15));
  return `<div class="catalogo-sezione-bar" style="height:${height}px;background:${background};color:${color};font-size:${fontSize}px">${escapeHtml(nome)}</div>`;
}

function fillTemplate(html: string, tokens: Record<string, string>): string {
  const safe: Record<string, string> = {};
  for (const [key, value] of Object.entries(tokens)) {
    safe[key] = escapeHtml(value);
  }
  return html.replace(/\{\{([A-Z0-9_]+)\}\}/g, (_match, key: string) => safe[key] ?? '');
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function cssColor(value: string, fallback: string): string {
  return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(value.trim()) ? value.trim() : fallback;
}

function articoloSempliceCss(): string {
  return `
    .art-semplice {
      max-width: 52rem;
      border: 1px solid #b8b8b8;
      background: #fff;
      color: #1a1a1a;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      font-size: 0.9375rem;
    }
    .art-semplice-riga {
      display: grid;
      grid-template-columns: 4.5rem 5.5rem minmax(10rem, 1.6fr) 4.5rem minmax(6rem, 1fr) 3rem;
      gap: 0.35rem;
      align-items: center;
      padding: 0.35rem 0.5rem;
      background: #f4f4f4;
      min-height: 3.5rem;
    }
    .art-semplice-foto-box {
      display: block;
      width: 3.25rem;
      height: 3.25rem;
      border: 1px solid #bbb;
      background: linear-gradient(145deg, #d9b08a 0%, #b9784a 55%, #8f5530 100%);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.25);
    }
    img.art-semplice-foto-box {
      object-fit: contain;
      background: #fff;
      box-shadow: none;
    }
    .art-semplice-codice,
    .art-semplice-nome { font-weight: 600; }
    .art-semplice-nome { text-transform: uppercase; letter-spacing: 0.01em; }
    .art-semplice-specchio { padding: 0.55rem 0.75rem 0.85rem 5.25rem; }
    .art-semplice-specchio-titoli,
    .art-semplice-specchio-riga {
      display: grid;
      grid-template-columns: 7rem 5rem minmax(10rem, 1.4fr) minmax(4rem, 1fr) 3rem;
      gap: 0.5rem;
      align-items: end;
    }
    .art-semplice-specchio-titoli {
      font-weight: 600;
      padding-bottom: 0.2rem;
      border-bottom: 1px solid #333;
      margin-bottom: 0.35rem;
    }
    .art-semplice-specchio-riga {
      padding: 0.15rem 0 0.35rem;
      border-bottom: 1px solid #cfcfcf;
    }
    .art-semplice-specchio-vuota {
      height: 1.15rem;
      border-bottom: 1px solid #cfcfcf;
    }
    .art-semplice-intestazione {
      display: grid;
      grid-template-columns: 4.5rem 5.5rem minmax(10rem, 1.6fr) 4.5rem minmax(6rem, 1fr) 3rem;
      gap: 0.35rem;
      align-items: center;
      max-width: 52rem;
      padding: 0.35rem 0.5rem;
      background: #bec4e4;
      color: #1a1a1a;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      font-size: 0.9375rem;
      font-weight: 700;
    }
  `;
}

function documentCss(theme: CatalogPdfTheme): string {
  const background = cssColor(theme.backgroundColor, '#C6B2B3');
  const color = cssColor(theme.textColor, '#333333');
  return `
    * { box-sizing: border-box; }
    body { margin: 0; background: #e9ecef; color: #212529; font-family: Arial, Helvetica, sans-serif; }
    .catalogo-pagina {
      width: ${PAGE_WIDTH_PX}px;
      height: ${PAGE_HEIGHT_PX}px;
      margin: 16px auto;
      background: #fff;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }
    .catalogo-pagina-corpo {
      height: ${PAGE_HEIGHT_PX - PAGE_FOOTER_PX}px;
      padding: ${PAGE_PADDING_TOP_PX}px 32px 0;
      overflow: hidden;
    }
    .catalogo-pagina-piede {
      height: ${PAGE_FOOTER_PX}px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      color: #6c757d;
      border-top: 1px solid #eee;
    }
    .catalogo-salto {
      width: ${PAGE_WIDTH_PX}px;
      margin: 4px auto;
      text-align: center;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #9a3412;
    }
    .catalogo-sezione-bar {
      display: flex;
      align-items: center;
      margin: 0 0 8px;
      padding: 0 10px;
      font-weight: 700;
      background: ${background};
      color: ${color};
    }
    .catalogo-articolo { overflow: hidden; }
    .catalogo-vuoto { margin: 0; color: #6c757d; }
    .fantini-catalogo-riga-articolo,
    .fantini-catalogo-riga-articolo-variazioni { display: flex; width: 100%; font-size: 12px; line-height: 1.35; }
    .fantini-catalogo-articolo-col { padding: 3px 6px; overflow: hidden; }
    .fantini-catalogo-riga-articolo.titolo,
    .fantini-catalogo-riga-articolo-variazioni.titolo,
    .fantini-catalogo-articolo-col.titolo { font-weight: 700; background: #f4f4f4; }
    .fant-col-20 { flex: 0 0 20%; }
    .fant-col-15 { flex: 0 0 15%; }
    .fant-col-30 { flex: 0 0 30%; }
    .fant-col-10 { flex: 0 0 10%; }
    .fant-col-5 { flex: 0 0 5%; }
    .fant-col-48 { flex: 0 0 48%; }
    .fant-col-0 { flex: 0 0 0; width: 0; padding: 0; }
    .catalogo-foto-ph, .wrap-img img { display: block; width: 64px; height: 64px; object-fit: contain; background: #f1f3f5; border: 1px solid #dee2e6; }
    ${articoloSempliceCss()}
    @media print {
      body { background: #fff; }
      .catalogo-salto { display: none; }
      .catalogo-pagina { margin: 0; box-shadow: none; break-after: page; page-break-after: always; }
      .catalogo-pagina:last-child { break-after: auto; page-break-after: auto; }
    }
    @page { size: ${PAGE_WIDTH_PX}px ${PAGE_HEIGHT_PX}px; margin: 0; }
  `;
}
