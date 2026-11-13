<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Products {
	public static function search( string $search, int $page = 1, int $per_page = 20 ): array {
		$page     = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$search   = trim( $search );

		if ( '' === $search ) {
			return self::search_title_only( $search, $page, $per_page );
		}

		// Build one ordered union, then paginate it: exact SKU first, partial
		// SKU matches next, and title matches last. Product IDs dedupe overlaps.
		$products  = array();
		$seen      = array();
		$sku_exact = self::resolve_sku_extra( $search );
		if ( null !== $sku_exact ) {
			self::append_unique_product( $products, $seen, $sku_exact );
		}

		foreach ( self::search_sku_like( $search ) as $product ) {
			self::append_unique_product( $products, $seen, $product );
		}
		foreach ( self::query_products( array( 's' => $search ) ) as $product ) {
			self::append_unique_product( $products, $seen, $product );
		}

		$total = count( $products );
		$page_products = array_slice( $products, ( $page - 1 ) * $per_page, $per_page );
		$items = array();
		foreach ( $page_products as $product ) {
			$items[] = self::map_product( $product, true );
		}

		return array(
			'items'   => $items,
			'page'    => $page,
			'perPage' => $per_page,
			'total'   => $total,
		);
	}

	private static function search_sku_like( string $search ): array {
		$filter = static function ( array $query, array $query_vars ): array {
			if ( empty( $query_vars['faa_sku_like'] ) ) {
				return $query;
			}
			$query['meta_query'][] = array(
				'key'     => '_sku',
				'value'   => (string) $query_vars['faa_sku_like'],
				'compare' => 'LIKE',
			);
			return $query;
		};

		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', $filter, 10, 2 );
		try {
			return self::query_products( array( 'faa_sku_like' => $search ) );
		} finally {
			remove_filter( 'woocommerce_product_data_store_cpt_get_products_query', $filter, 10 );
		}
	}

	private static function query_products( array $extra_args ): array {
		return wc_get_products(
			array_merge(
				array(
					'status'   => array( 'publish', 'private' ),
					'limit'    => -1,
					'paginate' => false,
					'orderby'  => 'title',
					'order'    => 'ASC',
				),
				$extra_args
			)
		);
	}

	private static function append_unique_product( array &$products, array &$seen, WC_Product $product ): void {
		if ( self::is_variation( $product ) ) {
			$parent = self::parent_of_variation( $product );
			if ( $parent instanceof WC_Product ) {
				self::append_unique_product( $products, $seen, $parent );
			}
			return;
		}

		$id = (int) $product->get_id();
		if ( isset( $seen[ $id ] ) ) {
			return;
		}
		$seen[ $id ] = true;
		$products[]  = $product;
	}

	private static function search_title_only( string $search, int $page, int $per_page ): array {
		$args = array(
			'status'   => array( 'publish', 'private' ),
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'title',
			'order'    => 'ASC',
			'type'     => array_values( array_diff( array_keys( wc_get_product_types() ), array( 'variation' ) ) ),
		);
		if ( $search !== '' ) {
			$args['s'] = $search;
		}
		$result = wc_get_products( $args );
		$items  = array();
		foreach ( $result->products as $product ) {
			if ( self::is_variation( $product ) ) {
				continue;
			}
			$items[] = self::map_product( $product, true );
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
		if ( self::is_variation( $product ) ) {
			$parent = self::parent_of_variation( $product );
			return $parent instanceof WC_Product ? $parent : null;
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
					'relation' => 'AND',
					array(
						'taxonomy'         => 'product_cat',
						'field'            => 'term_id',
						'terms'            => $term_ids,
						'operator'         => 'IN',
						'include_children' => false,
					),
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => array( 'exclude-from-catalog' ),
						'operator' => 'NOT IN',
					),
				),
			)
		);
		$products = $query->get_products();
		$items    = array();
		$seen     = array();
		foreach ( $products as $product ) {
			if ( self::is_variation( $product ) ) {
				continue;
			}
			$id = (int) $product->get_id();
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$items[]     = self::map_product( $product, true );
		}
		return $items;
	}

	private static function is_allowed_status( WC_Product $product ): bool {
		return in_array( $product->get_status(), array( 'publish', 'private' ), true );
	}

	private static function is_variation( WC_Product $product ): bool {
		return $product->is_type( 'variation' ) || $product instanceof WC_Product_Variation;
	}

	private static function parent_of_variation( WC_Product $product ): ?WC_Product {
		$parent_id = (int) $product->get_parent_id();
		if ( $parent_id <= 0 ) {
			return null;
		}
		$parent = wc_get_product( $parent_id );
		if ( ! $parent instanceof WC_Product || ! self::is_allowed_status( $parent ) ) {
			return null;
		}
		return $parent;
	}

	private static function map_product( WC_Product $product, bool $include_variations = false ): array {
		$sku  = (string) $product->get_sku();
		$code = (string) $product->get_meta( '_sku' );
		if ( $code === '' ) {
			$code = $sku;
		}
		$mapped = array(
			'id'    => (int) $product->get_id(),
			'sku'   => $sku,
			'name'  => (string) $product->get_name(),
			'code'  => $code,
			'type'  => (string) $product->get_type(),
			'image' => self::image_url( $product ),
		);

		if ( $include_variations && $product instanceof WC_Product_Variable ) {
			$variazioni = array();
			foreach ( $product->get_children() as $child_id ) {
				$child = wc_get_product( (int) $child_id );
				if ( ! $child instanceof WC_Product || ! self::is_allowed_status( $child ) ) {
					continue;
				}
				$variazioni[] = self::map_product( $child, false );
			}
			$mapped['variazioni'] = $variazioni;
		}

		return $mapped;
	}

	/**
	 * Scheda catalogo (foto, prezzo, misure, colore, IVA) per gli id richiesti.
	 *
	 * @param int[] $ids
	 * @return array<int, array<string, mixed>>
	 */
	public static function by_ids( array $ids ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		$ids = array_slice( $ids, 0, 500 );
		$items = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product instanceof WC_Product || ! self::is_allowed_status( $product ) ) {
				continue;
			}
			$mapped            = self::map_product( $product, false );
			$mapped['price']   = self::format_price( $product );
			$mapped['misure']  = self::format_misure( $product );
			$mapped['colore']  = self::format_colore( $product );
			$mapped['iva']     = self::format_iva( $product );
			$items[]           = $mapped;
		}
		return $items;
	}

	private static function image_url( WC_Product $product ): string {
		$image_id = (int) $product->get_image_id();
		if ( $image_id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $image_id, 'large' );
		if ( ! is_string( $url ) || $url === '' ) {
			$url = wp_get_attachment_url( $image_id );
		}
		return is_string( $url ) ? $url : '';
	}

	private static function format_price( WC_Product $product ): string {
		$amount = $product->get_regular_price();
		if ( $amount === '' || $amount === null ) {
			return '';
		}
		$text = html_entity_decode( wp_strip_all_tags( (string) wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', trim( $text ) );
		return is_string( $text ) ? $text : '';
	}

	private static function format_iva( WC_Product $product ): string {
		$iva = trim( (string) $product->get_meta( 'ci_iva' ) );
		if ( $iva === '' ) {
			return '';
		}
		return str_ends_with( $iva, '%' ) ? $iva : $iva . '%';
	}

	private static function format_colore( WC_Product $product ): string {
		$colore  = trim( (string) $product->get_attribute( 'Colore' ) );
		$modello = $product->is_type( 'variation' ) ? trim( (string) $product->get_attribute( 'Modello' ) ) : '';
		if ( $colore !== '' && $modello !== '' ) {
			return $colore . ' ' . $modello;
		}
		return $colore !== '' ? $colore : $modello;
	}

	/** Stesso formato del catalogo storico: cm. larghezza x lunghezza - h. altezza. */
	private static function format_misure( WC_Product $product ): string {
		$width  = self::dimension( $product->get_width() );
		$length = self::dimension( $product->get_length() );
		$height = self::dimension( $product->get_height() );
		$text   = '';
		if ( $width !== '' ) {
			$text = 'cm. ' . $width;
		}
		if ( $length !== '' ) {
			$text .= ( $text === '' ? 'cm. ' : ' x ' ) . $length;
		}
		if ( $height !== '' ) {
			$text .= ( $text === '' ? 'h. ' : ' - h. ' ) . $height;
		}
		if ( $text !== '' ) {
			return $text;
		}
		$misura = trim( (string) $product->get_attribute( 'Misura' ) );
		if ( $misura === '' ) {
			return '';
		}
		return preg_match( '/^cm\./i', $misura ) ? $misura : 'cm. ' . $misura;
	}

	private static function dimension( $value ): string {
		$raw = str_replace( ',', '.', trim( (string) $value ) );
		if ( $raw === '' || ! is_numeric( $raw ) || (float) $raw == 0.0 ) {
			return '';
		}
		$formatted = number_format( (float) $raw, 2, ',', '' );
		return rtrim( rtrim( $formatted, '0' ), ',' );
	}
}
