import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { finalize, switchMap } from 'rxjs';

import { CoverService, CoverSummary } from '../../../core/services/cover.service';

type CoverSortField = 'codice' | 'nome' | 'numeroAllegati' | 'updatedAt';

@Component({
  selector: 'app-fant-copertine',
  templateUrl: './fant-copertine.component.html',
  styleUrls: ['./fant-copertine.component.scss'],
  standalone: false
})
export class FantCopertineComponent implements OnInit {
  breadCrumbItems = [{ label: 'Cataloghi' }, { label: 'Copertine', active: true }];
  coverForm!: FormGroup;
  covers: CoverSummary[] = [];
  editingCode: string | null = null;
  deletingCover: CoverSummary | null = null;
  selectedFiles: File[] = [];
  searchTerm = '';
  page = 1;
  readonly pageSize = 10;
  sortField: CoverSortField = 'nome';
  sortDirection: 'asc' | 'desc' = 'asc';
  loading = false;
  saving = false;
  deleting = false;
  downloadingCode: string | null = null;
  error = '';
  success = '';
  private pdfNameManuallyEdited = false;

  constructor(
    private readonly formBuilder: FormBuilder,
    private readonly modalService: NgbModal,
    private readonly coverService: CoverService
  ) {}

  ngOnInit(): void {
    this.coverForm = this.formBuilder.group({
      codice: ['', [Validators.required, Validators.maxLength(80), Validators.pattern(/^[a-z0-9][a-z0-9_-]*$/)]],
      nome: ['', [Validators.required, Validators.maxLength(180)]],
      nomeFilePdf: ['', [Validators.required, Validators.maxLength(180), Validators.pattern(/^[a-zA-Z0-9][a-zA-Z0-9._-]*\.pdf$/i)]]
    });
    this.coverForm.controls['codice'].valueChanges.subscribe(value => {
      if (!this.pdfNameManuallyEdited) {
        const code = String(value ?? '').trim().toLowerCase();
        this.coverForm.controls['nomeFilePdf'].setValue(code ? `${code}.pdf` : '', { emitEvent: false });
      }
    });
    this.loadCovers();
  }

  get filteredCovers(): CoverSummary[] {
    const term = this.searchTerm.trim().toLocaleLowerCase('it');
    const result = term ? this.covers.filter(item => item.codice.includes(term) || item.nome.toLocaleLowerCase('it').includes(term)) : [...this.covers];
    return result.sort((a, b) => {
      const left = a[this.sortField];
      const right = b[this.sortField];
      const comparison = typeof left === 'number' && typeof right === 'number' ? left - right : String(left).localeCompare(String(right), 'it');
      return this.sortDirection === 'asc' ? comparison : -comparison;
    });
  }

  get visibleCovers(): CoverSummary[] {
    return this.filteredCovers.slice((this.page - 1) * this.pageSize, this.page * this.pageSize);
  }

  get firstVisibleItem(): number { return this.filteredCovers.length ? (this.page - 1) * this.pageSize + 1 : 0; }
  get lastVisibleItem(): number { return Math.min(this.page * this.pageSize, this.filteredCovers.length); }

  loadCovers(): void {
    this.loading = true;
    this.error = '';
    this.coverService.list().pipe(finalize(() => this.loading = false)).subscribe({
      next: covers => { this.covers = covers; this.ensureValidPage(); },
      error: error => this.error = this.errorMessage(error, 'Impossibile caricare le copertine.')
    });
  }

  setSearch(value: string): void { this.searchTerm = value; this.page = 1; }

  sortBy(field: CoverSortField): void {
    this.sortDirection = this.sortField === field && this.sortDirection === 'asc' ? 'desc' : 'asc';
    this.sortField = field;
    this.page = 1;
  }

  sortIcon(field: CoverSortField): string {
    return this.sortField !== field ? 'ri-expand-up-down-line' : this.sortDirection === 'asc' ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line';
  }

  openCreate(content: unknown): void {
    this.editingCode = null;
    this.pdfNameManuallyEdited = false;
    this.selectedFiles = [];
    this.error = '';
    this.coverForm.reset({ codice: '', nome: '', nomeFilePdf: '' });
    this.coverForm.controls['codice'].enable();
    this.modalService.open(content, { size: 'md', centered: true });
  }

  openEdit(cover: CoverSummary, content: unknown): void {
    this.editingCode = cover.codice;
    this.pdfNameManuallyEdited = true;
    this.selectedFiles = [];
    this.error = '';
    this.coverForm.reset({ codice: cover.codice, nome: cover.nome, nomeFilePdf: cover.pdfNome });
    this.coverForm.controls['codice'].disable();
    this.modalService.open(content, { size: 'md', centered: true });
  }

  selectFiles(event: Event): void {
    this.selectedFiles = Array.from((event.target as HTMLInputElement).files ?? []);
  }

  markPdfNameEdited(): void {
    this.pdfNameManuallyEdited = true;
  }

  save(): void {
    this.coverForm.markAllAsTouched();
    if (this.coverForm.invalid || this.saving) return;
    const value = this.coverForm.getRawValue();
    const editing = this.editingCode !== null;
    const code = editing ? this.editingCode! : value.codice;
    const payload = { nome: value.nome, nomeFilePdf: value.nomeFilePdf };
    const request = editing ? this.coverService.update(code, payload) : this.coverService.create({ codice: code, ...payload });
    this.saving = true;
    this.error = '';
    request.pipe(
      switchMap(cover => this.selectedFiles.length ? this.coverService.uploadAttachments(code, this.selectedFiles) : [cover]),
      finalize(() => this.saving = false)
    ).subscribe({
      next: cover => {
        this.modalService.dismissAll();
        this.upsertCover(cover);
        this.success = editing ? 'Copertina aggiornata e PDF rigenerato.' : 'Copertina e PDF creati.';
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile salvare la copertina.')
    });
  }

  downloadPdf(cover: CoverSummary): void {
    this.downloadingCode = cover.codice;
    this.error = '';
    this.coverService.downloadPdf(cover.codice).pipe(finalize(() => this.downloadingCode = null)).subscribe({
      next: pdf => {
        const bytes = Uint8Array.from(atob(pdf.contentBase64), char => char.charCodeAt(0));
        const url = URL.createObjectURL(new Blob([bytes], { type: pdf.mimeType }));
        const link = document.createElement('a');
        link.href = url;
        link.download = pdf.filename;
        link.click();
        URL.revokeObjectURL(url);
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile scaricare il PDF.')
    });
  }

  openDelete(cover: CoverSummary, content: unknown): void {
    this.deletingCover = cover;
    this.modalService.open(content, { centered: true });
  }

  confirmDelete(): void {
    if (!this.deletingCover || this.deleting) return;
    const cover = this.deletingCover;
    this.deleting = true;
    this.coverService.delete(cover.codice).pipe(finalize(() => this.deleting = false)).subscribe({
      next: () => {
        this.modalService.dismissAll();
        this.covers = this.covers.filter(item => item.codice !== cover.codice);
        this.deletingCover = null;
        this.success = 'Copertina, PDF e allegati eliminati.';
        this.ensureValidPage();
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile eliminare la copertina.')
    });
  }

  private upsertCover(cover: CoverSummary): void {
    this.covers = this.covers.some(item => item.codice === cover.codice)
      ? this.covers.map(item => item.codice === cover.codice ? cover : item)
      : [...this.covers, cover];
    this.ensureValidPage();
  }

  private ensureValidPage(): void {
    this.page = Math.min(this.page, Math.max(1, Math.ceil(this.filteredCovers.length / this.pageSize)));
  }

  private errorMessage(error: any, fallback: string): string {
    return error?.error?.message ?? error?.message ?? fallback;
  }
}
