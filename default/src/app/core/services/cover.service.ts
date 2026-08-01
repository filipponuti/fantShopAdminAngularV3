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
  private readonly apiUrl = `${environment.siteUrl}/wp-json/${environment.apiNamespace}/covers`;

  constructor(private readonly http: HttpClient) {}

  list(): Observable<CoverSummary[]> {
    return this.http.get<CoverSummary[]>(this.apiUrl);
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

  uploadAttachments(code: string, files: File[]): Observable<CoverSummary> {
    const formData = new FormData();
    files.forEach(file => formData.append('files[]', file, file.name));
    return this.http.post<CoverSummary>(`${this.apiUrl}/${encodeURIComponent(code)}/attachments`, formData);
  }

  downloadPdf(code: string): Observable<CoverPdf> {
    return this.http.get<CoverPdf>(`${this.apiUrl}/${encodeURIComponent(code)}/pdf`);
  }
}
