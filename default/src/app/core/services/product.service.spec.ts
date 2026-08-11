import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { environment } from '../../../environments/environment';
import { ProductService } from './product.service';

describe('ProductService', () => {
  let service: ProductService;
  let http: HttpTestingController;
  const apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}/products`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
    });
    service = TestBed.inject(ProductService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
  });

  it('search chiama /products con i parametri richiesti', () => {
    service.search('abc', 2, 10).subscribe();

    const req = http.expectOne(
      (request) =>
        request.url === apiUrl &&
        request.params.get('search') === 'abc' &&
        request.params.get('page') === '2' &&
        request.params.get('perPage') === '10',
    );
    expect(req.request.method).toBe('GET');
    req.flush({ items: [], page: 2, perPage: 10, total: 0 });
  });

  it('byCategory passa categoryId e includeChildren', () => {
    service.byCategory(197, true).subscribe();

    const req = http.expectOne(
      (request) => request.url === apiUrl && request.params.get('categoryId') === '197',
    );
    expect(req.request.method).toBe('GET');
    expect(req.request.params.get('includeChildren')).toBe('1');
    req.flush([]);
  });
});
