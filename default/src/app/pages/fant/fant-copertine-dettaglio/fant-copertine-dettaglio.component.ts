import { Component, OnDestroy, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { finalize } from 'rxjs';

import {
  AiProviderId,
  AiSettings,
  AiSettingsService,
} from '../../../core/services/ai-settings.service';
import {
  CoverAllegato,
  CoverArticolo,
  CoverDetail,
  CoverService,
} from '../../../core/services/cover.service';
import { ProductItem, ProductService } from '../../../core/services/product.service';
import {
  WooCategory,
  WooCategoryService,
} from '../../../core/services/woo-category.service';

type CoverEditorView = 'allegati' | 'articoli' | 'chat';

interface CategoryNode extends WooCategory {
  children: CategoryNode[];
}

interface AiBarProvider {
  id: AiProviderId;
  label: string;
  model: string;
  active: boolean;
}

@Component({
  selector: 'app-fant-copertine-dettaglio',
  templateUrl: './fant-copertine-dettaglio.component.html',
  styleUrls: ['./fant-copertine-dettaglio.component.scss'],
  standalone: false,
})
export class FantCopertineDettaglioComponent implements OnInit, OnDestroy {
  readonly mainNavItems: Array<{ id: CoverEditorView; label: string; icon: string }> = [
    { id: 'allegati', label: 'Allegati', icon: 'ri-attachment-2' },
    { id: 'articoli', label: 'Articoli', icon: 'ri-shopping-bag-3-line' },
    { id: 'chat', label: 'Chat', icon: 'ri-chat-3-line' },
  ];

  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];
  codice = '';
  detail: CoverDetail | null = null;
  activeView: CoverEditorView = 'allegati';
  mainSidebarCollapsed = false;
  loading = false;
  saving = false;
  uploading = false;
  openingPdf = false;
  error = '';
  success = '';
  articoliDirty = false;

  aiProviders: AiBarProvider[] = [];
  aiLoading = false;

  selectedFiles: File[] = [];
  editingAliasNome: string | null = null;
  aliasDraft = '';

  addArticoloTab: 'prodotto' | 'categoria' = 'prodotto';
  searchTerm = '';
  searchResults: ProductItem[] = [];
  searchingProducts = false;
  private searchTimer: ReturnType<typeof setTimeout> | null = null;
  private searchRequestId = 0;

  flatCategories: WooCategory[] = [];
  categoryTree: CategoryNode[] = [];
  expandedCategoryIds = new Set<number>();
  selectedCategoryId: number | null = null;
  includeChildren = true;
  loadingCategories = false;

  chatDraft = '';
  chatMessages: Array<{ role: 'user' | 'assistant'; content: string }> = [];

  constructor(
    private readonly route: ActivatedRoute,
    private readonly router: Router,
    private readonly covers: CoverService,
    private readonly aiSettings: AiSettingsService,
    private readonly products: ProductService,
    private readonly categories: WooCategoryService,
  ) {}

  ngOnInit(): void {
    this.codice = this.route.snapshot.paramMap.get('codice') || '';
    this.breadCrumbItems = [
      { label: 'Cataloghi' },
      { label: 'Copertine/Retri' },
      { label: this.codice || 'Dettaglio', active: true },
    ];
    this.load();
    this.loadAiBar();
    this.loadCategories();
  }

  ngOnDestroy(): void {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
      this.searchTimer = null;
    }
  }

  get title(): string {
    return this.detail?.testata?.nome || this.codice || 'Copertina';
  }

  get activeAiLabel(): string {
    const active = this.aiProviders.find(item => item.active);
    if (active) {
      return `${active.label} (${active.model || 'modello n/d'})`;
    }
    return 'Nessuna AI attiva';
  }

  get currentPdfName(): string {
    return (this.detail?.pdf?.nome || '').trim();
  }

  get allegati(): CoverAllegato[] {
    return this.detail?.allegati ?? [];
  }

  get articoli(): CoverArticolo[] {
    return this.detail?.articoli ?? [];
  }

  load(): void {
    if (!this.codice) {
      this.error = 'Codice copertina non valido.';
      return;
    }
    this.loading = true;
    this.error = '';
    this.covers.get(this.codice).pipe(finalize(() => this.loading = false)).subscribe({
      next: detail => {
        this.applyDetail(detail);
      },
      error: error => {
        this.error = error?.error?.message ?? 'Impossibile caricare la copertina.';
      },
    });
  }

  loadAiBar(): void {
    this.aiLoading = true;
    this.aiSettings.get().pipe(finalize(() => this.aiLoading = false)).subscribe({
      next: settings => {
        this.aiProviders = this.mapAiProviders(settings);
      },
      error: () => {
        this.aiProviders = [];
      },
    });
  }

  selectMainView(view: CoverEditorView): void {
    if (view === this.activeView) {
      return;
    }
    if (this.activeView === 'articoli' && this.articoliDirty) {
      const confirmed = window.confirm('Ci sono modifiche non salvate agli articoli. Uscire senza salvare?');
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

  selectFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFiles = input.files ? Array.from(input.files) : [];
  }

  uploadSelectedFiles(): void {
    if (!this.codice || !this.selectedFiles.length) {
      return;
    }
    this.uploading = true;
    this.error = '';
    this.success = '';
    this.covers.uploadAttachments(this.codice, this.selectedFiles)
      .pipe(finalize(() => this.uploading = false))
      .subscribe({
        next: detail => {
          this.applyDetail(detail);
          this.selectedFiles = [];
          this.success = 'Allegati caricati.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Upload allegati fallito.';
        },
      });
  }

  startEditAlias(item: CoverAllegato): void {
    this.editingAliasNome = item.nome;
    this.aliasDraft = item.alias;
  }

  cancelEditAlias(): void {
    this.editingAliasNome = null;
    this.aliasDraft = '';
  }

  saveAlias(item: CoverAllegato): void {
    if (!this.codice) {
      return;
    }
    const alias = this.aliasDraft.trim();
    if (!alias) {
      this.error = 'Il nome per la chat è obbligatorio.';
      return;
    }
    this.saving = true;
    this.error = '';
    this.covers.updateAttachmentAlias(this.codice, item.nome, alias)
      .pipe(finalize(() => this.saving = false))
      .subscribe({
        next: detail => {
          this.applyDetail(detail);
          this.cancelEditAlias();
          this.success = 'Nome allegato aggiornato.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Aggiornamento allegato fallito.';
        },
      });
  }

  removeAllegato(item: CoverAllegato): void {
    if (!this.codice) {
      return;
    }
    const confirmed = window.confirm(`Eliminare l'allegato "${item.nome}"?`);
    if (!confirmed) {
      return;
    }
    this.saving = true;
    this.error = '';
    this.covers.deleteAttachment(this.codice, item.nome)
      .pipe(finalize(() => this.saving = false))
      .subscribe({
        next: detail => {
          this.applyDetail(detail);
          this.success = 'Allegato eliminato.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Eliminazione allegato fallita.';
        },
      });
  }

  onSearchInput(value: string): void {
    this.searchTerm = value;
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }
    const term = value.trim();
    if (term.length < 2) {
      this.searchResults = [];
      return;
    }
    this.searchTimer = setTimeout(() => this.runProductSearch(term), 300);
  }

  addProduct(item: ProductItem): void {
    if (!item.sku) {
      this.error = 'Il prodotto non ha SKU.';
      return;
    }
    if (this.articoli.some(a => a.tipo === 'product' && a.productId === item.id)) {
      this.error = 'Articolo già presente.';
      return;
    }
    const next: CoverArticolo = {
      id: `p-${item.id}`,
      tipo: 'product',
      productId: item.id,
      sku: item.sku,
      nome: item.name,
      alias: item.name,
    };
    this.patchArticoli([...this.articoli, next]);
  }

  selectCategory(categoryId: number): void {
    this.selectedCategoryId = categoryId;
  }

  toggleCategory(categoryId: number): void {
    const next = new Set(this.expandedCategoryIds);
    if (next.has(categoryId)) {
      next.delete(categoryId);
    } else {
      next.add(categoryId);
    }
    this.expandedCategoryIds = next;
  }

  isCategoryExpanded(categoryId: number): boolean {
    return this.expandedCategoryIds.has(categoryId);
  }

  addSelectedCategory(): void {
    if (this.selectedCategoryId == null) {
      this.error = 'Seleziona una categoria.';
      return;
    }
    const category = this.flatCategories.find(item => item.id === this.selectedCategoryId);
    if (!category) {
      this.error = 'Categoria non trovata.';
      return;
    }
    if (this.articoli.some(a => a.tipo === 'category' && a.categoryId === category.id)) {
      this.error = 'Categoria già presente.';
      return;
    }
    const next: CoverArticolo = {
      id: `c-${category.id}`,
      tipo: 'category',
      categoryId: category.id,
      includeChildren: this.includeChildren,
      nome: category.name,
      alias: category.name,
    };
    this.patchArticoli([...this.articoli, next]);
  }

  updateArticoloAlias(index: number, value: string): void {
    const list = this.articoli.map((item, i) => i === index ? { ...item, alias: value } : item);
    this.patchArticoli(list);
  }

  removeArticolo(index: number): void {
    this.patchArticoli(this.articoli.filter((_, i) => i !== index));
  }

  saveArticoli(): void {
    if (!this.codice || !this.detail) {
      return;
    }
    this.saving = true;
    this.error = '';
    this.success = '';
    this.covers.updateArticoli(this.codice, this.articoli)
      .pipe(finalize(() => this.saving = false))
      .subscribe({
        next: detail => {
          this.applyDetail(detail);
          this.articoliDirty = false;
          this.success = 'Articoli salvati.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Salvataggio articoli fallito.';
        },
      });
  }

  sendChatPlaceholder(): void {
    const text = this.chatDraft.trim();
    if (!text) {
      return;
    }
    this.chatMessages = [
      ...this.chatMessages,
      { role: 'user', content: text },
      {
        role: 'assistant',
        content: 'La chat AI sarà collegata al provider attivo in Settings → AI. Per ora le istruzioni restano in bozza locale.',
      },
    ];
    this.chatDraft = '';
  }

  back(): void {
    this.router.navigate(['/fant-copertine']);
  }

  openCurrentPdf(): void {
    if (!this.codice || this.openingPdf) {
      return;
    }
    this.openingPdf = true;
    this.error = '';
    this.covers.downloadPdf(this.codice).pipe(finalize(() => this.openingPdf = false)).subscribe({
      next: pdf => {
        const bytes = Uint8Array.from(atob(pdf.contentBase64), char => char.charCodeAt(0));
        const url = URL.createObjectURL(new Blob([bytes], { type: pdf.mimeType || 'application/pdf' }));
        const opened = window.open(url, '_blank', 'noopener,noreferrer');
        if (!opened) {
          // Popup bloccato: fallback download.
          const link = document.createElement('a');
          link.href = url;
          link.download = pdf.filename || this.currentPdfName || 'copertina.pdf';
          link.click();
        }
        window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
      },
      error: error => {
        this.error = error?.error?.message ?? 'Impossibile aprire il PDF.';
      },
    });
  }

  private applyDetail(detail: CoverDetail): void {
    this.detail = {
      ...detail,
      allegati: Array.isArray(detail.allegati) ? detail.allegati : [],
      articoli: Array.isArray(detail.articoli) ? detail.articoli : [],
      chat: detail.chat ?? { messages: [], previewHtml: null },
    };
    this.articoliDirty = false;
    this.breadCrumbItems = [
      { label: 'Cataloghi' },
      { label: 'Copertine/Retri' },
      { label: detail.testata.nome, active: true },
    ];
    if (detail.chat?.messages?.length) {
      this.chatMessages = detail.chat.messages
        .filter(m => m.role === 'user' || m.role === 'assistant')
        .map(m => ({ role: m.role as 'user' | 'assistant', content: m.content }));
    }
  }

  private patchArticoli(list: CoverArticolo[]): void {
    if (!this.detail) {
      return;
    }
    this.detail = { ...this.detail, articoli: list };
    this.articoliDirty = true;
    this.success = '';
    this.error = '';
  }

  private loadCategories(): void {
    this.loadingCategories = true;
    this.categories.list().pipe(finalize(() => this.loadingCategories = false)).subscribe({
      next: categories => {
        this.flatCategories = categories;
        this.categoryTree = this.buildTree(categories);
        this.expandedCategoryIds = new Set(categories.map(item => item.id));
      },
      error: () => {
        this.error = 'Impossibile caricare le categorie.';
      },
    });
  }

  private runProductSearch(term: string): void {
    const requestId = ++this.searchRequestId;
    this.searchingProducts = true;
    this.products.search(term, 1, 20).pipe(finalize(() => {
      if (requestId === this.searchRequestId) {
        this.searchingProducts = false;
      }
    })).subscribe({
      next: result => {
        if (requestId !== this.searchRequestId) {
          return;
        }
        this.searchResults = result.items ?? [];
      },
      error: () => {
        if (requestId === this.searchRequestId) {
          this.searchResults = [];
          this.error = 'Ricerca prodotti fallita.';
        }
      },
    });
  }

  private buildTree(categories: WooCategory[]): CategoryNode[] {
    const map = new Map<number, CategoryNode>();
    categories.forEach(category => {
      map.set(category.id, { ...category, children: [] });
    });
    const roots: CategoryNode[] = [];
    map.forEach(node => {
      if (node.parent && map.has(node.parent)) {
        map.get(node.parent)!.children.push(node);
      } else {
        roots.push(node);
      }
    });
    const sortNodes = (nodes: CategoryNode[]) => {
      nodes.sort((a, b) => a.menuOrder - b.menuOrder || a.name.localeCompare(b.name, 'it'));
      nodes.forEach(node => sortNodes(node.children));
    };
    sortNodes(roots);
    return roots;
  }

  private mapAiProviders(settings: AiSettings): AiBarProvider[] {
    const labels: Record<AiProviderId, string> = {
      gemini: 'Gemini',
      openai: 'OpenAI',
      claude: 'Claude',
      'free-gemini': 'Gemini Free',
      'free-groq': 'Groq Free',
      'free-openrouter': 'OpenRouter Free',
    };
    const order: AiProviderId[] = [
      'openai',
      'gemini',
      'claude',
      'free-gemini',
      'free-groq',
      'free-openrouter',
    ];
    const enabled = order
      .map(id => {
        const provider = settings[id];
        return {
          id,
          label: labels[id],
          model: provider?.model || '',
          active: false,
          ready: !!provider?.enabled && !!provider?.apiKeyConfigured,
        };
      })
      .filter(item => item.ready);

    if (enabled.length) {
      enabled[0].active = true;
    }
    return enabled.map(({ id, label, model, active }) => ({ id, label, model, active }));
  }
}
