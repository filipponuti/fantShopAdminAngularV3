# Task 7
=== fant-cataloghi-articoli.component.html ===
<app-breadcrumbs
  [title]="'Articoli per il catalogo: ' + nome"
  [breadcrumbItems]="breadCrumbItems">
</app-breadcrumbs>

@if (error) {
  <div class="alert alert-danger" role="alert">{{ error }}</div>
}
@if (success) {
  <div class="alert alert-success" role="status">{{ success }}</div>
}

@if (loading) {
  <div class="card">
    <div class="card-body text-center py-5">
      <span class="spinner-border text-primary" role="status"></span>
      <p class="text-muted mt-3 mb-0">Caricamento catalogo...</p>
    </div>
  </div>
} @else {
  <div class="editor-layout">
    <aside class="editor-sidebar card">
      <div class="card-header">
        <h5 class="card-title mb-0">Aggiungi</h5>
      </div>
      <div class="card-body">
        <div class="btn-group w-100 mb-3" role="group" aria-label="Tipo elemento da aggiungere">
          <button
            type="button"
            class="btn"
            [class.btn-primary]="addTab === 'categoria'"
            [class.btn-light]="addTab !== 'categoria'"
            (click)="addTab = 'categoria'">
            Categoria
          </button>
          <button
            type="button"
            class="btn"
            [class.btn-primary]="addTab === 'articolo'"
            [class.btn-light]="addTab !== 'articolo'"
            (click)="addTab = 'articolo'">
            Articolo
          </button>
        </div>
        @if (addTab === 'categoria') {
          @if (loadingCategories) {
            <div class="category-status text-center text-muted">
              <span class="spinner-border spinner-border-sm me-2" role="status"></span>
              Caricamento categorie...
            </div>
          } @else if (categoryTree.length) {
            <div class="category-tree" role="tree" aria-label="Categorie prodotti">
              <ng-container
                *ngTemplateOutlet="categoryNodes; context: { $implicit: categoryTree }">
              </ng-container>
            </div>
          } @else {
            <p class="text-muted mb-0">Nessuna categoria disponibile.</p>
          }

          <button
            type="button"
            class="btn btn-primary w-100 mt-3"
            [disabled]="loadingCategories || loadingProducts || !selectedCategoryId"
            (click)="addCategorySection()">
            @if (loadingProducts) {
              <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            }
            Aggiungi
          </button>
        } @else {
          <label for="product-search" class="form-label">Cerca articolo</label>
          <input
            id="product-search"
            type="search"
            class="form-control"
            placeholder="SKU o nome"
            [value]="searchTerm"
            (input)="onSearchInput($any($event.target).value)">

          @if (searchingProducts) {
            <div class="text-center text-muted py-3">
              <span class="spinner-border spinner-border-sm me-2" role="status"></span>
              Ricerca in corso...
            </div>
          } @else if (searchResults.length) {
            <div class="list-group mt-3" aria-label="Risultati ricerca articoli">
              @for (product of searchResults; track product.id) {
                <label class="list-group-item d-flex gap-2 align-items-start">
                  <input
                    type="checkbox"
                    class="form-check-input mt-1"
                    [checked]="selectedProductIds.has(product.id)"
                    (change)="toggleProduct(product.id)">
                  <span>
                    <strong>{{ product.sku || 'Senza SKU' }}</strong>
                    <span class="d-block text-muted">{{ product.name }}</span>
                  </span>
                </label>
              }
            </div>
          } @else if (searchTerm) {
            <p class="text-muted mt-3 mb-0">Nessun articolo trovato.</p>
          }

          <button
            type="button"
            class="btn btn-primary w-100 mt-3"
            [disabled]="searchingProducts || selectedProductIds.size === 0"
            (click)="addSelectedProducts()">
            Aggiungi
          </button>
        }
      </div>
    </aside>

    <main class="editor-main card">
      <div class="card-header">
        <h5 class="card-title mb-0">Sezioni e articoli</h5>
      </div>
      <div class="card-body">
        @if (sezioni.length) {
          <div
            class="section-list"
            cdkDropList
            [cdkDropListData]="sezioni"
            (cdkDropListDropped)="dropSection($event)">
            @for (section of sezioni; track section; let sectionIndex = $index) {
              <section
                class="section-item"
                [class.selected]="selectedSectionIndex === sectionIndex"
                cdkDrag
                [cdkDragData]="section"
                (click)="selectSection(sectionIndex)">
                <div class="section-header">
                  <button
                    type="button"
                    class="drag-handle btn btn-sm btn-ghost-secondary"
                    cdkDragHandle
                    [attr.aria-label]="'Trascina sezione ' + section.nome"
                    title="Trascina sezione"
                    (click)="$event.stopPropagation()">
                    <i class="ri-drag-move-2-line"></i>
                  </button>
                  <div class="section-heading">
                    <span class="section-name">{{ section.nome }}</span>
                    <span class="section-count">
                      {{ section.articoli.length }}
                      {{ section.articoli.length === 1 ? 'articolo' : 'articoli' }}
                    </span>
                  </div>
                  <div class="section-actions" (click)="$event.stopPropagation()">
                    <div class="form-check form-switch mb-0">
                      <input
                        class="form-check-input"
                        type="checkbox"
                        role="switch"
                        [id]="'section-page-break-' + sectionIndex"
                        [checked]="section.saltoPagina"
                        (change)="toggleSectionSalto(sectionIndex)">
                      <label
                        class="form-check-label"
                        [for]="'section-page-break-' + sectionIndex">
                        Salto pagina
                      </label>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Riordina sezione">
                      <button
                        type="button"
                        class="btn btn-ghost-secondary"
                        title="Sposta sezione su"
                        [disabled]="sectionIndex === 0"
                        (click)="moveSection(sectionIndex, -1)">
                        <i class="ri-arrow-up-line"></i>
                      </button>
                      <button
                        type="button"
                        class="btn btn-ghost-secondary"
                        title="Sposta sezione giÃ¹"
                        [disabled]="sectionIndex === sezioni.length - 1"
                        (click)="moveSection(sectionIndex, 1)">
                        <i class="ri-arrow-down-line"></i>
                      </button>
                      <button
                        type="button"
                        class="btn btn-ghost-danger"
                        title="Elimina sezione"
                        (click)="removeSection(sectionIndex)">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </div>
                  </div>
                </div>

                @if (section.articoli.length) {
                  <div
                    class="article-list"
                    cdkDropList
                    [cdkDropListData]="section.articoli"
                    (cdkDropListDropped)="dropArticolo(sectionIndex, $event)"
                    (click)="$event.stopPropagation()">
                    @for (articolo of section.articoli; track articolo; let articoloIndex = $index) {
                      <div class="article-item" cdkDrag [cdkDragData]="articolo">
                        <button
                          type="button"
                          class="drag-handle btn btn-sm btn-ghost-secondary"
                          cdkDragHandle
                          [attr.aria-label]="'Trascina articolo ' + articolo.sku"
                          title="Trascina articolo">
                          <i class="ri-drag-move-2-line"></i>
                        </button>
                        <div class="article-heading">
                          <strong>{{ articolo.sku }}</strong>
                          <span>{{ articolo.nome }}</span>
                        </div>
                        <div class="article-actions">
                          <div class="form-check form-switch mb-0">
                            <input
                              class="form-check-input"
                              type="checkbox"
                              role="switch"
                              [id]="'article-page-break-' + sectionIndex + '-' + articoloIndex"
                              [checked]="articolo.saltoPagina"
                              (change)="toggleArticoloSalto(sectionIndex, articoloIndex)">
                            <label
                              class="form-check-label"
                              [for]="'article-page-break-' + sectionIndex + '-' + articoloIndex">
                              Salto pagina
                            </label>
                          </div>
                          <div class="btn-group btn-group-sm" role="group" aria-label="Riordina articolo">
                            <button
                              type="button"
                              class="btn btn-ghost-secondary"
                              title="Sposta articolo su"
                              [disabled]="articoloIndex === 0"
                              (click)="moveArticolo(sectionIndex, articoloIndex, -1)">
                              <i class="ri-arrow-up-line"></i>
                            </button>
                            <button
                              type="button"
                              class="btn btn-ghost-secondary"
                              title="Sposta articolo giÃ¹"
                              [disabled]="articoloIndex === section.articoli.length - 1"
                              (click)="moveArticolo(sectionIndex, articoloIndex, 1)">
                              <i class="ri-arrow-down-line"></i>
                            </button>
                            <button
                              type="button"
                              class="btn btn-ghost-danger"
                              title="Elimina articolo"
                              (click)="removeArticolo(sectionIndex, articoloIndex)">
                              <i class="ri-delete-bin-line"></i>
                            </button>
                          </div>
                        </div>
                      </div>
                    }
                  </div>
                } @else {
                  <p class="section-empty text-muted mb-0">Nessun articolo in questa sezione.</p>
                }
              </section>
            }
          </div>
        } @else {
          <div class="empty-state text-center text-muted">
            <i class="ri-file-list-3-line display-5"></i>
            <p class="mt-3 mb-0">Il catalogo non contiene ancora sezioni.</p>
          </div>
        }
      </div>
    </main>
  </div>
}

<div class="editor-footer">
  <button type="button" class="btn btn-light" [disabled]="saving" (click)="cancel()">Annulla</button>
  <button type="button" class="btn btn-success" [disabled]="saving || loading" (click)="save()">
    @if (saving) {
      <span class="spinner-border spinner-border-sm me-2" role="status"></span>
    }
    Salva
  </button>
</div>

<ng-template #categoryNodes let-nodes>
  <ul class="category-level">
    @for (category of nodes; track category.id) {
      <li role="treeitem" [attr.aria-expanded]="category.children.length ? isCategoryExpanded(category.id) : null">
        <div
          class="category-row"
          [class.selected]="selectedCategoryId === category.id">
          @if (category.children.length) {
            <button
              type="button"
              class="category-toggle"
              [attr.aria-label]="isCategoryExpanded(category.id) ? 'Comprimi ' + category.name : 'Espandi ' + category.name"
              (click)="toggleCategory(category.id)">
              <i
                [class.ri-arrow-down-s-line]="isCategoryExpanded(category.id)"
                [class.ri-arrow-right-s-line]="!isCategoryExpanded(category.id)">
              </i>
            </button>
          } @else {
            <span class="category-toggle-spacer"></span>
          }
          <button
            type="button"
            class="category-select"
            (click)="selectCategory(category.id)">
            <span>{{ category.name }}</span>
            <span class="category-count">{{ category.count }}</span>
          </button>
        </div>
        @if (category.children.length && isCategoryExpanded(category.id)) {
          <ng-container
            *ngTemplateOutlet="categoryNodes; context: { $implicit: category.children }">
          </ng-container>
        }
      </li>
    }
  </ul>
</ng-template>
=== fant-cataloghi-articoli.component.scss ===
.editor-layout {
  display: grid;
  grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
  gap: 1.5rem;
}

.editor-sidebar,
.editor-main {
  margin-bottom: 0;
}

.empty-state {
  padding: 3rem 1rem;
}

.category-status {
  padding: 2rem 0;
}

.category-tree {
  max-height: 28rem;
  overflow: auto;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.25rem;
  padding: 0.375rem;
}

.category-level {
  list-style: none;
  margin: 0;
  padding-left: 1rem;

  &:first-child {
    padding-left: 0;
  }
}

.category-row {
  display: flex;
  align-items: center;
  min-width: 0;
  border-radius: 0.25rem;

  &:hover {
    background: var(--vz-light);
  }

  &.selected {
    color: var(--vz-primary);
    background: rgba(var(--vz-primary-rgb), 0.12);
  }
}

.category-toggle,
.category-select {
  border: 0;
  color: inherit;
  background: transparent;
}

.category-toggle,
.category-toggle-spacer {
  flex: 0 0 1.75rem;
  width: 1.75rem;
  height: 2rem;
}

.category-select {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
  gap: 0.5rem;
  padding: 0.375rem 0.5rem 0.375rem 0;
  text-align: left;
}

.category-count {
  color: var(--vz-secondary-color);
  font-size: 0.75rem;
}

.section-list {
  display: grid;
  gap: 0.75rem;
}

.section-item {
  border: 1px solid var(--vz-border-color);
  border-radius: 0.375rem;
  padding: 1rem;
  color: inherit;
  background: var(--vz-card-bg);
  cursor: pointer;

  &:hover {
    border-color: var(--vz-primary);
  }

  &.selected {
    border-color: var(--vz-primary);
    box-shadow: 0 0 0 1px var(--vz-primary);
    background: rgba(var(--vz-primary-rgb), 0.06);
  }
}

.section-header,
.article-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.section-heading,
.article-heading {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.section-name {
  font-weight: 600;
}

.section-count {
  color: var(--vz-secondary-color);
  font-size: 0.8125rem;
}

.section-actions,
.article-actions {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.drag-handle {
  flex: 0 0 auto;
  cursor: grab;

  &:active {
    cursor: grabbing;
  }
}

.article-list {
  display: grid;
  gap: 0.5rem;
  margin-top: 1rem;
  padding-left: 2.75rem;
}

.article-item {
  border-top: 1px solid var(--vz-border-color);
  padding: 0.75rem 0;
  cursor: default;
}

.article-heading {
  span {
    overflow: hidden;
    color: var(--vz-secondary-color);
    font-size: 0.8125rem;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.section-empty {
  margin-top: 0.75rem;
  padding-left: 2.75rem;
  font-size: 0.8125rem;
}

.cdk-drag-preview {
  box-sizing: border-box;
  border: 1px solid var(--vz-primary);
  border-radius: 0.375rem;
  padding: 1rem;
  background: var(--vz-card-bg);
  box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.cdk-drag-placeholder {
  opacity: 0.25;
}

.cdk-drag-animating,
.section-list.cdk-drop-list-dragging .section-item:not(.cdk-drag-placeholder),
.article-list.cdk-drop-list-dragging .article-item:not(.cdk-drag-placeholder) {
  transition: transform 180ms ease;
}

.editor-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

@media (max-width: 991.98px) {
  .editor-layout {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767.98px) {
  .section-header,
  .article-item,
  .section-actions,
  .article-actions {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .section-heading,
  .article-heading {
    min-width: calc(100% - 3rem);
  }

  .section-actions,
  .article-actions {
    width: 100%;
    justify-content: space-between;
    padding-left: 2.75rem;
  }

  .article-list {
    padding-left: 0;
  }
}
=== fant-cataloghi-articoli.component.spec.ts ===
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
    );
    component.sezioni = [...catalog.prodotti];
    component.flatCategories = categories;
    component.selectedCategoryId = 10;

    component.addCategorySection();

    expect(products.byCategory).toHaveBeenCalledOnceWith(10, true);
    expect(component.sezioni[1]).toEqual({
      categoryId: 10,
      nome: 'Radice',
      saltoPagina: false,
      articoli: [
        { productId: 1, sku: 'SKU-1', nome: 'Primo', saltoPagina: false },
      ],
    });
    expect(component.selectedSectionIndex).toBe(1);
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
    );
    component.flatCategories = categories;
    component.selectedCategoryId = 10;

    component.addCategorySection();

    expect(component.sezioni).toEqual([]);
    expect(component.error).toBe('Impossibile caricare i prodotti della categoria.');
    expect(component.loadingProducts).toBeFalse();
  });

  it('cerca gli articoli dopo 300ms usando solo il termine piÃ¹ recente', fakeAsync(() => {
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

  it('blocca lâ€™aggiunta di articoli quando non Ã¨ selezionata una sezione', () => {
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

    component.addSelectedProducts();

    expect(component.sezioni[0].articoli).toEqual([
      { productId: 9, sku: 'sku-1', nome: 'Esistente', saltoPagina: false },
      { productId: 2, sku: 'SKU-2', nome: 'Secondo', saltoPagina: false },
    ]);
    expect(component.selectedProductIds.size).toBe(0);
    expect(component.error).toBe('');
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

  function createComponent(catalogs?: jasmine.SpyObj<CatalogService>): FantCataloghiArticoliComponent {
    const route = { snapshot: { paramMap: { get: () => 'demo' } } };
    const router = jasmine.createSpyObj('Router', ['navigate']);
    const catalogService = catalogs
      ?? jasmine.createSpyObj<CatalogService>('CatalogService', ['get', 'updateContenuto']);
    const products = jasmine.createSpyObj<ProductService>('ProductService', ['search', 'byCategory']);
    const categoryService = jasmine.createSpyObj<WooCategoryService>('WooCategoryService', ['list']);
    return new FantCataloghiArticoliComponent(
      route as any,
      router,
      catalogService,
      products,
      categoryService,
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
=== fant-cataloghi-articoli.component.ts ===
import { Component, OnInit } from '@angular/core';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { ActivatedRoute, Router } from '@angular/router';
import { finalize } from 'rxjs';

import {
  CatalogArticolo,
  CatalogService,
  CatalogSezione,
} from '../../../core/services/catalog.service';
import { ProductItem, ProductService } from '../../../core/services/product.service';
import {
  WooCategory,
  WooCategoryService,
} from '../../../core/services/woo-category.service';

interface CategoryNode extends WooCategory {
  children: CategoryNode[];
}

@Component({
  selector: 'app-fant-cataloghi-articoli',
  templateUrl: './fant-cataloghi-articoli.component.html',
  styleUrls: ['./fant-cataloghi-articoli.component.scss'],
  standalone: false,
})
export class FantCataloghiArticoliComponent implements OnInit {
  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];
  codice = '';
  nome = '';
  sezioni: CatalogSezione[] = [];
  selectedSectionIndex: number | null = null;
  loading = false;
  saving = false;
  error = '';
  success = '';
  addTab: 'categoria' | 'articolo' = 'categoria';
  flatCategories: WooCategory[] = [];
  categoryTree: CategoryNode[] = [];
  expandedCategoryIds = new Set<number>();
  selectedCategoryId: number | null = null;
  loadingCategories = false;
  loadingProducts = false;
  searchTerm = '';
  searchResults: ProductItem[] = [];
  selectedProductIds = new Set<number>();
  searchingProducts = false;
  private searchTimer: ReturnType<typeof setTimeout> | null = null;
  private searchRequestId = 0;

  constructor(
    private readonly route: ActivatedRoute,
    private readonly router: Router,
    private readonly catalogs: CatalogService,
    private readonly products: ProductService,
    private readonly categories: WooCategoryService,
  ) {}

  ngOnInit(): void {
    this.breadCrumbItems = [
      { label: 'Cataloghi' },
      { label: 'Articoli', active: true },
    ];
    this.codice = this.route.snapshot.paramMap.get('codice') || '';
    this.load();
    this.loadCategories();
  }

  load(): void {
    if (!this.codice) {
      this.error = 'Codice catalogo non valido.';
      return;
    }

    this.loading = true;
    this.error = '';
    this.catalogs.get(this.codice).pipe(finalize(() => this.loading = false)).subscribe({
      next: catalog => {
        this.nome = catalog.testata.nome;
        this.sezioni = catalog.prodotti;
      },
      error: error => {
        this.error = error?.error?.message ?? error?.message ?? 'Impossibile caricare il catalogo.';
      },
    });
  }

  loadCategories(): void {
    this.loadingCategories = true;
    this.categories.list().pipe(finalize(() => this.loadingCategories = false)).subscribe({
      next: categories => {
        this.flatCategories = categories;
        this.categoryTree = this.buildTree(categories);
        this.expandedCategoryIds = new Set(categories.map(category => category.id));
      },
      error: () => {
        this.error = 'Impossibile caricare le categorie.';
      },
    });
  }

  selectCategory(categoryId: number): void {
    this.selectedCategoryId = categoryId;
    this.error = '';
  }

  isCategoryExpanded(categoryId: number): boolean {
    return this.expandedCategoryIds.has(categoryId);
  }

  toggleCategory(categoryId: number): void {
    const expanded = new Set(this.expandedCategoryIds);
    if (expanded.has(categoryId)) {
      expanded.delete(categoryId);
    } else {
      expanded.add(categoryId);
    }
    this.expandedCategoryIds = expanded;
  }

  addCategorySection(): void {
    if (!this.selectedCategoryId) {
      this.error = 'Seleziona una categoria.';
      return;
    }

    const category = this.flatCategories.find(item => item.id === this.selectedCategoryId);
    if (!category) {
      this.error = 'Categoria selezionata non disponibile.';
      return;
    }

    this.loadingProducts = true;
    this.error = '';
    this.products.byCategory(category.id, true)
      .pipe(finalize(() => this.loadingProducts = false))
      .subscribe({
        next: items => {
          const articoli: CatalogArticolo[] = items
            .filter(item => !!item.sku)
            .map(item => ({
              productId: item.id,
              sku: item.sku,
              nome: item.name,
              saltoPagina: false,
            }));
          this.sezioni = [
            ...this.sezioni,
            {
              categoryId: category.id,
              nome: category.name,
              saltoPagina: false,
              articoli,
            },
          ];
          this.selectedSectionIndex = this.sezioni.length - 1;
        },
        error: () => {
          this.error = 'Impossibile caricare i prodotti della categoria.';
        },
      });
  }

  onSearchInput(value: string): void {
    this.searchTerm = value;
    this.error = '';
    this.selectedProductIds.clear();
    const requestId = ++this.searchRequestId;

    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }

    this.searchTimer = setTimeout(() => {
      this.searchTimer = null;
      this.searchingProducts = true;
      this.products.search(value, 1, 30)
        .pipe(finalize(() => {
          if (requestId === this.searchRequestId) {
            this.searchingProducts = false;
          }
        }))
        .subscribe({
          next: result => {
            if (requestId === this.searchRequestId) {
              this.searchResults = result.items;
            }
          },
          error: () => {
            if (requestId === this.searchRequestId) {
              this.error = 'Ricerca prodotti fallita.';
            }
          },
        });
    }, 300);
  }

  toggleProduct(productId: number): void {
    const selected = new Set(this.selectedProductIds);
    if (selected.has(productId)) {
      selected.delete(productId);
    } else {
      selected.add(productId);
    }
    this.selectedProductIds = selected;
  }

  addSelectedProducts(): void {
    const section = this.selectedSectionIndex === null
      ? undefined
      : this.sezioni[this.selectedSectionIndex];
    if (!section) {
      this.error = 'Seleziona una sezione nel riquadro di destra.';
      return;
    }

    const existingSkus = new Set(
      section.articoli
        .map(articolo => articolo.sku.trim().toLowerCase())
        .filter(sku => !!sku),
    );

    for (const product of this.searchResults) {
      const sku = product.sku.trim();
      const normalizedSku = sku.toLowerCase();
      if (
        !this.selectedProductIds.has(product.id)
        || !normalizedSku
        || existingSkus.has(normalizedSku)
      ) {
        continue;
      }

      section.articoli.push({
        productId: product.id,
        sku,
        nome: product.name,
        saltoPagina: false,
      });
      existingSkus.add(normalizedSku);
    }

    this.selectedProductIds.clear();
    this.error = '';
  }

  selectSection(index: number): void {
    this.selectedSectionIndex = index;
  }

  toggleSectionSalto(sectionIndex: number): void {
    const section = this.sezioni[sectionIndex];
    if (section) {
      section.saltoPagina = !section.saltoPagina;
    }
  }

  toggleArticoloSalto(sectionIndex: number, articoloIndex: number): void {
    const articolo = this.sezioni[sectionIndex]?.articoli[articoloIndex];
    if (articolo) {
      articolo.saltoPagina = !articolo.saltoPagina;
    }
  }

  removeSection(sectionIndex: number): void {
    if (!this.sezioni[sectionIndex]) {
      return;
    }

    this.sezioni = this.sezioni.filter((_, index) => index !== sectionIndex);
    if (this.selectedSectionIndex === sectionIndex) {
      this.selectedSectionIndex = null;
    } else if (
      this.selectedSectionIndex !== null
      && this.selectedSectionIndex > sectionIndex
    ) {
      this.selectedSectionIndex--;
    }
  }

  removeArticolo(sectionIndex: number, articoloIndex: number): void {
    const section = this.sezioni[sectionIndex];
    if (!section?.articoli[articoloIndex]) {
      return;
    }

    section.articoli = section.articoli.filter((_, index) => index !== articoloIndex);
  }

  moveSection(sectionIndex: number, delta: -1 | 1): void {
    const targetIndex = sectionIndex + delta;
    if (
      sectionIndex < 0
      || sectionIndex >= this.sezioni.length
      || targetIndex < 0
      || targetIndex >= this.sezioni.length
    ) {
      return;
    }

    const sections = [...this.sezioni];
    [sections[sectionIndex], sections[targetIndex]] = [
      sections[targetIndex],
      sections[sectionIndex],
    ];
    this.sezioni = sections;

    if (this.selectedSectionIndex === sectionIndex) {
      this.selectedSectionIndex = targetIndex;
    } else if (this.selectedSectionIndex === targetIndex) {
      this.selectedSectionIndex = sectionIndex;
    }
  }

  moveArticolo(sectionIndex: number, articoloIndex: number, delta: -1 | 1): void {
    const section = this.sezioni[sectionIndex];
    const targetIndex = articoloIndex + delta;
    if (
      !section
      || articoloIndex < 0
      || articoloIndex >= section.articoli.length
      || targetIndex < 0
      || targetIndex >= section.articoli.length
    ) {
      return;
    }

    const articoli = [...section.articoli];
    [articoli[articoloIndex], articoli[targetIndex]] = [
      articoli[targetIndex],
      articoli[articoloIndex],
    ];
    this.sezioni[sectionIndex] = { ...section, articoli };
  }

  dropSection(event: CdkDragDrop<CatalogSezione[]>): void {
    const selectedSection = this.selectedSectionIndex === null
      ? undefined
      : this.sezioni[this.selectedSectionIndex];
    moveItemInArray(this.sezioni, event.previousIndex, event.currentIndex);
    if (selectedSection) {
      this.selectedSectionIndex = this.sezioni.indexOf(selectedSection);
    }
  }

  dropArticolo(
    sectionIndex: number,
    event: CdkDragDrop<CatalogArticolo[]>,
  ): void {
    const section = this.sezioni[sectionIndex];
    if (section) {
      moveItemInArray(section.articoli, event.previousIndex, event.currentIndex);
    }
  }

  cancel(): void {
    this.router.navigate(['/fant-cataloghi']);
  }

  save(): void {
    this.saving = true;
    this.error = '';
    this.success = '';
    this.catalogs.updateContenuto(this.codice, this.sezioni)
      .pipe(finalize(() => this.saving = false))
      .subscribe({
        next: () => {
          this.success = 'Catalogo salvato.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Salvataggio fallito.';
        },
      });
  }

  private buildTree(categories: WooCategory[]): CategoryNode[] {
    const nodes = new Map<number, CategoryNode>();
    categories.forEach(category => nodes.set(category.id, { ...category, children: [] }));
    const roots: CategoryNode[] = [];

    categories.forEach(category => {
      const node = nodes.get(category.id)!;
      const parent = nodes.get(category.parent);
      if (parent && parent.id !== node.id) {
        parent.children.push(node);
      } else {
        roots.push(node);
      }
    });

    const sortNodes = (items: CategoryNode[]): void => {
      items.sort((a, b) => a.menuOrder - b.menuOrder || a.name.localeCompare(b.name));
      items.forEach(item => sortNodes(item.children));
    };
    sortNodes(roots);
    return roots;
  }
}
