import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

export interface WooCategory {
  id: number;
  name: string;
  slug: string;
  description: string;
  parent: number;
  display: string;
  image: { id: number; src: string; alt: string } | null;
  count: number;
  menuOrder: number;
}

export interface WooCategoryPayload {
  name: string;
  slug?: string;
  description?: string;
  parent?: number;
  display?: string;
}

export interface WooCategoryOrderItem {
  id: number;
  parent: number;
  menuOrder: number;
}

@Injectable({ providedIn: 'root' })
export class WooCategoryService {
  private readonly apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}/categories`;

  constructor(private readonly http: HttpClient) {}

  list(): Observable<WooCategory[]> {
    return this.http.get<WooCategory[]>(this.apiUrl);
  }

  create(payload: WooCategoryPayload): Observable<WooCategory> {
    return this.http.post<WooCategory>(this.apiUrl, payload);
  }

  update(id: number, payload: WooCategoryPayload): Observable<WooCategory> {
    return this.http.put<WooCategory>(`${this.apiUrl}/${id}`, payload);
  }

  delete(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }

  reorder(items: WooCategoryOrderItem[]): Observable<WooCategory[]> {
    return this.http.put<WooCategory[]>(`${this.apiUrl}/reorder`, { items });
  }
}

