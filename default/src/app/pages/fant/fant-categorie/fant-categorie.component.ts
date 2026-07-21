import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import {
  CdkDragDrop,
  moveItemInArray,
  transferArrayItem
} from '@angular/cdk/drag-drop';
import { finalize } from 'rxjs';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';

import {
  WooCategory,
  WooCategoryOrderItem,
  WooCategoryService
} from '../../../core/services/woo-category.service';

interface CategoryNode extends WooCategory {
  children: CategoryNode[];
}

@Component({
  selector: 'app-fant-categorie',
  templateUrl: './fant-categorie.component.html',
  styleUrls: ['./fant-categorie.component.scss'],
  standalone: false
})
export class FantCategorieComponent implements OnInit {
  breadCrumbItems: Array<{ label: string; active?: boolean }> = [];
  categoryForm!: FormGroup;
  categories: WooCategory[] = [];
  tree: CategoryNode[] = [];
  expandedCategoryIds = new Set<number>();
  selectedId: number | null = null;
  loading = false;
  saving = false;
  reordering = false;
  error = '';
  success = '';
  private expansionInitialized = false;

  readonly displayOptions = [
    { value: '', label: 'Predefinito' },
    { value: 'products', label: 'Prodotti' },
    { value: 'subcategories', label: 'Sottocategorie' },
    { value: 'both', label: 'Entrambi' }
  ];

  constructor(
    private readonly formBuilder: FormBuilder,
    private readonly categoryService: WooCategoryService,
    private readonly modalService: NgbModal
  ) {}

  ngOnInit(): void {
    this.breadCrumbItems = [
      { label: 'Shop' },
      { label: 'Categorie', active: true }
    ];
    this.categoryForm = this.formBuilder.group({
      name: ['', Validators.required],
      slug: [''],
      description: [''],
      parent: [0],
      display: ['']
    });
    this.loadCategories();
  }

  get dropListIds(): string[] {
    return [
      'category-list-0',
      ...this.categories
        .filter(category => this.expandedCategoryIds.has(category.id))
        .map(category => `category-list-${category.id}`)
    ];
  }

  loadCategories(selectId?: number): void {
    if (selectId !== undefined) {
      this.selectedId = selectId;
    }
    this.loading = true;
    this.error = '';
    this.categoryService.list().pipe(finalize(() => this.loading = false)).subscribe({
      next: categories => {
        this.categories = categories;
        this.tree = this.buildTree(categories);
        this.syncExpansionState(categories, selectId);
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile caricare le categorie.')
    });
  }

  newCategory(content: unknown, parent = 0): void {
    this.selectedId = null;
    this.success = '';
    this.error = '';
    this.categoryForm.reset({ name: '', slug: '', description: '', parent, display: '' });
    this.modalService.open(content, { size: 'md', centered: true });
  }

  edit(category: WooCategory, content: unknown): void {
    this.selectedId = category.id;
    this.success = '';
    this.error = '';
    this.categoryForm.reset({
      name: category.name,
      slug: category.slug,
      description: category.description,
      parent: category.parent,
      display: category.display
    });
    this.modalService.open(content, { size: 'md', centered: true });
  }

  save(): void {
    this.categoryForm.markAllAsTouched();
    if (this.categoryForm.invalid || this.saving) {
      return;
    }

    this.saving = true;
    this.error = '';
    this.success = '';
    const isEditing = this.selectedId !== null;
    const request = this.selectedId
      ? this.categoryService.update(this.selectedId, this.categoryForm.value)
      : this.categoryService.create(this.categoryForm.value);

    request.pipe(finalize(() => this.saving = false)).subscribe({
      next: category => {
        this.success = isEditing ? 'Categoria aggiornata.' : 'Categoria creata.';
        this.modalService.dismissAll();
        this.loadCategories(category.id);
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile salvare la categoria.')
    });
  }

  deleteSelected(): void {
    if (!this.selectedId || !confirm('Eliminare definitivamente questa categoria?')) {
      return;
    }

    const deletedId = this.selectedId;
    this.saving = true;
    this.categoryService.delete(deletedId).pipe(finalize(() => this.saving = false)).subscribe({
      next: () => {
        this.modalService.dismissAll();
        this.selectedId = null;
        this.categoryForm.reset({ name: '', slug: '', description: '', parent: 0, display: '' });
        this.success = 'Categoria eliminata.';
        this.loadCategories();
      },
      error: error => this.error = this.errorMessage(error, 'Impossibile eliminare la categoria.')
    });
  }

  drop(event: CdkDragDrop<CategoryNode[]>, parentId: number): void {
    if (this.reordering) {
      return;
    }

    const moved = event.previousContainer.data[event.previousIndex];
    if (moved.id === parentId || this.containsNode(moved, parentId)) {
      this.error = 'Non è possibile spostare una categoria dentro una sua sottocategoria.';
      return;
    }

    if (event.previousContainer === event.container) {
      moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
    } else {
      transferArrayItem(
        event.previousContainer.data,
        event.container.data,
        event.previousIndex,
        event.currentIndex
      );
      moved.parent = parentId;
    }

    this.persistOrder();
  }

  categoryLabel(category: WooCategory): string {
    return `${category.name} (${category.count})`;
  }

  isExpanded(categoryId: number): boolean {
    return this.expandedCategoryIds.has(categoryId);
  }

  toggleNode(categoryId: number): void {
    const expanded = new Set(this.expandedCategoryIds);
    if (expanded.has(categoryId)) {
      expanded.delete(categoryId);
    } else {
      expanded.add(categoryId);
    }
    this.expandedCategoryIds = expanded;
  }

  expandAll(): void {
    this.expandedCategoryIds = new Set(this.categories.map(category => category.id));
  }

  collapseAll(): void {
    this.expandedCategoryIds = new Set<number>();
  }

  trackByCategory(_index: number, category: WooCategory): number {
    return category.id;
  }

  private persistOrder(): void {
    const items: WooCategoryOrderItem[] = [];
    const collect = (nodes: CategoryNode[], parent: number): void => {
      nodes.forEach((node, index) => {
        items.push({ id: node.id, parent, menuOrder: index });
        collect(node.children, node.id);
      });
    };
    collect(this.tree, 0);

    this.reordering = true;
    this.error = '';
    this.categoryService.reorder(items).pipe(finalize(() => this.reordering = false)).subscribe({
      next: categories => {
        this.categories = categories;
        this.tree = this.buildTree(categories);
        this.syncExpansionState(categories);
        this.success = 'Ordine delle categorie aggiornato.';
      },
      error: error => {
        this.error = this.errorMessage(error, 'Impossibile salvare il nuovo ordine.');
        this.loadCategories(this.selectedId ?? undefined);
      }
    });
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

  private containsNode(node: CategoryNode, id: number): boolean {
    return node.children.some(child => child.id === id || this.containsNode(child, id));
  }

  private syncExpansionState(categories: WooCategory[], revealId?: number): void {
    if (!this.expansionInitialized) {
      this.expandAll();
      this.expansionInitialized = true;
      return;
    }

    const existingIds = new Set(categories.map(category => category.id));
    const expanded = new Set(
      [...this.expandedCategoryIds].filter(categoryId => existingIds.has(categoryId))
    );

    if (revealId) {
      const byId = new Map(categories.map(category => [category.id, category]));
      let parentId = byId.get(revealId)?.parent ?? 0;
      while (parentId) {
        expanded.add(parentId);
        parentId = byId.get(parentId)?.parent ?? 0;
      }
    }

    this.expandedCategoryIds = expanded;
  }

  private errorMessage(error: any, fallback: string): string {
    return error?.error?.message ?? error?.message ?? fallback;
  }
}
