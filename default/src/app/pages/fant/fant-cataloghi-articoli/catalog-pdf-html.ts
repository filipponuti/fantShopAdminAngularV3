/** Altezza foglio A4 a 96dpi, piede escluso dal flusso articoli. */
const PAGE_WIDTH_PX = 794;
const PAGE_HEIGHT_PX = 1123;
const PAGE_FOOTER_PX = 36;
const PAGE_PADDING_TOP_PX = 28;
const PAGE_CONTENT_PX = PAGE_HEIGHT_PX - PAGE_FOOTER_PX - PAGE_PADDING_TOP_PX;

export interface CatalogPdfArticle {
  sku: string;
  nome: string;
  saltoPagina: boolean;
  layout: string;
  height: number;
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

  const flush = (nextLabel: string): void => {
    if (!chunks.length) {
      return;
    }
    pages.push({ breakLabel, html: chunks.join('') });
    chunks = [];
    used = 0;
    breakLabel = nextLabel;
    sectionOnPage = false;
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
    if (used > 0 && used + sectionHeight > PAGE_CONTENT_PX) {
      flush('Pagina successiva');
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
      if (articolo.saltoPagina && used > 0) {
        flush('Salto pagina');
      }
      const incoming = (sectionOnPage ? 0 : sectionHeight) + Math.min(height, PAGE_CONTENT_PX);
      if (used > 0 && used + incoming > PAGE_CONTENT_PX) {
        flush('Pagina successiva');
      }
      placeSection(section);
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

function articleBlock(layoutHtml: string, sezione: string, articolo: CatalogPdfArticle, height: number): string {
  const filled = fillTemplate(layoutHtml, {
    SEZIONE: sezione,
    SKU: articolo.sku,
    NOME: articolo.nome,
    FOTO: '',
    VAR_SKU: articolo.sku,
    VAR_PREZZO: '',
    VAR_MISURE: '',
    VAR_COLORE: '',
    VAR_IVA: '',
  });
  const body = filled.replace(/<div class="fantini-catalogo-sezione-titolo">[\s\S]*?<\/div>/gi, '');
  const withoutEmptyImage = body.replace(
    /<img\b[^>]*src=(?:"\s*"|'\s*'|\s*)[^>]*>/gi,
    '<span class="catalogo-foto-ph"></span>',
  );
  return `<div class="catalogo-articolo" style="height:${Math.round(height)}px">${withoutEmptyImage}</div>`;
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
    @media print {
      body { background: #fff; }
      .catalogo-salto { display: none; }
      .catalogo-pagina { margin: 0; box-shadow: none; break-after: page; page-break-after: always; }
      .catalogo-pagina:last-child { break-after: auto; page-break-after: auto; }
    }
    @page { size: ${PAGE_WIDTH_PX}px ${PAGE_HEIGHT_PX}px; margin: 0; }
  `;
}
