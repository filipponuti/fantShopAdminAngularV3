import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { finalize } from 'rxjs';

import { CatalogService, CatalogSummary } from '../../../core/services/catalog.service';

type CatalogSortField = 'codice' | 'nome' | 'numeroProdotti' | 'updatedAt';

@Component({
  selector: 'app-fant-cataloghi',
  templateUrl: './fant-cataloghi.component.html',
  styleUrls: ['./fant-cataloghi.component.scss'],
  standalone: false
})
export class FantCataloghiComponent implements OnInit {
  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];
  catalogForm!: FormGroup;
  catalogs: CatalogSummary[] = [];
  editingCode: string | null = null;
  deletingCatalog: CatalogSummary | null = null;
  searchTerm = '';
  page = 1;
  readonly pageSize = 10;
  sortField: CatalogSortField = 'nome';
  sortDirection: 'asc' | 'desc' = 'asc';
  loading = false;
  saving = false;
  deleting = false;
  error = '';
  success = '';

  constructor(
    private readonly formBuilder: FormBuilder,
    private readonly modalService: NgbModal,
    private readonly catalogService: CatalogService
  ) {}

  ngOnInit(): void {
    this.breadCrumbItems = [
      { label: 'Shop' },
      { label: 'Cataloghi', active: true }
    ];
    this.catalogForm = this.formBuilder.group({
      codice: [
        '',
        [
          Validators.required,
          Validators.maxLength(80),
          Validators.pattern(/^[a-z0-9][a-z0-9_-]*$/)
        ]
      ],
      nome: ['', [Validators.required, Validators.maxLength(180)]]
    });
    this.loadCatalogs();
  }

  get filteredCatalogs(): CatalogSummary[] {
    const term = this.searchTerm.trim().toLocaleLowerCase('it');
    const filtered = term
      ? this.catalogs.filter(catalog =>
          catalog.codice.toLocaleLowerCase('it').includes(term) ||
          catalog.nome.toLocaleLowerCase('it').includes(term)
        )
      : [...this.catalogs];

    return filtered.sort((left, right) => {
      const leftValue = left[this.sortField];
      const rightValue = right[this.sortField];
      const result = typeof leftValue === 'number' && typeof rightValue === 'number'
        ? leftValue - rightValue
        : String(leftValue).localeCompare(String(rightValue), 'it', { sensitivity: 'base' });
      return this.sortDirection === 'asc' ? result : -result;
    });
  }

  get visibleCatalogs(): CatalogSummary[] {
    const start = (this.page - 1) * this.pageSize;
    return this.filteredCatalogs.slice(start, start + this.pageSize);
  }

  get firstVisibleItem(): number {
    return this.filteredCatalogs.length ? (this.page - 1) * this.pageSize + 1 : 0;
  }

  get lastVisibleItem(): number {
    return Math.min(this.page * this.pageSize, this.filteredCatalogs.length);
  }

  loadCatalogs(): void {
    this.loading = true;
    this.error = '';
    this.catalogService.list().pipe(finalize(() => this.loading = false)).subscribe({
      next: catalogs => {
        this.catalogs = catalogs;
        this.ensureValidPage();
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile caricare i cataloghi.')
    });
  }

  setSearch(value: string): void {
    this.searchTerm = value;
    this.page = 1;
  }

  sortBy(field: CatalogSortField): void {
    if (this.sortField === field) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortField = field;
      this.sortDirection = 'asc';
    }
    this.page = 1;
  }

  sortIcon(field: CatalogSortField): string {
    if (this.sortField !== field) {
      return 'ri-expand-up-down-line';
    }
    return this.sortDirection === 'asc' ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line';
  }

  openCreate(content: unknown): void {
    this.editingCode = null;
    this.error = '';
    this.success = '';
    this.catalogForm.reset({ codice: '', nome: '' });
    this.catalogForm.controls['codice'].enable();
    this.modalService.open(content, { size: 'md', centered: true });
  }

  openEdit(catalog: CatalogSummary, content: unknown): void {
    this.editingCode = catalog.codice;
    this.error = '';
    this.success = '';
    this.catalogForm.reset({ codice: catalog.codice, nome: catalog.nome });
    this.catalogForm.controls['codice'].disable();
    this.modalService.open(content, { size: 'md', centered: true });
  }

  save(): void {
    this.catalogForm.markAllAsTouched();
    if (this.catalogForm.invalid || this.saving) {
      return;
    }

    const value = this.catalogForm.getRawValue();
    const isEditing = this.editingCode !== null;
    const request = isEditing
      ? this.catalogService.update(this.editingCode!, { nome: value.nome })
      : this.catalogService.create({ codice: value.codice, nome: value.nome });

    this.saving = true;
    this.error = '';
    request.pipe(finalize(() => this.saving = false)).subscribe({
      next: catalog => {
        this.modalService.dismissAll();
        this.success = isEditing ? 'Catalogo aggiornato.' : 'Catalogo creato.';
        this.editingCode = null;
        this.upsertCatalog(catalog);
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile salvare il catalogo.')
    });
  }

  openDelete(catalog: CatalogSummary, content: unknown): void {
    this.deletingCatalog = catalog;
    this.error = '';
    this.modalService.open(content, { centered: true });
  }

  confirmDelete(): void {
    if (!this.deletingCatalog || this.deleting) {
      return;
    }

    const catalog = this.deletingCatalog;
    this.deleting = true;
    this.catalogService.delete(catalog.codice).pipe(finalize(() => this.deleting = false)).subscribe({
      next: () => {
        this.modalService.dismissAll();
        this.catalogs = this.catalogs.filter(item => item.codice !== catalog.codice);
        this.deletingCatalog = null;
        this.success = 'Catalogo eliminato.';
        this.ensureValidPage();
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile eliminare il catalogo.')
    });
  }

  private upsertCatalog(catalog: CatalogSummary): void {
    const index = this.catalogs.findIndex(item => item.codice === catalog.codice);
    this.catalogs = index >= 0
      ? this.catalogs.map(item => item.codice === catalog.codice ? catalog : item)
      : [...this.catalogs, catalog];
    this.ensureValidPage();
  }

  private ensureValidPage(): void {
    const lastPage = Math.max(1, Math.ceil(this.filteredCatalogs.length / this.pageSize));
    this.page = Math.min(this.page, lastPage);
  }

  private errorMessage(error: any, fallback: string): string {
    return error?.error?.message ?? error?.message ?? fallback;
  }
}
