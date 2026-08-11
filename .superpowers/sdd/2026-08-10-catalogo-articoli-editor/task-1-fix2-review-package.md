# Fix round 2 — class-faa-products.php full file
<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Products {
	public static function search( string $search, int $page = 1, int $per_page = 20 ): array {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$search   = trim( $search );

		$sku_extra = self::resolve_sku_extra( $search );
		if ( null === $sku_extra ) {
			return self::search_title_only( $search, $page, $per_page );
		}

		$base_args = array(
			'status'  => array( 'publish', 'private' ),
			'orderby' => 'title',
			'order'   => 'ASC',
			'exclude' => array( (int) $sku_extra->get_id() ),
		);
		if ( $search !== '' ) {
			$base_args['s'] = $search;
		}

		$count_result = wc_get_products(
			array_merge(
				$base_args,
				array(
					'limit'    => 1,
					'page'     => 1,
					'paginate' => true,
				)
			)
		);
		$title_total = (int) $count_result->total;
		$total       = $title_total + 1;
		$items       = array();

		if ( 1 === $page ) {
			$title_result = wc_get_products(
				array_merge(
					$base_args,
					array(
						'limit'    => max( 0, $per_page - 1 ),
						'page'     => 1,
						'paginate' => true,
					)
				)
			);
			$items[] = self::map_product( $sku_extra );
			foreach ( $title_result->products as $product ) {
				$items[] = self::map_product( $product );
			}
		} else {
			$title_products = wc_get_products(
				array_merge(
					$base_args,
					array(
						'limit'    => $per_page,
						'offset'   => ( $page - 1 ) * $per_page - 1,
						'paginate' => false,
					)
				)
			);
			foreach ( $title_products as $product ) {
				$items[] = self::map_product( $product );
			}
		}

		return array(
			'items'   => $items,
			'page'    => $page,
			'perPage' => $per_page,
			'total'   => $total,
		);
	}

	private static function search_title_only( string $search, int $page, int $per_page ): array {
		$args = array(
			'status'   => array( 'publish', 'private' ),
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'title',
			'order'    => 'ASC',
		);
		if ( $search !== '' ) {
			$args['s'] = $search;
		}
		$result = wc_get_products( $args );
		$items  = array();
		foreach ( $result->products as $product ) {
			$items[] = self::map_product( $product );
		}
		return array(
			'items'   => $items,
			'page'    => $page,
			'perPage' => $per_page,
			'total'   => (int) $result->total,
		);
	}

	private static function resolve_sku_extra( string $search ): ?WC_Product {
		if ( $search === '' ) {
			return null;
		}
		$sku_id = wc_get_product_id_by_sku( $search );
		if ( ! $sku_id ) {
			return null;
		}
		$product = wc_get_product( $sku_id );
		if ( ! $product instanceof WC_Product || ! self::is_allowed_status( $product ) ) {
			return null;
		}
		if ( strcasecmp( $search, (string) $product->get_sku() ) !== 0 ) {
			return null;
		}
		return $product;
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
