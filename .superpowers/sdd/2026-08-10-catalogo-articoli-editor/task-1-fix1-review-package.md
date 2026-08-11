# Fix round 1 — current class-faa-products.php (full file; untracked so no git diff hunks)
<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Products {
	public static function search( string $search, int $page = 1, int $per_page = 20 ): array {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$args     = array(
			'status'   => array( 'publish', 'private' ),
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'title',
			'order'    => 'ASC',
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
		$total = (int) $result->total;
		if ( $search !== '' && 1 === $page ) {
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
					if ( $p instanceof WC_Product && self::is_allowed_status( $p ) ) {
						$in_title_search = wc_get_products(
							array(
								'status'  => array( 'publish', 'private' ),
								'include' => array( (int) $sku_id ),
								's'       => $search,
								'limit'   => 1,
								'return'  => 'ids',
							)
						);
						if ( empty( $in_title_search ) ) {
							array_unshift( $items, self::map_product( $p ) );
							if ( count( $items ) > $per_page ) {
								$items = array_slice( $items, 0, $per_page );
							}
							++$total;
						}
					}
				}
			}
		}
		return array(
			'items'   => $items,
			'page'    => $page,
			'perPage' => $per_page,
			'total'   => $total,
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
		$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
		if ( ! $term_ids ) {
			return array();
		}

		$query = new WC_Product_Query(
			array(
				'status'    => array( 'publish', 'private' ),
				'limit'     => -1,
				'orderby'   => 'menu_order',
				'order'     => 'ASC',
				'return'    => 'objects',
				'tax_query' => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $term_ids,
						'operator' => 'IN',
					),
				),
			)
		);
		$products = $query->get_products();
		$items    = array();
		$seen     = array();
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

	private static function is_allowed_status( WC_Product $product ): bool {
		return in_array( $product->get_status(), array( 'publish', 'private' ), true );
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
