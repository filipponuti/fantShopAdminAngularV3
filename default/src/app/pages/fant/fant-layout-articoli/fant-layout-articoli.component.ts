import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';

import { LayoutService, LayoutSummary } from '../../../core/services/layout.service';

type LayoutSortField = 'codice' | 'nome' | 'family' | 'updatedAt';

@Component({
  selector: 'app-fant-layout-articoli',
  templateUrl: './fant-layout-articoli.component.html',
  styleUrls: ['./fant-layout-articoli.component.scss'],
  standalone: false,
})
export class FantLayoutArticoliComponent implements OnInit {
  breadCrumbItems = [{ label: 'Cataloghi' }, { label: 'Layout Articoli', active: true }];
  layouts: LayoutSummary[] = [];
  searchTerm = '';
  page = 1;
  readonly pageSize = 10;
  sortField: LayoutSortField = 'nome';
  sortDirection: 'asc' | 'desc' = 'asc';
  loading = false;
  error = '';

  constructor(
    private readonly layoutService: LayoutService,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    this.load();
  }

  get filteredLayouts(): LayoutSummary[] {
    const term = this.searchTerm.trim().toLocaleLowerCase('it');
    const result = term
      ? this.layouts.filter(item =>
          item.codice.includes(term)
          || item.nome.toLocaleLowerCase('it').includes(term)
          || item.family.toLocaleLowerCase('it').includes(term))
      : [...this.layouts];

    return result.sort((a, b) => {
      const left = a[this.sortField];
      const right = b[this.sortField];
      const comparison = String(left).localeCompare(String(right), 'it');
      return this.sortDirection === 'asc' ? comparison : -comparison;
    });
  }

  get visibleLayouts(): LayoutSummary[] {
    return this.filteredLayouts.slice((this.page - 1) * this.pageSize, this.page * this.pageSize);
  }

  get firstVisibleItem(): number {
    return this.filteredLayouts.length ? (this.page - 1) * this.pageSize + 1 : 0;
  }

  get lastVisibleItem(): number {
    return Math.min(this.page * this.pageSize, this.filteredLayouts.length);
  }

  load(): void {
    this.loading = true;
    this.error = '';
    this.layoutService.list().pipe(finalize(() => this.loading = false)).subscribe({
      next: layouts => {
        this.layouts = layouts;
        this.ensureValidPage();
      },
      error: error => {
        this.error = error?.error?.message ?? 'Impossibile caricare i layout articoli.';
      },
    });
  }

  setSearch(value: string): void {
    this.searchTerm = value;
    this.page = 1;
  }

  sortBy(field: LayoutSortField): void {
    if (this.sortField === field) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortField = field;
      this.sortDirection = 'asc';
    }
  }

  sortIcon(field: LayoutSortField): string {
    if (this.sortField !== field) {
      return 'ri-arrow-up-down-line text-muted';
    }
    return this.sortDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line';
  }

  openDetail(layout: LayoutSummary): void {
    this.router.navigate(['/fant-layout-articoli', layout.codice]);
  }

  familyLabel(family: string): string {
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

  private ensureValidPage(): void {
    const maxPage = Math.max(1, Math.ceil(this.filteredLayouts.length / this.pageSize) || 1);
    if (this.page > maxPage) {
      this.page = maxPage;
    }
  }
}
