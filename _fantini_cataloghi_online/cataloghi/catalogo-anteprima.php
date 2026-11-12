<?php
    //variabili globali
    $cfg_file = '';
    $cfg_config = [];
    $cfg_prod_hide = [];
    $cfg_prod_pdf = [];
    $cfg_section_pdf = [];
    $footer_catalogo = 'catalogo';
    $footer_sezione = 'sezione';
    $footer_logo = 'logo';
    $footer_pagina = 0;
    $curr_sezione=0;

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

    // function get_current_user_first_role() {
	// 	$current_user = wp_get_current_user();
	// 	return ( isset( $current_user->roles[0] ) && '' !== $current_user->roles[0] ) ? $current_user->roles[0] : 'guest';
	// }

    //scrivo la riga Titoli prodotto
    function rigaProdottoTitoli($tipo) {
        ?>
        <div class="fantini-catalogo-riga-articolo riga-clear titolo <?php echo $tipo; ?>">
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
            <!-- note -->
            <div class="fantini-catalogo-articolo-col titolo fant-col-20">
                Note
            </div>            
            <!-- prezzo -->
            <div class="fantini-catalogo-articolo-col titolo dx-titolo fant-col-15">
                <!-- Prezzo -->
                <div class="cella-doppia-sx">
                    Iva
                </div>
                <div class="cella-doppia-dx">
                    Prezzo
                </div>
            </div>
        </div>
        <?php
    }

    //scrivo la riga Titoli Per le Variazioni
    function rigaProdottoVariazioneTitoli($tipo, $is_nutrizione, $is_modello) {
        ?>
        <div class="fantini-catalogo-riga-articolo-variazioni riga-clear titolo <?php echo $tipo; ?>">
            <!-- img -->
            <div class="fantini-catalogo-articolo-col titolo variazione hide fant-col-16">
            </div>
            <!-- codice -->
            <div class="fantini-catalogo-articolo-col titolo variazione fant-col-20">
                Codice
            </div>
            <!-- codice a Barre -->
            <!--
            <div class="fantini-catalogo-articolo-col titolo variazione fant-col-20">
                Cod. a barre
            </div>
            -->
            <?php
            $_misura = 'Misura';
            $_colore = 'Colore';
            if ($is_modello == 'si'){
                $_colore = 'Modello';
            }
            if ( $is_nutrizione == '1' ){
                $_misura = 'Formato';
                $_colore = '';
            }
            ?>
            <!-- nome -->
            <div class="fantini-catalogo-articolo-col titolo variazione fant-col-15">
                <?php echo $_misura; ?>
            </div>
            <!-- note -->
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
            <div class="fantini-catalogo-articolo-col titolo variazione dx-titolo fant-col-15">
                <!-- Prezzo -->
                <div class="cella-doppia-sx">
                    Iva
                </div>
                <div class="cella-doppia-dx">
                    Prezzo
                </div>
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
        <div class="fantini-catalogo-articolo-riepilogo-senza-foto riga-clear">
        </div>
        <?php
    }

    //scrivo la riga prodotto BANCALI
    function rigaProdottoBancali($product, $sconto, $debug, $conta) {
        global $cfg_prod_pdf;
        $cls_col_foto = 'fant-col-20';
        $cls_col_dati = 'fant-col-80';
        $cls_col_cod  = 'fant-col-15';
        $cls_col_desc = 'fant-col-65';
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
        <div class="fantini-catalogo-riga-articolo bancali riga-clear <?php echo $cls_variable; ?> "
            data-sku="<?php echo trim('' . $product->get_sku()); ?>"
            data-tipo="<?php echo trim('' . $product->get_type()); ?>"
        >
            <!-- img -->
            <div class="fantini-catalogo-articolo-col bancali-foto <?php echo $cls_col_foto; ?>"  >
                <div class="wrap-img <?php echo $cls_img_variable; ?>  "> 
                    <img src="<?php echo wp_get_attachment_url( $product->get_image_id() ); ?>" />
                </div>
            </div>

            <!-- Pdf Salto Pagina BANCALI -->
            <div class="fantini-catalogo-articolo-col fant-col-20 view_pdf" >
                <?php
                    $sku = trim('' . $product->get_sku());
                    $selezionato = '';
                    if (in_array($sku, $cfg_prod_pdf)) {
                        $selezionato = 'checked';
                    } else {
                        $selezionato = '';
                    }
                    //echo "selezionato: $selezionato<br>";
                ?>
                <label class="grid-lbl-checkbox articolo">
                    <input type="checkbox" 
                        id="chk_<?php echo $product->get_sku(); ?>" 
                        name="chk_<?php echo $product->get_sku(); ?>"  
                        class="chk_pdf articolo"
                        data-sku="<?php echo trim('' . $product->get_sku()); ?>"
                        data-tipo="<?php echo trim('' . $product->get_type()); ?>"
                        <?php echo $selezionato; ?>
                    />
                    <!-- Nuova -->
                    <div class="chk_conta">
                    </div>
                </label>
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
        global $cfg_prod_pdf;

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
            $cls_col_prezzo = 'fant-col-15 campo-iva-simple';

            //2022.08.02
            $cls_img_variable = 'img-variable';

            //$simplev2
            if ($simplev2 == '1') {
                //come il variation
                $tipo_prodotto = 'variation';
                $cls_img_variable = 'img-variable';
                $cls_nascondi = 'nascondi';
                $cls_col_foto = 'fant-col-16';
                $cls_col_barcode = 'fant-col-20';
                $cls_col_cod  = 'fant-col-20';
                $cls_col_desc = 'fant-col-15';
                $cls_col_note = 'fant-col-15';
                $cls_col_prezzo = 'fant-col-15 campo-iva-simple';
            }

            if ($simplev2 == '2') {
                //come il variable
                $tipo_prodotto = 'variable';
                $cls_img_variable = 'img-variable';
                $cls_variable = 'variable';
                $cls_col_desc = 'fant-col-65';
                $cls_col_note = 'fant-col-0';
                $cls_col_prezzo = 'fant-col-0 campo-iva';
            }

        }
        
        if ($product->get_type() == 'variable') {
            $cls_img_variable = 'img-variable';
            $cls_variable = 'variable';
            $cls_col_desc = 'fant-col-65';
            $cls_col_note = 'fant-col-0';
            $cls_col_prezzo = 'fant-col-0 campo-iva';
        }

        if ($product->get_type() == 'variation') {
            $cls_img_variable = 'img-variable';
            $cls_nascondi = 'nascondi';
            $cls_col_foto = 'fant-col-16';
            $cls_col_barcode = 'fant-col-20';
            $cls_col_cod  = 'fant-col-20';
            $cls_col_desc = 'fant-col-15';
            $cls_col_note = 'fant-col-15';
            $cls_col_prezzo = 'fant-col-15 campo-iva-simple';
        }
        if ($is_nutrizione == '1')
            $cls_variable = $cls_variable . ' is-nutrizione';

        //sku
        $_sku = $product->get_sku();
        if ($simplev2 == '1'){
            $_sku = '-';
        }
        
        ?>
        <div class="fantini-catalogo-riga-articolo riga-clear <?php echo $cls_variable; ?> " 
                        data-sku="<?php echo trim('' . $_sku); ?>"
                        data-tipo="<?php echo trim('' . $tipo_prodotto); ?>" >
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

            <!-- Pdf Salto Pagina -->
            <div class="fantini-catalogo-articolo-col fant-col-20 view_pdf" >
                <?php
                    $sku = trim('' . $product->get_sku());
                    $selezionato = '';
                    if (in_array($sku, $cfg_prod_pdf)) {
                        $selezionato = 'checked';
                    } else {
                        $selezionato = '';
                    }
                    //echo "$selezionato<br>";
                ?>
                <label class="grid-lbl-checkbox articolo">
                    <?php
                    if ($simplev2 != '1') {
                    ?>
                    <input type="checkbox" 
                        id="chk_<?php echo $product->get_sku(); ?>" 
                        name="chk_<?php echo $product->get_sku(); ?>"  
                        class="chk_pdf articolo"
                        data-sku="<?php echo trim('' . $product->get_sku()); ?>"
                        data-tipo="<?php echo trim('' . $product->get_type()); ?>"
                        <?php echo $selezionato; ?>
                    />
                    <?php
                    }
                    ?>
                    <!--Nuova-->
                    <div class="chk_conta">
                    </div>
                </label>
            </div>

            <!-- codice -->
            <div class="fantini-catalogo-articolo-col <?php echo $cls_col_cod; ?>">
                <?php echo $product->get_sku(); ?>
                <?php //echo $product->get_type(); ?>
            </div>

            <!-- codice a Barre -->
            <?php
            //if ($product->get_type() == 'variation') {
            ?>
                <!--
                <div class="fantini-catalogo-articolo-col <?php echo $cls_col_barcode; ?>">
                    <?php 
                        //$barcode = get_post_meta( $product->get_id(), 'ci_barcode', true );
                        //$barcode = '-----';
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
                    if ($tipo_prodotto == 'variation') {
                        $s = $product->get_attribute( 'Misura' );
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
                        //echo '<pre>'; print_r($barcode); echo '</pre>';
                        //if ($barcode != null) {
                        //    if ($barcode != 'Array'){
                        //        $s ='<div class="barcode-info"><span>Cod. a barre:</span> ' . $barcode . '</div>';
                        //        echo $s;
                        //    }
                        //}
                    }
                ?>
            </div>
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

                    }

                    //non stampo le note
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
            <!-- prezzo -->
            <div class="fantini-catalogo-articolo-col dx prezzo <?php echo $cls_col_prezzo; ?>">
                <!--<?php //echo 'sconto: ' . $sconto; ?> -->
                
                <!-- Product Simple -->
                <?php
                if ($tipo_prodotto == 'simple') {
                ?>
                    <!-- prezzo per SIMPLE -->
                    <div class="cella-doppia-sx">
                        <?php 
                            echo $_iva; 
                        ?>
                    </div>
                    <div class="cella-doppia-dx">
                        <div class="p-pieno">
                            <?php 
                                $prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                echo wc_price($prezzo);
                            ?>
                        </div>
                    </div>
                <?php
                }
                ?>

                <!-- Product Variable -->
                <?php
                if ($tipo_prodotto == 'variable') {
                    //non stampo il prezzo
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
                    <div class="cella-doppia-dx">
                        <div class="p-pieno">
                            <?php 
                                $prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                echo wc_price($prezzo);
                            ?>
                        </div>
                    </div>
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
        $si_modello = '0';
        //scorro le variazioni e preparo l'array
        foreach ( $variations as $variation ){
            //dati
            $var_id = $variation['variation_id'];
            $var_sku = trim('' . $variation['sku']);
            $var_colore = '';
            $var_misura = '';
            $atts = $variation['attributes'];
            if (array_key_exists('attribute_pa_colore', $atts)) {			
                $slug = $atts['attribute_pa_colore'];
                $var_colore = get_term_by( 'slug', $slug, 'pa_colore' )->name;
                $si_colore = 'si';
            }
            if (array_key_exists('attribute_pa_misura', $atts)) {			
                $slug = $atts['attribute_pa_misura'];
                $var_misura = get_term_by( 'slug', $slug, 'pa_misura' )->name;
                $si_misura = 'si';
                //sistemo ordinamento, 7 in 07
                $var_misura = trim('' . $var_misura);
                //echo '<br> misura prima: ' . $var_misura;
                if (strlen($var_misura) == 1)
                    $var_misura = '00' . $var_misura;
                if (strlen($var_misura) == 2)
                    $var_misura = '0' . $var_misura;
                //echo '<br> misura dopo: ' . $var_misura;
            }
            //modello
            if (array_key_exists('attribute_pa_modello', $atts)) {
                $slug = $atts['attribute_pa_modello'];
                $var_modello = get_term_by( 'slug', $slug, 'pa_modello' )->name;
                $var_colore = $var_modello;
                $si_modello = 'si';
            }

            //campo sort
            $p_sort = $var_misura . '_' . $var_colore;
            //aggiungo record
            $a_prodotti[] =  array( 
                    'sort'          => $p_sort, 
                    'id'            => $var_id,
                    'sku'           => $var_sku, 
                    'colore'        => $var_colore,
                    'misura'        => $var_misura,
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
        foreach( $a_prodotti as $prod) { 
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

                //riga prodotto
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

        //ci devono essere almeno 8 variazioni
        $righe_minime = 8;
        if ( $conta_variations < $righe_minime ){
            $mancanti = $righe_minime - $conta_variations;
            for ($i = 1; $i <= $mancanti; $i++) {
                //aggiungo riga vuota
                ?>
                <div class="fantini-catalogo-articolo-sepavariation riga-clear hide">
                </div>
                <div class="fantini-catalogo-riga-articolo riga-clear riga-variation-vuota <?php echo 'r'.($righe_minime - $mancanti + $i); ?> ">
                </div>
                <?php
            }
        }

        //echo '<pre>'; print_r($acolori); echo '</pre>';
        if (count($acolori)>1 || count($amodelli)>1){
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
    //function simple_carica_come_Variations($product, $_iva) {
    function simple_carica_come_Variations($product, $_iva, $sconto, $debug, $conta, $_var_nutrizione) {
        $is_nutrizione = '0';
        $si_modello = '0';

        rigaProdottoVariazioneTitoli('', $is_nutrizione, $si_modello);

        rigaProdotto($product, $sconto, $debug, $conta, $_var_nutrizione, $_iva, '1');

        //ci devono essere almeno 8 variazioni
        $conta_variations = 1;
        $righe_minime = 8;
        if ( $conta_variations < $righe_minime ){
            $mancanti = $righe_minime - $conta_variations;
            for ($i = 1; $i <= $mancanti; $i++) {
                //aggiungo riga vuota
                ?>
                <div class="fantini-catalogo-articolo-sepavariation riga-clear hide">
                </div>
                <div class="fantini-catalogo-riga-articolo riga-clear riga-variation-vuota <?php echo 'r'.($righe_minime - $mancanti + $i); ?> ">
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
        <div class="fantini-catalogo-articolo-riepilogo-foto-sepa riga-clear">
            <div class="barra-sepa">
            </div>
        </div>
        <div class="fantini-catalogo-articolo-riepilogo-foto riga-clear">
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
        <div class="fantini-catalogo-articolo-sepa riga-clear">
        </div>
        <?php
    }

    //scrivo la riga rigaProdottoSepaVariation
    function rigaProdottoSepaVariation() {
        ?>
        <div class="fantini-catalogo-articolo-sepavariation riga-clear">
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
    function creaBarraMenu($categorie, $cfg_section_pdf, $cfg_section_pdf_names) {
        global $curr_sezione;

        ?>
        <!-- BARRA -->
        <div class="fantini-catalogo-barra anteprima">
            <!-- sezione 1: bottoni principali -->
            <div class="fantini-barra-sezione riga0">
                ANTEPRIMA SALTI PAGINA
            </div>
            <div class="fantini-barra-sezione riga1">
                <!-- select Salti Pagina Sezioni -->
                <form action="" method="post" onSubmit="if(!confirm('Vuoi procedere?')){return false;}">
                    <input type="hidden" name="action" value="admin-sezioni-load">

                    <div class="riga-campi">
                        <label for="salta-sezione-sel">Catalogo</label>
                        <select name="salta-sezione" id="salta-sezione-sel">
                            <option value="-1">--Seleziona una Sezione--</option>
                                <?php
                                $n=0;
                                foreach( $cfg_section_pdf as $cat ) {
                                    $nome = $cfg_section_pdf_names[$n];

                                    $term = get_term_by('id', $cat, 'product_cat');
                                    echo '<pre>'; print_r($term); echo '</pre>';

                                    $parent_id = $term->parent;
                                    $term_parent = get_term_by('id', $parent_id, 'product_cat');
                                    $parent_name = $term_parent->name;

                                    ?>
                                    <option value="<?php echo $cat; ?> "  <?php if($curr_sezione == $cat) echo "selected='selected'"; ?> >
                                        <?php //echo $nome . '-' . $cat . '(' . $parent_name .')'; ?>
                                        <?php echo $nome . '&nbsp;&nbsp;(' . $parent_name .')'; ?>
                                    </option>
                                    <?php
                                    $n = $n + 1;
                                }
                                ?>
                        </select>
                    </div>
                    <input type="submit" name="submit" value="Carica" class="ok-btn sel" />
                </form>
                <!--
                <input type="button" data-tipo="mostra-prezzi" data-sezione="" value="Mostra Prezzi" class="barra-btn sel" />
                <input type="button" data-tipo="mostra-prezzi" data-sezione="" value="Mostra Prezzi" class="barra-btn sel" />
                <input type="button" data-tipo="barra-riga2" data-sezione="" value="Filtra Categorie" class="barra-btn" />
                -->
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



            <!-- sezione 2: bottoni categorie -->
            <div class="fantini-barra-sezione riga4"  >

                <div class="fantini-barra-col ">
                    <input type="button" data-tipo="salva" data-sezione="" value="Salva" class="salti-btn sel" />
                </div>

                <div class="fantini-attendi" style="display: none;" >Salvataggio in corso...</div>
                
                <!--
                <div class="fantini-barra-col ">
                    <input type="number" id="pagina-altezza" class="input-text qty text" step="1" min="1" max="" name="pagina-altezza" value="1" size="4" placeholder="" inputmode="numeric">
                </div>

                <div class="fantini-barra-col ">
                    <input type="button" data-tipo="calcola" data-sezione="" value="Calcola" class="salti-btn sel" />
                </div>

                <div class="fantini-barra-col ">
                </div>
                -->

                <?php
                ?>
            </div>
        </div>

        <!-- jQuery -->
        <script type="text/javascript">
            jQuery.noConflict();
			jQuery(document).ready(function($) {
                
                //BARRA DEI SALTI PAGINA
                if ($(".fantini-catalogo-barra.anteprima").length > 0) {
                    //SALTO PAGINA
                    jQuery(".fantini-catalogo-barra .salti-btn").click(function(e){
					    e.preventDefault();
                        var tipo = jQuery(this).data("tipo");
                        var sez_id = jQuery(this).data("sezione");
                        
                        console.log('tipo', tipo);

                        //operazione
                        var operazione="";
                        if (tipo == 'salti-pagina') {
                            if (jQuery(this).hasClass( "sel" )) {
                                jQuery(this).removeClass("sel");
                                operazione="nascondi";
                            } else {
                                jQuery(this).addClass("sel");
                                operazione="visualizza";
                            }
                        }

                        //Mostra-Nascondi Barra Riga4
                        if (tipo == 'salti-pagina') {
                            var cerca = ".fantini-barra-sezione.riga4";
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();

                                //fantini-catalogo-pagina

                                //visualizzo le pagine
                                var cerca = ".fantini-catalogo-pagina";
                                jQuery(cerca).addClass("cataloghi-setting-salti-on");
                                
                                //aggiungo il numero di pagina
                                pag = 0;
                                $(cerca).each(function(index){
                                    s = 'pag-' + pag;
                                    console.log('....pag:', s);
                                    $(this).addClass(s);

                                    if (pag > 0) {
                                        cercaFiglio = ".pagina-altezza";
                                        figlio = $(this).find(cercaFiglio);
                                        figlio.addClass(s);
                                    }

                                    pag = pag + 1;
                                });



                            } else {
                                jQuery(cerca).hide();

                                //rimuovo le pagine
                                var cerca = ".fantini-catalogo-pagina";
                                jQuery(cerca).removeClass("cataloghi-setting-salti-on");

                            }
                        }

                        //SALVA
                        if (tipo == 'salva') {
                            console.log('---salva');
                            //return;
                            e.preventDefault();
                            attendi = jQuery(".fantini-attendi");
                            jQuery(".save_pdf").prop( "disabled", true );
                            //console.log('---salvo pdf');
                            if (confirm('Confermi il Salvataggio?')) {
                                attendi.show();
                                //salvo Prodotti
                                var sValori='';
                                var conta=0;
                                //cerca = ".view_pdf input";
                                cerca = ".view_pdf input.chk_pdf.articolo";
                                //chk_pdf.articolo
                                $(cerca).each(function(index){
                                    //console.log('-----pdf:', $(this));
                                    check = $(this).is(":checked");
                                    if(check == true) {
                                        conta = conta + 1;
                                        if (conta > 1 ) {
                                            sValori = sValori + '|';
                                        }
                                        sValori = sValori + $(this).data("sku");
                                    }
                                });
                                console.log('lista valori Prodotti', sValori);
                                //lista Sezioni
                                var sListaSezioni='';
                                cerca = "#lista_sezioni";
                                sListaSezioni = $(cerca).val();
                                console.log('sListaSezioni:', sListaSezioni);
                                //salvo Sezioni
                                var sSezioneCorr='';
                                cerca = "#curr_sezione";
                                sSezioneCorr = $(cerca).val();
                                console.log('Sezione:', sSezioneCorr);

                                //salvo Sezioni
                                var sValoriSezioni='';
                                var sValoriSezioniNomi='';
                                var conta=0;
                                cerca = "input.chk_pdf.sezione";
                                $(cerca).each(function(index){
                                    //console.log('-----pdf-SEZIONE:', $(this));
                                    check = $(this).is(":checked");
                                    if(check == true) {
                                        conta = conta + 1;
                                        if (conta > 1 ) {
                                            sValoriSezioni = sValoriSezioni + '|';
                                            sValoriSezioniNomi = sValoriSezioniNomi + '|';
                                        }
                                        sValoriSezioni = sValoriSezioni + $(this).data("id");
                                        sValoriSezioniNomi = sValoriSezioniNomi + $(this).data("sezionenome");
                                    }
                                });
                                //console.log('lista valori -sezioni', sValoriSezioni);
                                var file_cfg=$('.cgf_file_name').val();
                                //console.log('file:', file_cfg);

                                console.log('---sSezioneCorr',sSezioneCorr);
                                console.log('---sValori',sValori);

                                //salvo
                                ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ) ?>';
                                console.log('ajax url:', ajaxurl);
                                
                                jQuery.ajax({
                                    type: "POST",
                                    url : ajaxurl,
                                    data: {
                                        action: 'cataloghi_save_cfg',
                                        tipo: 'salto_pagina_anteprima',
                                        file: file_cfg,
                                        products: sValori,
                                        sezioneCorrente: sSezioneCorr
                                        //sezioni: sValoriSezioni,
                                        //sezioniNomi: sValoriSezioniNomi
                                    },
                                    success: function( data ) {
                                        // Azioni da eseguire in caso di successo chiamata
                                        console.log('salvataggio ok', data);
                                        attendi.hide();
                                        jQuery(".save_pdf").prop( "disabled", false );
                                    },
                                    error: function( error ) {
                                        console.log('errore salvataggio');
                                        attendi.hide();
                                        jQuery(".save_pdf").prop( "disabled", false );
                                    }
                                });
                            }
                        }
                        return false;
				    });
                }
                
                //SALTO PAGINA CHK
                $(document).on('click','.view_pdf input.chk_pdf.articolo',function(e){
                //jQuery(".view_pdf input.chk_pdf.articolo").click(function(e){
                    var checked = $(this).prop("checked");
                    var tipo =  $(this).data("tipo");

                    if (checked == true ){
                        //AGGIUNGO SALTO PAGINA
                        e.preventDefault();
                        //console.log('----CHK', checked);

                        var p = $(this).parent().closest('.fantini-catalogo-pagina');
                        var p2 = $(this).parent().closest('.fantini-catalogo-pagina');
                        var p3 = p2.clone();

                        //pulisco la pagina corrente
                        var current_sku = jQuery(this).data("sku");
                        //console.log('current_sku',current_sku);
                        cerca = ".riga-clear";
                        var esci = '0';
                        var conta = 0;

                        $( $(this).parent().closest('.fantini-catalogo-pagina').find(cerca ).get().reverse()).each(function(index){
                            conta = conta + 1;
                            var sku = jQuery(this).data("sku");
                            //console.log('sku e conta',sku, conta);
                            if (esci == '0') {
                                //console.log('sku', sku);
                                if (sku == current_sku ){
                                    //console.log('---ESCO');
                                    esci = '1';
                                    $(this).remove();
                                } else {
                                    $(this).remove();
                                }
                            }
                        });
                        
                        
                        //pulisco il clone - pagina successiva
                        var current_sku = jQuery(this).data("sku");
                        cerca = ".riga-clear";
                        var esci = '0';
                        var contaTitoliVariazioni = 0;
                        p3.find( cerca ).each(function(index){
                            //TITOLO PAGINA
                            if ($(this).hasClass( "fantini-catalogo-riga-articolo" )) {
                                if ($(this).hasClass( "titolo" )) {
                                    return;
                                }
                            }
                            //TITOLO PER I VARIATION
                            if (tipo == 'variation'){ 
                                if ($(this).hasClass( "fantini-catalogo-riga-articolo-variazioni" )) {
                                    if ($(this).hasClass( "titolo" )) {
                                        contaTitoliVariazioni = contaTitoliVariazioni + 1;
                                        //lascio solo la prima
                                        if (contaTitoliVariazioni == 1)
                                            return;
                                    }
                                }
                            }
                            //check e rimozione
                            var sku = jQuery(this).data("sku");
                            if (esci == '0') {
                                //console.log('sku', sku);
                                if (sku == current_sku ){
                                    esci = '1';
                                } else {
                                    $(this).remove();
                                }
                            }
                        });

                        //rimuovo la sezione nel clone
                        cerca = ".fantini-catalogo-sezione-titolo";
                        p3.find( cerca ).each(function(index){
                            $(this).remove();
                        });
                        //aggiungo il clone
                        p2.after(p3);
                        //aggiungo il salto pagina
                        var saltoPag = '<div class="fantini-catalogo-salta-pagina"></div> ';
                        p2.after(saltoPag);
                    } else {
                        //TOLGO IL SALTO PAGINA
                        //e.preventDefault();
                        console.log('----CHK');

                        var p = $(this).parent().closest('.fantini-catalogo-pagina');
                        //console.log('p', p);
                        //console.log('p.prev', p.prev());
                        //console.log('p.prev.prev', p.prev().prev());
                        //pagina e sezione articoli precedente
                        var p_prev = p.prev().prev();
                        cerca = ".fantini-catalogo-sezione-articoli";
                        var arts_prev = p_prev.find( cerca );
                        //console.log('articoli prev', arts_prev);
                        //console.log('count', arts_prev.children().length);

                        //scorro gli elemennti della pagina corrente e li copio nella pagina precedente
                        cerca = ".riga-clear";
                        var copia = '0';
                        var sku = '';
                        p.find( cerca ).each(function(index){
                            var sku = $(this).data("sku");
                            console.log('sku', sku);
                            //console.log('-eleemento');
                            if (sku != undefined) {
                                copia = '1';
                            }
                            if (copia == '1'){
                                var el = $(this);
                                arts_prev.append(el);
                            }
                        });
                        //elimino la pagina e il salto pagina;
                        p.prev().remove();
                        p.remove();
                        //console.log('count', arts_prev.children().length);
                    }

                });
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

            <!-- INFO PER IL SALTO PAGINA -->
            <?php
                global $footer_pagina;
                $info_id = 'info-pagina-' . $footer_pagina;
            ?>
            <div class="fantini-catalogo-pagina-info" id="<?php echo $info_id; ?>" >
                <div class="pagina-info-col nr-pagina">
                    <?php
                        global $footer_pagina;
                        echo 'Pagina: ' . $footer_pagina;
                    ?>
                </div> 

                <div class="pagina-info-col calcola">
                    <span class="pagina-altezza">0</span>
                </div> 

            </div> 

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
			    <img src ="<?php echo $logo; ?>" />
            </span>
            <span class="due">
                <?php echo $footer_catalogo; ?>
            </span>
            <span class="tre">
                <?php echo $footer_sezione; ?>
            </span>
            <span class="quattro">
                Pag. &nbsp;<?php echo strval($footer_pagina); ?>
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
    function renderPageAnteprima($atts) {
        global $footer_catalogo;
        global $footer_sezione;
        global $footer_pagina;
        global $curr_sezione;
        global $cfg_prod_pdf;

        $footer_pagina = 0;

        //CONTROLLO ACCESSO
        // $current_user_role = get_current_user_first_role();
        // //echo 'current_user_role: ' . $current_user_role  . '<br>';
        // if ($current_user_role == 'guest'){
        //     utenteNonAbilitato();
        //     return;
        // }

        //POST
        if(isset($_POST['submit']) and $_POST['action']=='admin-sezioni-load'){
            $curr_sezione = 0;
            $s = trim('' . $_POST['salta-sezione']);
            if ($s == '') {
                $curr_sezione = 0;
            } else {
                $curr_sezione = intval($s);
            }
        }

        //Sconto
		$sconto = 0;
        //$ruolo_sconto = $current_user_role;
        if (is_user_logged_in()){
            $user = wp_get_current_user();
            $roles = (array) $user->roles;
            //li testo
            // if ( in_array( 'clientesconto50', $roles )) $ruolo_sconto = 'clientesconto50';
            // if ( in_array( 'clientesconto40', $roles )) $ruolo_sconto = 'clientesconto40';
            // if ( in_array( 'clientesconto0', $roles )) $ruolo_sconto = 'clientesconto0';
            // if ( in_array( 'clientisconto50', $roles )) $ruolo_sconto = 'clientisconto50';
            // if ( in_array( 'clientisconto40', $roles )) $ruolo_sconto = 'clientisconto40';
            // if ( in_array( 'clientisconto0', $roles )) $ruolo_sconto = 'clientisconto0';
            //echo '<pre>'; print_r($roles); echo '</pre>';
        } else {
            utenteNonAbilitato();
            return;
        }

        if (!in_array( 'administrator', $roles )){
            utenteNonAbilitato();
            return;
        }

        //echo 'ruolo_sconto SCONTO: ' . $ruolo_sconto  . '<br>';

        // //controllo accesso
        // if ($current_user_role == 'guest'){
        //     utenteNonAbilitato();
        //     return;
        // }

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
        $cfg_prod_pdf = explode('|',$cfg_config['newpage']['products']);
        $cfg_section_pdf = explode('|',$cfg_config['newpage']['sections']);
        $cfg_lista_sezioni = $cfg_config['newpage']['sections'];
        $cfg_section_pdf_names = explode('|',$cfg_config['newpage']['sectionsNames']);

        //echo '<pre>'; print_r($cfg_prod_hide); echo '</pre>';
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
        //$liv = $curr_sezione;
        //$categorie=listaCategorie($cat_id, $liv);
        //echo 'CATEORIE: <br>';
        //echo '<pre>'; print_r($categorie); echo '</pre>';

        //Modalità Debug
        $debug='off';
        if (isset($_GET['debug'])) {
            $debug='on';
        }



        //BARRA MENU
        creaBarraMenu($categorie, $cfg_section_pdf, $cfg_section_pdf_names);
        ?>  
        <div class="fantini-catalogo-container anteprima <?php echo strtolower($cat_nome); ?> ">
            <input type="hidden" id="curr_sezione" name="curr_sezione" value="<?php echo $curr_sezione; ?> ">
            <input type="hidden" id="lista_sezioni" name="lista_sezioni" value="<?php echo $cfg_lista_sezioni; ?> ">
            <input type="hidden" name="cgf_file_name" class="cgf_file_name" value="<?php echo $cfg_file; ?>">
            
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
                        'livello' =>  0,
                    );

                    //record della sezione corrente
                    $category = get_term_by( 'id', $curr_sezione, 'product_cat' );
                    $categorie[] = array(
                        'slug' =>  $category->slug,
                        'nome' =>  $category->name,
                        'id' =>  $curr_sezione,
                        'livello' =>  0,
                    );

                }
                //da rivedere!!!! posso avere prodotti anche se ho sottocategorie

                //scorro le categorie
                $cat_parent_nome= '';
                foreach ($categorie as $categoria) {
                    $prodotti=[];

                    if ($categoria['livello'] == 0)
                        $cat_parent_nome = $categoria['nome'];

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
                    $footer_sezione = $categoria['nome'];

                    $prodotti = $query->get_products();
                    //echo 'prodotti: ' . strval(count($prodotti)) . '<br>';
                    $sez_tag = "sezione_id_" . strval($categoria['id']);

                    //SEZIONE - SALTO PAGINA
                    if (in_array($categoria['id'], $cfg_section_pdf)) {
                        //se presente nelle sottocategorie lo salto
                        creaRigaSaltaPagina('sezione', '');
                    }

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
                                //$titolo_categoria = $titolo_categoria . '-' . strval($categoria['livello']);
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
                                            //simple_carica_come_Variations($prodotto, $_iva, $sconto, $debug, $conta, );
                                            simple_carica_come_Variations($prodotto, $_iva, $sconto, $debug, $conta, $_is_nutrizione);

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
        </div> <!-- <div class="fantini-catalogo-container"> -->
        <?php
    return;
}
?> 

