import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

export interface LayoutSummary {
  codice: string;
  nome: string;
  family: string;
  showVariationImages: boolean;
  bancali: boolean;
  updatedAt: string;
}

export interface LayoutDetail {
  schemaVersion?: number;
  testata: {
    codice: string;
    nome: string;
    createdAt: string;
    updatedAt: string;
  };
  layout: {
    family: string;
    showVariationImages: boolean;
    bancali: boolean;
    cssClass?: string;
    labels?: {
      confezione?: string;
      set?: string;
    };
    htmlPreview?: string | null;
    notes?: string;
  };
}

export interface LayoutUpdatePayload {
  htmlPreview?: string | null;
  notes?: string;
}

@Injectable({ providedIn: 'root' })
export class LayoutService {
  private readonly apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}/layouts`;

  constructor(private readonly http: HttpClient) {}

  list(): Observable<LayoutSummary[]> {
    return this.http.get<LayoutSummary[]>(this.apiUrl);
  }

  get(code: string): Observable<LayoutDetail> {
    return this.http.get<LayoutDetail>(`${this.apiUrl}/${encodeURIComponent(code)}`);
  }

  update(code: string, payload: LayoutUpdatePayload): Observable<LayoutDetail> {
    return this.http.put<LayoutDetail>(`${this.apiUrl}/${encodeURIComponent(code)}`, payload);
  }
}
