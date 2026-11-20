import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import { environment } from '../../../environments/environment';

export interface CatalogArticolo {
  productId: number;
  sku: string;
  nome: string;
  saltoPagina: boolean;
  /** Codice layout in fant-layout-articoli, ad esempio articolo-semplice. */
  layout?: string;
  variazioni?: CatalogArticolo[];
}

export interface CatalogSezione {
  categoryId: number;
  nome: string;
  saltoPagina: boolean;
  articoli: CatalogArticolo[];
}

export interface CatalogSezioneSettings {
  backgroundColor: string;
  textColor: string;
  fontSize: number;
}

export interface CatalogFilesAggiuntivi {
  copertina: string;
  retro: string;
}

export interface CatalogSettings {
  sezione: CatalogSezioneSettings;
  filesAggiuntivi?: CatalogFilesAggiuntivi;
}

export interface CatalogAnteprimaSezione {
  nome: string;
  posizione: number;
}

export interface CatalogAnteprima {
  id: string;
  nome: string;
  sezioni: CatalogAnteprimaSezione[];
  generato: boolean;
  fileHtml: string;
  pathHtml: string;
  filePdf: string;
  pathPdf: string;
}

export interface CatalogAnteprimaState {
  anteprime: CatalogAnteprima[];
  anteprimaCorrente: string;
}

export interface CatalogPdfRecord {
  id: string;
  nome: string;
  anteprimaId: string;
  generato: boolean;
  filePdf: string;
  pathPdf: string;
  usaCopertina: boolean;
  usaRetro: boolean;
}

export interface CatalogPdfUso {
  usaCopertina: boolean;
  usaRetro: boolean;
}

export interface CatalogPdfState {
  pdfs: CatalogPdfRecord[];
  pdfCorrente: string;
}

export interface CatalogDetail {
  schemaVersion?: number;
  testata: {
    codice: string;
    nome: string;
    createdAt: string;
    updatedAt: string;
  };
  settings?: CatalogSettings;
  prodotti: CatalogSezione[];
  anteprime?: CatalogAnteprima[];
  anteprimaCorrente?: string;
  pdfs?: CatalogPdfRecord[];
  pdfCorrente?: string;
  pdf?: {
    filename: string;
    path: string;
  };
}

export interface CatalogPdf {
  filename: string;
  path: string;
  mimeType: string;
  contentBase64: string;
}

export interface CatalogAnteprimaFile extends Partial<CatalogAnteprimaState> {
  id?: string;
  filename: string;
  path: string;
  html: string;
}

export interface CatalogSummary {
  codice: string;
  nome: string;
  numeroProdotti: number;
  createdAt: string;
  updatedAt: string;
}

export interface CatalogPayload {
  codice?: string;
  nome: string;
}

@Injectable({ providedIn: 'root' })
export class CatalogService {
  private readonly apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}/catalogs`;

  constructor(private readonly http: HttpClient) {}

  list(): Observable<CatalogSummary[]> {
    return this.http.get<CatalogSummary[]>(this.apiUrl);
  }

  create(payload: Required<CatalogPayload>): Observable<CatalogSummary> {
    return this.http.post<CatalogSummary>(this.apiUrl, payload);
  }

  update(code: string, payload: CatalogPayload): Observable<CatalogSummary> {
    return this.http.put<CatalogSummary>(`${this.apiUrl}/${encodeURIComponent(code)}`, payload);
  }

  get(code: string): Observable<CatalogDetail> {
    return this.http
      .get<CatalogDetail>(`${this.apiUrl}/${encodeURIComponent(code)}`)
      .pipe(map((catalog) => ({ ...catalog, prodotti: Array.isArray(catalog.prodotti) ? catalog.prodotti : [] })));
  }

  updateContenuto(code: string, prodotti: CatalogSezione[]): Observable<CatalogSummary> {
    return this.http.put<CatalogSummary>(
      `${this.apiUrl}/${encodeURIComponent(code)}/contenuto`,
      { prodotti },
    );
  }

  updateSettings(code: string, settings: CatalogSettings): Observable<CatalogSummary> {
    return this.http.put<CatalogSummary>(
      `${this.apiUrl}/${encodeURIComponent(code)}/settings`,
      settings,
    );
  }

  delete(code: string): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${encodeURIComponent(code)}`);
  }

  createAnteprima(
    code: string,
    nome: string,
    sezioni: CatalogAnteprimaSezione[],
  ): Observable<CatalogAnteprimaState> {
    return this.http.post<CatalogAnteprimaState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/anteprime`,
      { nome, sezioni },
    );
  }

  updateAnteprima(
    code: string,
    id: string,
    nome: string,
    sezioni: CatalogAnteprimaSezione[],
  ): Observable<CatalogAnteprimaState> {
    return this.http.put<CatalogAnteprimaState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/anteprime/${encodeURIComponent(id)}`,
      { nome, sezioni },
    );
  }

  deleteAnteprimaRecord(code: string, id: string): Observable<CatalogAnteprimaState> {
    return this.http.delete<CatalogAnteprimaState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/anteprime/${encodeURIComponent(id)}`,
    );
  }

  loadAnteprima(code: string, id: string): Observable<CatalogAnteprimaState> {
    return this.http.post<CatalogAnteprimaState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/anteprime/${encodeURIComponent(id)}/carica`,
      {},
    );
  }

  getAnteprima(code: string): Observable<CatalogAnteprimaFile> {
    return this.http.get<CatalogAnteprimaFile>(`${this.apiUrl}/${encodeURIComponent(code)}/anteprima`);
  }

  saveAnteprima(code: string, html: string, id = '', makeCurrent = true): Observable<CatalogAnteprimaFile> {
    return this.http.post<CatalogAnteprimaFile>(
      `${this.apiUrl}/${encodeURIComponent(code)}/anteprima`,
      { html, id, makeCurrent },
    );
  }

  createPdf(code: string, nome: string, anteprimaId: string): Observable<CatalogPdfState> {
    return this.http.post<CatalogPdfState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/pdfs`,
      { nome, anteprimaId },
    );
  }

  updatePdf(code: string, id: string, nome: string, anteprimaId: string): Observable<CatalogPdfState> {
    return this.http.put<CatalogPdfState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/pdfs/${encodeURIComponent(id)}`,
      { nome, anteprimaId },
    );
  }

  updatePdfUso(code: string, id: string, uso: CatalogPdfUso): Observable<CatalogPdfState> {
    return this.http.put<CatalogPdfState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/pdfs/${encodeURIComponent(id)}/uso`,
      uso,
    );
  }

  deletePdfRecord(code: string, id: string): Observable<CatalogPdfState> {
    return this.http.delete<CatalogPdfState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/pdfs/${encodeURIComponent(id)}`,
    );
  }

  loadPdf(code: string, id: string): Observable<CatalogPdfState> {
    return this.http.post<CatalogPdfState>(
      `${this.apiUrl}/${encodeURIComponent(code)}/pdfs/${encodeURIComponent(id)}/carica`,
      {},
    );
  }

  deleteAnteprima(code: string): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${encodeURIComponent(code)}/anteprima`);
  }

  getPdf(code: string): Observable<CatalogPdf> {
    return this.http.get<CatalogPdf>(`${this.apiUrl}/${encodeURIComponent(code)}/pdf`);
  }

  generatePdf(code: string, uso?: CatalogPdfUso): Observable<CatalogPdf> {
    return this.http.post<CatalogPdf>(`${this.apiUrl}/${encodeURIComponent(code)}/pdf`, uso ?? {});
  }
}
