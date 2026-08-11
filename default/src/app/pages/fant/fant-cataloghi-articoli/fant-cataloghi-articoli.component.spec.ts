import { fakeAsync, tick } from '@angular/core/testing';
import { Subject, of, throwError } from 'rxjs';

import { CatalogDetail, CatalogService } from '../../../core/services/catalog.service';
import { ProductService } from '../../../core/services/product.service';
import { WooCategory, WooCategoryService } from '../../../core/services/woo-category.service';
import { FantCataloghiArticoliComponent } from './fant-cataloghi-articoli.component';

describe('FantCataloghiArticoliComponent', () => {
  const categories: WooCategory[] = [
    {
      id: 20,
      name: 'Figlia',
      slug: 'figlia',
      description: '',
      parent: 10,
      display: '',
      image: null,
      count: 2,
      menuOrder: 0,
    },
    {
      id: 10,
      name: 'Radice',
      slug: 'radice',
      description: '',
      parent: 0,
      display: '',
      image: null,
      count: 3,
      menuOrder: 0,
    },
  ];

  const catalog: CatalogDetail = {
    schemaVersion: 2,
    testata: {
      codice: 'demo',
      nome: 'Catalogo Demo',
      createdAt: '2026-08-10T10:00:00Z',
      updatedAt: '2026-08-10T11:00:00Z',
    },
    prodotti: [
      {
        categoryId: 197,
        nome: 'Categoria',
        saltoPagina: false,
        articoli: [],
      },
    ],
  };

  it('carica nome e sezioni usando il codice della rotta', () => {
    const route = {
      snapshot: { paramMap: { get: () => 'demo' } },
    };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get']);
    catalogs.get.and.returnValue(of(catalog));

    const products = jasmine.createSpyObj<ProductService>('ProductService', ['byCategory']);
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    categoryService.list.and.returnValue(of(categories));
    const component = new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogs,
      products,
      categoryService,
      jasmine.createSpyObj('NgbModal', ['open']),
    );
    component.ngOnInit();

    expect(catalogs.get).toHaveBeenCalledOnceWith('demo');
    expect(categoryService.list).toHaveBeenCalledTimes(1);
    expect(component.codice).toBe('demo');
    expect(component.nome).toBe('Catalogo Demo');
    expect(component.sezioni).toEqual(catalog.prodotti);
    expect(component.categoryTree[0].children[0].id).toBe(20);
    expect(component.loading).toBeFalse();
    expect(component.loadingCategories).toBeFalse();
  });

  it('annulla tornando alla lista cataloghi', () => {
    const route = {
      snapshot: { paramMap: { get: () => 'demo' } },
    };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get']);
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['byCategory']);
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    const component = new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogs,
      products,
      categoryService,
      jasmine.createSpyObj('NgbModal', ['open']),
    );

    component.cancel();

    expect(router.navigate).toHaveBeenCalledOnceWith(['/fant-cataloghi']);
  });

  it('aggiunge la categoria con i prodotti delle sottocategorie e seleziona la nuova sezione', () => {
    const route = { snapshot: { paramMap: { get: () => 'demo' } } };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get']);
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['byCategory']);
    products.byCategory.and.returnValue(of([
      { id: 1, sku: 'SKU-1', name: 'Primo', code: 'SKU-1' },
      { id: 2, sku: '', name: 'Senza SKU', code: '' },
    ]));
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    const component = new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogs,
      products,
      categoryService,
      jasmine.createSpyObj('NgbModal', ['open']),
    );
    component.sezioni = [...catalog.prodotti];
    component.flatCategories = categories;
    component.selectedCategoryId = 10;
    component.success = 'Catalogo salvato.';

    component.addCategorySection();

    expect(products.byCategory).toHaveBeenCalledOnceWith(10, true);
    expect(component.sezioni[1]).toEqual({
      categoryId: 10,
      nome: 'Radice',
      saltoPagina: false,
      articoli: [
        { productId: 1, sku: 'SKU-1', nome: 'Primo', saltoPagina: false, variazioni: [] },
      ],
    });
    expect(component.selectedSectionIndex).toBe(1);
    expect(component.success).toBe('');
    expect(component.loadingProducts).toBeFalse();
  });

  it('mostra un errore e ripristina il loading se il caricamento prodotti fallisce', () => {
    const route = { snapshot: { paramMap: { get: () => 'demo' } } };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get']);
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['byCategory']);
    products.byCategory.and.returnValue(throwError(() => new Error('network')));
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    const component = new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogs,
      products,
      categoryService,
      jasmine.createSpyObj('NgbModal', ['open']),
    );
    component.flatCategories = categories;
    component.selectedCategoryId = 10;

    component.addCategorySection();

    expect(component.sezioni).toEqual([]);
    expect(component.error).toBe('Impossibile caricare i prodotti della categoria.');
    expect(component.loadingProducts).toBeFalse();
  });

  it('cerca gli articoli dopo 300ms usando solo il termine più recente', fakeAsync(() => {
    const route = { snapshot: { paramMap: { get: () => 'demo' } } };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get']);
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['search', 'byCategory']);
    products.search.and.returnValue(of({
      items: [{ id: 1, sku: 'SKU-1', name: 'Primo', code: 'SKU-1' }],
      page: 1,
      perPage: 30,
      total: 1,
    }));
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    const component = new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogs,
      products,
      categoryService,
      jasmine.createSpyObj('NgbModal', ['open']),
    );

    component.onSearchInput('pri');
    tick(200);
    component.onSearchInput('primo');
    tick(299);
    expect(products.search).not.toHaveBeenCalled();

    tick(1);

    expect(products.search).toHaveBeenCalledOnceWith('primo', 1, 30);
    expect(component.searchResults).toEqual([
      { id: 1, sku: 'SKU-1', name: 'Primo', code: 'SKU-1' },
    ]);
  }));

  it('annulla la ricerca in debounce quando il componente viene distrutto', fakeAsync(() => {
    const component = createComponent();
    const products = (component as any).products as jasmine.SpyObj<ProductService>;

    component.onSearchInput('T.PRI');
    component.ngOnDestroy();
    tick(300);

    expect(products.search).not.toHaveBeenCalled();
  }));

  it('blocca l’aggiunta di articoli quando non è selezionata una sezione', () => {
    const route = { snapshot: { paramMap: { get: () => 'demo' } } };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get']);
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['search', 'byCategory']);
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    const component = new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogs,
      products,
      categoryService,
      jasmine.createSpyObj('NgbModal', ['open']),
    );
    component.searchResults = [{ id: 1, sku: 'SKU-1', name: 'Primo', code: 'SKU-1' }];
    component.selectedProductIds.add(1);

    component.addSelectedProducts();

    expect(component.error).toBe('Seleziona una sezione nel riquadro di destra.');
    expect(component.selectedProductIds.has(1)).toBeTrue();
  });

  it('aggiunge solo SKU non vuoti e non duplicati nella sezione selezionata', () => {
    const route = { snapshot: { paramMap: { get: () => 'demo' } } };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get']);
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['search', 'byCategory']);
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    const component = new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogs,
      products,
      categoryService,
      jasmine.createSpyObj('NgbModal', ['open']),
    );
    component.sezioni = [{
      categoryId: 10,
      nome: 'Radice',
      saltoPagina: false,
      articoli: [
        { productId: 9, sku: 'sku-1', nome: 'Esistente', saltoPagina: false },
      ],
    }];
    component.selectedSectionIndex = 0;
    component.searchResults = [
      { id: 1, sku: 'SKU-1', name: 'Duplicato', code: 'SKU-1' },
      { id: 2, sku: ' SKU-2 ', name: 'Secondo', code: 'SKU-2' },
      { id: 3, sku: '   ', name: 'Senza SKU', code: '' },
      { id: 4, sku: 'SKU-3', name: 'Non selezionato', code: 'SKU-3' },
    ];
    component.selectedProductIds = new Set([1, 2, 3]);
    component.success = 'Catalogo salvato.';

    component.addSelectedProducts();

    expect(component.sezioni[0].articoli).toEqual([
      { productId: 9, sku: 'sku-1', nome: 'Esistente', saltoPagina: false },
      { productId: 2, sku: 'SKU-2', nome: 'Secondo', saltoPagina: false, variazioni: [] },
    ]);
    expect(component.selectedProductIds.size).toBe(0);
    expect(component.error).toBe('');
    expect(component.success).toBe('');
  });

  it('attiva e disattiva il salto pagina di sezioni e articoli', () => {
    const component = createComponent();
    component.sezioni = twoSections();

    component.toggleSectionSalto(0);
    component.toggleArticoloSalto(0, 0);

    expect(component.sezioni[0].saltoPagina).toBeTrue();
    expect(component.sezioni[0].articoli[0].saltoPagina).toBeTrue();

    component.toggleSectionSalto(0);
    component.toggleArticoloSalto(0, 0);

    expect(component.sezioni[0].saltoPagina).toBeFalse();
    expect(component.sezioni[0].articoli[0].saltoPagina).toBeFalse();
  });

  it('seleziona una sezione da tastiera e impedisce lo scroll con Spazio', () => {
    const component = createComponent();
    const enterEvent = new KeyboardEvent('keydown', { key: 'Enter' });
    const spaceEvent = new KeyboardEvent('keydown', { key: ' ' });
    spyOn(spaceEvent, 'preventDefault');

    component.onSectionKeydown(enterEvent, 0);
    expect(component.selectedSectionIndex).toBe(0);

    component.onSectionKeydown(spaceEvent, 1);
    expect(component.selectedSectionIndex).toBe(1);
    expect(spaceEvent.preventDefault).toHaveBeenCalledTimes(1);
  });

  it('rimuove sezioni mantenendo coerente la selezione', () => {
    const component = createComponent();
    component.sezioni = twoSections();
    component.selectedSectionIndex = 1;

    component.removeSection(0);

    expect(component.sezioni.map(section => section.categoryId)).toEqual([20]);
    expect(component.selectedSectionIndex).toBe(0);

    component.removeSection(0);

    expect(component.sezioni).toEqual([]);
    expect(component.selectedSectionIndex).toBeNull();
  });

  it('rimuove un articolo dalla sezione indicata', () => {
    const component = createComponent();
    component.sezioni = twoSections();

    component.removeArticolo(0, 0);

    expect(component.sezioni[0].articoli).toEqual([]);
  });

  it('riordina sezioni e articoli con le frecce aggiornando la selezione', () => {
    const component = createComponent();
    component.sezioni = twoSections();
    component.selectedSectionIndex = 0;

    component.moveSection(0, 1);

    expect(component.sezioni.map(section => section.categoryId)).toEqual([20, 10]);
    expect(component.selectedSectionIndex).toBe(1);

    component.sezioni[1].articoli.push({
      productId: 2,
      sku: 'SKU-2',
      nome: 'Secondo',
      saltoPagina: false,
    });
    component.moveArticolo(1, 1, -1);

    expect(component.sezioni[1].articoli.map(item => item.productId)).toEqual([2, 1]);
  });

  it('riordina le sezioni via drag mantenendo selezionata la stessa sezione', () => {
    const component = createComponent();
    component.sezioni = [
      ...twoSections(),
      { categoryId: 30, nome: 'Tre', saltoPagina: false, articoli: [] },
    ];
    component.selectedSectionIndex = 1;

    component.dropSection({ previousIndex: 2, currentIndex: 0 } as any);

    expect(component.sezioni.map(section => section.categoryId)).toEqual([30, 10, 20]);
    expect(component.selectedSectionIndex).toBe(2);
  });

  it('riordina gli articoli via drag', () => {
    const component = createComponent();
    component.sezioni = twoSections();
    component.sezioni[0].articoli.push({
      productId: 2,
      sku: 'SKU-2',
      nome: 'Secondo',
      saltoPagina: false,
    });

    component.dropArticolo(0, { previousIndex: 0, currentIndex: 1 } as any);

    expect(component.sezioni[0].articoli.map(item => item.productId)).toEqual([2, 1]);
  });

  it('cancella il feedback di successo dopo ogni modifica al contenuto', () => {
    const component = createComponent();
    component.sezioni = twoSections();

    const expectSuccessCleared = (mutate: () => void): void => {
      component.success = 'Catalogo salvato.';
      mutate();
      expect(component.success).withContext('la modifica deve invalidare il feedback').toBe('');
    };

    expectSuccessCleared(() => component.toggleSectionSalto(0));
    expectSuccessCleared(() => component.toggleArticoloSalto(0, 0));
    expectSuccessCleared(() => component.moveSection(0, 1));
    component.sezioni[1].articoli.push({
      productId: 2,
      sku: 'SKU-2',
      nome: 'Secondo',
      saltoPagina: false,
    });
    expectSuccessCleared(() => component.moveArticolo(1, 0, 1));
    expectSuccessCleared(() => component.dropSection({ previousIndex: 0, currentIndex: 1 } as any));
    expectSuccessCleared(() => component.dropArticolo(
      0,
      { previousIndex: 0, currentIndex: 1 } as any,
    ));
    expectSuccessCleared(() => component.removeArticolo(0, 0));
    expectSuccessCleared(() => component.removeSection(0));
  });

  it('salva il contenuto restando nella pagina e mostra il feedback', () => {
    const saveResult = new Subject<any>();
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get', 'updateContenuto']);
    catalogs.updateContenuto.and.returnValue(saveResult);
    const component = createComponent(catalogs);
    component.codice = 'demo';
    component.sezioni = twoSections();
    component.error = 'Errore precedente';

    component.save();

    expect(catalogs.updateContenuto).toHaveBeenCalledOnceWith('demo', component.sezioni);
    expect(component.saving).toBeTrue();
    expect(component.error).toBe('');

    saveResult.next({});
    saveResult.complete();

    expect(component.saving).toBeFalse();
    expect(component.success).toBe('Catalogo salvato.');
  });

  it('mostra il messaggio API quando il salvataggio fallisce', () => {
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get', 'updateContenuto']);
    catalogs.updateContenuto.and.returnValue(throwError(() => ({
      error: { message: 'Contenuto non valido.' },
    })));
    const component = createComponent(catalogs);
    component.codice = 'demo';

    component.save();

    expect(component.saving).toBeFalse();
    expect(component.success).toBe('');
    expect(component.error).toBe('Contenuto non valido.');
  });

  it('non salva senza un codice catalogo valido', () => {
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get', 'updateContenuto']);
    const component = createComponent(catalogs);
    component.codice = '';

    component.save();

    expect(catalogs.updateContenuto).not.toHaveBeenCalled();
    expect(component.saving).toBeFalse();
    expect(component.error).toBe('Codice catalogo non valido.');
  });

  it('passa a Settings senza confirm se non ci sono modifiche', () => {
    const component = createComponent();
    component.activeView = 'sezioni';
    spyOn(window, 'confirm');

    component.selectMainView('settings');

    expect(window.confirm).not.toHaveBeenCalled();
    expect(component.activeView).toBe('settings');
  });

  it('chiede conferma se si lascia Sezioni con modifiche non salvate', () => {
    const component = createComponent();
    component.activeView = 'sezioni';
    component.sezioni = [...catalog.prodotti];
    component.toggleSectionSalto(0);
    spyOn(window, 'confirm').and.returnValue(false);

    component.selectMainView('pdf');

    expect(window.confirm).toHaveBeenCalled();
    expect(component.activeView).toBe('sezioni');
  });

  it('salva Settings senza chiamare updateContenuto se non dirty', () => {
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get', 'updateContenuto', 'updateSettings']);
    const component = createComponent(catalogs);
    component.activeView = 'settings';

    component.save();

    expect(catalogs.updateContenuto).not.toHaveBeenCalled();
    expect(catalogs.updateSettings).not.toHaveBeenCalled();
    expect(component.success).toBe('Nessuna modifica da salvare.');
  });

  it('salva Settings con updateSettings quando dirty', () => {
    const catalogs = jasmine.createSpyObj<CatalogService>('CatalogService', ['get', 'updateContenuto', 'updateSettings']);
    catalogs.updateSettings.and.returnValue(of({
      codice: 'demo',
      nome: 'Catalogo Demo',
      numeroProdotti: 0,
      createdAt: '',
      updatedAt: '',
    }));
    const component = createComponent(catalogs);
    component.activeView = 'settings';
    component.sezioneSettings = {
      backgroundColor: '#AABBCC',
      textColor: '#112233',
      fontSize: 16,
    };
    (component as any).settingsDirty = true;

    component.save();

    expect(catalogs.updateSettings).toHaveBeenCalledOnceWith('demo', {
      sezione: {
        backgroundColor: '#AABBCC',
        textColor: '#112233',
        fontSize: 16,
      },
    });
    expect(component.success).toBe('Settings salvate.');
  });

  it('aggiunge una sezione manuale vuota dal tab Sezione', () => {
    const component = createComponent();
    component.sezioni = [...catalog.prodotti];
    component.newSectionName = '  Manuale  ';
    component.success = 'ok';

    component.addManualSection();

    expect(component.sezioni[1]).toEqual({
      categoryId: 0,
      nome: 'Manuale',
      saltoPagina: false,
      articoli: [],
    });
    expect(component.selectedSectionIndex).toBe(1);
    expect(component.newSectionName).toBe('');
    expect(component.success).toBe('');
  });

  it('refresh senza differenze mostra messaggio di allineamento', () => {
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['search', 'byCategory']);
    products.byCategory.and.returnValue(of([
      { id: 1, sku: 'SKU-1', name: 'Primo', code: 'SKU-1', variazioni: [] },
    ]));
    const component = createComponent(undefined, products);
    component.sezioni = [{
      categoryId: 10,
      nome: 'Uno',
      saltoPagina: false,
      articoli: [{ productId: 1, sku: 'SKU-1', nome: 'Primo', saltoPagina: false, variazioni: [] }],
    }];
    spyOn(window, 'confirm');

    component.refreshSection(0);

    expect(products.byCategory).toHaveBeenCalledOnceWith(10, true);
    expect(window.confirm).not.toHaveBeenCalled();
    expect(component.success).toContain('nessun articolo nuovo');
  });

  it('refresh con differenze chiede conferma e apre la modale', () => {
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['search', 'byCategory']);
    products.byCategory.and.returnValue(of([
      { id: 1, sku: 'SKU-1', name: 'Primo', code: 'SKU-1', variazioni: [] },
      { id: 2, sku: 'SKU-2', name: 'Secondo', code: 'SKU-2', variazioni: [] },
    ]));
    const modal = jasmine.createSpyObj('NgbModal', ['open']);
    modal.open.and.returnValue({ dismiss: jasmine.createSpy('dismiss') });
    const component = createComponent(undefined, products, modal);
    component.sezioni = [{
      categoryId: 10,
      nome: 'Uno',
      saltoPagina: false,
      articoli: [{ productId: 1, sku: 'SKU-1', nome: 'Primo', saltoPagina: false }],
    }];
    component.missingProductsModal = {} as any;
    spyOn(window, 'confirm').and.returnValue(true);

    component.refreshSection(0);

    expect(window.confirm).toHaveBeenCalled();
    expect(modal.open).toHaveBeenCalled();
    expect(component.missingCandidates.map(item => item.productId)).toEqual([2]);
  });

  function createComponent(
    catalogs?: jasmine.SpyObj<CatalogService>,
    products?: jasmine.SpyObj<ProductService>,
    modal?: jasmine.SpyObj<any>,
  ): FantCataloghiArticoliComponent {
    const route = { snapshot: { paramMap: { get: () => 'demo' } } };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogService = catalogs
      ?? jasmine.createSpyObj<CatalogService>('CatalogService', ['get', 'updateContenuto', 'updateSettings']);
    const productService = products
      ?? jasmine.createSpyObj<ProductService>('ProductService', ['search', 'byCategory']);
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    const modalService = modal ?? jasmine.createSpyObj('NgbModal', ['open']);
    return new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogService,
      productService,
      categoryService,
      modalService,
    );
  }

  function twoSections() {
    return [
      {
        categoryId: 10,
        nome: 'Uno',
        saltoPagina: false,
        articoli: [
          { productId: 1, sku: 'SKU-1', nome: 'Primo', saltoPagina: false },
        ],
      },
      {
        categoryId: 20,
        nome: 'Due',
        saltoPagina: false,
        articoli: [],
      },
    ];
  }
});
