# Catalogo Articoli Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Editor Angular + API per gestire sezioni/articoli di un catalogo con salti pagina, riordino e Salva/Annulla.

**Architecture:** Estendere `fant-admin-api` con endpoint prodotti e `PUT .../contenuto` che persiste `prodotti` come array di sezioni nel JSON catalogo. L’Angular aggiunge `ProductService`, estende `CatalogService`, e una pagina `fant-cataloghi/:codice` con sidebar Aggiungi (Categoria/Articolo) e main sezioni/SKU.

**Tech Stack:** WordPress/WooCommerce REST PHP 8.x (`fant-admin/v1`), Angular 21, Angular CDK DragDrop, HttpClient, Bootstrap/Velzon UI patterns già usati in `fant-cataloghi` / `fant-categorie`.

**Spec:** `docs/superpowers/specs/2026-08-10-catalogo-articoli-editor-design.md`

## Global Constraints

- Rotta edit: `/fant-cataloghi/:codice` (non path nudo).
- Titolo pagina: `Articoli per il catalogo: ` + nome.
- Aggiungi Categoria → nuova sezione + prodotti caricati subito (anche sottocategorie).
- Aggiungi Articolo → solo nella sezione selezionata nel main.
- Toggle salto pagina su sezione e su ogni SKU (default `false` in creazione).
- Rimuovere e riordinare con drag & drop **e** frecce.
- Annulla scarta e torna a `/fant-cataloghi`; Salva persiste e resta in pagina con feedback.
- Nessuna scrittura `.cfg` legacy in questa delivery.
- `numeroProdotti` in lista = somma di tutti gli articoli nelle sezioni.
- Commit solo se l’utente lo richiede esplicitamente (non auto-commit).

---

## File map

| File | Responsabilità |
|------|----------------|
| `fant-admin-api/includes/class-faa-products.php` | Query Woo prodotti (search / by category+children) |
| `fant-admin-api/includes/class-faa-catalogs.php` | `update_contenuto`, normalize sezioni, `summary` recount |
| `fant-admin-api/includes/class-faa-api.php` | Route `/products`, `/catalogs/{code}/contenuto` |
| `fant-admin-api/fant-admin-api.php` | require nuova class + bump version |
| `default/src/app/core/services/product.service.ts` | Client HTTP prodotti |
| `default/src/app/core/services/catalog.service.ts` | `get`, `updateContenuto`, tipi sezione/articolo |
| `default/src/app/pages/fant/fant-cataloghi-articoli/*` | Pagina editor |
| `default/src/app/pages/fant/fant-cataloghi/fant-cataloghi.component.*` | Bottone riga → navigate |
| `default/src/app/pages/fant/fant-routing.module.ts` | Route parametrica |
| `default/src/app/pages/fant/fant.module.ts` | Declare component + `FormsModule` se serve |

---

### Task 1: API prodotti (`fant-admin/v1/products`)

**Files:**
- Create: `fant-admin-api/includes/class-faa-products.php`
- Modify: `fant-admin-api/fant-admin-api.php`
- Modify: `fant-admin-api/includes/class-faa-api.php`

**Interfaces:**
- Produces: `Fant_Admin_API_V4_Products::search( string $search, int $page, int $per_page ): array|{items,page,perPage,total}`
- Produces: `Fant_Admin_API_V4_Products::by_category( int $category_id, bool $include_children ): array` di item `{ id, sku, name, code }`
- Produces: REST `GET /wp-json/fant-admin/v1/products?search=&page=&perPage=` e `?categoryId=&includeChildren=1`

- [ ] **Step 1: Creare `class-faa-products.php`**

```php
<?php
defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Products {
	public static function search( string $search, int $page = 1, int $per_page = 20 ): array {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$args     = array(
			'status'         => array( 'publish', 'private' ),
			'limit'          => $per_page,
			'page'           => $page,
			'paginate'       => true,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		$search = trim( $search );
		if ( $search !== '' ) {
			$args['s'] = $search;
		}
		$result = wc_get_products( $args );
		$items  = array();
		foreach ( $result->products as $product ) {
			$items[] = self::map_product( $product );
		}
		// Se search per SKU esatto non è nei risultati title-search, arricchire con wc_get_product_id_by_sku
		if ( $search !== '' ) {
			$sku_id = wc_get_product_id_by_sku( $search );
			if ( $sku_id ) {
				$found = false;
				foreach ( $items as $item ) {
					if ( (int) $item['id'] === (int) $sku_id ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$p = wc_get_product( $sku_id );
					if ( $p ) {
						array_unshift( $items, self::map_product( $p ) );
					}
				}
			}
		}
		return array(
			'items'   => $items,
			'page'    => $page,
			'perPage' => $per_page,
			'total'   => (int) $result->total,
		);
	}

	public static function by_category( int $category_id, bool $include_children = true ): array {
		$term_ids = array( $category_id );
		if ( $include_children ) {
			$children = get_term_children( $category_id, 'product_cat' );
			if ( ! is_wp_error( $children ) ) {
				$term_ids = array_merge( $term_ids, array_map( 'intval', $children ) );
			}
		}
		$products = wc_get_products(
			array(
				'status'   => array( 'publish', 'private' ),
				'limit'    => -1,
				'orderby'  => 'menu_order',
				'order'    => 'ASC',
				'category' => array_map(
					static function ( int $id ): string {
						$term = get_term( $id, 'product_cat' );
						return ( $term && ! is_wp_error( $term ) ) ? (string) $term->slug : '';
					},
					$term_ids
				),
			)
		);
		// Nota: wc_get_products 'category' usa slug; filtrare slug vuoti.
		$items = array();
		$seen  = array();
		foreach ( $products as $product ) {
			$id = (int) $product->get_id();
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$items[]     = self::map_product( $product );
		}
		return $items;
	}

	private static function map_product( WC_Product $product ): array {
		$sku  = (string) $product->get_sku();
		$code = (string) $product->get_meta( '_sku' );
		if ( $code === '' ) {
			$code = $sku;
		}
		return array(
			'id'   => (int) $product->get_id(),
			'sku'  => $sku,
			'name' => (string) $product->get_name(),
			'code' => $code,
		);
	}
}
```

Se `wc_get_products` con array multi-slug è scomodo nell’ambiente reale, usare `tax_query` via `WC_Product_Query` con `term_id` IN `$term_ids`. Preferire `term_id` se lo slug approach fallisce in test.

- [ ] **Step 2: Registrare require e route**

In `fant-admin-api.php` aggiungere dopo gli altri require:

```php
require_once FANT_ADMIN_API_V4_PATH . 'includes/class-faa-products.php';
```

Bump `Version` e `FANT_ADMIN_API_V4_VERSION` a `0.6.0`.

In `class-faa-api.php` `register_routes()`:

```php
self::route( '/products', WP_REST_Server::READABLE, 'products' );
```

Handler:

```php
public static function products( WP_REST_Request $request ) {
	$category_id = (int) $request->get_param( 'categoryId' );
	if ( $category_id > 0 ) {
		$include = ! in_array( strtolower( (string) $request->get_param( 'includeChildren' ) ), array( '0', 'false' ), true );
		return rest_ensure_response( Fant_Admin_API_V4_Products::by_category( $category_id, $include ) );
	}
	return rest_ensure_response(
		Fant_Admin_API_V4_Products::search(
			(string) $request->get_param( 'search' ),
			(int) ( $request->get_param( 'page' ) ?: 1 ),
			(int) ( $request->get_param( 'perPage' ) ?: 20 )
		)
	);
}
```

- [ ] **Step 3: Verifica manuale API**

Con JWT admin sul sito di test:

```bash
# search
curl -s -H "Authorization: Bearer $TOKEN" \
  "$SITE/wp-json/fant-admin/v1/products?search=T.&perPage=5"

# by category
curl -s -H "Authorization: Bearer $TOKEN" \
  "$SITE/wp-json/fant-admin/v1/products?categoryId=197&includeChildren=1"
```

Expected: JSON con `id`, `sku`, `name`, `code`; by category = array (non wrapper) o wrapper coerente — **scegliere array puro per by_category e wrapper per search**, come sopra.

---

### Task 2: API contenuto catalogo + recount `numeroProdotti`

**Files:**
- Modify: `fant-admin-api/includes/class-faa-catalogs.php`
- Modify: `fant-admin-api/includes/class-faa-api.php`

**Interfaces:**
- Consumes: struttura sezioni dalla spec
- Produces: `Fant_Admin_API_V4_Catalogs::update_contenuto( string $code, array $prodotti ): array` (summary)
- Produces: REST `PUT /catalogs/{catalogCode}/contenuto` body `{ "prodotti": [...] }`
- Produces: `summary()` conta articoli ricorsivi

- [ ] **Step 1: Aggiungere normalize + update_contenuto in `class-faa-catalogs.php`**

```php
public static function update_contenuto( string $code, $prodotti ) {
	$catalog = self::find( $code );
	if ( is_wp_error( $catalog ) ) {
		return $catalog;
	}
	$normalized = self::normalize_prodotti( $prodotti );
	if ( is_wp_error( $normalized ) ) {
		return $normalized;
	}
	$catalog['prodotti']               = $normalized;
	$catalog['schemaVersion']          = 2;
	$catalog['testata']['updatedAt']   = gmdate( 'c' );
	$path = self::catalog_path( $code );
	if ( is_wp_error( $path ) ) {
		return $path;
	}
	$result = self::write( $path, $catalog );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	self::rebuild_index();
	return self::summary( $catalog );
}

public static function normalize_prodotti( $prodotti ) {
	if ( ! is_array( $prodotti ) ) {
		return self::error( 'catalog_contenuto_invalid', 'prodotti deve essere un array.', 400 );
	}
	$sections = array();
	foreach ( $prodotti as $section ) {
		if ( ! is_array( $section ) ) {
			return self::error( 'catalog_contenuto_invalid', 'Ogni sezione deve essere un oggetto.', 400 );
		}
		$category_id = (int) ( $section['categoryId'] ?? 0 );
		$nome        = trim( (string) ( $section['nome'] ?? '' ) );
		if ( $category_id <= 0 || $nome === '' ) {
			return self::error( 'catalog_contenuto_invalid', 'categoryId e nome sezione sono obbligatori.', 400 );
		}
		$articoli_in = $section['articoli'] ?? array();
		if ( ! is_array( $articoli_in ) ) {
			return self::error( 'catalog_contenuto_invalid', 'articoli deve essere un array.', 400 );
		}
		$articoli = array();
		$seen_sku = array();
		foreach ( $articoli_in as $articolo ) {
			if ( ! is_array( $articolo ) ) {
				return self::error( 'catalog_contenuto_invalid', 'Ogni articolo deve essere un oggetto.', 400 );
			}
			$product_id = (int) ( $articolo['productId'] ?? 0 );
			$sku        = trim( (string) ( $articolo['sku'] ?? '' ) );
			$anome      = trim( (string) ( $articolo['nome'] ?? '' ) );
			if ( $product_id <= 0 || $sku === '' || $anome === '' ) {
				return self::error( 'catalog_contenuto_invalid', 'productId, sku e nome articolo sono obbligatori.', 400 );
			}
			$key = strtolower( $sku );
			if ( isset( $seen_sku[ $key ] ) ) {
				continue;
			}
			$seen_sku[ $key ] = true;
			$articoli[]       = array(
				'productId'   => $product_id,
				'sku'         => $sku,
				'nome'        => $anome,
				'saltoPagina' => ! empty( $section['articoli'] ) && ! empty( $articolo['saltoPagina'] ),
			);
		}
		// fix saltoPagina articolo:
		// usare (bool) ! empty( $articolo['saltoPagina'] ) nel loop sopra (correggere la riga errata).
		$sections[] = array(
			'categoryId'  => $category_id,
			'nome'        => $nome,
			'saltoPagina' => ! empty( $section['saltoPagina'] ),
			'articoli'    => $articoli,
		);
	}
	return $sections;
}
```

**Correzione obbligatoria nell’implementazione:** nel loop articoli usare:

```php
'saltoPagina' => ! empty( $articolo['saltoPagina'] ),
```

- [ ] **Step 2: Aggiornare `summary()`**

```php
private static function summary( array $catalog ): array {
	$header   = $catalog['testata'];
	$products = is_array( $catalog['prodotti'] ?? null ) ? $catalog['prodotti'] : array();
	$count    = 0;
	foreach ( $products as $section ) {
		if ( is_array( $section ) && isset( $section['articoli'] ) && is_array( $section['articoli'] ) ) {
			$count += count( $section['articoli'] );
		}
	}
	// Retrocompat: se prodotti è lista piatta legacy senza 'articoli', count( $products )
	if ( $count === 0 && $products && ! isset( $products[0]['articoli'] ) && ! isset( $products[0]['categoryId'] ) ) {
		$count = count( $products );
	}
	return array(
		'codice'         => (string) ( $header['codice'] ?? '' ),
		'nome'           => (string) ( $header['nome'] ?? '' ),
		'numeroProdotti' => $count,
		'createdAt'      => (string) ( $header['createdAt'] ?? '' ),
		'updatedAt'      => (string) ( $header['updatedAt'] ?? '' ),
	);
}
```

- [ ] **Step 3: Route PUT contenuto**

In `register_routes()` **prima** delle route generiche del code se necessario (path più specifico):

```php
self::route( '/catalogs/(?P<catalogCode>[a-zA-Z0-9_-]+)/contenuto', 'PUT', 'update_catalog_contenuto' );
```

```php
public static function update_catalog_contenuto( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	$params = is_array( $params ) ? $params : array();
	return rest_ensure_response(
		Fant_Admin_API_V4_Catalogs::update_contenuto(
			strtolower( (string) $request['catalogCode'] ),
			$params['prodotti'] ?? null
		)
	);
}
```

- [ ] **Step 4: Verifica**

```bash
curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"prodotti":[{"categoryId":197,"nome":"Test","saltoPagina":true,"articoli":[{"productId":1,"sku":"X","nome":"Y","saltoPagina":false}]}]}' \
  "$SITE/wp-json/fant-admin/v1/catalogs/CODICE/contenuto"
```

Expected: summary con `numeroProdotti: 1`.  
`GET /catalogs/CODICE` deve restituire `prodotti` con la sezione.

---

### Task 3: Angular `CatalogService` + `ProductService`

**Files:**
- Create: `default/src/app/core/services/product.service.ts`
- Modify: `default/src/app/core/services/catalog.service.ts`
- Test: `default/src/app/core/services/catalog.service.spec.ts` (creare se assente)
- Test: `default/src/app/core/services/product.service.spec.ts`

**Interfaces:**
- Produces tipi:

```ts
export interface CatalogArticolo {
  productId: number;
  sku: string;
  nome: string;
  saltoPagina: boolean;
}

export interface CatalogSezione {
  categoryId: number;
  nome: string;
  saltoPagina: boolean;
  articoli: CatalogArticolo[];
}

export interface CatalogDetail {
  schemaVersion?: number;
  testata: { codice: string; nome: string; createdAt: string; updatedAt: string };
  prodotti: CatalogSezione[];
}

export interface ProductItem {
  id: number;
  sku: string;
  name: string;
  code: string;
}

export interface ProductSearchResult {
  items: ProductItem[];
  page: number;
  perPage: number;
  total: number;
}
```

- Produces: `CatalogService.get(code: string): Observable<CatalogDetail>`
- Produces: `CatalogService.updateContenuto(code: string, prodotti: CatalogSezione[]): Observable<CatalogSummary>`
- Produces: `ProductService.search(search: string, page?: number, perPage?: number): Observable<ProductSearchResult>`
- Produces: `ProductService.byCategory(categoryId: number, includeChildren?: boolean): Observable<ProductItem[]>`

- [ ] **Step 1: Scrivere test `product.service.spec.ts`**

```ts
it('search chiama /products con query search', () => {
  service.search('abc', 1, 10).subscribe();
  const req = http.expectOne((r) => r.url.includes('/products') && r.params.get('search') === 'abc');
  expect(req.request.method).toBe('GET');
  req.flush({ items: [], page: 1, perPage: 10, total: 0 });
});

it('byCategory passa categoryId e includeChildren', () => {
  service.byCategory(197, true).subscribe();
  const req = http.expectOne((r) => r.url.includes('/products') && r.params.get('categoryId') === '197');
  expect(req.request.params.get('includeChildren')).toBe('1');
  req.flush([]);
});
```

- [ ] **Step 2: Implementare `product.service.ts`**

```ts
@Injectable({ providedIn: 'root' })
export class ProductService {
  private readonly apiUrl = `${environment.siteUrl.replace(/\/$/, '')}/wp-json/${environment.apiNamespace}/products`;
  constructor(private readonly http: HttpClient) {}

  search(search: string, page = 1, perPage = 20): Observable<ProductSearchResult> {
    const params = new HttpParams()
      .set('search', search)
      .set('page', String(page))
      .set('perPage', String(perPage));
    return this.http.get<ProductSearchResult>(this.apiUrl, { params });
  }

  byCategory(categoryId: number, includeChildren = true): Observable<ProductItem[]> {
    const params = new HttpParams()
      .set('categoryId', String(categoryId))
      .set('includeChildren', includeChildren ? '1' : '0');
    return this.http.get<ProductItem[]>(this.apiUrl, { params });
  }
}
```

- [ ] **Step 3: Estendere `catalog.service.ts`**

Aggiungere interfacce sopra + metodi:

```ts
get(code: string): Observable<CatalogDetail> {
  return this.http.get<CatalogDetail>(`${this.apiUrl}/${encodeURIComponent(code)}`);
}

updateContenuto(code: string, prodotti: CatalogSezione[]): Observable<CatalogSummary> {
  return this.http.put<CatalogSummary>(
    `${this.apiUrl}/${encodeURIComponent(code)}/contenuto`,
    { prodotti }
  );
}
```

Normalizzare in get (map RxJS) se `prodotti` manca o non è array → `[]`.

- [ ] **Step 4: Test catalog get/updateContenuto**

```ts
it('get legge /catalogs/{code}', () => {
  service.get('demo').subscribe();
  const req = http.expectOne((r) => r.url.endsWith('/catalogs/demo'));
  req.flush({ testata: { codice: 'demo', nome: 'Demo', createdAt: '', updatedAt: '' }, prodotti: [] });
});

it('updateContenuto fa PUT /contenuto', () => {
  service.updateContenuto('demo', []).subscribe();
  const req = http.expectOne((r) => r.url.endsWith('/catalogs/demo/contenuto'));
  expect(req.request.method).toBe('PUT');
  req.flush({ codice: 'demo', nome: 'Demo', numeroProdotti: 0, createdAt: '', updatedAt: '' });
});
```

- [ ] **Step 5: Eseguire test**

Run: `cd default && npx ng test --include=src/app/core/services/product.service.spec.ts --include=src/app/core/services/catalog.service.spec.ts --browsers=ChromeHeadless --watch=false`  
Expected: PASS (se karma non è configurato headless, verificare almeno che i file compilino con `npx ng build --configuration development`).

---

### Task 4: Scaffold pagina edit + rotta + bottone lista

**Files:**
- Create: `default/src/app/pages/fant/fant-cataloghi-articoli/fant-cataloghi-articoli.component.ts`
- Create: `default/src/app/pages/fant/fant-cataloghi-articoli/fant-cataloghi-articoli.component.html`
- Create: `default/src/app/pages/fant/fant-cataloghi-articoli/fant-cataloghi-articoli.component.scss`
- Modify: `default/src/app/pages/fant/fant-routing.module.ts`
- Modify: `default/src/app/pages/fant/fant.module.ts`
- Modify: `default/src/app/pages/fant/fant-cataloghi/fant-cataloghi.component.ts`
- Modify: `default/src/app/pages/fant/fant-cataloghi/fant-cataloghi.component.html`

**Interfaces:**
- Consumes: `CatalogService.get`
- Produces: pagina navigabile con titolo e footer Annulla/Salva (Salva stub ok fino a Task 7)

- [ ] **Step 1: Aggiungere route**

```ts
import { FantCataloghiArticoliComponent } from './fant-cataloghi-articoli/fant-cataloghi-articoli.component';

const routes: Routes = [
  // ...
  { path: 'fant-cataloghi/:codice', component: FantCataloghiArticoliComponent },
  { path: 'fant-cataloghi', component: FantCataloghiComponent },
  // ...
];
```

Dichiarare il component in `fant.module.ts`. Importare `FormsModule` se si usa `[(ngModel)]`.

- [ ] **Step 2: Scaffold component**

```ts
@Component({
  selector: 'app-fant-cataloghi-articoli',
  templateUrl: './fant-cataloghi-articoli.component.html',
  styleUrls: ['./fant-cataloghi-articoli.component.scss'],
})
export class FantCataloghiArticoliComponent implements OnInit {
  codice = '';
  nome = '';
  sezioni: CatalogSezione[] = [];
  selectedSectionIndex: number | null = null;
  loading = false;
  saving = false;
  error = '';
  success = '';
  // sidebar
  addTab: 'categoria' | 'articolo' = 'categoria';
  // ...

  constructor(
    private readonly route: ActivatedRoute,
    private readonly router: Router,
    private readonly catalogs: CatalogService,
    private readonly products: ProductService,
    private readonly categories: WooCategoryService,
  ) {}

  ngOnInit(): void {
    this.codice = this.route.snapshot.paramMap.get('codice') || '';
    this.load();
  }

  load(): void { /* catalogs.get → nome + sezioni */ }
  cancel(): void { this.router.navigate(['/fant-cataloghi']); }
  save(): void { /* Task 7 */ }
}
```

HTML skeleton:

```html
<app-breadcrumbs [title]="'Articoli per il catalogo: ' + nome" [breadcrumbItems]="breadCrumbItems"></app-breadcrumbs>

<div class="editor-layout">
  <aside class="editor-sidebar">...</aside>
  <main class="editor-main">...</main>
</div>

<div class="editor-footer">
  <button type="button" class="btn btn-light" (click)="cancel()">Annulla</button>
  <button type="button" class="btn btn-success" [disabled]="saving" (click)="save()">Salva</button>
</div>
```

- [ ] **Step 3: Bottone in lista**

In `fant-cataloghi.component.html` azioni:

```html
<button type="button" class="btn btn-sm btn-ghost-info" title="Articoli"
  (click)="openArticoli(catalog)">
  <i class="ri-list-check-2 fs-16"></i>
</button>
```

```ts
openArticoli(catalog: CatalogSummary): void {
  this.router.navigate(['/fant-cataloghi', catalog.codice]);
}
```

Iniettare `Router` nel list component.

- [ ] **Step 4: Verifica UI**

`ng serve` → aprire lista → click Articoli → URL `/fant-cataloghi/{codice}`, titolo corretto, Annulla torna alla lista.

---

### Task 5: Sidebar tab Categoria → aggiungi sezione con prodotti

**Files:**
- Modify: `fant-cataloghi-articoli.component.ts/html/scss`
- Consumes: `WooCategoryService.list`, `ProductService.byCategory`

- [ ] **Step 1: Caricare albero categorie (read-only select)**

Riusare la logica `buildTree` da `fant-categorie.component.ts` (copiare le funzioni minime necessarie nel component edit, senza drag reorder/edit).

- Click nodo → `selectedCategoryId`
- Bottone Aggiungi chiama:

```ts
addCategorySection(): void {
  if (!this.selectedCategoryId) { this.error = 'Seleziona una categoria.'; return; }
  const cat = this.flatCategories.find(c => c.id === this.selectedCategoryId);
  this.loadingProducts = true;
  this.products.byCategory(this.selectedCategoryId, true).subscribe({
    next: (items) => {
      const articoli: CatalogArticolo[] = items
        .filter(p => !!p.sku)
        .map(p => ({
          productId: p.id,
          sku: p.sku,
          nome: p.name,
          saltoPagina: false,
        }));
      this.sezioni = [
        ...this.sezioni,
        {
          categoryId: cat!.id,
          nome: cat!.name,
          saltoPagina: false,
          articoli,
        },
      ];
      this.selectedSectionIndex = this.sezioni.length - 1;
      this.loadingProducts = false;
    },
    error: () => { this.error = 'Impossibile caricare i prodotti della categoria.'; this.loadingProducts = false; }
  });
}
```

- [ ] **Step 2: UI tab**

Due bottoni/tab Bootstrap: Categoria | Articolo. Nel tab Categoria: albero + `Aggiungi`.

- [ ] **Step 3: Verifica**

Aggiungere una categoria → nel main appare sezione con N articoli; sezione risulta selezionata.

---

### Task 6: Sidebar tab Articolo → multi-select + aggiungi a sezione selezionata

**Files:**
- Modify: `fant-cataloghi-articoli.component.ts/html`

- [ ] **Step 1: Search con debounce**

```ts
searchTerm = '';
searchResults: ProductItem[] = [];
selectedProductIds = new Set<number>();

onSearchInput(value: string): void {
  this.searchTerm = value;
  // debounce 300ms via subject o setTimeout clear
  this.products.search(value, 1, 30).subscribe({
    next: (res) => { this.searchResults = res.items; },
    error: () => { this.error = 'Ricerca prodotti fallita.'; }
  });
}

toggleProduct(id: number): void {
  if (this.selectedProductIds.has(id)) this.selectedProductIds.delete(id);
  else this.selectedProductIds.add(id);
}

addSelectedProducts(): void {
  if (this.selectedSectionIndex === null) {
    this.error = 'Seleziona una sezione nel riquadro di destra.';
    return;
  }
  const section = this.sezioni[this.selectedSectionIndex];
  const existing = new Set(section.articoli.map(a => a.sku.toLowerCase()));
  for (const p of this.searchResults) {
    if (!this.selectedProductIds.has(p.id) || !p.sku) continue;
    if (existing.has(p.sku.toLowerCase())) continue;
    section.articoli.push({
      productId: p.id,
      sku: p.sku,
      nome: p.name,
      saltoPagina: false,
    });
    existing.add(p.sku.toLowerCase());
  }
  this.selectedProductIds.clear();
}
```

- [ ] **Step 2: UI lista risultati**

Checkbox + sku + name; bottone Aggiungi.

- [ ] **Step 3: Verifica**

Senza sezione selezionata → messaggio. Con sezione → SKU aggiunti senza duplicati.

---

### Task 7: Main — toggle, elimina, frecce, drag & drop, Salva

**Files:**
- Modify: `fant-cataloghi-articoli.component.ts/html/scss`

**Interfaces:**
- Consumes: `CatalogService.updateContenuto`
- Pattern drag: come `fant-categorie` (`cdkDropList`, `cdkDrag`)

- [ ] **Step 1: Selezione sezione + toggle**

```ts
selectSection(index: number): void { this.selectedSectionIndex = index; }
toggleSectionSalto(i: number): void { this.sezioni[i].saltoPagina = !this.sezioni[i].saltoPagina; }
toggleArticoloSalto(si: number, ai: number): void {
  const a = this.sezioni[si].articoli[ai];
  a.saltoPagina = !a.saltoPagina;
}
```

UI: `form-check form-switch` Bootstrap.

- [ ] **Step 2: Elimina**

```ts
removeSection(i: number): void {
  this.sezioni = this.sezioni.filter((_, idx) => idx !== i);
  if (this.selectedSectionIndex === i) this.selectedSectionIndex = null;
  else if (this.selectedSectionIndex !== null && this.selectedSectionIndex > i) this.selectedSectionIndex--;
}
removeArticolo(si: number, ai: number): void {
  this.sezioni[si].articoli = this.sezioni[si].articoli.filter((_, idx) => idx !== ai);
}
```

- [ ] **Step 3: Frecce**

```ts
moveSection(i: number, delta: -1 | 1): void {
  const j = i + delta;
  if (j < 0 || j >= this.sezioni.length) return;
  const copy = [...this.sezioni];
  [copy[i], copy[j]] = [copy[j], copy[i]];
  this.sezioni = copy;
  if (this.selectedSectionIndex === i) this.selectedSectionIndex = j;
  else if (this.selectedSectionIndex === j) this.selectedSectionIndex = i;
}
moveArticolo(si: number, ai: number, delta: -1 | 1): void {
  const list = [...this.sezioni[si].articoli];
  const j = ai + delta;
  if (j < 0 || j >= list.length) return;
  [list[ai], list[j]] = [list[j], list[ai]];
  this.sezioni[si] = { ...this.sezioni[si], articoli: list };
}
```

- [ ] **Step 4: CDK drag-drop**

```ts
dropSection(event: CdkDragDrop<CatalogSezione[]>): void {
  moveItemInArray(this.sezioni, event.previousIndex, event.currentIndex);
  // aggiornare selectedSectionIndex se necessario
}
dropArticolo(si: number, event: CdkDragDrop<CatalogArticolo[]>): void {
  moveItemInArray(this.sezioni[si].articoli, event.previousIndex, event.currentIndex);
}
```

Template: `cdkDropList` sulle sezioni; un `cdkDropList` per gli articoli di ogni sezione.

- [ ] **Step 5: Salva**

```ts
save(): void {
  this.saving = true;
  this.error = '';
  this.success = '';
  this.catalogs.updateContenuto(this.codice, this.sezioni).subscribe({
    next: () => {
      this.success = 'Catalogo salvato.';
      this.saving = false;
    },
    error: (err) => {
      this.error = err?.error?.message || 'Salvataggio fallito.';
      this.saving = false;
    }
  });
}
```

- [ ] **Step 6: Verifica end-to-end**

1. Apri edit catalogo  
2. Aggiungi categoria → prodotti presenti  
3. Toggle salto sezione + 1 SKU  
4. Riordina con freccia e drag  
5. Aggiungi articolo da search  
6. Elimina un articolo  
7. Salva → reload pagina → stato persistito  
8. Annulla dopo modifica non salvata → lista, reload edit → modifica non presente  
9. Lista cataloghi: `numeroProdotti` aggiornato  

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Bottone lista → edit | 4 |
| Route `/fant-cataloghi/:codice` + titolo | 4 |
| Sidebar Aggiungi tabs | 5–6 |
| Categoria → sezione + prodotti + children | 1, 5 |
| Articolo search multi → sezione selezionata | 1, 6 |
| Toggle salto sezione/SKU | 7 |
| Rimuovi + drag + frecce | 7 |
| Annulla / Salva | 4, 7 |
| JSON sezioni + API contenuto | 2 |
| `numeroProdotti` = somma articoli | 2 |
| Nessun `.cfg` | (vincolo globale) |

## Self-review notes

- Tipi `CatalogSezione` / `CatalogArticolo` / `ProductItem` allineati tra Task 2–7.
- In Task 2 il snippet `normalize` ha una riga commentata errata su `saltoPagina` articolo — l’implementer deve usare `! empty( $articolo['saltoPagina'] )`.
- Preferire `WC_Product_Query` + `tax_query` su `term_id` se multi-slug `category` di `wc_get_products` è inaffidabile.
- Non auto-commit: chiedere all’utente.
