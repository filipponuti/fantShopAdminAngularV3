import { Component, OnDestroy, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { NgbModal, NgbModalRef } from '@ng-bootstrap/ng-bootstrap';
import { finalize, forkJoin, Observable } from 'rxjs';
import { map } from 'rxjs/operators';

import {
  CatalogAnteprima,
  CatalogAnteprimaSezione,
  CatalogAnteprimaState,
  CatalogArticolo,
  CatalogPdf,
  CatalogPdfRecord,
  CatalogPdfState,
  CatalogPdfUso,
  CatalogSezioneSettings,
  CatalogService,
  CatalogSezione,
} from '../../../core/services/catalog.service';
import { CoverService, CoverSummary } from '../../../core/services/cover.service';
import { LayoutService } from '../../../core/services/layout.service';
import { defaultLayoutHeaderHtml, defaultLayoutHtml } from '../fant-layout-articoli-dettaglio/layout-template-defaults';
import {
  buildCatalogPdfDocument,
  CATALOG_PAGE_CONTENT_PX,
  catalogPdfPageCount,
} from './catalog-pdf-html';
import { ProductItem, ProductService } from '../../../core/services/product.service';
import {
  WooCategory,
  WooCategoryService,
} from '../../../core/services/woo-category.service';

interface CategoryNode extends WooCategory {
  children: CategoryNode[];
}

type CatalogEditorView = 'settings' | 'sezioni' | 'anteprima' | 'pdf';
type AnteprimaTab = 'lista' | 'corrente';
type PdfTab = 'lista' | 'file';

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
    { id: 'anteprima', label: 'ANTEPRIMA', icon: 'ri-eye-line' },
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
  private productSchede = new Map<number, ProductItem>();
  readonly fontSizeOptions = [10, 11, 12, 13, 14, 15, 16, 18, 20, 24];
  sezioneSettings: CatalogSezioneSettings = {
    backgroundColor: '#C6B2B3',
    textColor: '#333333',
    fontSize: 15,
  };
  covers: CoverSummary[] = [];
  coversError = '';
  fileCopertina = '';
  fileRetro = '';
  addTab: 'categoria' | 'sezione' | 'articolo' = 'categoria';
  newSectionName = '';
  flatCategories: WooCategory[] = [];
  categoryTree: CategoryNode[] = [];
  expandedCategoryIds = new Set<number>();
  selectedCategoryId: number | null = null;
  loadingCategories = false;
  loadingProducts = false;
  previewLoading = false;
  generatingPdf = false;
  loadingPdf = false;
  pdfPath = '';
  pdfFilename = '';
  pdfObjectUrl: string | null = null;
  previewReady = false;
  anteprimaTab: AnteprimaTab = 'lista';
  anteprime: CatalogAnteprima[] = [];
  anteprimaCorrente = '';
  anteprimaFormOpen = false;
  anteprimaFormId = '';
  anteprimaFormNome = '';
  anteprimaFormSezioni = new Set<number>();
  savingAnteprimaRecord = false;
  deletingAnteprimaId = '';
  loadingAnteprimaId = '';
  pdfTab: PdfTab = 'lista';
  pdfs: CatalogPdfRecord[] = [];
  pdfCorrente = '';
  pdfFormOpen = false;
  pdfFormId = '';
  pdfFormNome = '';
  pdfFormAnteprimaId = '';
  savingPdfRecord = false;
  deletingPdfId = '';
  loadingPdfId = '';
  anteprimaRichiesta = false;
  anteprimaPath = '';
  savingAnteprima = false;
  previewPageCount = 0;
  previewUrl: SafeResourceUrl | null = null;
  private previewHtml = '';
  private previewObjectUrl: string | null = null;
  private previewTicket = 0;
  private anteprimaTicket = 0;
  private pdfMissingRetried = false;
  private renderingAnteprimaId = '';
  private pdfUsoQueue: Promise<void> = Promise.resolve();
  private layoutHeights = new Map<string, number>([['articolo-semplice', 220]]);
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
    private readonly layouts?: LayoutService,
    private readonly sanitizer?: DomSanitizer,
    private readonly coversService?: CoverService,
  ) {}

  ngOnInit(): void {
    this.breadCrumbItems = [
      { label: 'Cataloghi' },
      { label: 'Articoli', active: true },
    ];
    this.codice = this.route.snapshot.paramMap.get('codice') || '';
    this.load();
    this.loadCategories();
    this.loadLayouts();
    this.loadCovers();
  }

  ngOnDestroy(): void {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
      this.searchTimer = null;
    }
    this.revokePreviewUrl();
    this.revokePdfUrl();
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
        this.sezioni = catalog.prodotti.map(section => ({
          ...section,
          articoli: section.articoli.map(articolo => this.withLayout(articolo)),
        }));
        this.sezioneSettings = {
          backgroundColor: catalog.settings?.sezione?.backgroundColor || '#C6B2B3',
          textColor: catalog.settings?.sezione?.textColor || '#333333',
          fontSize: catalog.settings?.sezione?.fontSize || 15,
        };
        this.fileCopertina = catalog.settings?.filesAggiuntivi?.copertina || '';
        this.fileRetro = catalog.settings?.filesAggiuntivi?.retro || '';
        this.sezioniDirty = false;
        this.settingsDirty = false;
        this.applyAnteprimaState({
          anteprime: catalog.anteprime ?? [],
          anteprimaCorrente: catalog.anteprimaCorrente ?? '',
        });
        this.applyPdfState({
          pdfs: catalog.pdfs ?? [],
          pdfCorrente: catalog.pdfCorrente ?? '',
        });
        this.revokePdfUrl();
        this.syncPdfFromCurrent();
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
    this.sectionsForCategory(category, [])
      .pipe(finalize(() => this.loadingProducts = false))
      .subscribe({
        next: created => {
          const start = this.sezioni.length;
          this.sezioni = [...this.sezioni, ...created];
          for (let index = start; index < this.sezioni.length; index++) {
            this.includeSectionInFilter(index);
          }
          this.selectedSectionIndex = this.sezioni.length - 1;
          this.markDirty();
        },
        error: () => {
          this.error = 'Impossibile caricare i prodotti della categoria.';
        },
      });
  }

  private sectionsForCategory(category: WooCategory, ancestorNames: string[]): Observable<CatalogSezione[]> {
    const children = this.directChildren(category.id);
    const nome = ancestorNames.length ? `${ancestorNames.join('->')}->${category.name}` : category.name;
    if (!children.length) {
      return this.products.byCategory(category.id, false).pipe(
        map(items => [this.catalogSection(category.id, nome, this.toArticoli(items))]),
      );
    }
    return forkJoin(
      children.map(child => this.sectionsForCategory(child, [...ancestorNames, category.name])),
    ).pipe(map(branches => [this.catalogSection(category.id, nome, []), ...branches.flat()]));
  }

  private directChildren(categoryId: number): WooCategory[] {
    return this.flatCategories
      .filter(item => item.parent === categoryId)
      .sort((left, right) => left.menuOrder - right.menuOrder || left.name.localeCompare(right.name, 'it'));
  }

  private catalogSection(categoryId: number, nome: string, articoli: CatalogArticolo[]): CatalogSezione {
    return { categoryId, nome, saltoPagina: false, articoli };
  }

  private toArticoli(items: ProductItem[]): CatalogArticolo[] {
    return items
      .map(item => this.mapProductToArticolo(item))
      .filter((item): item is CatalogArticolo => !!item);
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

  collapseAllSections(): void {
    this.collapsedSectionIds = new Set(this.sezioni.map((_, index) => index));
  }

  expandAllSections(): void {
    this.collapsedSectionIds = new Set();
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
    if (view === 'anteprima') {
      this.anteprimaTab = 'lista';
      this.anteprimaFormOpen = false;
    }
    if (view === 'pdf') {
      this.pdfTab = 'lista';
      this.pdfFormOpen = false;
    }
  }

  selectAnteprimaTab(tab: AnteprimaTab): void {
    this.anteprimaTab = tab;
    this.error = '';
    this.success = '';
    if (tab === 'lista') {
      return;
    }
    this.anteprimaFormOpen = false;
    const corrente = this.currentAnteprima();
    if (corrente?.generato && !this.previewUrl) {
      this.loadSavedAnteprima();
    }
  }

  currentAnteprima(): CatalogAnteprima | null {
    return this.anteprime.find(item => item.id === this.anteprimaCorrente) ?? null;
  }

  nuovaAnteprima(): void {
    this.anteprimaFormOpen = true;
    this.anteprimaFormId = '';
    this.anteprimaFormNome = '';
    this.anteprimaFormSezioni = new Set();
    this.error = '';
    this.success = '';
  }

  modificaAnteprima(item: CatalogAnteprima): void {
    this.anteprimaFormOpen = true;
    this.anteprimaFormId = item.id;
    this.anteprimaFormNome = item.nome;
    this.anteprimaFormSezioni = new Set(
      this.sezioni
        .map((section, index) => this.sectionMatches(section.nome, index, item.sezioni) ? index : -1)
        .filter(index => index >= 0),
    );
    this.error = '';
    this.success = '';
  }

  annullaAnteprimaForm(): void {
    this.anteprimaFormOpen = false;
  }

  toggleAnteprimaSection(index: number): void {
    const next = new Set(this.anteprimaFormSezioni);
    if (next.has(index)) {
      next.delete(index);
    } else {
      next.add(index);
    }
    this.anteprimaFormSezioni = next;
  }

  salvaAnteprimaRecord(): void {
    if (!this.codice || this.savingAnteprimaRecord) {
      return;
    }
    const nome = this.anteprimaFormNome.trim();
    if (!nome) {
      this.error = 'Il nome dell\'anteprima è obbligatorio.';
      return;
    }
    if (nome.length > 80) {
      this.error = 'Il nome dell\'anteprima può avere al massimo 80 caratteri.';
      return;
    }
    const taken = this.anteprime.some(item =>
      item.id !== this.anteprimaFormId && item.nome.toLocaleLowerCase() === nome.toLocaleLowerCase(),
    );
    if (taken) {
      this.error = 'Esiste già un\'anteprima con questo nome.';
      return;
    }
    const sezioni = [...this.anteprimaFormSezioni]
      .filter(index => index >= 0 && index < this.sezioni.length)
      .sort((left, right) => left - right)
      .map(index => ({ nome: this.sezioni[index].nome, posizione: index }));
    if (!sezioni.length) {
      this.error = 'Seleziona almeno una sezione.';
      return;
    }
    this.savingAnteprimaRecord = true;
    this.error = '';
    this.success = '';
    const request = this.anteprimaFormId
      ? this.catalogs.updateAnteprima(this.codice, this.anteprimaFormId, nome, sezioni)
      : this.catalogs.createAnteprima(this.codice, nome, sezioni);
    request.pipe(finalize(() => this.savingAnteprimaRecord = false)).subscribe({
      next: state => {
        this.applyAnteprimaState(state);
        this.anteprimaFormOpen = false;
        this.success = this.anteprimaFormId ? 'Anteprima aggiornata.' : 'Anteprima creata.';
      },
      error: error => {
        this.error = error?.error?.message ?? 'Impossibile salvare l\'anteprima.';
      },
    });
  }

  eliminaAnteprimaRecord(item: CatalogAnteprima): void {
    if (!this.codice || this.deletingAnteprimaId) {
      return;
    }
    const confirmed = window.confirm(`Eliminare l'anteprima «${item.nome}» e i suoi file?`);
    if (!confirmed) {
      return;
    }
    const wasCurrent = item.id === this.anteprimaCorrente;
    this.deletingAnteprimaId = item.id;
    this.error = '';
    this.success = '';
    this.catalogs.deleteAnteprimaRecord(this.codice, item.id)
      .pipe(finalize(() => this.deletingAnteprimaId = ''))
      .subscribe({
        next: state => {
          this.applyAnteprimaState(state);
          if (wasCurrent) {
            this.anteprimaTicket++;
            this.clearAnteprima();
            this.syncPdfFromCurrent();
          }
          if (this.anteprimaFormId === item.id) {
            this.anteprimaFormOpen = false;
          }
          this.success = 'Anteprima eliminata.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Impossibile eliminare l\'anteprima.';
        },
      });
  }

  caricaAnteprima(item: CatalogAnteprima): void {
    if (!this.codice || this.loadingAnteprimaId) {
      return;
    }
    this.loadingAnteprimaId = item.id;
    this.error = '';
    this.success = '';
    this.catalogs.loadAnteprima(this.codice, item.id)
      .pipe(finalize(() => this.loadingAnteprimaId = ''))
      .subscribe({
        next: state => {
          this.applyAnteprimaState(state);
          this.anteprimaFormOpen = false;
          this.anteprimaTab = 'corrente';
          this.anteprimaTicket++;
          this.clearAnteprima();
          this.syncPdfFromCurrent();
          if (this.currentAnteprima()?.generato) {
            this.loadSavedAnteprima();
          }
          this.success = `Anteprima «${item.nome}» caricata.`;
        },
        error: error => {
          this.error = error?.error?.message ?? 'Impossibile caricare l\'anteprima.';
        },
      });
  }

  selectPdfTab(tab: PdfTab): void {
    this.pdfTab = tab;
    this.error = '';
    this.success = '';
    if (tab === 'lista') {
      return;
    }
    this.pdfFormOpen = false;
  }

  currentPdf(): CatalogPdfRecord | null {
    return this.pdfs.find(item => item.id === this.pdfCorrente) ?? null;
  }

  togglePdfUso(kind: 'usaCopertina' | 'usaRetro', checked: boolean): void {
    const pdf = this.currentPdf();
    if (!pdf || !this.codice || this.generatingPdf) {
      return;
    }
    const previous = pdf[kind];
    pdf[kind] = checked;
    const uso = this.pdfUsoOf(pdf);
    this.enqueuePdfUso(pdf.id, uso, () => {
      if (pdf[kind] === checked) {
        pdf[kind] = previous;
      }
    });
  }

  anteprimaNome(id: string): string {
    return this.anteprime.find(item => item.id === id)?.nome || 'Anteprima mancante';
  }

  nuovoPdf(): void {
    this.pdfFormOpen = true;
    this.pdfFormId = '';
    this.pdfFormNome = '';
    this.pdfFormAnteprimaId = this.anteprimaCorrente || this.anteprime[0]?.id || '';
    this.error = '';
    this.success = '';
  }

  modificaPdf(item: CatalogPdfRecord): void {
    this.pdfFormOpen = true;
    this.pdfFormId = item.id;
    this.pdfFormNome = item.nome;
    this.pdfFormAnteprimaId = item.anteprimaId;
    this.error = '';
    this.success = '';
  }

  annullaPdfForm(): void {
    this.pdfFormOpen = false;
  }

  salvaPdfRecord(): void {
    if (!this.codice || this.savingPdfRecord) {
      return;
    }
    const nome = this.pdfFormNome.trim();
    if (!nome) {
      this.error = 'Il nome del PDF è obbligatorio.';
      return;
    }
    if (nome.length > 80) {
      this.error = 'Il nome del PDF può avere al massimo 80 caratteri.';
      return;
    }
    const taken = this.pdfs.some(item =>
      item.id !== this.pdfFormId && item.nome.toLocaleLowerCase() === nome.toLocaleLowerCase(),
    );
    if (taken) {
      this.error = 'Esiste già un PDF con questo nome.';
      return;
    }
    if (!this.pdfFormAnteprimaId || !this.anteprime.some(item => item.id === this.pdfFormAnteprimaId)) {
      this.error = 'Seleziona un\'anteprima del catalogo.';
      return;
    }
    this.savingPdfRecord = true;
    this.error = '';
    this.success = '';
    const editing = this.pdfFormId;
    const request = editing
      ? this.catalogs.updatePdf(this.codice, editing, nome, this.pdfFormAnteprimaId)
      : this.catalogs.createPdf(this.codice, nome, this.pdfFormAnteprimaId);
    request.pipe(finalize(() => this.savingPdfRecord = false)).subscribe({
      next: state => {
        this.applyPdfState(state);
        this.pdfFormOpen = false;
        this.success = editing ? 'PDF aggiornato.' : 'PDF creato.';
      },
      error: error => {
        this.error = error?.error?.message ?? 'Impossibile salvare il PDF.';
      },
    });
  }

  eliminaPdfRecord(item: CatalogPdfRecord): void {
    if (!this.codice || this.deletingPdfId) {
      return;
    }
    const confirmed = window.confirm(`Eliminare il PDF «${item.nome}» e il suo file?`);
    if (!confirmed) {
      return;
    }
    const wasCurrent = item.id === this.pdfCorrente;
    this.deletingPdfId = item.id;
    this.error = '';
    this.success = '';
    this.catalogs.deletePdfRecord(this.codice, item.id)
      .pipe(finalize(() => this.deletingPdfId = ''))
      .subscribe({
        next: state => {
          this.applyPdfState(state);
          if (wasCurrent) {
            this.syncPdfFromCurrent();
          }
          if (this.pdfFormId === item.id) {
            this.pdfFormOpen = false;
          }
          this.success = 'PDF eliminato.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Impossibile eliminare il PDF.';
        },
      });
  }

  caricaPdf(item: CatalogPdfRecord): void {
    if (!this.codice || this.loadingPdfId) {
      return;
    }
    this.loadingPdfId = item.id;
    this.error = '';
    this.success = '';
    this.catalogs.loadPdf(this.codice, item.id)
      .pipe(finalize(() => this.loadingPdfId = ''))
      .subscribe({
        next: state => {
          this.applyPdfState(state);
          this.pdfFormOpen = false;
          this.pdfTab = 'file';
          this.syncPdfFromCurrent();
          this.success = `PDF «${item.nome}» caricato.`;
        },
        error: error => {
          this.error = error?.error?.message ?? 'Impossibile caricare il PDF.';
        },
      });
  }

  toggleMainSidebar(): void {
    this.mainSidebarCollapsed = !this.mainSidebarCollapsed;
  }

  cancel(): void {
    this.router.navigate(['/fant-cataloghi']);
  }

  save(): void {
    if (this.activeView === 'anteprima' || this.activeView === 'pdf') {
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
      this.catalogs.updateSettings(this.codice, {
        sezione: { ...this.sezioneSettings },
        filesAggiuntivi: {
          copertina: this.fileCopertina,
          retro: this.fileRetro,
        },
      })
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

  onFileCopertinaChange(event: Event): void {
    this.fileCopertina = (event.target as HTMLSelectElement).value;
    this.markSettingsDirty();
  }

  onFileRetroChange(event: Event): void {
    this.fileRetro = (event.target as HTMLSelectElement).value;
    this.markSettingsDirty();
  }

  get hasUnsavedSettings(): boolean {
    return this.settingsDirty;
  }

  coverOptionMissing(code: string): boolean {
    return !!code && !this.covers.some(item => item.codice === code);
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
      layout: this.layoutForProduct(product, variazioni),
      variazioni,
    };
  }

  articleLayout(articolo: CatalogArticolo): string {
    return articolo.layout || (!articolo.variazioni?.length ? 'articolo-semplice' : '');
  }

  articleHeight(articolo: CatalogArticolo): number | null {
    const layout = this.articleLayout(articolo);
    if (!layout) {
      return null;
    }
    const height = this.layoutHeights.get(layout);
    return height && height > 0 ? height : null;
  }

  articlePageFill(sectionIndex: number, articleIndex: number): { height: number; total: number; overflow: boolean } | null {
    const target = this.sezioni[sectionIndex]?.articoli[articleIndex];
    if (!target) {
      return null;
    }
    const height = this.articleHeight(target);
    if (height == null) {
      return null;
    }
    let used = 0;
    for (let sectionCursor = 0; sectionCursor < this.sezioni.length; sectionCursor++) {
      const section = this.sezioni[sectionCursor];
      if (section.saltoPagina && used > 0) {
        used = 0;
      }
      for (let articleCursor = 0; articleCursor < section.articoli.length; articleCursor++) {
        const rowHeight = this.articleHeight(section.articoli[articleCursor]) ?? 220;
        if (section.articoli[articleCursor].saltoPagina && used > 0) {
          used = 0;
        }
        used += rowHeight;
        if (sectionCursor === sectionIndex && articleCursor === articleIndex) {
          return { height, total: used, overflow: used > CATALOG_PAGE_CONTENT_PX };
        }
      }
    }
    return null;
  }

  get showUnsavedPdfNote(): boolean {
    return this.sezioniDirty;
  }

  generaAnteprima(): void {
    if (!this.codice) {
      this.error = 'Codice catalogo non valido.';
      return;
    }
    if (!this.anteprimaCorrente) {
      this.error = 'Carica un\'anteprima prima di generarla.';
      return;
    }
    if (!this.sezioniAnteprima().length) {
      this.error = 'Nessuna sezione selezionata è ancora presente nel catalogo.';
      return;
    }
    this.anteprimaTicket++;
    this.anteprimaRichiesta = true;
    this.error = '';
    this.success = '';
    this.refreshPdfPreview(html => this.persistAnteprima(html, true));
  }

  generatePdf(): void {
    if (!this.codice) {
      this.error = 'Codice catalogo non valido.';
      return;
    }
    const pdf = this.currentPdf();
    if (!pdf) {
      this.error = 'Carica un PDF prima di generarlo.';
      return;
    }
    const anteprima = this.anteprime.find(item => item.id === pdf.anteprimaId);
    if (!anteprima) {
      this.error = 'L\'anteprima collegata a questo PDF non esiste più.';
      return;
    }
    this.generatingPdf = true;
    this.pdfMissingRetried = false;
    this.renderingAnteprimaId = anteprima.id;
    this.error = '';
    this.success = '';
    void this.pdfUsoQueue.then(() => {
      if (!this.generatingPdf) {
        return;
      }
      if (anteprima.generato) {
        this.requestPdf();
        return;
      }
      if (!this.sezioniAnteprima().length) {
        this.generatingPdf = false;
        this.renderingAnteprimaId = '';
        this.error = 'Nessuna sezione selezionata è ancora presente nel catalogo.';
        return;
      }
      this.refreshPdfPreview(html => this.persistAnteprima(html, false, () => this.requestPdf(), false));
    });
  }

  private requestPdf(): void {
    if (!this.codice) {
      this.generatingPdf = false;
      return;
    }
    this.generatingPdf = true;
    const pdf = this.currentPdf();
    this.catalogs.generatePdf(this.codice, {
      usaCopertina: !!pdf?.usaCopertina,
      usaRetro: !!pdf?.usaRetro,
    }).subscribe({
      next: pdf => {
        this.generatingPdf = false;
        this.renderingAnteprimaId = '';
        this.rememberPdf(pdf);
        this.success = 'PDF salvato.';
      },
      error: error => {
        if (error?.status === 404 && !this.pdfMissingRetried) {
          this.pdfMissingRetried = true;
          if (!this.sezioniAnteprima().length) {
            this.generatingPdf = false;
            this.renderingAnteprimaId = '';
            this.error = 'Nessuna sezione selezionata è ancora presente nel catalogo.';
            return;
          }
          this.refreshPdfPreview(html => this.persistAnteprima(html, false, () => this.requestPdf(), false));
          return;
        }
        this.generatingPdf = false;
        this.renderingAnteprimaId = '';
        this.error = error?.error?.message ?? 'Impossibile generare il PDF.';
      },
    });
  }

  downloadPdf(): void {
    this.withPdfFile(() => {
      if (!this.pdfObjectUrl) {
        return;
      }
      const link = document.createElement('a');
      link.href = this.pdfObjectUrl;
      link.download = this.pdfFilename || 'catalogo.pdf';
      link.click();
    });
  }

  openPdf(): void {
    this.withPdfFile(() => {
      if (!this.pdfObjectUrl) {
        return;
      }
      const opened = window.open(this.pdfObjectUrl, '_blank', 'noopener');
      if (!opened) {
        this.error = 'Il browser ha bloccato l\'apertura del PDF.';
      }
    });
  }

  private withPdfFile(then: () => void): void {
    if (this.pdfObjectUrl) {
      then();
      return;
    }
    if (!this.codice) {
      return;
    }
    this.loadingPdf = true;
    this.error = '';
    this.catalogs.getPdf(this.codice)
      .pipe(finalize(() => this.loadingPdf = false))
      .subscribe({
        next: pdf => {
          this.rememberPdf(pdf);
          then();
        },
        error: error => {
          this.error = error?.error?.message ?? 'Impossibile leggere il PDF salvato.';
        },
      });
  }

  private rememberPdf(pdf: CatalogPdf): void {
    this.revokePdfUrl();
    this.pdfPath = pdf.path || '';
    this.pdfFilename = pdf.filename || `${this.codice || 'catalogo'}.pdf`;
    const corrente = this.currentPdf();
    if (corrente) {
      corrente.generato = true;
      corrente.filePdf = this.pdfFilename;
      corrente.pathPdf = this.pdfPath;
    }
    const bytes = Uint8Array.from(atob(pdf.contentBase64), char => char.charCodeAt(0));
    this.pdfObjectUrl = URL.createObjectURL(new Blob([bytes], { type: pdf.mimeType || 'application/pdf' }));
  }

  private revokePdfUrl(): void {
    if (this.pdfObjectUrl) {
      URL.revokeObjectURL(this.pdfObjectUrl);
      this.pdfObjectUrl = null;
    }
  }

  private loadSavedAnteprima(): void {
    if (!this.codice) {
      return;
    }
    const ticket = ++this.anteprimaTicket;
    this.previewLoading = true;
    this.catalogs.getAnteprima(this.codice).subscribe({
      next: saved => {
        if (ticket !== this.anteprimaTicket) {
          return;
        }
        this.showAnteprimaFile(saved);
      },
      error: error => {
        if (ticket !== this.anteprimaTicket) {
          return;
        }
        this.previewLoading = false;
        this.clearAnteprima();
        if (error?.status !== 404) {
          this.error = error?.error?.message ?? 'Impossibile caricare l\'anteprima.';
        }
      },
    });
  }

  private persistAnteprima(html: string, announce: boolean, then?: () => void, makeCurrent = true): void {
    if (!this.codice) {
      this.generatingPdf = false;
      this.renderingAnteprimaId = '';
      return;
    }
    const anteprimaId = this.renderingAnteprimaId || this.anteprimaCorrente;
    this.savingAnteprima = true;
    this.catalogs.saveAnteprima(this.codice, html, anteprimaId, makeCurrent)
      .pipe(finalize(() => this.savingAnteprima = false))
      .subscribe({
        next: saved => {
          this.anteprimaTicket++;
          if (saved.anteprime) {
            this.applyAnteprimaState({
              anteprime: saved.anteprime,
              anteprimaCorrente: saved.anteprimaCorrente || this.anteprimaCorrente,
            });
          }
          if (makeCurrent) {
            this.showAnteprimaFile(saved);
          }
          if (announce) {
            this.success = 'Anteprima salvata.';
          }
          then?.();
        },
        error: error => {
          this.generatingPdf = false;
          this.renderingAnteprimaId = '';
          this.error = error?.error?.message ?? 'Impossibile salvare il file di anteprima.';
        },
      });
  }

  private showAnteprimaFile(saved: { path: string; html: string }): void {
    this.anteprimaPath = saved.path;
    this.anteprimaRichiesta = true;
    this.previewHtml = saved.html;
    this.previewPageCount = catalogPdfPageCount(saved.html);
    this.previewReady = saved.html.length > 0;
    this.previewLoading = false;
    if (!this.sanitizer) {
      return;
    }
    this.revokePreviewUrl();
    this.previewObjectUrl = URL.createObjectURL(new Blob([saved.html], { type: 'text/html;charset=utf-8' }));
    this.previewUrl = this.sanitizer.bypassSecurityTrustResourceUrl(this.previewObjectUrl);
  }

  private clearAnteprima(): void {
    this.anteprimaPath = '';
    this.anteprimaRichiesta = false;
    this.previewHtml = '';
    this.previewPageCount = 0;
    this.previewReady = false;
    this.previewUrl = null;
    this.revokePreviewUrl();
  }

  private refreshPdfPreview(afterPublish?: (html: string) => void): void {
    const ticket = ++this.previewTicket;
    const codes = this.usedLayoutCodes();
    const templates = new Map<string, string>();
    const headers = new Map<string, string>();
    for (const code of codes) {
      templates.set(code, defaultLayoutHtml(code));
      headers.set(code, code === 'articolo-semplice' ? defaultLayoutHeaderHtml(code) : '');
    }
    this.productSchede = new Map();

    const ids = this.previewProductIds();
    const loadLayouts = codes.length > 0 && !!this.layouts;
    if (!loadLayouts && !ids.length) {
      this.publishPreview(templates, headers, ticket, afterPublish);
      return;
    }

    this.previewLoading = true;
    let pending = (loadLayouts ? codes.length : 0) + (ids.length ? 1 : 0);
    const finish = (): void => {
      if (--pending === 0) {
        this.publishPreview(templates, headers, ticket, afterPublish);
      }
    };

    if (loadLayouts && this.layouts) {
      for (const code of codes) {
        this.layouts.get(code).subscribe({
          next: detail => {
            const saved = (detail.layout.htmlPreview || '').trim();
            templates.set(code, saved || defaultLayoutHtml(code));
            const savedHeader = (detail.layout.headerHtml || '').trim();
            headers.set(code, savedHeader || (code === 'articolo-semplice' ? defaultLayoutHeaderHtml(code) : ''));
            finish();
          },
          error: () => finish(),
        });
      }
    }

    if (ids.length) {
      this.products.byIds(ids).subscribe({
        next: items => {
          if (ticket === this.previewTicket) {
            this.productSchede = new Map(items.map(item => [item.id, item]));
          }
          finish();
        },
        error: () => finish(),
      });
    }
  }

  private previewProductIds(): number[] {
    const ids = new Set<number>();
    for (const section of this.sezioniAnteprima()) {
      for (const articolo of section.articoli) {
        if (articolo.productId > 0) {
          ids.add(articolo.productId);
        }
      }
    }
    return [...ids];
  }

  private usedLayoutCodes(): string[] {
    const codes = new Set<string>();
    for (const section of this.sezioniAnteprima()) {
      for (const articolo of section.articoli) {
        const layout = this.articleLayout(articolo);
        if (layout) {
          codes.add(layout);
        }
      }
    }
    return [...codes];
  }

  private publishPreview(
    layoutHtml: ReadonlyMap<string, string>,
    layoutHeaderHtml: ReadonlyMap<string, string>,
    ticket: number,
    afterPublish?: (html: string) => void,
  ): void {
    if (ticket !== this.previewTicket) {
      return;
    }
    const html = buildCatalogPdfDocument({
      titolo: this.nome || this.codice || 'Catalogo',
      codice: this.codice,
      theme: { ...this.sezioneSettings },
      sezioni: this.sezioniAnteprima().map(section => ({
        nome: section.nome,
        saltoPagina: section.saltoPagina,
        articoli: section.articoli.map(articolo => {
          const scheda = this.productSchede.get(articolo.productId);
          const prezzo = scheda?.price ?? '';
          const iva = scheda?.iva ?? '';
          return {
            sku: articolo.sku,
            nome: articolo.nome,
            saltoPagina: articolo.saltoPagina,
            layout: this.articleLayout(articolo),
            height: this.articleHeight(articolo) ?? 220,
            prezzo,
            iva,
            foto: scheda?.image ?? '',
            varPrezzo: prezzo,
            varMisure: scheda?.misure ?? '',
            varColore: scheda?.colore ?? '',
            varIva: iva,
          };
        }),
      })),
      layoutHtml,
      layoutHeaderHtml,
    });
    this.previewHtml = html;
    this.previewPageCount = catalogPdfPageCount(html);
    this.previewReady = html.length > 0;
    this.previewLoading = false;
    if (this.sanitizer) {
      this.revokePreviewUrl();
      this.previewObjectUrl = URL.createObjectURL(new Blob([html], { type: 'text/html;charset=utf-8' }));
      this.previewUrl = this.sanitizer.bypassSecurityTrustResourceUrl(this.previewObjectUrl);
    }
    afterPublish?.(html);
  }

  private sezioniAnteprima(): CatalogSezione[] {
    const id = this.renderingAnteprimaId || this.anteprimaCorrente;
    const corrente = this.anteprime.find(item => item.id === id) ?? null;
    if (!corrente) {
      return [];
    }
    return this.sezioni.filter((section, index) =>
      this.sectionMatches(section.nome, index, corrente.sezioni),
    );
  }

  private sectionMatches(nome: string, index: number, refs: CatalogAnteprimaSezione[]): boolean {
    const sameName = this.sezioni.filter(section => section.nome === nome).length;
    if (sameName <= 1) {
      return refs.some(ref => ref.nome === nome);
    }
    return refs.some(ref => ref.nome === nome && ref.posizione === index);
  }

  private applyAnteprimaState(state: CatalogAnteprimaState): void {
    this.anteprime = Array.isArray(state.anteprime) ? state.anteprime : [];
    this.anteprimaCorrente = state.anteprimaCorrente || '';
  }

  private applyPdfState(state: CatalogPdfState): void {
    this.pdfs = (Array.isArray(state.pdfs) ? state.pdfs : []).map(item => ({
      ...item,
      usaCopertina: !!item.usaCopertina,
      usaRetro: !!item.usaRetro,
    }));
    this.pdfCorrente = state.pdfCorrente || '';
  }

  private syncPdfFromCurrent(): void {
    const corrente = this.currentPdf();
    const path = corrente?.pathPdf || '';
    const filename = corrente?.filePdf || '';
    if (path !== this.pdfPath) {
      this.revokePdfUrl();
    }
    this.pdfPath = path;
    this.pdfFilename = filename;
  }

  private revokePreviewUrl(): void {
    if (this.previewObjectUrl) {
      URL.revokeObjectURL(this.previewObjectUrl);
      this.previewObjectUrl = null;
    }
  }

  private loadCovers(): void {
    if (!this.coversService) {
      return;
    }
    this.coversService.list().subscribe({
      next: items => {
        this.covers = [...items].sort((left, right) => left.nome.localeCompare(right.nome, 'it'));
        this.coversError = '';
      },
      error: () => {
        this.coversError = 'Impossibile caricare le copertine.';
      },
    });
  }

  private pdfUsoOf(pdf: CatalogPdfRecord): CatalogPdfUso {
    return {
      usaCopertina: !!pdf.usaCopertina,
      usaRetro: !!pdf.usaRetro,
    };
  }

  private enqueuePdfUso(id: string, uso: CatalogPdfUso, revert: () => void): void {
    this.pdfUsoQueue = this.pdfUsoQueue.then(() => new Promise<void>(resolve => {
      if (!this.codice) {
        resolve();
        return;
      }
      this.catalogs.updatePdfUso(this.codice, id, uso).subscribe({
        next: () => resolve(),
        error: error => {
          revert();
          this.error = error?.error?.message ?? 'Impossibile salvare l\'impostazione del PDF.';
          resolve();
        },
      });
    }));
  }

  private loadLayouts(): void {
    this.layouts?.list().subscribe({
      next: items => {
        const heights = new Map<string, number>([['articolo-semplice', 220]]);
        for (const item of items) {
          if (item.minRowHeight == null) {
            continue;
          }
          const height = Number(item.minRowHeight) || 0;
          if (height > 0 || item.codice !== 'articolo-semplice') {
            heights.set(item.codice, height);
          }
        }
        this.layoutHeights = heights;
      },
    });
  }

  private withLayout(articolo: CatalogArticolo, nested = false): CatalogArticolo {
    const variazioni = articolo.variazioni?.map(item => this.withLayout(item, true));
    if (nested) {
      return { ...articolo, variazioni };
    }
    const layout = articolo.layout || (!variazioni?.length ? 'articolo-semplice' : undefined);
    return { ...articolo, layout, variazioni };
  }

  private layoutForProduct(product: ProductItem, variazioni: CatalogArticolo[]): string | undefined {
    if (product.type === 'variation') {
      return undefined;
    }
    if (product.type === 'simple' || (!product.type && !variazioni.length)) {
      return 'articolo-semplice';
    }
    return undefined;
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
