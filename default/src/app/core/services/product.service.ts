import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { forkJoin, Observable, of } from 'rxjs';
import { map } from 'rxjs/operators';

import { environment } from '../../../environments/environment';

export interface ProductItem {
  id: number;
  sku: string;
  name: string;
  code: string;
  type?: string;
  image?: string;
  price?: string;
  misure?: string;
  colore?: string;
  iva?: string;
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

  byIds(ids: number[]): Observable<ProductItem[]> {
    const unique = [...new Set(ids.filter(id => id > 0))];
    if (!unique.length) {
      return of([]);
    }
    const chunks: number[][] = [];
    for (let index = 0; index < unique.length; index += 200) {
      chunks.push(unique.slice(index, index + 200));
    }
    return forkJoin(chunks.map(chunk => {
      const params = new HttpParams().set('ids', chunk.join(','));
      return this.http.get<ProductItem[]>(this.apiUrl, { params });
    })).pipe(map(groups => groups.flat()));
  }

  byCategory(categoryId: number, includeChildren = true): Observable<ProductItem[]> {
    const params = new HttpParams()
      .set('categoryId', String(categoryId))
      .set('includeChildren', includeChildren ? '1' : '0');

    return this.http.get<ProductItem[]>(this.apiUrl, { params });
  }
}
