  <div class="alert alert-danger" role="alert">{{ error }}</div>
  <div class="alert alert-success" role="status">{{ success }}</div>
      <span class="spinner-border text-primary" role="status"></span>
        <div class="btn-group w-100 mb-3" role="group" aria-label="Tipo elemento da aggiungere">
              <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            <div class="category-tree" role="tree" aria-label="Categorie prodotti">
              <span class="spinner-border spinner-border-sm me-2" role="status"></span>
              <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            <div class="list-group mt-3" aria-label="Risultati ricerca articoli">
                role="button"
                tabindex="0"
                [attr.aria-pressed]="selectedSectionIndex === sectionIndex"
                (click)="selectSection(sectionIndex)"
                (keydown)="onSectionKeydown($event, sectionIndex)">
                    [attr.aria-label]="'Trascina sezione ' + section.nome"
                    (keydown)="$event.stopPropagation()">
                    (keydown)="$event.stopPropagation()">
                        role="switch"
                    <div class="btn-group btn-group-sm" role="group" aria-label="Riordina sezione">
                    (keydown)="$event.stopPropagation()">
                          [attr.aria-label]="'Trascina articolo ' + articolo.sku"
                              role="switch"
                          <div class="btn-group btn-group-sm" role="group" aria-label="Riordina articolo">
      <span class="spinner-border spinner-border-sm me-2" role="status"></span>
      <li role="treeitem" [attr.aria-expanded]="category.children.length ? isCategoryExpanded(category.id) : null">
              [attr.aria-label]="isCategoryExpanded(category.id) ? 'Comprimi ' + category.name : 'Espandi ' + category.name"

--- html excerpt around sections ---
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
                role="button"
                tabindex="0"
                [attr.aria-pressed]="selectedSectionIndex === sectionIndex"
                cdkDrag
                [cdkDragData]="section"
                (click)="selectSection(sectionIndex)"
                (keydown)="onSectionKeydown($event, sectionIndex)">
                <div class="section-header">
                  <button
                    type="button"
                    class="drag-handle btn btn-sm btn-ghost-secondary"
                    cdkDragHandle
                    [attr.aria-label]="'Trascina sezione ' + section.nome"
                    title="Trascina sezione"
                    (click)="$event.stopPropagation()"
                    (keydown)="$event.stopPropagation()">
                    <i class="ri-drag-move-2-line"></i>
                  </button>
                  <div class="section-heading">
                    <span class="section-name">{{ section.nome }}</span>
                    <span class="section-count">
                      {{ section.articoli.length }}
                      {{ section.articoli.length === 1 ? 'articolo' : 'articoli' }}
                    </span>
                  </div>
                  <div
                    class="section-actions"
                    (click)="$event.stopPropagation()"
                    (keydown)="$event.stopPropagation()">
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
                        title="Sposta sezione giù"
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
                    (click)="$event.stopPropagation()"
                    (keydown)="$event.stopPropagation()">
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
                              title="Sposta articolo giù"
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
    const enterEvent = new KeyboardEvent('keydown', { key: 'Enter' });
    const spaceEvent = new KeyboardEvent('keydown', { key: ' ' });
    component.onSectionKeydown(enterEvent, 0);
    component.onSectionKeydown(spaceEvent, 1);
  selectSection(index: number): void {
  onSectionKeydown(event: KeyboardEvent, sectionIndex: number): void {
    this.selectSection(sectionIndex);
