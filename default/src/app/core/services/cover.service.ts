import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

export interface CoverSummary {
  codice: string;
  nome: string;
  numeroAllegati: number;
  pdfNome: string;
  createdAt: string;
  updatedAt: string;
}

export interface CoverAllegato {
  nome: string;
  alias: string;
  percorso: string;
  tipo: string;
  dimensione: number;
  uploadedAt: string;
}

export interface CoverArticoloProduct {
  id: string;
  tipo: 'product';
  productId: number;
  sku: string;
  nome: string;
  alias: string;
}

export interface CoverArticoloCategory {
  id: string;
  tipo: 'category';
  categoryId: number;
  includeChildren: boolean;
  nome: string;
  alias: string;
}

export type CoverArticolo = CoverArticoloProduct | CoverArticoloCategory;

export interface CoverChat {
  messages: Array<{
    id?: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    createdAt?: string;
  }>;
  previewHtml?: string | null;
}

export interface CoverDetail {
  schemaVersion?: number;
  testata: {
    codice: string;
    nome: string;
    createdAt: string;
    updatedAt: string;
  };
  pdf: {
    nome: string;
    generatedAt?: string;
  };
  allegati: CoverAllegato[];
  articoli: CoverArticolo[];
  chat?: CoverChat;
}

export interface CoverPayload {
  codice?: string;
  nome: string;
  nomeFilePdf: string;
}

interface CoverPdf {
  filename: string;
  mimeType: string;
  contentBase64: string;
}

@Injectable({ providedIn: 'root' })
export class CoverService {
  private readonly apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}/covers`;

  constructor(private readonly http: HttpClient) {}

  list(): Observable<CoverSummary[]> {
    return this.http.get<CoverSummary[]>(this.apiUrl);
  }

  get(code: string): Observable<CoverDetail> {
    return this.http.get<CoverDetail>(`${this.apiUrl}/${encodeURIComponent(code)}`);
  }

  create(payload: Required<CoverPayload>): Observable<CoverSummary> {
    return this.http.post<CoverSummary>(this.apiUrl, payload);
  }

  update(code: string, payload: CoverPayload): Observable<CoverSummary> {
    return this.http.put<CoverSummary>(`${this.apiUrl}/${encodeURIComponent(code)}`, payload);
  }

  delete(code: string): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${encodeURIComponent(code)}`);
  }

  uploadAttachments(code: string, files: File[]): Observable<CoverDetail> {
    const formData = new FormData();
    files.forEach(file => formData.append('files[]', file, file.name));
    return this.http.post<CoverDetail>(`${this.apiUrl}/${encodeURIComponent(code)}/attachments`, formData);
  }

  updateAttachmentAlias(code: string, fileName: string, alias: string): Observable<CoverDetail> {
    return this.http.put<CoverDetail>(
      `${this.apiUrl}/${encodeURIComponent(code)}/attachments/${encodeURIComponent(fileName)}`,
      { alias },
    );
  }

  deleteAttachment(code: string, fileName: string): Observable<CoverDetail> {
    return this.http.delete<CoverDetail>(
      `${this.apiUrl}/${encodeURIComponent(code)}/attachments/${encodeURIComponent(fileName)}`,
    );
  }

  updateArticoli(code: string, articoli: CoverArticolo[]): Observable<CoverDetail> {
    return this.http.put<CoverDetail>(
      `${this.apiUrl}/${encodeURIComponent(code)}/articoli`,
      { articoli },
    );
  }

  downloadPdf(code: string): Observable<CoverPdf> {
    return this.http.get<CoverPdf>(`${this.apiUrl}/${encodeURIComponent(code)}/pdf`);
  }
}
