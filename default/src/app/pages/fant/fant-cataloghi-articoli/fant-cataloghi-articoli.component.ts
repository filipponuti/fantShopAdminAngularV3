import { Component, OnDestroy, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { ActivatedRoute, Router } from '@angular/router';
import { NgbModal, NgbModalRef } from '@ng-bootstrap/ng-bootstrap';
import { finalize } from 'rxjs';

import {
  CatalogArticolo,
  CatalogSezioneSettings,
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

type CatalogEditorView = 'settings' | 'sezioni' | 'pdf';

interface MissingCandidate {
  productId: number;
  sku: string;
  nome: string;
  parentProductId: number | null;
  selected: boolean;
}

@Component({
  selector: 'app-fant-cataloghi-articoli',
  templateUrl: './fant-cataloghi-articoli.component.html',
  styleUrls: ['./fant-cataloghi-articoli.component.scss'],
  standalone: false,
})
export class FantCataloghiArticoliComponent implements OnInit, OnDestroy {
  @ViewChild('missingProductsModal') missingProductsModal?: TemplateRef<unknown>;

  readonly mainNavItems: Array<{ id: CatalogEditorView; label: string; icon: string }> = [
    { id: 'settings', label: 'Settings', icon: 'ri-settings-3-line' },
    { id: 'sezioni', label: 'Sezioni e articoli', icon: 'ri-list-check-2' },
    { id: 'pdf', label: 'PDF', icon: 'ri-file-pdf-2-line' },
  ];

  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];
  codice = '';
  nome = '';
  activeView: CatalogEditorView = 'sezioni';
  mainSidebarCollapsed = false;
  sezioni: CatalogSezione[] = [];
  selectedSectionIndex: number | null = null;
  loading = false;
  saving = false;
  error = '';
  success = '';
  private sezioniDirty = false;
  private settingsDirty = false;
  readonly fontSizeOptions = [10, 11, 12, 13, 14, 15, 16, 18, 20, 24];
  sezioneSettings: CatalogSezioneSettings = {
    backgroundColor: '#C6B2B3',
    textColor: '#333333',
    fontSize: 15,
  };
  addTab: 'categoria' | 'sezione' | 'articolo' = 'categoria';
  newSectionName = '';
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
  refreshingSectionIndex: number | null = null;
  collapsedSectionIds = new Set<number>();
  collapsedArticleKeys = new Set<string>();
  sectionFilterOpen = false;
  /** null = tutte visibili; altrimenti indici sezione da mostrare */
  private sectionFilterIndexes: Set<number> | null = null;
  missingCandidates: MissingCandidate[] = [];
  missingSectionIndex: number | null = null;
  private missingModalRef: NgbModalRef | null = null;

  constructor(
    private readonly route: ActivatedRoute,
    private readonly router: Router,
    private readonly catalogs: CatalogService,
    private readonly products: ProductService,
    private readonly categories: WooCategoryService,
    private readonly modalService: NgbModal,
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

  ngOnDestroy(): void {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
      this.searchTimer = null;
    }
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
        this.sezioneSettings = {
          backgroundColor: catalog.settings?.sezione?.backgroundColor || '#C6B2B3',
          textColor: catalog.settings?.sezione?.textColor || '#333333',
          fontSize: catalog.settings?.sezione?.fontSize || 15,
        };
        this.sezioniDirty = false;
        this.settingsDirty = false;
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
            .map(item => this.mapProductToArticolo(item))
            .filter((item): item is CatalogArticolo => !!item);
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
          this.includeSectionInFilter(this.selectedSectionIndex);
          this.markDirty();
        },
        error: () => {
          this.error = 'Impossibile caricare i prodotti della categoria.';
        },
      });
  }

  addManualSection(): void {
    const nome = this.newSectionName.trim();
    if (!nome) {
      this.error = 'Inserisci il nome della sezione.';
      return;
    }

    this.error = '';
    this.sezioni = [
      ...this.sezioni,
      {
        categoryId: 0,
        nome,
        saltoPagina: false,
        articoli: [],
      },
    ];
    this.selectedSectionIndex = this.sezioni.length - 1;
    this.newSectionName = '';
    this.includeSectionInFilter(this.selectedSectionIndex);
    this.markDirty();
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

    const existingIds = this.collectSectionProductIds(section);
    const existingSkus = this.collectSectionSkus(section);
    let changed = false;
    for (const product of this.searchResults) {
      if (!this.selectedProductIds.has(product.id)) {
        continue;
      }
      if (this.appendProductTree(section, product, existingIds, existingSkus)) {
        changed = true;
      }
    }

    this.selectedProductIds.clear();
    this.error = '';
    if (changed) {
      this.markDirty();
    }
  }

  selectSection(index: number): void {
    this.selectedSectionIndex = index;
  }

  onSectionKeydown(event: KeyboardEvent, sectionIndex: number): void {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }
    if (event.key === ' ') {
      event.preventDefault();
    }
    this.selectSection(sectionIndex);
  }

  toggleSectionSalto(sectionIndex: number): void {
    const section = this.sezioni[sectionIndex];
    if (section) {
      section.saltoPagina = !section.saltoPagina;
      this.markDirty();
    }
  }

  toggleArticoloSalto(sectionIndex: number, articoloIndex: number): void {
    const articolo = this.sezioni[sectionIndex]?.articoli[articoloIndex];
    if (articolo) {
      articolo.saltoPagina = !articolo.saltoPagina;
      this.markDirty();
    }
  }

  toggleVariazioneSalto(
    sectionIndex: number,
    articoloIndex: number,
    variazioneIndex: number,
  ): void {
    const variazione = this.sezioni[sectionIndex]?.articoli[articoloIndex]
      ?.variazioni?.[variazioneIndex];
    if (variazione) {
      variazione.saltoPagina = !variazione.saltoPagina;
      this.markDirty();
    }
  }

  removeSection(sectionIndex: number): void {
    if (!this.sezioni[sectionIndex]) {
      return;
    }

    this.sezioni = this.sezioni.filter((_, index) => index !== sectionIndex);
    this.remapSectionFilterAfterRemove(sectionIndex);
    this.markDirty();
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
    this.markDirty();
  }

  removeVariazione(sectionIndex: number, articoloIndex: number, variazioneIndex: number): void {
    const articolo = this.sezioni[sectionIndex]?.articoli[articoloIndex];
    if (!articolo?.variazioni?.[variazioneIndex]) {
      return;
    }
    articolo.variazioni = articolo.variazioni.filter((_, index) => index !== variazioneIndex);
    this.markDirty();
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
    this.remapSectionFilterAfterSwap(sectionIndex, targetIndex);
    this.markDirty();

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
    this.markDirty();
  }

  moveVariazione(
    sectionIndex: number,
    articoloIndex: number,
    variazioneIndex: number,
    delta: -1 | 1,
  ): void {
    const articolo = this.sezioni[sectionIndex]?.articoli[articoloIndex];
    const variazioni = articolo?.variazioni;
    const targetIndex = variazioneIndex + delta;
    if (
      !variazioni
      || variazioneIndex < 0
      || variazioneIndex >= variazioni.length
      || targetIndex < 0
      || targetIndex >= variazioni.length
    ) {
      return;
    }

    const next = [...variazioni];
    [next[variazioneIndex], next[targetIndex]] = [next[targetIndex], next[variazioneIndex]];
    articolo.variazioni = next;
    this.markDirty();
  }

  dropSection(event: CdkDragDrop<CatalogSezione[]>): void {
    if (
      event.previousIndex === event.currentIndex
      || !this.sezioni[event.previousIndex]
      || !this.sezioni[event.currentIndex]
    ) {
      return;
    }
    const selectedSection = this.selectedSectionIndex === null
      ? undefined
      : this.sezioni[this.selectedSectionIndex];
    moveItemInArray(this.sezioni, event.previousIndex, event.currentIndex);
    this.remapSectionFilterAfterMove(event.previousIndex, event.currentIndex);
    if (selectedSection) {
      this.selectedSectionIndex = this.sezioni.indexOf(selectedSection);
    }
    this.markDirty();
  }

  dropArticolo(
    sectionIndex: number,
    event: CdkDragDrop<CatalogArticolo[]>,
  ): void {
    const section = this.sezioni[sectionIndex];
    if (
      section
      && event.previousIndex !== event.currentIndex
      && section.articoli[event.previousIndex]
      && section.articoli[event.currentIndex]
    ) {
      moveItemInArray(section.articoli, event.previousIndex, event.currentIndex);
      this.markDirty();
    }
  }

  dropVariazione(
    sectionIndex: number,
    articoloIndex: number,
    event: CdkDragDrop<CatalogArticolo[] | undefined>,
  ): void {
    const variazioni = this.sezioni[sectionIndex]?.articoli[articoloIndex]?.variazioni;
    if (
      variazioni
      && event.previousIndex !== event.currentIndex
      && variazioni[event.previousIndex]
      && variazioni[event.currentIndex]
    ) {
      moveItemInArray(variazioni, event.previousIndex, event.currentIndex);
      this.markDirty();
    }
  }

  refreshSection(sectionIndex: number): void {
    const section = this.sezioni[sectionIndex];
    if (!section?.categoryId) {
      return;
    }

    this.refreshingSectionIndex = sectionIndex;
    this.error = '';
    this.success = '';
    this.products.byCategory(section.categoryId, true)
      .pipe(finalize(() => this.refreshingSectionIndex = null))
      .subscribe({
        next: items => {
          const missing = this.findMissingCandidates(section, items);
          if (!missing.length) {
            this.success = 'La sezione è allineata alla categoria: nessun articolo nuovo.';
            return;
          }

          const confirmed = window.confirm(
            `Trovati ${missing.length} articoli/variazioni non presenti in questa sezione. Vuoi selezionarli da aggiungere?`,
          );
          if (!confirmed || !this.missingProductsModal) {
            return;
          }

          this.missingSectionIndex = sectionIndex;
          this.missingCandidates = missing.map(item => ({ ...item, selected: true }));
          this.missingModalRef = this.modalService.open(this.missingProductsModal, {
            centered: true,
            size: 'lg',
            backdrop: 'static',
          });
        },
        error: () => {
          this.error = 'Impossibile aggiornare i prodotti della categoria.';
        },
      });
  }

  toggleMissingCandidate(productId: number): void {
    const row = this.missingCandidates.find(item => item.productId === productId);
    if (row) {
      row.selected = !row.selected;
    }
  }

  get selectedMissingCount(): number {
    return this.missingCandidates.filter(item => item.selected).length;
  }

  dismissMissingModal(): void {
    this.missingModalRef?.dismiss();
    this.missingModalRef = null;
    this.missingCandidates = [];
    this.missingSectionIndex = null;
  }

  confirmMissingAdd(): void {
    if (this.missingSectionIndex === null) {
      this.dismissMissingModal();
      return;
    }
    const section = this.sezioni[this.missingSectionIndex];
    if (!section) {
      this.dismissMissingModal();
      return;
    }

    const selected = this.missingCandidates.filter(item => item.selected);
    if (!selected.length) {
      return;
    }

    const existingIds = this.collectSectionProductIds(section);
    const existingSkus = this.collectSectionSkus(section);
    const parentsById = new Map<number, MissingCandidate>();
    selected
      .filter(item => item.parentProductId === null)
      .forEach(item => parentsById.set(item.productId, item));

    // Auto-include parent stub if only variations are selected.
    for (const item of selected) {
      if (item.parentProductId === null) {
        continue;
      }
      const parentInSection = section.articoli.some(a => a.productId === item.parentProductId);
      if (parentInSection || parentsById.has(item.parentProductId)) {
        continue;
      }
      const parentCandidate = this.missingCandidates.find(
        row => row.productId === item.parentProductId && row.parentProductId === null,
      );
      if (parentCandidate) {
        parentsById.set(parentCandidate.productId, parentCandidate);
      }
    }

    let added = 0;
    for (const parent of parentsById.values()) {
      if (existingIds.has(parent.productId) || existingSkus.has(parent.sku.trim().toLowerCase())) {
        continue;
      }
      const sku = parent.sku.trim();
      if (!sku) {
        continue;
      }
      section.articoli.push({
        productId: parent.productId,
        sku,
        nome: parent.nome,
        saltoPagina: false,
        variazioni: [],
      });
      existingIds.add(parent.productId);
      existingSkus.add(sku.toLowerCase());
      added++;
    }

    for (const item of selected) {
      if (item.parentProductId === null) {
        continue;
      }
      let parent = section.articoli.find(a => a.productId === item.parentProductId);
      if (!parent) {
        const parentCandidate = parentsById.get(item.parentProductId)
          ?? this.missingCandidates.find(row => row.productId === item.parentProductId);
        if (!parentCandidate?.sku.trim()) {
          continue;
        }
        if (
          existingIds.has(parentCandidate.productId)
          || existingSkus.has(parentCandidate.sku.trim().toLowerCase())
        ) {
          parent = section.articoli.find(a => a.productId === parentCandidate.productId);
          if (!parent) {
            continue;
          }
        } else {
          parent = {
            productId: parentCandidate.productId,
            sku: parentCandidate.sku.trim(),
            nome: parentCandidate.nome,
            saltoPagina: false,
            variazioni: [],
          };
          section.articoli.push(parent);
          existingIds.add(parent.productId);
          existingSkus.add(parent.sku.toLowerCase());
          added++;
        }
      }
      parent.variazioni = parent.variazioni ?? [];
      const sku = item.sku.trim();
      if (
        !sku
        || existingIds.has(item.productId)
        || existingSkus.has(sku.toLowerCase())
      ) {
        continue;
      }
      parent.variazioni.push({
        productId: item.productId,
        sku,
        nome: item.nome,
        saltoPagina: false,
      });
      existingIds.add(item.productId);
      existingSkus.add(sku.toLowerCase());
      added++;
    }

    this.dismissMissingModal();
    if (added > 0) {
      this.markDirty();
      this.success = `Aggiunti ${added} elementi.`;
    }
  }

  isSectionCollapsed(sectionIndex: number): boolean {
    return this.collapsedSectionIds.has(sectionIndex);
  }

  collapseSection(sectionIndex: number): void {
    const next = new Set(this.collapsedSectionIds);
    next.add(sectionIndex);
    this.collapsedSectionIds = next;
  }

  expandSection(sectionIndex: number): void {
    const next = new Set(this.collapsedSectionIds);
    next.delete(sectionIndex);
    this.collapsedSectionIds = next;
  }

  articleKey(sectionIndex: number, productId: number): string {
    return `${sectionIndex}-${productId}`;
  }

  isArticleCollapsed(sectionIndex: number, productId: number): boolean {
    return this.collapsedArticleKeys.has(this.articleKey(sectionIndex, productId));
  }

  collapseArticle(sectionIndex: number, productId: number): void {
    const next = new Set(this.collapsedArticleKeys);
    next.add(this.articleKey(sectionIndex, productId));
    this.collapsedArticleKeys = next;
  }

  expandArticle(sectionIndex: number, productId: number): void {
    const next = new Set(this.collapsedArticleKeys);
    next.delete(this.articleKey(sectionIndex, productId));
    this.collapsedArticleKeys = next;
  }

  get sectionFilterLabel(): string {
    if (this.sectionFilterIndexes === null) {
      return 'Tutte';
    }
    const count = this.sectionFilterIndexes.size;
    return `${count}/${this.sezioni.length}`;
  }

  get isSectionFilterAll(): boolean {
    return this.sectionFilterIndexes === null;
  }

  get hasVisibleSections(): boolean {
    return this.sezioni.some((_, index) => this.isSectionVisible(index));
  }

  toggleSectionFilterPanel(): void {
    this.sectionFilterOpen = !this.sectionFilterOpen;
  }

  closeSectionFilterPanel(): void {
    this.sectionFilterOpen = false;
  }

  isSectionVisible(sectionIndex: number): boolean {
    return this.sectionFilterIndexes === null || this.sectionFilterIndexes.has(sectionIndex);
  }

  isSectionFilterChecked(sectionIndex: number): boolean {
    return this.isSectionVisible(sectionIndex);
  }

  setSectionFilterAll(): void {
    this.sectionFilterIndexes = null;
  }

  setSectionFilterNone(): void {
    this.sectionFilterIndexes = new Set();
  }

  toggleSectionFilter(sectionIndex: number, checked: boolean): void {
    const next = this.sectionFilterIndexes === null
      ? new Set(this.sezioni.map((_, index) => index))
      : new Set(this.sectionFilterIndexes);

    if (checked) {
      next.add(sectionIndex);
    } else {
      next.delete(sectionIndex);
    }

    this.sectionFilterIndexes = next.size === this.sezioni.length ? null : next;
  }

  private includeSectionInFilter(sectionIndex: number): void {
    if (this.sectionFilterIndexes === null || sectionIndex < 0) {
      return;
    }
    const next = new Set(this.sectionFilterIndexes);
    next.add(sectionIndex);
    this.sectionFilterIndexes = next.size === this.sezioni.length ? null : next;
  }

  selectMainView(view: CatalogEditorView): void {
    if (view === this.activeView) {
      return;
    }
    if (this.isCurrentViewDirty()) {
      const confirmed = window.confirm(
        'Ci sono modifiche non salvate. Vuoi uscire senza salvare?',
      );
      if (!confirmed) {
        return;
      }
    }
    this.activeView = view;
    this.error = '';
    this.success = '';
  }

  toggleMainSidebar(): void {
    this.mainSidebarCollapsed = !this.mainSidebarCollapsed;
  }

  cancel(): void {
    this.router.navigate(['/fant-cataloghi']);
  }

  save(): void {
    if (this.activeView === 'pdf') {
      this.error = '';
      this.success = 'Nessuna modifica da salvare.';
      return;
    }

    if (!this.codice) {
      this.error = 'Codice catalogo non valido.';
      return;
    }

    if (this.activeView === 'settings') {
      if (!this.settingsDirty) {
        this.error = '';
        this.success = 'Nessuna modifica da salvare.';
        return;
      }
      this.saving = true;
      this.error = '';
      this.success = '';
      this.catalogs.updateSettings(this.codice, { sezione: { ...this.sezioneSettings } })
        .pipe(finalize(() => this.saving = false))
        .subscribe({
          next: () => {
            this.settingsDirty = false;
            this.success = 'Settings salvate.';
          },
          error: error => {
            this.error = error?.error?.message ?? 'Salvataggio settings fallito.';
          },
        });
      return;
    }

    this.saving = true;
    this.error = '';
    this.success = '';
    this.catalogs.updateContenuto(this.codice, this.sezioni)
      .pipe(finalize(() => this.saving = false))
      .subscribe({
        next: () => {
          this.sezioniDirty = false;
          this.success = 'Catalogo salvato.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Salvataggio fallito.';
        },
      });
  }

  onSezioneBackgroundChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    this.sezioneSettings = { ...this.sezioneSettings, backgroundColor: value.toUpperCase() };
    this.markSettingsDirty();
  }

  onSezioneTextColorChange(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    this.sezioneSettings = { ...this.sezioneSettings, textColor: value.toUpperCase() };
    this.markSettingsDirty();
  }

  onSezioneFontSizeChange(event: Event): void {
    const value = Number((event.target as HTMLSelectElement).value);
    this.sezioneSettings = { ...this.sezioneSettings, fontSize: value };
    this.markSettingsDirty();
  }

  private isCurrentViewDirty(): boolean {
    if (this.activeView === 'sezioni') {
      return this.sezioniDirty;
    }
    if (this.activeView === 'settings') {
      return this.settingsDirty;
    }
    return false;
  }

  private markDirty(): void {
    this.sezioniDirty = true;
    this.success = '';
  }

  private markSettingsDirty(): void {
    this.settingsDirty = true;
    this.success = '';
  }

  private remapSectionFilterAfterRemove(removedIndex: number): void {
    if (this.sectionFilterIndexes === null) {
      return;
    }
    const next = new Set<number>();
    for (const index of this.sectionFilterIndexes) {
      if (index === removedIndex) {
        continue;
      }
      next.add(index > removedIndex ? index - 1 : index);
    }
    this.sectionFilterIndexes = next.size === this.sezioni.length ? null : next;
  }

  private remapSectionFilterAfterSwap(from: number, to: number): void {
    if (this.sectionFilterIndexes === null) {
      return;
    }
    const next = new Set<number>();
    for (const index of this.sectionFilterIndexes) {
      if (index === from) {
        next.add(to);
      } else if (index === to) {
        next.add(from);
      } else {
        next.add(index);
      }
    }
    this.sectionFilterIndexes = next;
  }

  private remapSectionFilterAfterMove(previousIndex: number, currentIndex: number): void {
    if (this.sectionFilterIndexes === null || previousIndex === currentIndex) {
      return;
    }
    const n = this.sezioni.length;
    const sequence = Array.from({ length: n }, (_, i) => i);
    const [moved] = sequence.splice(previousIndex, 1);
    sequence.splice(currentIndex, 0, moved);
    const mapping = new Array<number>(n);
    sequence.forEach((oldIndex, newIndex) => {
      mapping[oldIndex] = newIndex;
    });
    const next = new Set<number>();
    for (const oldIndex of this.sectionFilterIndexes) {
      if (oldIndex >= 0 && oldIndex < n) {
        next.add(mapping[oldIndex]);
      }
    }
    this.sectionFilterIndexes = next.size === n ? null : next;
  }

  private mapProductToArticolo(product: ProductItem): CatalogArticolo | null {
    const sku = product.sku?.trim() ?? '';
    if (!sku) {
      return null;
    }
    const variazioni = (product.variazioni ?? [])
      .map(item => this.mapProductToArticolo(item))
      .filter((item): item is CatalogArticolo => !!item)
      .map(item => ({ ...item, variazioni: undefined }));

    return {
      productId: product.id,
      sku,
      nome: product.name,
      saltoPagina: false,
      variazioni,
    };
  }

  private collectSectionProductIds(section: CatalogSezione): Set<number> {
    const ids = new Set<number>();
    for (const articolo of section.articoli) {
      ids.add(articolo.productId);
      for (const variazione of articolo.variazioni ?? []) {
        ids.add(variazione.productId);
      }
    }
    return ids;
  }

  private collectSectionSkus(section: CatalogSezione): Set<string> {
    const skus = new Set<string>();
    for (const articolo of section.articoli) {
      const sku = articolo.sku.trim().toLowerCase();
      if (sku) {
        skus.add(sku);
      }
      for (const variazione of articolo.variazioni ?? []) {
        const childSku = variazione.sku.trim().toLowerCase();
        if (childSku) {
          skus.add(childSku);
        }
      }
    }
    return skus;
  }

  private appendProductTree(
    section: CatalogSezione,
    product: ProductItem,
    existingIds: Set<number>,
    existingSkus: Set<string> = this.collectSectionSkus(section),
  ): boolean {
    const mapped = this.mapProductToArticolo(product);
    if (!mapped) {
      return false;
    }

    let changed = false;
    if (!existingIds.has(mapped.productId) && !existingSkus.has(mapped.sku.toLowerCase())) {
      const keptVariations = (mapped.variazioni ?? []).filter(item => {
        if (existingIds.has(item.productId) || existingSkus.has(item.sku.toLowerCase())) {
          return false;
        }
        existingIds.add(item.productId);
        existingSkus.add(item.sku.toLowerCase());
        return true;
      });
      mapped.variazioni = keptVariations;
      section.articoli.push(mapped);
      existingIds.add(mapped.productId);
      existingSkus.add(mapped.sku.toLowerCase());
      return true;
    }

    const parent = section.articoli.find(item => item.productId === mapped.productId);
    if (!parent) {
      return false;
    }
    parent.variazioni = parent.variazioni ?? [];
    for (const variazione of mapped.variazioni ?? []) {
      if (
        existingIds.has(variazione.productId)
        || existingSkus.has(variazione.sku.toLowerCase())
      ) {
        continue;
      }
      parent.variazioni.push(variazione);
      existingIds.add(variazione.productId);
      existingSkus.add(variazione.sku.toLowerCase());
      changed = true;
    }
    return changed;
  }

  private findMissingCandidates(
    section: CatalogSezione,
    products: ProductItem[],
  ): MissingCandidate[] {
    const existingIds = this.collectSectionProductIds(section);
    const missing: MissingCandidate[] = [];

    for (const product of products) {
      const parentSku = product.sku?.trim() ?? '';
      const parentInSection = existingIds.has(product.id);
      if (!parentInSection && parentSku) {
        missing.push({
          productId: product.id,
          sku: parentSku,
          nome: product.name,
          parentProductId: null,
          selected: true,
        });
      }

      for (const variazione of product.variazioni ?? []) {
        const sku = variazione.sku?.trim() ?? '';
        if (!sku || existingIds.has(variazione.id)) {
          continue;
        }
        // Parent stub needed in modal list when only variations are missing.
        if (parentInSection || parentSku) {
          if (!parentInSection && parentSku && !missing.some(m => m.productId === product.id)) {
            missing.push({
              productId: product.id,
              sku: parentSku,
              nome: product.name,
              parentProductId: null,
              selected: true,
            });
          }
          missing.push({
            productId: variazione.id,
            sku,
            nome: variazione.name,
            parentProductId: product.id,
            selected: true,
          });
        }
      }
    }

    return missing;
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
