import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

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
  private readonly apiUrl = `${environment.siteUrl}/wp-json/${environment.apiNamespace}/catalogs`;

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

  delete(code: string): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${encodeURIComponent(code)}`);
  }
}
