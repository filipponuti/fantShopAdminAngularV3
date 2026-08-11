import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

export interface ProductItem {
  id: number;
  sku: string;
  name: string;
  code: string;
  type?: string;
  variazioni?: ProductItem[];
}

export interface ProductSearchResult {
  items: ProductItem[];
  page: number;
  perPage: number;
  total: number;
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}/products`;

  constructor(private readonly http: HttpClient) {}

  search(search: string, page = 1, perPage = 20): Observable<ProductSearchResult> {
    const params = new HttpParams()
      .set('search', search)
      .set('page', String(page))
      .set('perPage', String(perPage));

    return this.http.get<ProductSearchResult>(this.apiUrl, { params });
  }

  byCategory(categoryId: number, includeChildren = true): Observable<ProductItem[]> {
    const params = new HttpParams()
      .set('categoryId', String(categoryId))
      .set('includeChildren', includeChildren ? '1' : '0');

    return this.http.get<ProductItem[]>(this.apiUrl, { params });
  }
}
