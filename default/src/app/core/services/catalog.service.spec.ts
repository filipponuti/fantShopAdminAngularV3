import { HttpClientTestingModule, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { environment } from '../../../environments/environment';
import { CatalogDetail, CatalogService } from './catalog.service';

describe('CatalogService', () => {
  let service: CatalogService;
  let http: HttpTestingController;
  const apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}/catalogs`;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
    });
    service = TestBed.inject(CatalogService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
  });

  it('get legge /catalogs/{code}', () => {
    service.get('demo').subscribe();

    const req = http.expectOne(`${apiUrl}/demo`);
    expect(req.request.method).toBe('GET');
    req.flush({
      testata: { codice: 'demo', nome: 'Demo', createdAt: '', updatedAt: '' },
      prodotti: [],
    });
  });

  it('get normalizza prodotti mancanti a un array vuoto', () => {
    let result: CatalogDetail | undefined;
    service.get('demo').subscribe((detail) => {
      result = detail;
    });

    const req = http.expectOne(`${apiUrl}/demo`);
    req.flush({
      testata: { codice: 'demo', nome: 'Demo', createdAt: '', updatedAt: '' },
    });

    expect(result?.prodotti).toEqual([]);
  });

  it('get normalizza prodotti non array a un array vuoto', () => {
    let result: CatalogDetail | undefined;
    service.get('demo').subscribe((detail) => {
      result = detail;
    });

    const req = http.expectOne(`${apiUrl}/demo`);
    req.flush({
      testata: { codice: 'demo', nome: 'Demo', createdAt: '', updatedAt: '' },
      prodotti: null,
    });

    expect(result?.prodotti).toEqual([]);
  });

  it('updateContenuto fa PUT /contenuto con i prodotti', () => {
    const prodotti = [
      {
        categoryId: 197,
        nome: 'Test',
        saltoPagina: false,
        articoli: [],
      },
    ];
    service.updateContenuto('demo', prodotti).subscribe();

    const req = http.expectOne(`${apiUrl}/demo/contenuto`);
    expect(req.request.method).toBe('PUT');
    expect(req.request.body).toEqual({ prodotti });
    req.flush({
      codice: 'demo',
      nome: 'Demo',
      numeroProdotti: 0,
      createdAt: '',
      updatedAt: '',
    });
  });
});
