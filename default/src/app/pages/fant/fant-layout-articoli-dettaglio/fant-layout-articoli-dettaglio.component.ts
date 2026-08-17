import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import { finalize } from 'rxjs';

import { LayoutDetail, LayoutService } from '../../../core/services/layout.service';
import { defaultLayoutHtml } from './layout-template-defaults';

@Component({
  selector: 'app-fant-layout-articoli-dettaglio',
  templateUrl: './fant-layout-articoli-dettaglio.component.html',
  styleUrls: ['./fant-layout-articoli-dettaglio.component.scss'],
  standalone: false,
})
export class FantLayoutArticoliDettaglioComponent implements OnInit {
  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];
  codice = '';
  detail: LayoutDetail | null = null;
  loading = false;
  saving = false;
  error = '';
  success = '';
  activeTemplateTab: 'anteprima' | 'editor' = 'anteprima';
  readonly emptyVariantLines = [1, 2, 3, 4, 5, 6];
  public Editor = ClassicEditor;
  public editorData = '';

  constructor(
    private readonly route: ActivatedRoute,
    private readonly router: Router,
    private readonly layouts: LayoutService,
  ) {}

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
        this.editorData = saved || defaultLayoutHtml(detail.testata.codice);
      },
      error: error => {
        this.error = error?.error?.message ?? 'Impossibile caricare il layout.';
      },
    });
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

  back(): void {
    this.router.navigate(['/fant-layout-articoli']);
  }
}
