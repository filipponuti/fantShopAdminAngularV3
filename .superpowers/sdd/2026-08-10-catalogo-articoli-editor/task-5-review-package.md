# Task 5 review
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
          <p class="text-muted mb-0">
            La ricerca degli articoli sarÃ  disponibile nel prossimo passaggio.
          </p>
        }
      </div>
    </aside>

    <main class="editor-main card">
      <div class="card-header">
        <h5 class="card-title mb-0">Sezioni e articoli</h5>
      </div>
      <div class="card-body">
        @if (sezioni.length) {
          <div class="section-list">
            @for (section of sezioni; track $index; let sectionIndex = $index) {
              <button
                type="button"
                class="section-item"
                [class.selected]="selectedSectionIndex === sectionIndex"
                [attr.aria-pressed]="selectedSectionIndex === sectionIndex"
                (click)="selectSection(sectionIndex)">
                <span class="section-name">{{ section.nome }}</span>
                <span class="section-count">
                  {{ section.articoli.length }}
                  {{ section.articoli.length === 1 ? 'articolo' : 'articoli' }}
                </span>
              </button>
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
  <button type="button" class="btn btn-success" [disabled]="saving || loading" (click)="save()">Salva</button>
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
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  width: 100%;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.375rem;
  padding: 1rem;
  color: inherit;
  background: var(--vz-card-bg);
  text-align: left;

  &:hover {
    border-color: var(--vz-primary);
  }

  &.selected {
    border-color: var(--vz-primary);
    box-shadow: 0 0 0 1px var(--vz-primary);
    background: rgba(var(--vz-primary-rgb), 0.06);
  }
}

.section-name {
  font-weight: 600;
}

.section-count {
  color: var(--vz-secondary-color);
  white-space: nowrap;
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
=== fant-cataloghi-articoli.component.spec.ts ===
import { of, throwError } from 'rxjs';

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
});
=== fant-cataloghi-articoli.component.ts ===
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { finalize } from 'rxjs';

import {
  CatalogArticolo,
  CatalogService,
  CatalogSezione,
} from '../../../core/services/catalog.service';
import { ProductService } from '../../../core/services/product.service';
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

  selectSection(index: number): void {
    this.selectedSectionIndex = index;
  }

  cancel(): void {
    this.router.navigate(['/fant-cataloghi']);
  }

  save(): void {
    // La persistenza del contenuto verrÃ  implementata nella Task 7.
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
