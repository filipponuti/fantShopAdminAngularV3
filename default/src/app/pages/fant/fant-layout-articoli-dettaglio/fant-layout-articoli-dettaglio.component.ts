import { Component, OnDestroy, OnInit } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { ActivatedRoute, Router } from '@angular/router';
import { NgbNavChangeEvent } from '@ng-bootstrap/ng-bootstrap';
import { finalize } from 'rxjs';

import { buildLayoutHtmlPreview } from '../fant-cataloghi-articoli/catalog-pdf-html';
import { LayoutDetail, LayoutService } from '../../../core/services/layout.service';
import { ProductItem, ProductService } from '../../../core/services/product.service';
import { defaultLayoutHeaderHtml, defaultLayoutHtml } from './layout-template-defaults';

@Component({
  selector: 'app-fant-layout-articoli-dettaglio',
  templateUrl: './fant-layout-articoli-dettaglio.component.html',
  styleUrls: ['./fant-layout-articoli-dettaglio.component.scss'],
  standalone: false,
})
export class FantLayoutArticoliDettaglioComponent implements OnInit, OnDestroy {
  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];
  codice = '';
  detail: LayoutDetail | null = null;
  loading = false;
  saving = false;
  savingHeader = false;
  savingHeight = false;
  savingExample = false;
  exampleSku = '';
  exampleName = '';
  exampleDraftSku = '';
  exampleDraftName = '';
  exampleSearch = '';
  exampleResults: ProductItem[] = [];
  exampleImage = '';
  searchingExample = false;
  minRowHeight = 0;
  error = '';
  success = '';
  activeTemplateTab: 'modello' | 'intestazione' | 'intestazioneHtml' | 'anteprima' | 'editor' = 'modello';
  readonly emptyVariantLines = [1, 2, 3, 4, 5, 6];
  editorData = '';
  headerEditorData = '';
  previewUrl: SafeResourceUrl | null = null;
  headerPreviewUrl: SafeResourceUrl | null = null;
  readonly placeholderHint = '{{SKU}} {{NOME}} {{PREZZO}} {{NOTE}} {{IVA}} {{VAR_SKU}} {{VAR_PREZZO}} {{VAR_MISURE}} {{VAR_COLORE}} {{VAR_IVA}}';
  private previewObjectUrl: string | null = null;
  private headerPreviewObjectUrl: string | null = null;
  private exampleSearchTimer: ReturnType<typeof setTimeout> | null = null;
  private exampleSearchRequest = 0;
  private exampleImageRequest = 0;

  constructor(
    private readonly route: ActivatedRoute,
    private readonly router: Router,
    private readonly layouts: LayoutService,
    private readonly products: ProductService,
    private readonly sanitizer: DomSanitizer,
  ) {}

  ngOnDestroy(): void {
    if (this.exampleSearchTimer) {
      clearTimeout(this.exampleSearchTimer);
      this.exampleSearchTimer = null;
    }
    this.revokePreviewUrl();
    this.revokeHeaderPreviewUrl();
  }

  ngOnInit(): void {
    this.codice = this.route.snapshot.paramMap.get('codice') || '';
    this.breadCrumbItems = [
      { label: 'Cataloghi' },
      { label: 'Layout Articoli' },
      { label: this.codice || 'Dettaglio', active: true },
    ];
    this.load();
  }

  get title(): string {
    return this.detail?.testata?.nome || 'Layout Articoli';
  }

  get isArticoloSemplice(): boolean {
    return this.detail?.testata?.codice === 'articolo-semplice'
      || this.detail?.layout?.family === 'semplice';
  }

  load(): void {
    if (!this.codice) {
      this.error = 'Codice layout non valido.';
      return;
    }

    this.loading = true;
    this.error = '';
    this.success = '';
    this.layouts.get(this.codice).pipe(finalize(() => this.loading = false)).subscribe({
      next: detail => {
        this.detail = detail;
        this.breadCrumbItems = [
          { label: 'Cataloghi' },
          { label: 'Layout Articoli' },
          { label: detail.testata.nome, active: true },
        ];
        const saved = (detail.layout.htmlPreview || '').trim();
        const fresh = defaultLayoutHtml(detail.testata.codice);
        this.editorData = !saved || saved.includes('fantini-catalogo-') ? fresh : saved;
        const savedHeader = (detail.layout.headerHtml || '').trim();
        this.headerEditorData = savedHeader || defaultLayoutHeaderHtml(detail.testata.codice);
        this.minRowHeight = Number(detail.layout.minRowHeight) || 0;
        this.applyExample(detail);
        this.refreshHtmlPreview();
        this.refreshHeaderPreview();
      },
      error: error => {
        this.error = error?.error?.message ?? 'Impossibile caricare il layout.';
      },
    });
  }

  get exampleDirty(): boolean {
    return this.exampleDraftSku !== this.exampleSku || this.exampleDraftName !== this.exampleName;
  }

  onExampleSearch(value: string): void {
    this.exampleSearch = value;
    const requestId = ++this.exampleSearchRequest;
    if (this.exampleSearchTimer) {
      clearTimeout(this.exampleSearchTimer);
    }
    const term = value.trim();
    if (!term) {
      this.exampleResults = [];
      this.searchingExample = false;
      return;
    }
    this.exampleSearchTimer = setTimeout(() => {
      this.exampleSearchTimer = null;
      this.searchingExample = true;
      this.products.search(term, 1, 20)
        .pipe(finalize(() => {
          if (requestId === this.exampleSearchRequest) {
            this.searchingExample = false;
          }
        }))
        .subscribe({
          next: result => {
            if (requestId === this.exampleSearchRequest) {
              this.exampleResults = result.items;
            }
          },
          error: () => {
            if (requestId === this.exampleSearchRequest) {
              this.exampleResults = [];
              this.error = 'Ricerca articoli fallita.';
            }
          },
        });
    }, 300);
  }

  selectExample(product: ProductItem): void {
    this.exampleDraftSku = product.sku || product.code || '';
    this.exampleDraftName = product.name || '';
    this.exampleResults = [];
    this.exampleSearch = '';
    this.error = '';
    this.resolveExampleImage(this.exampleDraftSku, product.id, product.image);
  }

  saveExample(): void {
    if (!this.codice) {
      this.error = 'Codice layout non valido.';
      return;
    }
    const sku = this.exampleDraftSku.trim();
    if (!sku) {
      this.error = 'Cerca e scegli l\'articolo di esempio.';
      this.success = '';
      return;
    }
    this.savingExample = true;
    this.error = '';
    this.success = '';
    this.layouts.update(this.codice, { exampleArticle: { sku, name: this.exampleDraftName.trim() } })
      .pipe(finalize(() => this.savingExample = false))
      .subscribe({
        next: detail => {
          this.detail = detail;
          this.applyExample(detail);
          this.refreshHtmlPreview();
          this.success = 'Articolo di esempio salvato.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Salvataggio articolo di esempio fallito.';
        },
      });
  }

  onTemplateTab(event: NgbNavChangeEvent): void {
    if (event.nextId === 'anteprima') {
      this.refreshHtmlPreview();
    }
    if (event.nextId === 'intestazione') {
      this.refreshHeaderPreview();
    }
  }

  familyLabel(family: string | undefined): string {
    switch (family) {
      case 'semplice':
        return 'Semplice';
      case 'variazioni':
        return 'Variazioni';
      case 'nutrizione':
        return 'Nutrizione';
      default:
        return family || '—';
    }
  }

  saveMinRowHeight(): void {
    if (!this.codice) {
      this.error = 'Codice layout non valido.';
      return;
    }
    const height = Number(this.minRowHeight);
    if (!Number.isInteger(height) || height < 0 || height > 2000) {
      this.error = 'L\'altezza minima deve essere un numero intero di pixel tra 0 e 2000.';
      this.success = '';
      return;
    }

    this.savingHeight = true;
    this.error = '';
    this.success = '';
    this.layouts.update(this.codice, { minRowHeight: height })
      .pipe(finalize(() => this.savingHeight = false))
      .subscribe({
        next: detail => {
          this.detail = detail;
          this.minRowHeight = Number(detail.layout.minRowHeight) || 0;
          this.success = 'Altezza minima salvata.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Salvataggio altezza fallito.';
        },
      });
  }

  saveTemplate(): void {
    if (!this.codice) {
      this.error = 'Codice layout non valido.';
      return;
    }

    this.saving = true;
    this.error = '';
    this.success = '';
    this.layouts.update(this.codice, { htmlPreview: this.editorData })
      .pipe(finalize(() => this.saving = false))
      .subscribe({
        next: detail => {
          this.detail = detail;
          this.success = 'Template salvato.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Salvataggio template fallito.';
        },
      });
  }

  restoreTemplate(): void {
    const confirmed = window.confirm(
      'Ripristinare il template predefinito? Le modifiche non salvate andranno perse.',
    );
    if (!confirmed) {
      return;
    }
    this.editorData = defaultLayoutHtml(this.codice);
    this.success = '';
    this.error = '';
  }

  saveHeader(): void {
    if (!this.codice) {
      this.error = 'Codice layout non valido.';
      return;
    }
    this.savingHeader = true;
    this.error = '';
    this.success = '';
    this.layouts.update(this.codice, { headerHtml: this.headerEditorData })
      .pipe(finalize(() => this.savingHeader = false))
      .subscribe({
        next: detail => {
          this.detail = detail;
          this.headerEditorData = (detail.layout.headerHtml || '').trim() || this.headerEditorData;
          this.refreshHeaderPreview();
          this.success = 'Intestazione salvata.';
        },
        error: error => {
          this.error = error?.error?.message ?? 'Salvataggio intestazione fallito.';
        },
      });
  }

  restoreHeader(): void {
    const confirmed = window.confirm(
      'Ripristinare l\'intestazione predefinita? Le modifiche non salvate andranno perse.',
    );
    if (!confirmed) {
      return;
    }
    this.headerEditorData = defaultLayoutHeaderHtml(this.codice);
    this.success = '';
    this.error = '';
  }

  back(): void {
    this.router.navigate(['/fant-layout-articoli']);
  }

  private refreshHtmlPreview(): void {
    const example = this.exampleSku || this.exampleImage
      ? { sku: this.exampleSku, nome: this.exampleName, foto: this.exampleImage }
      : undefined;
    const html = buildLayoutHtmlPreview(this.editorData || defaultLayoutHtml(this.codice), example);
    this.revokePreviewUrl();
    this.previewObjectUrl = URL.createObjectURL(new Blob([html], { type: 'text/html;charset=utf-8' }));
    this.previewUrl = this.sanitizer.bypassSecurityTrustResourceUrl(this.previewObjectUrl);
  }

  private refreshHeaderPreview(): void {
    const html = buildLayoutHtmlPreview(this.headerEditorData || defaultLayoutHeaderHtml(this.codice));
    this.revokeHeaderPreviewUrl();
    this.headerPreviewObjectUrl = URL.createObjectURL(new Blob([html], { type: 'text/html;charset=utf-8' }));
    this.headerPreviewUrl = this.sanitizer.bypassSecurityTrustResourceUrl(this.headerPreviewObjectUrl);
  }

  private applyExample(detail: LayoutDetail): void {
    const example = detail.layout.exampleArticle;
    this.exampleSku = example?.sku?.trim() || '';
    this.exampleName = example?.name?.trim() || '';
    this.exampleDraftSku = this.exampleSku;
    this.exampleDraftName = this.exampleName;
    this.resolveExampleImage(this.exampleSku);
  }

  private resolveExampleImage(sku: string, productId = 0, knownImage?: string): void {
    const request = ++this.exampleImageRequest;
    if (!knownImage) {
      this.exampleImage = '';
    }
    const apply = (url: string): void => {
      if (request !== this.exampleImageRequest) {
        return;
      }
      this.exampleImage = url;
      this.refreshHtmlPreview();
    };
    if (knownImage) {
      apply(knownImage);
      return;
    }
    const loadById = (id: number): void => {
      if (id <= 0) {
        apply('');
        return;
      }
      this.products.byIds([id]).subscribe({
        next: items => apply(items.find(item => item.id === id)?.image || ''),
        error: () => apply(''),
      });
    };
    if (productId > 0) {
      loadById(productId);
      return;
    }
    const term = sku.trim();
    if (!term) {
      apply('');
      return;
    }
    this.products.search(term, 1, 20).subscribe({
      next: result => {
        if (request !== this.exampleImageRequest) {
          return;
        }
        const wanted = term.toLowerCase();
        const match = result.items.find(item =>
          (item.sku || '').trim().toLowerCase() === wanted
          || (item.code || '').trim().toLowerCase() === wanted,
        );
        if (!match) {
          apply('');
          return;
        }
        if (match.image) {
          apply(match.image);
          return;
        }
        loadById(match.id);
      },
      error: () => apply(''),
    });
  }

  private revokePreviewUrl(): void {
    if (this.previewObjectUrl) {
      URL.revokeObjectURL(this.previewObjectUrl);
      this.previewObjectUrl = null;
    }
  }

  private revokeHeaderPreviewUrl(): void {
    if (this.headerPreviewObjectUrl) {
      URL.revokeObjectURL(this.headerPreviewObjectUrl);
      this.headerPreviewObjectUrl = null;
    }
  }
}
