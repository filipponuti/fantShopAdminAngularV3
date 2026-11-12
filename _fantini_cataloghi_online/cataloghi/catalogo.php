<?php
    //variabili globali
    $cfg_file = '';
    $cfg_config = [];
    $cfg_prod_hide = [];
    $cfg_section_hide = [];
    $cfg_prod_pdf = [];
    $cfg_section_pdf = [];
    $footer_catalogo = 'catalogo';
    $footer_sezione = 'sezione';
    $footer_logo = 'logo';
    $footer_pagina = 0;

    //lista categorie
    function listaCategorie($parent, $livello) {
        $categorie = array();
        try {
            $args = array(
                'taxonomy'   => "product_cat",
                'hierarchical' => true,
                'hide_empty' => 1,
                'parent' => $parent
            );
            $children = get_terms($args);
            foreach( $children as $nodo ) {
                $nodo_id = $nodo->term_id;
                $nodo_nome = $nodo->name;
                $data['id'] = $nodo_id;
                $data['nome'] = $nodo_nome;
                $data['slug'] = $nodo->slug;
                $data['livello'] = $livello;
                $desc = str_repeat("--", $livello);
                if ($livello > 0) {
                    $desc = $desc . '> ';
                }
                $desc = $desc . $nodo_nome;
                $data['desc'] = $desc;
                // aggiungo alle categorie
                array_push($categorie, $data);
                //chiamo i figli
                $liv = $livello+1; 
                $a=listaCategorie($nodo_id, $liv);
                $categorie = array_merge($categorie, $a);
            }
        } catch (Exception $ex) {
            /* ERROR LIKE "SKU ALREADY EXISTS" */
            echo "ERRORE: " . $ex->getMessage();
        }            
        return $categorie;
    }

    function get_current_user_first_role() {
		$current_user = wp_get_current_user();
		return ( isset( $current_user->roles[0] ) && '' !== $current_user->roles[0] ) ? $current_user->roles[0] : 'guest';
	}

    //scrivo la riga Titoli prodotto
    function rigaProdottoTitoli($tipo) {
        ?>
        <div class="fantini-catalogo-riga-articolo titolo <?php echo $tipo; ?>">
            <!-- img -->
            <div class="fantini-catalogo-articolo-col titolo fant-col-20">
                Foto
            </div>
            <!-- codice -->
            <div class="fantini-catalogo-articolo-col titolo fant-col-15">
                Codice
            </div>
            <!-- nome -->
            <div class="fantini-catalogo-articolo-col titolo fant-col-30">
                Articolo
            </div>
            <!-- prezzo -->
            <div class="fantini-catalogo-articolo-col titolo dx-titolo fant-col-10">
                <!-- Prezzo -->
                <!--
                <div class="cella-doppia-sx">
                    Iva
                </div>
                -->
                <div class="cella-doppia-dx">
                    Prezzo
                </div>
            </div>
            <!-- note -->
            <div class="fantini-catalogo-articolo-col titolo fant-col-20">
                Note
            </div>            
            <!-- prezzo -->
            <div class="fantini-catalogo-articolo-col titolo dx-titolo fant-col-5">
                <!-- Prezzo -->
                <div class="cella-doppia-sx">
                    Iva
                </div>
                <!--
                <div class="cella-doppia-dx">
                    Prezzo
                </div>
                -->
            </div>
        </div>
        <?php
    }

    //scrivo la riga Titoli Per le Variazioni
    function rigaProdottoVariazioneTitoli($tipo, $is_nutrizione, $is_modello) {
        ?>
        <div class="fantini-catalogo-riga-articolo-variazioni titolo <?php echo $tipo; ?>">
            <!-- img -->
            <div class="fantini-catalogo-articolo-col titolo variazione hide fant-col-20">
            </div>
            <!-- codice -->
            <div class="fantini-catalogo-articolo-col titolo variazione fant-col-20">
                <!-- Codice -->
                <?php
                echo 'Codice'; // . $is_nutrizione;
                ?>
            </div>
            <!-- Prezzo -->
            <div class="fantini-catalogo-articolo-col titolo variazione dx fant-col-10">
                <!-- Codice -->
                <?php
                echo 'Prezzo'; // . $is_nutrizione;
                ?>
            </div>
            <!-- codice a Barre -->
            <!--
            <div class="fantini-catalogo-articolo-col titolo variazione fant-col-20">
                Cod. a barre
            </div>
            -->
            <?php
            //$_misura = 'Mis. ( Larg. x Lung. - Alt. )';
            $_misura = 'Misure Esterne';
            $_colore = 'Colore';
            if ($is_modello == 'si'){
                $_colore = 'Modello';
            }
            if ( $is_nutrizione == '1' ){
                $_misura = 'Formato';
                $_colore = '';
            }
            //echo '<br>is_modello: ' . $is_modello;
            ?>
            <!-- misura -->
            <div class="fantini-catalogo-articolo-col titolo variazione fant-col-15">
                <?php echo $_misura; ?>
            </div>
            <!-- colore -->
            <?php 
            if ( $is_nutrizione != '1' ){
            ?>
            <div class="fantini-catalogo-articolo-col titolo variazione fant-col-15">
                <?php echo $_colore; ?>
            </div>
            <?php 
            }
            ?>         
            <!-- prezzo -->
            <div class="fantini-catalogo-articolo-col titolo variazione center-titolo fant-col-5">
                <!-- Prezzo -->
                <div class="cella-doppia-sx">
                    Iva
                </div>
                <!--
                <div class="cella-doppia-dx">
                    Prezzo
                </div>
                -->
            </div>
        </div>
        <?php
    }

        //scrivo la riga Titoli Per le Variazioni BANCALI
        function rigaProdottoVariazioniBancaliTitoli($tipo, $formato, $unita_misura, $_is_cartone) {
            $_titolo_listino_2 = 'Da 1 a 2 BANCALI';
            $_titolo_listino_3 = 'Da 3 a 5 BANCALI';
            $_titolo_listino_4 = 'Da 6 BANCALI';
            if ($_is_cartone == '1'){
                $_titolo_listino_2 = 'Da 1 a 6 BANCALI';
                $_titolo_listino_3 = 'Da 7 BANCALI';
                $_titolo_listino_4 = 'PALBOX';
            }

            ?>
            <div class="fantini-catalogo-riga-articolo-variazioni titolo <?php echo $tipo; ?>">
                <!-- formato -->
                <div class="fantini-catalogo-articolo-col titolo variazione fant-col-10">
                    <!-- Litri per sacco -->
                    <?php echo $formato; ?>
                </div>
                <!-- sacchi -->
                <div class="fantini-catalogo-articolo-col titolo variazione fant-col-17">
                    <!-- Pezzi per pallet -->
                    <?php echo $unita_misura; ?>
                </div>
                <!-- Listino 2 -->
                <div class="fantini-catalogo-articolo-col titolo variazione fant-col-18">
                    <?php echo $_titolo_listino_2; ?>
                    <br>
                    <span>CARICO ASSORTITO</span>
                </div>
                <!-- Listino 3 -->
                <div class="fantini-catalogo-articolo-col titolo variazione fant-col-18">
                    <?php echo $_titolo_listino_3; ?>
                    <br>
                    <span>CARICO ASSORTITO</span>
                </div>
                <!-- Listino 4 -->
                <div class="fantini-catalogo-articolo-col titolo variazione fant-col-18">
                    <?php echo $_titolo_listino_4; ?>
                    <?php 
                    if ($_is_cartone != '1'){
                    ?>
                        <br>
                        <span>CARICO ASSORTITO</span>
                    <?php 
                    }
                    ?>
                </div>
                <!-- Iva -->
                <div class="fantini-catalogo-articolo-col titolo variazione fant-col-5">
                    Iva
                </div>
            </div>
            <?php
        }
    
    function rigaProdottoVariazioneSenzaRiepilogoFoto() {
        //pippo
        ?>
        <div class="fantini-catalogo-articolo-riepilogo-senza-foto">
        </div>
        <?php
    }

    //scrivo la riga prodotto BANCALI
    function rigaProdottoBancali($product, $sconto, $debug, $conta) {
        $cls_col_foto = 'fant-col-20';
        $cls_col_dati = 'fant-col-80';
        $cls_col_cod  = 'fant-col-15';
        $cls_col_desc = 'fant-col-65';

        $cls_variable = '';
        $cls_img_variable = '';
        //echo '<pre>'; print_r($product); echo '</pre>';

        //um
        $unita_misura = $product->get_attribute( 'um' );
        $unita_misuraVal = 'Pezzi per pallet';
        $formato_label = 'Litri per sacco';
        if ($unita_misura) {
            $unita_misuraVal = $unita_misura;
            $formato_label = 'Formato';
        }

        //verifico se CARTONI
        $confezione = $product->get_attribute( 'bancali' );
        $_is_cartone = '0';
        //echo '<br>....' . $confezione;
        if ($confezione) {
            $confezione = strtolower($confezione);
            if ($confezione == 'cartoni' || $confezione == 'cartone' ) {
                $formato_label = 'Formato';
                $unita_misuraVal = 'Q.tà x cart.';
                $_is_cartone = '1';
            }
        }

        //ecologico
        $ecologico = $product->get_attribute( 'ecologico' );
        //SORT: inizio
        //------------
        $variations = $product->get_available_variations();
        //echo '<pre>'; print_r($variations); echo '</pre>';
        $var_id = '';
        $var_sku = '';
        $a_prodotti = [];
        //iva
        $_iva = get_post_meta( $product->get_id(), 'ci_iva', true );
        $_iva = $_iva . '%';
        //scorro le variazioni e preparo l'array
        foreach ( $variations as $variation ){
            //dati
            $var_id = $variation['variation_id'];
            $var_sku = trim('' . $variation['sku']);
            $var_formato = '';
            $var_misura = '';
            $bancali_l2 = 0;
			$bancali_l3 = 0;
			$bancali_l4 = 0;
            $sacchi = 0;
            $atts = $variation['attributes'];
            //echo '<pre>'; print_r($atts); echo '</pre>';
            if (array_key_exists('attribute_pa_formato', $atts)) {			
                $slug = $atts['attribute_pa_formato'];
                $var_formato = get_term_by( 'slug', $slug, 'pa_formato' )->name;
            }
            //palbox
            $_cartone_palbox = '0';
            if ($_is_cartone == '1'){
                $_tmp = strtoupper($var_formato);
                $_box = 'BOX';
                if(strpos($_tmp, $_box) !== false)
                    $_cartone_palbox = '1';
            }
            //echo '<br>...._cartone_palbox:' . $_cartone_palbox;
            //campo sort
            $p_sort = $var_formato;
            //altri dati
            $sacchi = get_post_meta( $var_id,   'bancali_sacchi_field', true );
			$listino2 = get_post_meta($var_id , '_regular_price', true );
			$listino3 = get_post_meta( $var_id, 'ci_listino3', true );
		    $listino4 = get_post_meta( $var_id, 'ci_listino4', true );
            $listino3 = str_replace(',', '.', $listino3);
            $listino4 = str_replace(',', '.', $listino4);
            //listini in caso di cartoni
            if ($_is_cartone == '1'){
                if ($_cartone_palbox == '0') {
                    //cartone
                    $listino4 = '-';
                } else {
                    //palbox
                    $listino2 = '-';
                    $listino3 = '-';
                }
            }
            //aggiungo record
            $a_prodotti[] =  array( 
                    'sort'          => $p_sort, 
                    'id'            => $var_id,
                    'sku'           => $var_sku, 
                    'formato'       => $var_formato,
                    'sacchi'        => $sacchi,
                    'listino2'      => $listino2,
                    'listino3'      => $listino3,
                    'listino4'      => $listino4
            );
            //echo '<pre>'; print_r($a_prodotti); echo '</pre>';
        }
        // riordino per il campo sort
        array_multisort( array_column($a_prodotti, "sort"), SORT_ASC, $a_prodotti );
        //SORT: fine
        //------------
        ?>
        <div class="fantini-catalogo-riga-articolo bancali <?php echo $cls_variable; ?> ">
            <!-- img -->
            <div class="fantini-catalogo-articolo-col bancali-foto <?php echo $cls_col_foto; ?>"  >
                <div class="wrap-img <?php echo $cls_img_variable; ?>  "> 
                    <img src="<?php echo wp_get_attachment_url( $product->get_image_id() ); ?>" />
                </div>
            </div>
            <!-- DATI -->
            <div class="fantini-catalogo-articolo-col bancali-dati <?php echo $cls_col_dati; ?>">
                <!-- riga TITOLO -->
                <div class="fantini-catalogo-bancali-riga titolo">
                    <!-- codice -->
                    <div class="fantini-catalogo-articolo-col codice <?php echo $cls_col_cod; ?>">
                        <?php echo $product->get_sku(); ?>
                    </div>
                    <!-- nome -->
                    <div class="fantini-catalogo-articolo-col desc <?php echo $cls_col_desc; ?>">
                        <?php 
                            echo $product->get_name();
                        ?>
                    </div>
                </div>
                <!-- riga PREZZI -->
                <div class="fantini-catalogo-bancali-riga variazioni ">
                <?php
                    //scorro le vairazioni
                    $conta = 0;
                    foreach($a_prodotti as $prod) {
                        $conta = $conta + 1;
                        $listino2 = $prod['listino2'];
                        $listino3 = $prod['listino3'];
                        $listino4 = $prod['listino4'];
                        $formato =  $prod['formato'];
                        $sacchi =   $prod['sacchi'];
                        if ( $conta == 1)
                            rigaProdottoVariazioniBancaliTitoli('bancali', $formato_label, $unita_misuraVal, $_is_cartone);
                        ?>
                        <!-- riga prezzi -->
                        <div class="fantini-catalogo-bancali-riga prezzi ">
                            <!-- formato -->
                            <div class="fantini-catalogo-articolo-col prezzo fant-col-10">
                                <div class="p-bancale">
                                    <?php 
                                        echo $formato;
                                    ?>
                                </div>
                            </div>
                            <!-- sacchi -->
                            <div class="fantini-catalogo-articolo-col prezzo fant-col-17">
                                <div class="p-bancale">
                                    <?php 
                                        echo $sacchi;
                                    ?>
                                </div>
                            </div>
                            <!-- listino 2 -->
                            <div class="fantini-catalogo-articolo-col  fant-col-18">
                                <div class="p-bancale">
                                    <?php 
                                        if ($listino2 == '-')
                                        echo $listino2;
                                    else
                                        echo wc_price($listino2);
                                ?>
                                </div>
                            </div>
                            <!-- listino 3 -->
                            <div class="fantini-catalogo-articolo-col  fant-col-18">
                                <div class="p-bancale">
                                    <?php 
                                        if ($listino3 == '-')
                                        echo $listino3;
                                    else
                                        echo wc_price($listino3);
                                ?>
                                </div>
                            </div>
                            <!-- listino 4 -->
                            <div class="fantini-catalogo-articolo-col  fant-col-18">
                                <div class="p-bancale">
                                    <?php 
                                        if ($listino4 == '-')
                                        echo $listino4;
                                    else
                                        echo wc_price($listino4);
                                ?>
                                </div>
                            </div>

                            <!-- Iva -->
                            <div class="fantini-catalogo-articolo-col  fant-col-5">
                                <div class="p-bancale">
                                    <?php 
                                        echo $_iva;
                                    ?>
                                </div>
                            </div>

                        </div>
                        <?php
                    }
                ?>
                </div>
                <!-- riga DESC -->
                <div class="fantini-catalogo-bancali-riga descrizione">
                    <?php
                        //echo $product->get_description();
                        echo wpautop($product->get_description());
                        ?>
                    </div>

                <!-- riga ICONE -->
                <div class="fantini-catalogo-bancali-riga biologico">
                    <?php
                    if ($ecologico) {
                        if ($ecologico == 'Biologico') {
                            $upload_dir = wp_get_upload_dir();
                            $img = $upload_dir['baseurl'] . '/prod-bio.png';
                            echo '<div class="fantini-griglia-prod-img-color">';
                                    echo  '<img class="prod-icon-color" src="' . $img . '" />';
                            echo '</div>';
                        }
                        if ($ecologico == 'Riciclato') {
                            $upload_dir = wp_get_upload_dir();
                            $img = $upload_dir['baseurl'] . '/prod-ric.png';
                            echo '<div class="fantini-griglia-prod-img-color">';
                                    echo  '<img class="prod-icon-color" src="' . $img . '" />';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    //scrivo la riga prodotto
    function rigaProdotto($product, $sconto, $debug, $conta, $is_nutrizione, $_iva, $simplev2) {
        $cls_variable = '';
        $cls_img_variable = '';
        $cls_nascondi = '';
        $cls_col_foto = 'fant-col-20';
        $cls_col_cod  = 'fant-col-15';
        $cls_col_barcode  = 'fant-col-20';
        $cls_col_desc = 'fant-col-30';
        $cls_col_note = 'fant-col-20';
        $cls_col_prezzo = 'fant-col-15';

        $tipo_prodotto = $product->get_type();

        if ($product->get_type() == 'simple') {
            //$cls_col_prezzo = 'fant-col-15 campo-iva-simple';
            $cls_col_prezzo = 'fant-col-5 campo-iva-simple';
            //2022.08.02
            $cls_img_variable = 'img-variable';

            //$simplev2
            if ($simplev2 == '1') {
                //come il variation
                $tipo_prodotto = 'variation';
                $cls_img_variable = 'img-variable';
                $cls_nascondi = 'nascondi';
                $cls_col_foto = 'fant-col-20';
                $cls_col_barcode = 'fant-col-20';
                $cls_col_cod  = 'fant-col-20';
                $cls_col_desc = 'fant-col-15';
                $cls_col_note = 'fant-col-15';
                //$cls_col_prezzo = 'fant-col-15 campo-iva-simple';
                $cls_col_prezzo = 'fant-col-5 campo-iva-simple';
            }

            if ($simplev2 == '2') {
                //come il variable
                $tipo_prodotto = 'variable';
                $cls_img_variable = 'img-variable';
                $cls_variable = 'variable';
                $cls_col_desc = 'fant-col-48';
                $cls_col_note = 'fant-col-20';
                $cls_col_prezzo = 'fant-col-15 campo-iva';
                $cls_col_prezzo = 'fant-col-0';
            }
        }

        if ($product->get_type() == 'variable') {
            $cls_img_variable = 'img-variable';
            $cls_variable = 'variable';
            $cls_col_desc = 'fant-col-48';
            $cls_col_note = 'fant-col-0';
            $cls_col_prezzo = 'fant-col-15 campo-iva';
        }

        if ($product->get_type() == 'variation') {
            $cls_img_variable = 'img-variable';
            $cls_nascondi = 'nascondi';
            $cls_col_foto = 'fant-col-20';
            $cls_col_barcode = 'fant-col-20';
            $cls_col_cod  = 'fant-col-20';
            $cls_col_desc = 'fant-col-15';
            $cls_col_note = 'fant-col-15';
            //$cls_col_prezzo = 'fant-col-15 campo-iva-simple';
            $cls_col_prezzo = 'fant-col-5 campo-iva-simple';
        }

        if ($is_nutrizione == '1')
            $cls_variable = $cls_variable . ' is-nutrizione';

        ?>
        <div class="fantini-catalogo-riga-articolo  <?php echo $cls_variable; ?> "
            data-sku="<?php echo trim('' . $product->get_sku()); ?>"
            data-tipo="<?php echo trim('' . $tipo_prodotto); ?>"
        >
            <!-- img -->
            <div class="fantini-catalogo-articolo-col <?php echo $cls_col_foto; ?>"  >
                <?php
                    if ($product->get_type() == 'variation' || $simplev2 == '1') {
                        //non carico l'immagine per i variations
                    } else {
                ?>
                    <div class="wrap-img <?php echo $cls_img_variable; ?>  "> 
                        <img src="<?php echo wp_get_attachment_url( $product->get_image_id() ); ?>" />
                    </div>
                <?php
                    }
                    //DEBUG
                    if ($debug == 'on'){
                        echo "<br>" . $conta;
                    }
                ?>
            </div>
            <!-- codice -->
            <div class="fantini-catalogo-articolo-col <?php echo $cls_col_cod; ?>">
                <?php echo $product->get_sku(); ?>
                <?php //echo $product->get_type(); ?>
            </div>

            <!-- Prezzo per le Variation -->
            <!-- Product Variations -->
            <?php
            if ($product->get_type() == 'variation' || $simplev2 == '1') {
            ?>
                <div class="fantini-catalogo-articolo-col dx prezzo fant-col-10">
                    <!--
                    <div class="cella-doppia-sx">
                        <?php 
                            //echo $_iva; 
                        ?>
                    </div>
                    -->
                    <div class="cella-doppia-dx">
                        <div class="p-pieno">
                            <?php 
                                $prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                echo wc_price($prezzo);
                            ?>
                        </div>
                    </div>

                        <!--
                        <div class="p-pieno">
                            <?php 
                                //$prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                //echo wc_price($prezzo);
                            ?>
                        </div>
                        -->
                </div>
            <?php
            }
            ?>

            <!-- codice a Barre -->
            <?php
            //if ($product->get_type() == 'variation') {
            ?>
                <!--
                <div class="fantini-catalogo-articolo-col <?php echo $cls_col_barcode; ?>">
                    <?php 
                        //$barcode = get_post_meta( $product->get_id(), 'ci_barcode', true );
                        //echo $barcode; 
                    ?>
                </div>
                -->
            <?php
            //}
            ?>

            <!-- nome -->
            <div class="fantini-catalogo-articolo-col nome <?php echo $cls_col_desc; ?>">
                <?php 
                    //echo $product->get_name();
                    if ($tipo_prodotto == 'variation' ) {
                        $s_attributo = $product->get_attribute( 'Misura' );
                        //costruisco la misura LxWxH
                        $_l = '000';
                        $_w = '000';
                        $_h = '000';
                        if ($product->get_length())
                            $_l = $product->get_length();
                        if ($product->get_width())
                            $_w = $product->get_width();
                        if ($product->get_height())
                            $_h = $product->get_height();

                        $_w = str_replace('.', ',', $_w);
                        $_l = str_replace('.', ',', $_l);
                        $_h = str_replace('.', ',', $_h);
                        //valore da stampare
                        //$s = 'cm. ' . $_w . ' x ' . $_l . '   h. ' . $_h;
                        $s = '';
                        //larg
                        if ($_w != '000')
                            $s = $s . 'cm. ' . $_w;
                        //lung
                        if ($_l != '000')
                            //$s = $s . '&nbsp; x &nbsp;' . $_l;
                            $s = $s . ' x ' . $_l;
                        //alt
                        if ($_h != '000')
                            $s = $s . ' - h. ' . $_h;
                        //nessun valore
                        if ($s == '')
                            $s = 'cm. ' . $s_attributo;

                        if ($is_nutrizione == 'var-nutrizione') {
                            //$slug = $atts['attribute_pa_formato'];
                            //$var_formato = get_term_by( 'slug', $slug, 'pa_formato' )->name;
                            $s = $product->get_attribute( 'Formato' );
                        }
                        echo $s;
                    } else {
                        echo $product->get_name();
                        //per i simple ed i variable riporto il barcode qui
                        //$barcode = get_post_meta( $product->get_id(), 'ci_barcode', true );
                        //if ($barcode != null) {
                        //    if ($barcode != 'Array'){
                        //        $s ='<div class="barcode-info"><span>Cod. a barre:</span> ' . $barcode . '</div>';
                        //        echo $s;
                        //    }
                        //}
                    }
                ?>
            </div>


            <!-- Prezzo per i Simple -->
            <!-- Product Simple -->
            <?php
            if ($tipo_prodotto == 'simple') {
                //$_iva = get_post_meta( $product->get_id(), 'ci_iva', true );
                //$_iva = $_iva . '%';
                $prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                //echo $_iva; 
            ?>
                <div class="fantini-catalogo-articolo-col dx prezzo fant-col-10">
                    <!-- prezzo per SIMPLE -->
                    <!--
                    <div class="cella-doppia-sx">
                        <?php 
                            //echo $_iva; 
                        ?>
                    </div>
                    -->
                    <div class="cella-doppia-dx">
                        <div class="p-pieno">
                            <?php 
                                $prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                echo wc_price($prezzo);
                            ?>
                        </div>
                    </div>
                </div>
            <?php
            }
            ?>

            <!-- note -->
            <?php
            if ($is_nutrizione != 'var-nutrizione') {
            ?>
            <div class="fantini-catalogo-articolo-col note <?php echo $cls_col_note; ?>">
                <?php 
                if ($tipo_prodotto == 'variable') {

                    if ($simplev2 == '2') {
                        //Confezione
                        $s = $product->get_attribute( 'Confezione' );
                        if ($s != '') {
                            $info = 'Confezione da ' . $s . ' pz.';
                            ?>
                                <div class="note-conf">
                                    <?php echo $info; ?>
                                </div>
                            <?php
                        }
                        //Set
                        $s = $product->get_attribute( 'Set' );
                        if ($s != '') {
                            $info = 'Set da ' . $s . ' pz.';
                            ?>
                                <div class="note-set">
                                    <?php echo $info; ?>
                                </div>
                            <?php
                        }
                    } else {

                    //non stampo le note
                    ?>
                    <div class="note-info">
                    </div>
                    <?php
                    }

                } else {
                    if ($tipo_prodotto != 'variation') {
                        $info = '';
                        //Misura
                        $s = $product->get_attribute( 'Misura' );
                        if ($s != '') {
                            $info = 'Misura: ' . $s . '';
                            ?>
                                <!--
                                <div class="note-info">
                                    <?php //echo $info; ?>
                                </div>
                                -->
                                <div class="note-info">
                                    <span>Misura: </span><?php echo $s; ?>
                                </div>
                            <?php
                        }
                    }
                    //Colore
                    if ($tipo_prodotto != 'variation') {
                        $s = $product->get_attribute( 'Colore' );
                        if ($s != '') {
                            $info = 'Colore: ' . $s . '';
                            ?>
                                <!--
                                <div class="note-info">
                                    <?php //echo $info; ?>
                                </div>
                                -->
                                <div class="note-info">
                                    <span>Colore: </span><?php echo $s; ?>
                                </div>
                            <?php
                        }
                    } else {
                        //per la VARIAZIONE
                        $s = $product->get_attribute( 'Colore' );
                        $s_modello = $product->get_attribute( 'Modello' ); 
                        $s = $s . $s_modello;
                        echo $s;
                    }
                    if ($tipo_prodotto != 'variation') {
                        //Confezione
                        $s = $product->get_attribute( 'Confezione' );
                        if ($s != '') {
                            $info = 'Confezione da ' . $s . ' pz.';
                            ?>
                                <div class="note-conf">
                                    <?php echo $info; ?>
                                </div>
                            <?php
                        }
                        //Set
                        $s = $product->get_attribute( 'Set' );
                        if ($s != '') {
                            $info = 'Set da ' . $s . ' pz.';
                            ?>
                                <div class="note-set">
                                    <?php echo $info; ?>
                                </div>
                            <?php
                        }
                    }
                }
                ?>
            </div>
            <?php
            }
            ?>
            <!-- EX prezzo, è diventato solo Iva --> 
            <div class="fantini-catalogo-articolo-col dx prezzo <?php echo $cls_col_prezzo; ?>">
                <!--<?php //echo 'sconto: ' . $sconto; ?> -->
                
                <!-- Product Simple -->
                <?php
                if ($tipo_prodotto == 'simple') {
                    //$_iva = get_post_meta( $product->get_id(), 'ci_iva', true );
                    //$_iva = $_iva . '%';
                    $prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                    //echo $_iva; 
                ?>
                    <!-- prezzo per SIMPLE -->
                    <div class="cella-doppia-sx">
                        <?php 
                            echo $_iva; 
                        ?>
                    </div>
                    <!--
                    <div class="cella-doppia-dx">
                        <div class="p-pieno">
                            <?php 
                                //$prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                //echo wc_price($prezzo);
                            ?>
                        </div>
                    </div>
                    -->
                <?php
                }
                ?>

                <!-- Product Variable -->
                <?php
                if ($tipo_prodotto == 'variable') {
                    //non stampo il prezzo
                    //iva
                    //$_iva = get_post_meta( $product->get_id(), 'ci_iva', true );
                    //$_iva = $_iva . '%';
                    //echo $_iva; 
                }
                ?>

                <!-- Product Variations -->
                <?php
                if ($tipo_prodotto == 'variation') {
                ?>
                    <div class="cella-doppia-sx">
                        <?php 
                            echo $_iva; 
                        ?>
                    </div>
                    <!--
                    <div class="cella-doppia-dx">
                        <div class="p-pieno">
                            <?php 
                                //$prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                //echo wc_price($prezzo);
                            ?>
                        </div>
                    </div>
                    -->
                <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    //carico le variations
    function caricaVariations($product, $sconto, $cfg_prod_hide, $cfg_prod_pdf, $debug, $conta, $sez_tag, $is_nutrizione, $iva) {
        //SORT: inizio
        //------------
        $variations = $product->get_available_variations();
        $var_id = '';
        $var_sku = '';
        $a_prodotti = [];
        $si_formato = '0';
        $si_modello = '0';
        
        //scorro le variazioni e preparo l'array
        foreach ( $variations as $variation ){
            //if ($variation->get_status( 'edit' ) == 'private') {
                // do something if NOT enabled
            
            //    continue;
            //}    
            
            //if ( ! $variation->variation_is_visible() ){
            //    continue; // Disabled or hidden      
            //}
                   
        
        
            //echo '<pre>'; print_r($variation); echo '</pre>';
        
            
            
            //dati
            $var_id = $variation['variation_id'];
            $var_sku = trim('' . $variation['sku']);
            $var_colore = '';
            $var_misura = '';
            $var_formato = '';
            $atts = $variation['attributes'];
            if (array_key_exists('attribute_pa_colore', $atts)) {			
                $slug = $atts['attribute_pa_colore'];
                //$var_colore = get_term_by( 'slug', $slug, 'pa_colore' )->name;
                if ( get_term_by( 'slug', $slug, 'pa_colore' )){
                    $var_colore = get_term_by( 'slug', $slug, 'pa_colore' )->name;
                }
                //$si_colore = 'si';
            }
            if (array_key_exists('attribute_pa_misura', $atts)) {
                $slug = $atts['attribute_pa_misura'];
                $var_misura = get_term_by( 'slug', $slug, 'pa_misura' )->name;
                $var_misura = trim('' . $var_misura);
                //echo '<br> misura prima: ' . $var_misura;
                if (strlen($var_misura) == 1)
                    $var_misura = '00' . $var_misura;
                if (strlen($var_misura) == 2)
                    $var_misura = '0' . $var_misura;
                //echo '<br> misura dopo: ' . $var_misura;
                //$si_misura = 'si';
            }
            if (array_key_exists('attribute_pa_formato', $atts)) {
                $si_formato = '1';
                $slug = $atts['attribute_pa_formato'];
                $var_formato = get_term_by( 'slug', $slug, 'pa_formato' )->name;
            }
            //modello
            if (array_key_exists('attribute_pa_modello', $atts)) {
                $slug = $atts['attribute_pa_modello'];
                $var_modello = get_term_by( 'slug', $slug, 'pa_modello' )->name;
                $var_colore = $var_modello;
                $si_modello = 'si';
            }
            //echo '<br>modello: ' . $si_modello . ' - ' . $var_colore;
            //campo sort
            $p_sort = $var_misura . '_' . $var_colore;
            if ($si_formato == '1'){
                $s = strtoupper($var_formato);
                $s = str_replace('L', '', $s);
                $s = str_replace('T', '', $s);
                $p_sort = $s;
            }
            //aggiungo record
            $a_prodotti[] =  array( 
                    'sort'          => $p_sort, 
                    'id'            => $var_id,
                    'sku'           => $var_sku, 
                    'colore'        => $var_colore,
                    'misura'        => $var_misura,
                    'formato'       => $var_formato,
            );
        }
        // riordino per il campo sort
        array_multisort( array_column($a_prodotti, "sort"), SORT_ASC, $a_prodotti );
        //echo '<pre>'; print_r($a_prodotti); echo '</pre>';
        //SORT: fine
        //------------
        //OLD
        //$variations_ids = $product->get_children();

        //riepilogo delle foto
        $s = $product->get_attribute( 'Colore' );
        $acolori = [];
        $s = trim($s);
        if (strlen($s)>0)
        $acolori = explode(",", $s);
        //modelli
        $s = $product->get_attribute( 'Modello' );
        $amodelli = [];
        $s = trim($s);
        if (strlen($s)>0)
            $amodelli = explode(",", $s);
        //foto
        $foto=[];
        $foto_colori=[];

        $n=0;
        $nSaltato=-1;
        $conta_variations = 0;
        //foreach( $variations_ids as $var_id) {
        //echo '<pre>'; print_r($foto_colori); echo '</pre>';
        foreach( $a_prodotti as $prod) {
            //echo '<br> sort: ' . $prod['sort'];
            //$n=$n+1;
            $var_id = $prod['id'];
            $variation = wc_get_product($var_id);
            //echo '<pre>'; print_r($variation); echo '</pre>';
            //rigaProdotto($variation, $sconto);

            //verifica Hide prodotto
            $sku = trim('' . $variation->get_sku());
            $nascondi = '0';
            if (in_array($sku, $cfg_prod_hide)) {
                $nascondi = '1';
            } 
            //echo 'nascondi: ' . $nascondi . ' <br>';
            if ($nascondi == '1'){
            } else {
                $n=$n+1;
                $conta_variations = $conta_variations + 1;
                //intestazione titoli per le variazioni
                if ( $n == 1 ) {
                    rigaProdottoVariazioneTitoli('', $is_nutrizione, $si_modello);
                }

                if ( $n > 1 )
                    rigaProdottoSepaVariation();
                
                    //riga SaltaPagina: PRE
                if (in_array($sku, $cfg_prod_pdf)) {
                    //echo '<br>--SALTAAAAAAAAA';
                    creaRigaSaltaPagina('variation', $sez_tag);
                    //rigaProdottoSepaVariation();
                    rigaProdottoTitoli('newpage');
                    rigaProdottoVariazioneTitoli('newpage', $is_nutrizione, $si_modello);
                }

                //riga prodotto
                $_var_nutrizione = '';
                if ($is_nutrizione == '1') {
                    $_var_nutrizione = 'var-nutrizione';
                }
                rigaProdotto($variation, $sconto,$debug, $conta, $_var_nutrizione, $iva, '0');
                //if ( $n < count($variations_ids))
                //rigaProdottoSepaVariation();

                //foto
                $colore =  $variation->get_attribute( 'Colore' );
                if ($si_modello == '0') {
                    if (! in_array($colore,$foto_colori)){
                        $foto_colori[] = $colore;
                        $foto[] = wp_get_attachment_url( $variation->get_image_id() );
                    }
                }

                //foto modello
                //echo '<br> si_modello :' . $si_modello ;
                if ($si_modello == 'si') {
                    //$foto_colori=[];
                    $colore =  $variation->get_attribute( 'Modello' );
                    if (! in_array($colore,$foto_colori)){
                        $foto_colori[] = $colore;
                        $foto[] = wp_get_attachment_url( $variation->get_image_id() );
                    }
                }

            }
            //riga SaltaPagina: post
            //if (in_array($sku, $cfg_prod_pdf)) {
            //    //echo '<br>--SALTAAAAAAAAA';
            //    creaRigaSaltaPagina();
            //    $nSaltato = $n;
            //}
        }

        //echo '<pre>'; print_r($foto_colori); echo '</pre>';
        //echo '<pre>'; print_r($foto); echo '</pre>';

        //ci devono essere almeno 8 variazioni
        $righe_minime = 8;
        if ( $conta_variations < $righe_minime ){
            $mancanti = $righe_minime - $conta_variations;
            for ($i = 1; $i <= $mancanti; $i++) {
                //aggiungo riga vuota
                ?>
                <div class="fantini-catalogo-articolo-sepavariation hide">
                </div>
                <div class="fantini-catalogo-riga-articolo  riga-variation-vuota <?php echo 'r'.($righe_minime - $mancanti + $i);?> ">
                </div>
                <?php
            }
        }

        //echo '<pre>'; print_r($acolori); echo '</pre>';
        if ( count($acolori)>1 || count($amodelli)>1) {
            //riepilogo immagini
            rigaProdottoRiepilogoFoto($foto, $foto_colori);
        } else {
            //se non ho saltato l'ultima variazione scrivo il divisore
            //if ($nSaltato <> $n) //per il POST
            //    rigaProdottoVariazioneSenzaRiepilogoFoto();
            rigaProdottoVariazioneSenzaRiepilogoFoto();
        }
    }


    //carico le variations
    function simple_carica_come_Variations($product, $_iva, $sconto, $debug) {
        $is_nutrizione = '0';
        $si_modello = '0';

        rigaProdottoVariazioneTitoli('', $is_nutrizione, $si_modello);

        //rigaProdotto($product, $sconto,$debug, $conta, $_var_nutrizione, $_iva, '1');
        rigaProdotto($product, $sconto,$debug, 1, $is_nutrizione, $_iva, '1');

        //ci devono essere almeno 8 variazioni
        $conta_variations = 1;
        $righe_minime = 8;
        if ( $conta_variations < $righe_minime ){
            $mancanti = $righe_minime - $conta_variations;
            for ($i = 1; $i <= $mancanti; $i++) {
                //aggiungo riga vuota
                ?>
                <div class="fantini-catalogo-articolo-sepavariation hide">
                </div>
                <div class="fantini-catalogo-riga-articolo  riga-variation-vuota <?php echo 'r'.($righe_minime - $mancanti + $i); ?> ">
                </div>
                <?php
            }
        }
        rigaProdottoVariazioneSenzaRiepilogoFoto();
    }

    //scrivo la riga riepilogo foto
    function rigaProdottoRiepilogoFoto($foto, $foto_colori) {
        ?>
        <!--
        <div class="fantini-catalogo-articolo-riepilogo-foto hide">
        -->
        <div class="fantini-catalogo-articolo-riepilogo-foto-sepa">
            <div class="barra-sepa">
            </div>
        </div>
        <div class="fantini-catalogo-articolo-riepilogo-foto">
            <?php
            //scorro le foto
            $n=0;
            foreach ($foto_colori as $colore){
                $foto_img = $foto[$n];
                //box della foto
                ?>
                <div class="fantini-foto-box">
                    <div class="fantini-foto-img-wrap">
                        <img src="<?php echo $foto_img; ?>" />
                    </div>
                    <div class="fantini-foto-label">
                        <?php echo '' . $colore; ?>
                    </div>
                </div>
                <?php
                //next
                $n=$n+1;
            }
            ?>
        </div>
        <?php
    }

    //scrivo la riga rigaProdottoSepa
    function rigaProdottoSepa() {
        ?>
        <div class="fantini-catalogo-articolo-sepa">
        </div>
        <?php
    }

    //scrivo la riga rigaProdottoSepaVariation
    function rigaProdottoSepaVariation() {
        ?>
        <div class="fantini-catalogo-articolo-sepavariation">
        </div>
        <?php
    }

    //crea Riga Sconto - Al momento non la visualizzo
    function creaRigaSconto($sconto) {
        ?>
        <div class="fantini-catalogo-sezione-sconto">
            <h3>
                Prezzi di Listino scontati del <span> <?php echo '' . $sconto; ?> %</span>
            </h3>
        </div>
        <?php
    }

    //crea Riga Catalogo
    function creaRigaCatalogo($categoria) {
        ?>
        <div class="fantini-catalogo-sezione-titolo-catalogo">
            <h3>
                Catalogo: <span> <?php echo '' . $categoria; ?></span>
            </h3>
        </div>
        <?php
    }

    //crea la barra menu
    function creaBarraMenu($categorie) {
        ?>
        <!-- BARRA -->
        <div class="fantini-catalogo-barra">
            <!-- sezione 1: bottoni principali -->
            <div class="fantini-barra-sezione riga1">
                <input type="button" data-tipo="mostra-prezzi" data-sezione="" value="Mostra Prezzi" class="barra-btn sel" />
                <input type="button" data-tipo="barra-riga2" data-sezione="" value="Filtra Categorie" class="barra-btn" />
            </div>

            <!-- sezione 2: bottoni categorie -->
            <div class="fantini-barra-sezione riga2" style="display: none;" >
                <?php
                    foreach($categorie as $categoria) {
                        $sez_tag = "sezione_id_" . strval($categoria['id']);
                        $cat_desc = trim( $categoria['desc'] );
                        $cat_desc = str_replace('-->', '>>', $cat_desc);
                        //$categoria['nome']
                ?>
                        <input type="button" data-tipo="sezione" data-sezione="<?php echo $sez_tag; ?>" value="<?php echo $cat_desc; ?>" class="barra-btn sel" />
                <?php
                    }
                ?>
            </div>
        </div>

        <!-- jQuery -->
        <script type="text/javascript">
            jQuery.noConflict();
			jQuery(document).ready(function($) {
                //se la barra esiste
                if ($(".fantini-catalogo-barra").length > 0) {
                    //console.log('---dentro');
                    jQuery(".fantini-catalogo-barra .barra-btn").click(function(e){
					    e.preventDefault();
                        var tipo = jQuery(this).data("tipo");
                        var sez_id = jQuery(this).data("sezione");
                        //operazione
                        var operazione="";
                        if (jQuery(this).hasClass( "sel" )) {
                            jQuery(this).removeClass("sel");
                            operazione="nascondi";
                        } else {
                            jQuery(this).addClass("sel");
                            operazione="visualizza";
                        }
                        //Mostra-Nascondi Prezzi
                        if (tipo == 'mostra-prezzi') {
                            var cerca = ".p-pieno";
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();
                            } else {
                                jQuery(cerca).hide();
                            }
                        }
                        //Mostra-Nascondi Barra Riga2
                        if (tipo == 'barra-riga2') {
                            var cerca = ".fantini-barra-sezione.riga2";
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();
                            } else {
                                jQuery(cerca).hide();
                            }
                        }
                        //Articoli della Categoria
                        if (tipo == 'sezione') {
                            //var cerca = "." + sez_id + " > .fantini-catalogo-sezione-articoli";
                            var cerca = ".fantini-catalogo-sezione." + sez_id + "";
                            //console.log('cerca', cerca);
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();

                                cerca = '.fantini-catalogo-pagina:has(>' + cerca + ')';
                                jQuery(cerca).removeClass("nascondi");
                            } else {
                                jQuery(cerca).hide();

                                cerca = '.fantini-catalogo-pagina:has(>' + cerca + ')';
                                jQuery(cerca).addClass("nascondi");
                            }
                        }

                        //immagini
                        /*
                        if (tipo == 'immagini') {
                            var cerca = ".fantini-catalogo-riga-articolo .wrap-img.img-variable";
                            var cerca_riep = ".fantini-catalogo-articolo-riepilogo-foto";
                            //console.log('cerca', cerca);
                            if (operazione == 'nascondi'){
                                jQuery(cerca).show();
                                //jQuery(cerca_riep).hide();
                                jQuery(cerca_riep).addClass("hide");
                            } else {
                                jQuery(cerca).hide();
                                //jQuery(cerca_riep).show();
                                jQuery(cerca_riep).removeClass("hide");
                            }
                        }
                        */
                        return false;
				    });
                }
            });
        </script>
        <?php
    }

    //RIGA SALTA PAGINA
    function creaRigaSaltaPagina($chiamante, $sez_tag) {
        //echo "......SALTA PAGINA  <br>";

        
        if ($chiamante == 'articolo' || $chiamante == 'variation' ){
            //chiudo Articoli e Sezione
        ?>
                    </div> <!-- articoli -->
                </div> <!-- sezione -->
        <?php
        }
        ?>

        <!-- Footer -->
        <?php paginaFooter(); ?>
            <!-- chiudo la pagina -->
        </div> <!-- pagina -->

        <!-- salta pagina -->
        <div class="fantini-catalogo-salta-pagina">
        </div> 

        <!-- e la riapro... -->
        <div class="fantini-catalogo-pagina">

        <?php
        if ($chiamante == 'articolo' || $chiamante == 'variation' ){
            //$sez_tag = 'ciccio';
        ?>
            <div class="fantini-catalogo-sezione <?php echo $sez_tag; ?> newpage">
                <div class="fantini-catalogo-sezione-articoli">

        <?php
        }
    }

    //PAGINA - FOOTER
    function paginaFooter() {
        global $footer_catalogo;
        global $footer_sezione;
        global $footer_pagina;
        $copertina = '';

        if ($footer_pagina == 0)
            $copertina = 'copertina';
        $logo = 'https://www.shopfantini.com/shop/wp-content/uploads/2021/07/logo_generico_trasp.png';

        ?>
        <div class="pagina-footer print <?php echo $copertina; ?>">
            <span class="uno">
                <!--
                <img src ="<?php //echo $logo; ?>" />
                -->
            </span>
            <span class="due">
                <?php //echo $footer_catalogo; ?>
            </span>
            <span class="tre">
                <img src ="<?php echo $logo; ?>" />
                <?php 
                    echo $footer_catalogo . ' - ' . $footer_sezione . ' &nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp; Pag. ' . strval($footer_pagina); 
                ?>
            </span>
            <span class="quattro">
                <!--
                Pag. &nbsp;<?php //echo strval($footer_pagina); ?>
                -->
            </span>

        </div>
        <?php
        $footer_pagina = $footer_pagina + 1;
    }

    //UTENTE NON ABILITATO
    function utenteNonAbilitato() {
        //https://www.fantini.srl/shop/my-account/
        $page_object = get_page_by_title( 'My account' );
        $page_id = $page_object->ID;
        $permalink = get_permalink( $page_id );
        //echo "link: " . $permalink;
        //echo "page_id: " . $page_id;
        ?>
        <div class="fantini-cataloghi-utente">
            <h2>Area riservata</h2>
            <h3>Accesso riservato agli utenti registrati</h3>
            <div class="login-btn">
                <a href="<?php echo $permalink; ?>" class="btn" role="button">
                    Accedi
                </a>
            </div>
        </div>
        <?php
    }

    //RENDER PAGE
    function renderPage($atts) {
        global $footer_catalogo;
        global $footer_sezione;
        global $footer_pagina;

        $footer_pagina = 0;

        //CONTROLLO ACCESSO
        // $current_user_role = get_current_user_first_role();
        // //echo 'current_user_role: ' . $current_user_role  . '<br>';
        // if ($current_user_role == 'guest'){
        //     utenteNonAbilitato();
        //     return;
        // }

        // //Sconto
		// $sconto = 0;
        // $ruolo_sconto = $current_user_role;
        if (is_user_logged_in()){
             $user = wp_get_current_user();
             $roles = (array) $user->roles;
        //     //li testo
        //     if ( in_array( 'clientesconto50', $roles )) $ruolo_sconto = 'clientesconto50';
        //     if ( in_array( 'clientesconto40', $roles )) $ruolo_sconto = 'clientesconto40';
        //     if ( in_array( 'clientesconto0', $roles )) $ruolo_sconto = 'clientesconto0';
        //     if ( in_array( 'clientisconto50', $roles )) $ruolo_sconto = 'clientisconto50';
        //     if ( in_array( 'clientisconto40', $roles )) $ruolo_sconto = 'clientisconto40';
        //     if ( in_array( 'clientisconto0', $roles )) $ruolo_sconto = 'clientisconto0';
        //     //echo '<pre>'; print_r($roles); echo '</pre>';
        } else {
            utenteNonAbilitato();
            return;
        }
        //echo 'ruolo_sconto SCONTO: ' . $ruolo_sconto  . '<br>';

        //controllo accesso
        // if ($current_user_role == 'guest'){
        //     utenteNonAbilitato();
        //     return;
        // }
        if (!in_array( 'administrator', $roles )){
            utenteNonAbilitato();
            return;
        }

		// //$koef = get_option( 'alg_wc_price_by_user_role_' . $current_user_role, 1 );
        // $koef = get_option( 'alg_wc_price_by_user_role_' . $ruolo_sconto, 1 );
        // $sconto = $koef * 100;
        // if ($sconto == 100 ) $sconto = 0;
        // //echo 'Sconto: ' .  sconto  . '<br>';


		$sconto = 0;
		$customer_group_id = 0;
		//$discount = 0;
		if ( $customer_group_id = wcb2b_get_customer_group() ) {
			$_discount = get_post_meta( $customer_group_id, 'wcb2b_group_discount', true );
			if (!is_null($_discount)){
				//$discount = $_discount;
				$sconto = $_discount;
			}
		}




        //CATEGORIA CORRENTE
        $cat_nome = trim('' . $atts['categoria']);
        $category = get_term_by( 'name', $cat_nome, 'product_cat' );
        $footer_catalogo = "Catalogo " . $cat_nome;
        //echo '<pre>'; print_r($category); echo '</pre>';
        $cat_id = $category->term_id;
        $cat_slug = $category->slug;

        //CARICO I SETTING
        //echo "load setting: " . $cat_slug . "<br>";
        $cfg_config = loadSetting($cat_slug);
        $cfg_prod_hide = explode('|',$cfg_config['hide']['products']);
        $cfg_section_hide = explode('|',$cfg_config['hide']['sections']);
        $cfg_prod_pdf = explode('|',$cfg_config['newpage']['products']);
        $cfg_section_pdf = explode('|',$cfg_config['newpage']['sections']);

        //echo '<pre>'; print_r($cfg_prod_hide); echo '</pre>';
        //echo '<pre>'; print_r($cfg_section_hide); echo '</pre>';
        //echo '<pre>'; print_r($cfg_prod_pdf); echo '</pre>';
        //echo '<pre>'; print_r($cfg_section_pdf); echo '</pre>';
        
        //nome file cfg
        $cfg_file = cfg_get_file_name($cat_slug);
        
        //echo 'main nome file cfg: ' . $cfg_file . '<br>';
        //echo 'padre: ' . $cat_nome  . '<br>';
        //echo 'id padre: ' . strval($cat_id) . '<br>';
        //echo 'slug: ' . $cat_slug . '<br>';

        //recupero le sottocategorie
        $categorie=[];
        $liv = 0;
        $categorie=listaCategorie($cat_id, $liv);
        //echo 'CATEORIE: <br>';
        //echo '<pre>'; print_r($categorie); echo '</pre>';

        //Modalità Debug
        $debug='off';
        if (isset($_GET['debug'])) {
            $debug='on';
        }

        //BARRA MENU
        creaBarraMenu($categorie);
        ?>  
        <div class="fantini-catalogo-container <?php echo strtolower($cat_nome); ?>">
            <div class="fantini-catalogo-pagina zero">

            <?php
                //Riepilogo sconto
                //creaRigaSconto($sconto);
                //RIGA CATALOGO
                //creaRigaCatalogo($cat_nome);
                // se non ci sono sottocategorie creo una sezione con la root
                if (count($categorie) == 0) {
                    $categorie[] = array(
                        'slug' =>  $cat_slug,
                        'nome' =>  $cat_nome,
                        'id' =>  $cat_id,
                    );
                }
                //da rivedere!!!! posso avere prodotti anche se ho sottocategorie

                //scorro le categorie
                $cat_parent_nome= '';
                $a_cat_parent_nome = [];
                for ($i = 1; $i <= 10; $i++) {
                    $a_cat_parent_nome[] = '';
                }
                $conta_categorie = 0;
                foreach ($categorie as $categoria) {
                    //per debug
                    $conta_categorie = $conta_categorie + 1;
                    //if ( $conta_categorie > 2)
                    //    continue; //STOP

                    $prodotti=[];

                    if ($categoria['livello'] == 0)
                        $cat_parent_nome = $categoria['nome'];

                    //array con in nomi categorie corrente
                    $a_cat_parent_nome[$categoria['livello']] = $categoria['nome'];

                    //sottocategorie
                    $children = get_categories( array ('taxonomy' => 'product_cat', 'parent' => $categoria['id'] ));
                    $aSotto = [];
                    foreach ($children as $child) {
                        $aSotto[] = $child->term_id;
                    }
                    //echo '<pre>'; print_r($children); echo '</pre>';
                    //echo '<pre>'; print_r($aSotto); echo '</pre>';
                    /*
                    $prodotti = wc_get_products(array(
                        'category' => array($categoria['slug']),
                        'limit' => -1,
                        'status' => 'publish')
                    );
                    */
                    $query = new WC_Product_Query(
                        array(
                            'limit' => -1,
                            'category' => array($categoria['slug']),
                            //'orderby' => 'meta_value',
                            //'meta_key' => '_sku',
                            'orderby' => 'menu_order',
                            'order' => 'ASC',
                            //Mostro solo i prodotto che sono VISIBILI
                            'tax_query' => array(
                                array(
                                    'taxonomy' => 'product_visibility',
                                    'terms' => array( 'exclude-from-catalog', 'exclude-from-search' ),
                                    'field' => 'name',
                                    'operator' => 'NOT IN',
                                )
                            )
                        )
                    );

                    //nome sezione per il footer
                    //$footer_sezione = $categoria['nome'];

                    $prodotti = $query->get_products();

                    //echo 'prodotti: ' . strval(count($prodotti)) . '<br>';
                    $sez_tag = "sezione_id_" . strval($categoria['id']);

                    //SEZIONE - SALTO LE SEZIONI NASCOSTE
                    if (in_array($categoria['id'], $cfg_section_hide)) {
                        continue;
                    }

                    //SEZIONE - SALTO PAGINA
                    if (in_array($categoria['id'], $cfg_section_pdf)) {
                        //se presente nelle sottocategorie lo salto
                        creaRigaSaltaPagina('sezione', '');
                    }

                    //nome sezione per il footer
                    $footer_sezione = $categoria['nome'];

                    //check se la sezione ha delle sottocategorie
                    $padre = '';
                    $args = array(
                        'taxonomy' => 'product_cat',
                        'orderby' => 'name',
                        'parent' => $categoria['id'],
                        'show_count' => 0,
                        'pad_counts' => 0,
                        'hierarchical' => 1,
                        'title_li' => '',
                        'hide_empty' => true
                    );
                    $figli = get_categories($args);
                    //$figli = get_categories($categoria['id']);
                    if (!empty($figli)) {
                        $padre = 'genitore';
                    }
                    //echo '<br> ct id: ' . $categoria['id'] . ' - ' . $padre;

                    ?>  
                    <div class="fantini-catalogo-sezione <?php echo $sez_tag; ?> <?php echo $padre; ?>">
                        <div class="fantini-catalogo-sezione-titolo">
                            <?php
                                $tot_articoli = count($prodotti);
                                $titolo_categoria = $categoria['nome'];
                                //eventuale nome della sottocategoria
                                if ($categoria['livello'] == 1)
                                    $titolo_categoria = $cat_parent_nome . ' >> ' . $titolo_categoria;
                                //debug
                                if ($debug == 'on')
                                    $titolo_categoria = $titolo_categoria . ' - ' . $tot_articoli;

                                    //nome della sottocategotia
                                $titolo_categoria = '';
                                for ($i = 0; $i <= $categoria['livello']; $i++) {
                                    if ($i > 0) {
                                        $titolo_categoria = $titolo_categoria . ' >> ';
                                    }
                                    $titolo_categoria = $titolo_categoria . $a_cat_parent_nome[$i];
                                }

                                $footer_sezione = $titolo_categoria;
                                //$titolo_categoria = $titolo_categoria . ' - ' . $categoria['livello'];
                                //$titolo_categoria = $titolo_categoria . ' - ' . $tot_articoli;
                            ?>
                            <h2><?php echo $titolo_categoria; ?></h2>
                        </div> 

                        <div class="fantini-catalogo-sezione-articoli">
                            <?php
                            //scorro gli articoli
                            $conta = 0;
                            foreach ($prodotti as $prodotto){
                                //verifico se scartare il prodotto perche appartiene a una sottocategoria
                                //echo 'prodotto: ' . $prodotto->get_sku() . '<br>';
                                if (count($aSotto) > 0){
                                    $salta = '0';
                                    //verifico se il prodotto appartiene a una sottocategoria
                                    $prod_categories = wp_get_post_terms( $prodotto->get_id(), 'product_cat' );
                                    //echo '<pre>'; print_r($prod_categories); echo '</pre>';
                                    foreach (  $prod_categories as $prod_cat){
                                        if (in_array($prod_cat->term_id, $aSotto)) {
                                            //se presente nelle sottocategorie lo salto
                                            $salta = '1';
                                            break;
                                        }
                                    }
                                    if ($salta == '1'){
                                        continue;
                                    }
                                    //continue;
                                }
                                $conta = $conta + 1;
                                if ($conta == 1) {
                                    rigaProdottoTitoli('');
                                }
                                //RIGA PRODOTTO
                                //verifica Hide prodotto
                                $sku = trim('' . $prodotto->get_sku());
                                $nascondi = '0';
                                if (in_array($sku, $cfg_prod_hide)) {
                                    $nascondi = '1';
                                } 
                                //echo 'nascondi: ' . $nascondi . ' <br>';
                                if ($nascondi == '1'){
                                    //Nascondo
                                } else {
                                    //riga SaltaPagina: PRE
                                    if (in_array($sku, $cfg_prod_pdf)) {
                                        creaRigaSaltaPagina('articolo', $sez_tag);
                                        rigaProdottoTitoli('newpage');
                                    }

                                    //verifico se è nutrizione
                                    $_is_nutrizione = '0';
                                    if ( has_term( 'Nutrizione', 'product_cat', $prodotto->get_id() ) )
                                        $_is_nutrizione = '1';

                                    //IVA
                                    $_iva = get_post_meta( $prodotto->get_id(), 'ci_iva', true );
                                    $_iva = $_iva . '%';

                                    //check se prodotto a bancali
                                    $bancali = $prodotto->get_attribute( 'bancali' );
                                    if ($bancali) {
                                        //prodotto a BANCALI
                                        rigaProdottoBancali($prodotto, $sconto, $debug, $conta);
                                        rigaProdottoSepa();
                                    } else {
                                        //prodotto NON A BANCALE
                                        rigaProdotto($prodotto, $sconto, $debug, $conta, $_is_nutrizione, $_iva, '2');
                                        //Mostro riga prodotto
                                        if ($prodotto->get_type() == 'simple') {
                                            //specchietto
                                            simple_carica_come_Variations($prodotto, $_iva, $sconto, $debug);
                                            //separazione
                                            rigaProdottoSepa();
                                        }
                                        //se prodotto variabile, carico le variations
                                        if ($prodotto->get_type() == 'variable') {
                                            //if ($prodotto->get_sku() == 'NI56')  //FILIPPO
                                            caricaVariations($prodotto, $sconto, $cfg_prod_hide, $cfg_prod_pdf, $debug, $conta, $sez_tag, $_is_nutrizione, $_iva);
                                            rigaProdottoSepa();
                                        }
                                        //riga SaltaPagina: POST
                                        //if (in_array($sku, $cfg_prod_pdf)) {
                                        //    creaRigaSaltaPagina();
                                        //}
                                    }
                                }
                            }
                            ?>
                        </div> 
                    </div>  <!-- <div class="fantini-catalogo-sezione"> -->
                    <?php
                }
                ?> 
                <!-- footer -->
                <?php paginaFooter(); ?>
            </div> <!-- <div class="fantini-catalogo-pagina"> -->

            <?php
                //LE PAGINE DEVONO ESSERE UN MULTIPLO DI 4
                $tot_pagine = $footer_pagina - 1 + 2;
                $_resto = $tot_pagine % 4;
                $_mancanti = 0;
                if ( $_resto > 0)
                    $_mancanti = 4 - $_resto;
                //echo '<br>';
                //echo 'footer_pagina: ' . $footer_pagina;
                //echo '<br>';
                //echo 'NR PAGINE: ' . $tot_pagine;
                //echo '<br>';
                //echo 'mancanti: ' . $_mancanti;
                if ( $_mancanti >= 1){
                    ?>
                    <!-- salta pagina -->
                    <!-- 2023.01.11
                    <div class="fantini-catalogo-salta-pagina">
                    </div>
                    --> 
                    <?php
                }
                //pagine mancanti
                for ($i = 1; $i <= $_mancanti; $i++) {
                    ?>
                    <!-- salta pagina 2023.01.11 -->
                    <div class="fantini-catalogo-salta-pagina">
                    </div> 

                    <!-- e la riapro... -->
                    <div class="fantini-catalogo-pagina">
                        <div class="fantini-catalogo-sezione <?php echo $sez_tag; ?> newpage">
                            <div class="fantini-catalogo-sezione-articoli">
                            </div> <!-- articoli -->
                        </div> <!-- sezione -->
                        <!-- footer -->
                        <?php paginaFooter(); ?>
                    </div> <!-- <div class="fantini-catalogo-pagina"> -->
                    <!-- salta pagina -->
                    <!-- 2023.01.11
                    <div class="fantini-catalogo-salta-pagina">
                    </div> 
                    -->
                    <?php
                }
            ?>
        </div> <!-- <div class="fantini-catalogo-container"> -->
        <?php
    return;
}
?> 

