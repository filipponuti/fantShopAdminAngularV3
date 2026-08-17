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

export interface CatalogSettings {
  sezione: CatalogSezioneSettings;
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
}
