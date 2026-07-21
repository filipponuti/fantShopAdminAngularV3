<?php

defined( 'ABSPATH' ) || exit;

final class AMA_API {
    private const NS = 'agent-mobile/v1';

    public static function init(): void {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes(): void {
        self::route( '/auth/login', 'POST', 'login', '__return_true' );
        self::route( '/auth/refresh', 'POST', 'refresh', '__return_true' );
        self::route( '/auth/logout', 'POST', 'logout' );
        self::route( '/me', 'GET', 'me' );
        self::route( '/settings', 'GET', 'settings' );

        register_rest_route( self::NS, '/customers', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'customers' ),
                'permission_callback' => array( __CLASS__, 'authorized' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'create_customer' ),
                'permission_callback' => array( __CLASS__, 'authorized' ),
            ),
        ) );
        self::route( '/customers/(?P<customerId>\d+)', 'GET', 'customer' );
        self::route( '/categories', 'GET', 'categories' );
        self::route( '/products', 'GET', 'products' );
        self::route( '/products/(?P<productId>\d+)', 'GET', 'product' );

        register_rest_route( self::NS, '/documents', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'documents' ),
                'permission_callback' => array( __CLASS__, 'authorized' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'create_document' ),
                'permission_callback' => array( __CLASS__, 'authorized' ),
            ),
        ) );
        self::route( '/documents/validate', 'POST', 'validate_document' );
        self::route( '/documents/(?P<documentId>\d+)', 'GET', 'document' );
        self::route( '/sync', 'GET', 'sync' );
    }

    private static function route( string $path, string $methods, string $callback, $permission = null ): void {
        register_rest_route( self::NS, $path, array(
            'methods'             => $methods,
            'callback'            => array( __CLASS__, $callback ),
            'permission_callback' => $permission ?: array( __CLASS__, 'authorized' ),
        ) );
    }

    public static function authorized() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return self::error( 'unauthorized', 'Token assente, non valido o scaduto.', 401 );
        }
        if ( ! AMA_Auth::is_sales_agent( $user_id ) ) {
            return self::error( 'agent_forbidden', 'Utente non abilitato come agente.', 403 );
        }
        return true;
    }

    public static function login( WP_REST_Request $request ) {
        $username = sanitize_user( (string) $request->get_param( 'username' ) );
        $password = (string) $request->get_param( 'password' );
        $device_id = sanitize_text_field( (string) $request->get_param( 'deviceId' ) );

        if ( '' === $username || '' === $password || ! self::is_uuid( $device_id ) ) {
            return self::error( 'invalid_login_request', 'Username, password e deviceId sono obbligatori.', 422 );
        }

        $user = wp_authenticate( $username, $password );
        if ( is_wp_error( $user ) ) {
            return self::error( 'invalid_credentials', 'Credenziali non valide.', 401 );
        }
        if ( ! AMA_Auth::is_sales_agent( (int) $user->ID ) ) {
            return self::error( 'agent_forbidden', 'Utente non abilitato come agente.', 403 );
        }

        try {
            $tokens = AMA_Auth::issue(
                (int) $user->ID,
                $device_id,
                (string) $request->get_param( 'deviceName' )
            );
        } catch ( Throwable $e ) {
            return self::error( 'session_error', 'Impossibile creare la sessione.', 500 );
        }

        return rest_ensure_response( array_merge( $tokens, array( 'agent' => self::agent_data( $user ) ) ) );
    }

    public static function refresh( WP_REST_Request $request ) {
        $token = (string) $request->get_param( 'refreshToken' );
        $tokens = $token ? AMA_Auth::rotate( $token ) : null;
        if ( ! $tokens ) {
            return self::error( 'invalid_refresh_token', 'Refresh token non valido o scaduto.', 401 );
        }

        $session_user = self::user_from_access_token( $tokens['accessToken'] );
        if ( ! $session_user || ! AMA_Auth::is_sales_agent( (int) $session_user->ID ) ) {
            return self::error( 'agent_forbidden', 'Utente non abilitato come agente.', 403 );
        }

        return rest_ensure_response( array_merge( $tokens, array( 'agent' => self::agent_data( $session_user ) ) ) );
    }

    public static function logout( WP_REST_Request $request ) {
        $token = (string) $request->get_param( 'refreshToken' );
        if ( $token ) {
            AMA_Auth::revoke( $token );
        }
        return new WP_REST_Response( null, 204 );
    }

    public static function me() {
        return rest_ensure_response( self::agent_data( wp_get_current_user() ) );
    }

    public static function settings() {
        return rest_ensure_response( array(
            'defaultPriceLevel' => 'L1',
            'priceLevels'       => array( 'L0', 'L1', 'L2', 'L3' ),
            'allowManualPrice'  => 'yes' === get_option( 'ama_allow_manual_price', 'yes' ),
            'allowDiscounts'    => 'yes' === get_option( 'ama_allow_discounts', 'yes' ),
            'maxDiscount1'      => self::nullable_number_option( 'ama_max_discount_1' ),
            'maxDiscount2'      => self::nullable_number_option( 'ama_max_discount_2' ),
            'orderStatus'       => 'completed',
            'emailEnabled'      => 'yes' !== get_option( 'ama_disable_emails', 'no' ),
        ) );
    }

    public static function customers( WP_REST_Request $request ) {
        $page = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, (int) ( $request->get_param( 'perPage' ) ?: 50 ) ) );
        $args = array(
            'role__in'   => get_option( 'wcb2bsa_has_role_customer', array( 'customer' ) ),
            'number'     => $per_page,
            'paged'      => $page,
            'fields'     => 'all_with_meta',
            'count_total'=> true,
            'meta_query' => array( array(
                'key'     => 'wcb2bsa_sales_agent',
                'value'   => get_current_user_id(),
                'compare' => '=',
            ) ),
        );
        $search = trim( (string) $request->get_param( 'search' ) );
        if ( $search ) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
        }

        $query = new WP_User_Query( $args );
        $response = rest_ensure_response( array_values( array_map( array( __CLASS__, 'customer_data' ), $query->get_results() ) ) );
        $response->header( 'X-WP-Total', (string) $query->get_total() );
        $response->header( 'X-WP-TotalPages', (string) ceil( $query->get_total() / $per_page ) );
        return $response;
    }

    public static function customer( WP_REST_Request $request ) {
        $customer = self::authorized_customer( (int) $request['customerId'] );
        return $customer ? rest_ensure_response( self::customer_data( $customer ) ) : self::not_found();
    }

    public static function create_customer( WP_REST_Request $request ) {
        $key = self::idempotency_key( $request );
        if ( is_wp_error( $key ) ) {
            return $key;
        }
        $replay = self::idempotency_replay( $key, $request, 'customer' );
        if ( $replay ) {
            return $replay;
        }

        $company = sanitize_text_field( (string) $request->get_param( 'company' ) );
        $local_id = sanitize_text_field( (string) $request->get_param( 'localId' ) );
        $email = sanitize_email( (string) $request->get_param( 'email' ) );
        if ( '' === $company || ! self::is_uuid( $local_id ) ) {
            return self::error( 'invalid_customer', 'Ragione sociale e localId sono obbligatori.', 422 );
        }
        if ( $email && email_exists( $email ) ) {
            return self::error( 'customer_duplicate', 'Esiste gia un utente con questa email.', 409 );
        }

        $username = $email ? sanitize_user( strstr( $email, '@', true ), true ) : 'mobile-' . substr( $local_id, 0, 12 );
        $username = self::unique_username( $username ?: 'mobile-customer' );
        $user_id = self::with_email_policy( static function () use ( $username, $email, $company ) {
            return wp_insert_user( array(
                'user_login'   => $username,
                'user_pass'    => wp_generate_password( 32, true, true ),
                'user_email'   => $email,
                'display_name' => $company,
                'role'         => 'customer',
            ) );
        } );
        if ( is_wp_error( $user_id ) ) {
            return self::error( 'customer_create_failed', $user_id->get_error_message(), 422 );
        }

        $address = (array) $request->get_param( 'billingAddress' );
        $meta = array(
            'billing_company'     => $company,
            'billing_first_name'  => sanitize_text_field( (string) $request->get_param( 'firstName' ) ),
            'billing_last_name'   => sanitize_text_field( (string) $request->get_param( 'lastName' ) ),
            'billing_email'       => $email,
            'billing_phone'       => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
            'billing_address_1'   => sanitize_text_field( (string) ( $address['address1'] ?? '' ) ),
            'billing_address_2'   => sanitize_text_field( (string) ( $address['address2'] ?? '' ) ),
            'billing_city'        => sanitize_text_field( (string) ( $address['city'] ?? '' ) ),
            'billing_state'       => sanitize_text_field( (string) ( $address['state'] ?? '' ) ),
            'billing_postcode'    => sanitize_text_field( (string) ( $address['postcode'] ?? '' ) ),
            'billing_country'     => sanitize_text_field( (string) ( $address['country'] ?? '' ) ),
            'billing_piva'        => sanitize_text_field( (string) $request->get_param( 'vatNumber' ) ),
            'billing_cf'          => sanitize_text_field( (string) $request->get_param( 'fiscalCode' ) ),
            'billing_sdi'         => sanitize_text_field( (string) $request->get_param( 'sdi' ) ),
            'billing_pagamento'   => sanitize_text_field( (string) $request->get_param( 'paymentTerms' ) ),
            'billing_iban'        => sanitize_text_field( (string) $request->get_param( 'iban' ) ),
            'billing_note'        => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
            'wcb2bsa_sales_agent' => get_current_user_id(),
            '_ama_local_id'       => $local_id,
        );
        foreach ( $meta as $meta_key => $value ) {
            update_user_meta( $user_id, $meta_key, $value );
        }
        do_action( 'wcb2bsa_compatibility__set_default_group', $user_id, get_current_user_id() );

        $data = self::customer_data( get_userdata( $user_id ) );
        self::idempotency_store( $key, $request, 'customer', $user_id, $data );
        return new WP_REST_Response( $data, 201 );
    }

    public static function categories() {
        $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
        if ( is_wp_error( $terms ) ) {
            return self::error( 'categories_failed', $terms->get_error_message(), 500 );
        }
        return rest_ensure_response( array_map( array( __CLASS__, 'category_data' ), $terms ) );
    }

    public static function products( WP_REST_Request $request ) {
        $page = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, (int) ( $request->get_param( 'perPage' ) ?: 50 ) ) );
        $args = array(
            'status'   => 'publish',
            'type'     => 'simple',
            'limit'    => $per_page,
            'page'     => $page,
            'paginate' => true,
            'orderby'  => 'name',
            'order'    => 'ASC',
        );
        if ( $request->get_param( 'search' ) ) {
            $args['s'] = sanitize_text_field( (string) $request->get_param( 'search' ) );
        }
        if ( $request->get_param( 'categoryId' ) ) {
            $term = get_term( (int) $request->get_param( 'categoryId' ), 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                $args['category'] = array( $term->slug );
            }
        }

        $result = wc_get_products( $args );
        $response = rest_ensure_response( array_map( array( __CLASS__, 'product_data' ), $result->products ) );
        $response->header( 'X-WP-Total', (string) $result->total );
        $response->header( 'X-WP-TotalPages', (string) $result->max_num_pages );
        return $response;
    }

    public static function product( WP_REST_Request $request ) {
        $product = wc_get_product( (int) $request['productId'] );
        if ( ! $product || ! $product->is_type( 'simple' ) || 'publish' !== $product->get_status() ) {
            return self::not_found();
        }
        return rest_ensure_response( self::product_data( $product ) );
    }

    public static function documents( WP_REST_Request $request ) {
        $type = strtoupper( (string) $request->get_param( 'type' ) );
        if ( ! in_array( $type, array( 'ORDER', 'QUOTE' ), true ) ) {
            return self::error( 'invalid_document_type', 'type deve essere ORDER o QUOTE.', 422 );
        }

        $page = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, (int) ( $request->get_param( 'perPage' ) ?: 50 ) ) );
        $meta_query = array( array(
            'key'     => 'wcb2bsa_sales_agent',
            'value'   => get_current_user_id(),
            'compare' => '=',
        ) );
        $meta_query[] = 'QUOTE' === $type
            ? array( 'key' => 'billing_tipo_doc', 'value' => 'PREVENTIVO', 'compare' => '=' )
            : array(
                'relation' => 'OR',
                array( 'key' => 'billing_tipo_doc', 'value' => 'PREVENTIVO', 'compare' => '!=' ),
                array( 'key' => 'billing_tipo_doc', 'compare' => 'NOT EXISTS' ),
            );

        $args = array(
            'limit'      => $per_page,
            'page'       => $page,
            'paginate'   => true,
            'type'       => 'shop_order',
            'status'     => array_keys( wc_get_order_statuses() ),
            'orderby'    => 'date',
            'order'      => 'DESC',
            'meta_query' => $meta_query,
        );
        if ( $request->get_param( 'customerId' ) ) {
            $customer = self::authorized_customer( (int) $request->get_param( 'customerId' ) );
            if ( ! $customer ) {
                return self::not_found();
            }
            $args['customer_id'] = $customer->get_id();
        }

        $result = wc_get_orders( $args );
        $response = rest_ensure_response( array_map( array( __CLASS__, 'document_summary' ), $result->orders ) );
        $response->header( 'X-WP-Total', (string) $result->total );
        $response->header( 'X-WP-TotalPages', (string) $result->max_num_pages );
        return $response;
    }

    public static function document( WP_REST_Request $request ) {
        $order = self::authorized_order( (int) $request['documentId'] );
        return $order ? rest_ensure_response( self::document_data( $order ) ) : self::not_found();
    }

    public static function validate_document( WP_REST_Request $request ) {
        $validation = self::prepare_document( $request, false );
        return is_wp_error( $validation ) ? $validation : rest_ensure_response( $validation );
    }

    public static function create_document( WP_REST_Request $request ) {
        $key = self::idempotency_key( $request );
        if ( is_wp_error( $key ) ) {
            return $key;
        }
        $replay = self::idempotency_replay( $key, $request, 'document' );
        if ( $replay ) {
            return $replay;
        }

        $result = self::prepare_document( $request, true );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        self::idempotency_store( $key, $request, 'document', (int) $result['id'], $result );
        return new WP_REST_Response( $result, 201 );
    }

    public static function sync( WP_REST_Request $request ) {
        $limit = min( 100, max( 1, (int) ( $request->get_param( 'limit' ) ?: 100 ) ) );
        $state = self::decode_cursor( (string) $request->get_param( 'cursor' ) );
        $phase = in_array( $state['phase'] ?? '', array( 'customers', 'categories', 'products', 'documents' ), true )
            ? $state['phase']
            : 'customers';
        $page = max( 1, (int) ( $state['page'] ?? 1 ) );
        $snapshot = sanitize_text_field( (string) ( $state['snapshot'] ?? gmdate( 'c' ) ) );
        $payload = array(
            'customers' => array(), 'categories' => array(), 'products' => array(),
            'documents' => array(), 'deleted' => array(),
        );
        $has_more_in_phase = false;

        if ( 'customers' === $phase ) {
            $query = new WP_User_Query( array(
                'role__in'    => get_option( 'wcb2bsa_has_role_customer', array( 'customer' ) ),
                'number'      => $limit,
                'paged'       => $page,
                'fields'      => 'all_with_meta',
                'count_total' => true,
                'meta_query'  => array( array(
                    'key' => 'wcb2bsa_sales_agent', 'value' => get_current_user_id(), 'compare' => '=',
                ) ),
            ) );
            $payload['customers'] = array_values( array_map( array( __CLASS__, 'customer_data' ), $query->get_results() ) );
            $has_more_in_phase = $page < (int) ceil( $query->get_total() / $limit );
        } elseif ( 'categories' === $phase ) {
            $terms = get_terms( array(
                'taxonomy' => 'product_cat', 'hide_empty' => false,
                'number' => $limit, 'offset' => ( $page - 1 ) * $limit,
            ) );
            if ( is_wp_error( $terms ) ) {
                return self::error( 'sync_categories_failed', $terms->get_error_message(), 500 );
            }
            $payload['categories'] = array_map( array( __CLASS__, 'category_data' ), $terms );
            $has_more_in_phase = count( $terms ) === $limit;
        } elseif ( 'products' === $phase ) {
            $result = wc_get_products( array(
                'status' => 'publish', 'type' => 'simple', 'limit' => $limit,
                'page' => $page, 'paginate' => true, 'orderby' => 'ID', 'order' => 'ASC',
            ) );
            $payload['products'] = array_map( array( __CLASS__, 'product_data' ), $result->products );
            $has_more_in_phase = $page < (int) $result->max_num_pages;
        } else {
            $result = wc_get_orders( array(
                'limit' => $limit, 'page' => $page, 'paginate' => true,
                'status' => array_keys( wc_get_order_statuses() ), 'orderby' => 'ID', 'order' => 'ASC',
                'meta_query' => array( array(
                    'key' => 'wcb2bsa_sales_agent', 'value' => get_current_user_id(), 'compare' => '=',
                ) ),
            ) );
            $payload['documents'] = array_map( array( __CLASS__, 'document_data' ), $result->orders );
            $has_more_in_phase = $page < (int) $result->max_num_pages;
        }

        if ( $has_more_in_phase ) {
            $next = array( 'phase' => $phase, 'page' => $page + 1, 'snapshot' => $snapshot );
            $has_more = true;
        } else {
            $phases = array( 'customers', 'categories', 'products', 'documents' );
            $index = array_search( $phase, $phases, true );
            if ( false !== $index && isset( $phases[ $index + 1 ] ) ) {
                $next = array( 'phase' => $phases[ $index + 1 ], 'page' => 1, 'snapshot' => $snapshot );
                $has_more = true;
            } else {
                $next = array( 'phase' => 'customers', 'page' => 1, 'snapshot' => gmdate( 'c' ) );
                $has_more = false;
            }
        }

        $payload['nextCursor'] = self::encode_cursor( $next );
        $payload['hasMore'] = $has_more;
        return rest_ensure_response( $payload );
    }

    private static function prepare_document( WP_REST_Request $request, bool $persist ) {
        $type = strtoupper( (string) $request->get_param( 'type' ) );
        $local_id = sanitize_text_field( (string) $request->get_param( 'localId' ) );
        $customer = self::authorized_customer( (int) $request->get_param( 'customerId' ) );
        $items = $request->get_param( 'items' );
        if ( ! in_array( $type, array( 'ORDER', 'QUOTE' ), true ) || ! self::is_uuid( $local_id ) || ! $customer || ! is_array( $items ) || ! $items ) {
            return self::error( 'invalid_document', 'Tipo, localId, cliente autorizzato e righe sono obbligatori.', 422 );
        }

        try {
            if ( $persist ) {
                $order = wc_create_order( array( 'customer_id' => $customer->get_id(), 'created_via' => 'agent-mobile-api' ) );
                if ( is_wp_error( $order ) ) {
                    return self::error( 'order_create_failed', $order->get_error_message(), 422 );
                }
            } else {
                $order = new WC_Order();
                $order->set_customer_id( $customer->get_id() );
                $order->set_created_via( 'agent-mobile-api-validation' );
            }

            $order->set_address( $customer->get_billing(), 'billing' );
            $order->set_address( $customer->get_shipping(), 'shipping' );
            $order->set_customer_note( sanitize_textarea_field( (string) $request->get_param( 'customerNote' ) ) );
            $order->update_meta_data( '_ama_local_id', $local_id );
            $order->update_meta_data( 'wcb2bsa_sales_agent', get_current_user_id() );
            $order->update_meta_data( 'wcb2bsa_created_by', 'sales_agent' );
            $order->update_meta_data( 'billing_tipo_doc', 'QUOTE' === $type ? 'PREVENTIVO' : 'ORDINE' );

            foreach ( $items as $input ) {
                $item_result = self::build_order_item( (array) $input );
                if ( is_wp_error( $item_result ) ) {
                    if ( $persist ) {
                        $order->delete( true );
                    }
                    return $item_result;
                }
                $order->add_item( $item_result );
            }

            do_action( 'ama_before_calculate_document', $order, $request );
            $order->calculate_totals( true );

            if ( $persist ) {
                self::with_email_policy( static function () use ( $order ) {
                    $order->save();
                    $order->update_status( 'completed' );
                } );
                return self::document_data( $order );
            }

            $data = self::document_data( $order );
            return array(
                'valid'           => true,
                'currency'        => $data['currency'],
                'subtotal'        => $data['subtotal'],
                'shippingTotal'   => $data['shippingTotal'],
                'taxTotal'        => $data['taxTotal'],
                'total'           => $data['total'],
                'items'           => $data['items'],
                'priceAdjustments'=> array(),
            );
        } catch ( Throwable $e ) {
            return self::error( 'document_failed', 'Errore durante il calcolo del documento.', 500, array( 'exception' => $e->getMessage() ) );
        }
    }

    private static function build_order_item( array $input ) {
        $product = wc_get_product( absint( $input['productId'] ?? 0 ) );
        $quantity = (float) ( $input['quantity'] ?? 0 );
        $level = strtoupper( sanitize_text_field( (string) ( $input['priceLevel'] ?? 'L1' ) ) );
        if ( ! $product || ! $product->is_type( 'simple' ) || $quantity <= 0 || ! in_array( $level, array( 'L0', 'L1', 'L2', 'L3' ), true ) ) {
            return self::error( 'invalid_item', 'Prodotto, quantita o livello prezzo non validi.', 422 );
        }

        $base_price = self::product_level_price( $product, $level );
        if ( null === $base_price ) {
            return self::error( 'price_unavailable', 'Il listino selezionato non e disponibile per il prodotto.', 422, array( 'productId' => $product->get_id(), 'priceLevel' => $level ) );
        }
        $price = $base_price;
        $manual = $input['manualPrice'] ?? null;
        if ( 'L0' !== $level && null !== $manual && 'yes' === get_option( 'ama_allow_manual_price', 'yes' ) ) {
            $price = max( 0, (float) $manual );
        }

        $discount_1 = 'yes' === get_option( 'ama_allow_discounts', 'yes' ) ? self::discount( $input['discount1'] ?? 0, 'ama_max_discount_1' ) : 0;
        $discount_2 = 'yes' === get_option( 'ama_allow_discounts', 'yes' ) ? self::discount( $input['discount2'] ?? 0, 'ama_max_discount_2' ) : 0;
        if ( 'L0' === $level ) {
            $price = 0;
            $discount_1 = 0;
            $discount_2 = 0;
        }
        $final = $price * ( 1 - $discount_1 / 100 ) * ( 1 - $discount_2 / 100 );

        $item = new WC_Order_Item_Product();
        $item->set_product( $product );
        $item->set_quantity( $quantity );
        $item->set_subtotal( wc_format_decimal( $final * $quantity ) );
        $item->set_total( wc_format_decimal( $final * $quantity ) );
        $item->add_meta_data( '_ama_local_id', sanitize_text_field( (string) ( $input['localId'] ?? '' ) ), true );
        $item->add_meta_data( '_ama_price_level', $level, true );
        $item->add_meta_data( '_ama_list_price', wc_format_decimal( $base_price ), true );
        $item->add_meta_data( '_ama_manual_price', null === $manual ? '' : wc_format_decimal( $manual ), true );
        $item->add_meta_data( '_ama_discount_1', wc_format_decimal( $discount_1 ), true );
        $item->add_meta_data( '_ama_discount_2', wc_format_decimal( $discount_2 ), true );
        $item->add_meta_data( '_ama_final_unit_price', wc_format_decimal( $final ), true );
        return $item;
    }

    private static function product_level_price( WC_Product $product, string $level ): ?float {
        if ( 'L0' === $level ) {
            return 0.0;
        }

        $prefix = strtolower( $level );
        $fields = apply_filters(
            'ama_price_field_candidates',
            array( $prefix . '_conf', $prefix . 'c' ),
            $level,
            $product
        );

        foreach ( array_unique( array_filter( (array) $fields ) ) as $field ) {
            $value = function_exists( 'get_field' )
                ? get_field( $field, $product->get_id() )
                : get_post_meta( $product->get_id(), $field, true );
            if ( '' !== $value && null !== $value && is_numeric( str_replace( ',', '.', (string) $value ) ) ) {
                return (float) str_replace( ',', '.', (string) $value );
            }
        }

        return null;
    }

    private static function authorized_customer( int $customer_id ): ?WC_Customer {
        if ( $customer_id <= 0 || (int) get_user_meta( $customer_id, 'wcb2bsa_sales_agent', true ) !== get_current_user_id() ) {
            return null;
        }
        try {
            $customer = new WC_Customer( $customer_id );
            return $customer->get_id() ? $customer : null;
        } catch ( Throwable $e ) {
            return null;
        }
    }

    private static function authorized_order( int $order_id ): ?WC_Order {
        $order = wc_get_order( $order_id );
        if ( ! $order || (int) $order->get_meta( 'wcb2bsa_sales_agent', true ) !== get_current_user_id() ) {
            return null;
        }
        return self::authorized_customer( $order->get_customer_id() ) ? $order : null;
    }

    public static function customer_data( $user ): array {
        $user_id = $user instanceof WP_User ? $user->ID : ( $user instanceof WC_Customer ? $user->get_id() : (int) ( $user->ID ?? 0 ) );
        return array(
            'id'             => $user_id,
            'company'        => (string) get_user_meta( $user_id, 'billing_company', true ),
            'firstName'      => (string) get_user_meta( $user_id, 'billing_first_name', true ),
            'lastName'       => (string) get_user_meta( $user_id, 'billing_last_name', true ),
            'email'          => (string) get_user_meta( $user_id, 'billing_email', true ) ?: (string) get_userdata( $user_id )->user_email,
            'phone'          => (string) get_user_meta( $user_id, 'billing_phone', true ),
            'billingAddress' => array(
                'address1' => (string) get_user_meta( $user_id, 'billing_address_1', true ),
                'address2' => (string) get_user_meta( $user_id, 'billing_address_2', true ),
                'city'     => (string) get_user_meta( $user_id, 'billing_city', true ),
                'state'    => (string) get_user_meta( $user_id, 'billing_state', true ),
                'postcode' => (string) get_user_meta( $user_id, 'billing_postcode', true ),
                'country'  => (string) get_user_meta( $user_id, 'billing_country', true ),
            ),
            'vatNumber'     => (string) get_user_meta( $user_id, 'billing_piva', true ),
            'fiscalCode'    => (string) get_user_meta( $user_id, 'billing_cf', true ),
            'sdi'           => (string) get_user_meta( $user_id, 'billing_sdi', true ),
            'paymentTerms'  => (string) get_user_meta( $user_id, 'billing_pagamento', true ),
            'iban'          => (string) get_user_meta( $user_id, 'billing_iban', true ),
            'notes'         => (string) get_user_meta( $user_id, 'billing_note', true ),
            'b2bGroupId'    => (int) get_user_meta( $user_id, 'wcb2b_group', true ) ?: null,
            'salesAgentId'  => (int) get_user_meta( $user_id, 'wcb2bsa_sales_agent', true ),
            'updatedAt'     => gmdate( 'c', strtotime( get_userdata( $user_id )->user_registered . ' UTC' ) ),
        );
    }

    public static function category_data( WP_Term $term ): array {
        $image_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
        return array(
            'id'          => $term->term_id,
            'parentId'    => $term->parent ?: null,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description ?: null,
            'imageUrl'    => $image_id ? wp_get_attachment_url( $image_id ) : null,
            'menuOrder'   => (int) get_term_meta( $term->term_id, 'order', true ),
            'updatedAt'   => gmdate( 'c' ),
        );
    }

    public static function product_data( WC_Product $product ): array {
        $image_id = $product->get_image_id();
        return array(
            'id'               => $product->get_id(),
            'sku'              => $product->get_sku() ?: null,
            'name'             => $product->get_name(),
            'type'             => 'simple',
            'description'      => $product->get_description() ?: null,
            'shortDescription' => $product->get_short_description() ?: null,
            'categoryIds'      => array_map( 'intval', $product->get_category_ids() ),
            'imageUrl'         => $image_id ? wp_get_attachment_url( $image_id ) : null,
            'prices'           => array(
                'L0' => '0',
                'L1' => self::nullable_decimal( self::product_level_price( $product, 'L1' ) ),
                'L2' => self::nullable_decimal( self::product_level_price( $product, 'L2' ) ),
                'L3' => self::nullable_decimal( self::product_level_price( $product, 'L3' ) ),
            ),
            'taxClass'         => $product->get_tax_class() ?: null,
            'stockStatus'      => $product->get_stock_status(),
            'stockQuantity'    => $product->get_stock_quantity(),
            'package'          => array(
                'unit'   => self::custom_field( $product->get_id(), 'confezione_um' ),
                'pieces' => self::custom_field( $product->get_id(), 'confezione_nr_pezzi' ),
                'format' => self::custom_field( $product->get_id(), 'confezione_formato' ),
            ),
            'updatedAt'        => $product->get_date_modified() ? $product->get_date_modified()->date( DATE_ATOM ) : gmdate( 'c' ),
        );
    }

    public static function document_summary( WC_Order $order ): array {
        return array(
            'id'              => $order->get_id(),
            'number'          => $order->get_order_number(),
            'type'            => 'PREVENTIVO' === $order->get_meta( 'billing_tipo_doc', true ) ? 'QUOTE' : 'ORDER',
            'customerId'      => $order->get_customer_id(),
            'customerCompany' => $order->get_billing_company(),
            'status'          => $order->get_status(),
            'currency'        => $order->get_currency(),
            'total'           => $order->get_total(),
            'createdAt'       => $order->get_date_created() ? $order->get_date_created()->date( DATE_ATOM ) : gmdate( 'c' ),
            'updatedAt'       => $order->get_date_modified() ? $order->get_date_modified()->date( DATE_ATOM ) : gmdate( 'c' ),
        );
    }

    public static function document_data( WC_Order $order ): array {
        $data = self::document_summary( $order );
        $data['localId'] = (string) $order->get_meta( '_ama_local_id', true );
        $data['salesAgentId'] = (int) $order->get_meta( 'wcb2bsa_sales_agent', true );
        $data['customerNote'] = $order->get_customer_note() ?: null;
        $data['subtotal'] = wc_format_decimal( $order->get_subtotal() );
        $data['shippingTotal'] = $order->get_shipping_total();
        $data['taxTotal'] = $order->get_total_tax();
        $data['items'] = array();
        foreach ( $order->get_items() as $item ) {
            $quantity = (float) $item->get_quantity();
            $data['items'][] = array(
                'id'             => $item->get_id() ?: null,
                'localId'        => (string) $item->get_meta( '_ama_local_id', true ),
                'productId'      => $item->get_product_id(),
                'productName'    => $item->get_name(),
                'sku'            => $item->get_product() ? $item->get_product()->get_sku() : null,
                'quantity'       => $quantity,
                'priceLevel'     => (string) $item->get_meta( '_ama_price_level', true ) ?: 'L1',
                'manualPrice'    => self::nullable_decimal( $item->get_meta( '_ama_manual_price', true ) ),
                'discount1'      => self::nullable_decimal( $item->get_meta( '_ama_discount_1', true ) ),
                'discount2'      => self::nullable_decimal( $item->get_meta( '_ama_discount_2', true ) ),
                'listPrice'      => (string) $item->get_meta( '_ama_list_price', true ),
                'finalUnitPrice' => (string) $item->get_meta( '_ama_final_unit_price', true ) ?: wc_format_decimal( $quantity ? $item->get_total() / $quantity : 0 ),
                'subtotal'       => $item->get_subtotal(),
                'taxTotal'       => $item->get_total_tax(),
                'total'          => $item->get_total(),
            );
        }
        $data['priceAdjustments'] = array();
        return $data;
    }

    private static function agent_data( WP_User $user ): array {
        return array(
            'id'          => (int) $user->ID,
            'username'    => $user->user_login,
            'displayName' => $user->display_name,
            'email'       => $user->user_email ?: null,
            'roles'       => array_values( $user->roles ),
            'status'      => AMA_Auth::is_sales_agent( (int) $user->ID ) ? 'active' : null,
        );
    }

    private static function idempotency_key( WP_REST_Request $request ) {
        $key = sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' ) );
        return self::is_uuid( $key ) ? $key : self::error( 'invalid_idempotency_key', 'Idempotency-Key UUID obbligatoria.', 422 );
    }

    private static function idempotency_replay( string $key, WP_REST_Request $request, string $type ): ?WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'ama_idempotency';
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND idempotency_key = %s LIMIT 1",
            get_current_user_id(), $key
        ) );
        if ( ! $row ) {
            return null;
        }
        if ( ! hash_equals( $row->request_hash, self::request_hash( $request ) ) || $row->resource_type !== $type ) {
            return new WP_REST_Response( self::error_data( 'idempotency_conflict', 'Chiave gia usata con un payload diverso.' ), 409 );
        }
        return new WP_REST_Response( json_decode( $row->response_json, true ), 200 );
    }

    private static function idempotency_store( string $key, WP_REST_Request $request, string $type, int $resource_id, array $response ): void {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'ama_idempotency', array(
            'user_id'        => get_current_user_id(),
            'idempotency_key'=> $key,
            'request_hash'   => self::request_hash( $request ),
            'resource_type'  => $type,
            'resource_id'    => $resource_id,
            'response_json'  => wp_json_encode( $response ),
            'created_at'     => current_time( 'mysql', true ),
        ), array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' ) );
    }

    private static function request_hash( WP_REST_Request $request ): string {
        return hash( 'sha256', wp_json_encode( $request->get_json_params() ) );
    }

    private static function with_email_policy( callable $callback ) {
        $block = static function () { return false; };
        if ( 'yes' === get_option( 'ama_disable_emails', 'no' ) ) {
            add_filter( 'pre_wp_mail', $block, PHP_INT_MAX );
        }
        try {
            return $callback();
        } finally {
            remove_filter( 'pre_wp_mail', $block, PHP_INT_MAX );
        }
    }

    private static function discount( $value, string $option ): float {
        $discount = min( 100, max( 0, (float) $value ) );
        $max = self::nullable_number_option( $option );
        return null === $max ? $discount : min( $discount, $max );
    }

    private static function custom_field( int $post_id, string $key ) {
        return function_exists( 'get_field' ) ? get_field( $key, $post_id ) : get_post_meta( $post_id, $key, true );
    }

    private static function nullable_number_option( string $key ): ?float {
        $value = get_option( $key, '' );
        return '' === $value ? null : (float) $value;
    }

    private static function nullable_decimal( $value ): ?string {
        return '' === $value || null === $value ? null : wc_format_decimal( $value );
    }

    private static function unique_username( string $base ): string {
        $base = substr( sanitize_user( $base, true ), 0, 50 ) ?: 'mobile-customer';
        $candidate = $base;
        $suffix = 1;
        while ( username_exists( $candidate ) ) {
            $candidate = $base . '-' . $suffix++;
        }
        return $candidate;
    }

    private static function is_uuid( string $value ): bool {
        return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
    }

    private static function encode_cursor( array $data ): string {
        return rtrim( strtr( base64_encode( wp_json_encode( $data ) ), '+/', '-_' ), '=' );
    }

    private static function decode_cursor( string $cursor ): array {
        if ( ! $cursor ) {
            return array();
        }
        $decoded = base64_decode( strtr( $cursor, '-_', '+/' ), true );
        $data = $decoded ? json_decode( $decoded, true ) : null;
        return is_array( $data ) ? $data : array();
    }

    private static function user_from_access_token( string $token ): ?WP_User {
        global $wpdb;
        $table = $wpdb->prefix . 'ama_sessions';
        $user_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE access_hash = %s AND revoked_at IS NULL LIMIT 1",
            hash( 'sha256', $token )
        ) );
        return $user_id ? get_userdata( (int) $user_id ) : null;
    }

    private static function not_found() {
        return self::error( 'not_found', 'Risorsa non trovata.', 404 );
    }

    private static function error( string $code, string $message, int $status, array $details = array() ): WP_Error {
        return new WP_Error( $code, $message, array( 'status' => $status, 'details' => $details, 'requestId' => wp_generate_uuid4() ) );
    }

    private static function error_data( string $code, string $message, array $details = array() ): array {
        return array( 'code' => $code, 'message' => $message, 'details' => $details ?: null, 'requestId' => wp_generate_uuid4() );
    }
}
