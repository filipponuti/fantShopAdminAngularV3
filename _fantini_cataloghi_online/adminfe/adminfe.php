<?php
    //variabili globali
    $curr_cat=0;
    $curr_operazione='';
    $curr_load_img='SI';
    $cfg_file = '';
    $cfg_config = [];
    $cfg_prod_hide = [];
    $cfg_prod_pdf = [];
    $cfg_section_pdf = [];
    $cfg_section_hide = [];

    //lista Cataloghi
    function listaCataloghi() {
        //echo "listaCataloghi <br>";
        $cataloghi = array();
        $a=listaCategorie('0', 0, '1');
        $cataloghi = array_merge($cataloghi, $a);
        //echo '<pre>'; print_r($cataloghi); echo '</pre>';
        return $cataloghi;
    }

    //lista categorie
    function listaCategorie($parent, $livello, $solo1livello) {
        //echo "listaCategorie di: " . $parent . " <br>";
        $categorie = array();
        try {
            $args = array(
                'taxonomy'   => "product_cat",
                'hierarchical' => true,
                'hide_empty' => true,
                'parent' => $parent
            );
            //echo '<pre>'; print_r($args); echo '</pre>';
            $children = get_terms($args);
            //echo '<pre>'; print_r($children); echo '</pre>';
            foreach( $children as $nodo ) {
                //echo "scorro <br>";
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
                if ($solo1livello == '0') {
                    $liv = $livello+1;
                    $a=listaCategorie($nodo_id, $liv, $solo1livello);
                    $categorie = array_merge($categorie, $a);
                }
            }
            //echo "categorie: <br>";
            //echo '<pre>'; print_r($categorie); echo '</pre>';
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
    function rigaProdottoTitoli() {
        ?>
        <div class="fantini-catalogo-riga-articolo titolo">
            <!-- a Catalogo -->
            <div class="fantini-catalogo-articolo-col titolo fant-col-20 view_a_cat" style="display: none;">
                A Catalogo
            </div>
            <!-- a Catalogo -->
            <div class="fantini-catalogo-articolo-col titolo fant-col-20 view_pdf" style="display: none;">
                Salto Pagina
            </div>
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
                Prezzo
            </div>
        </div>
        <?php
    }

    //scrivo la riga prodotto
    function rigaProdotto($product, $sconto) {
        global $curr_load_img;
        global $cfg_prod_hide;
        global $cfg_prod_pdf;
        
        $cls_variable = '';
        $cls_img_variable = '';
        if ($product->get_type() == 'variable') {
            $cls_variable = 'variable';
        }
        if ($product->get_type() == 'variation') {
            $cls_img_variable = 'img-variable';
        }
        $sku = trim('' . $product->get_sku());
        //echo '<pre>'; print_r($cfg_prod_hide); echo '</pre>';
        //$cfg_prod_hide
        ?>
        <div class="fantini-catalogo-riga-articolo  <?php echo $cls_variable; ?> ">
            <!-- a Catalogo -->
            <div class="fantini-catalogo-articolo-col fant-col-20 view_a_cat" style="display: none;">
                <?php
                    $sku = trim('' . $product->get_sku());
                    if (in_array($sku, $cfg_prod_hide)) {
                        $selezionato = 'checked';
                    } else {
                        $selezionato = '';
                    }
                ?>

                <label class="grid-lbl-checkbox">
                    <input type="checkbox" 
                        id="chk_<?php echo $product->get_sku(); ?>" 
                        name="chk_<?php echo $product->get_sku(); ?>"  
                        class="chk-a-catalogo"
                        data-sku="<?php echo trim('' . $product->get_sku()); ?>"
                        data-tipo="<?php echo trim('' . $product->get_type()); ?>"
                        <?php echo $selezionato; ?>
                    />
                Nacondi</label>
            </div>
            <!-- Pdf Salto Pagina -->
            <!--
            <div class="fantini-catalogo-articolo-col fant-col-20 view_pdf" style="display: none;">
                <?php
                    /*
                    $sku = trim('' . $product->get_sku());
                    if (in_array($sku, $cfg_prod_pdf)) {
                        $selezionato = 'checked';
                    } else {
                        $selezionato = '';
                    }
                    */
                ?>
                <label class="grid-lbl-checkbox articolo">
                    <input type="checkbox" 
                        id="chk_<?php //echo $product->get_sku(); ?>" 
                        name="chk_<?php //echo $product->get_sku(); ?>"  
                        class="chk_pdf articolo"
                        data-sku="<?php //echo trim('' . $product->get_sku()); ?>"
                        data-tipo="<?php //echo trim('' . $product->get_type()); ?>"
                        <?php //echo $selezionato; ?>
                    />
                    Cambia pag.
                    <div class="chk_conta">
                    </div>
                </label>
            </div>
            -->
            <!-- img -->
            <div class="fantini-catalogo-articolo-col fant-col-20 vert">
                <?php 
                $cls_prod = 'prod_' . strval($product->get_id());
                if ($curr_load_img == 'SI') { 
                ?>
                <div class="wrap-img <?php echo $cls_img_variable;?> <?php echo $cls_prod; ?>  "> 
                    <?php 
                        // echo 'prod id: ' . $product->get_id() . '<br>'; 
                        // echo 'prod img: ' . $product->get_image_id() . '<br>'; 
                    ?>
                    <img src="<?php echo wp_get_attachment_url( $product->get_image_id() ); ?>" />
                </div>
                <div class="change-img" style="display: none;">
                    <input type="button" data-prod-id="<?php echo trim('' . $product->get_id()); ?>" 
                           value="cambia" class="btn-change-img" />
                </div>
                <?php } ?>
            </div>
            <!-- codice -->
            <div class="fantini-catalogo-articolo-col fant-col-15">
                <?php echo $product->get_sku(); ?>
                <?php //echo $product->get_type(); ?>
            </div>
            <!-- nome -->
            <div class="fantini-catalogo-articolo-col fant-col-30">
                <?php echo $product->get_name(); ?>
            </div>
            <!-- note -->
            <div class="fantini-catalogo-articolo-col note fant-col-20">
                <?php 
                if ($product->get_type() == 'variable') {
                    //non stampo le note
                } else {

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
                    //Colore
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
                ?>
            </div>
            <!-- prezzo -->
            <div class="fantini-catalogo-articolo-col dx prezzo fant-col-15">
                <!--<?php //echo 'sconto: ' . $sconto; ?> -->
                
                <!-- Product Simple -->
                <?php
                if ($product->get_type() == 'simple') {
                ?>
                    <?php //if ($sconto > 0) { ?>
                        <div class="p-pieno">
                            <?php 
                                $prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                echo wc_price($prezzo);
                            ?>
                        </div>
                    <?php //} ?>
                    <!-- <div class="p-scontato"> -->
                        <?php //echo '' . wc_price($product->get_price()); ?>
                    <!-- </div> -->
                <?php
                }
                ?>

                <!-- Product Variable -->
                <?php
                if ($product->get_type() == 'variable') {
                    //non stampo il prezzo
                }
                ?>

                <!-- Product Variations -->
                <?php
                if ($product->get_type() == 'variation') {
                ?>
                    <?php //if ($sconto > 0) { ?>
                        <div class="p-pieno">
                            <?php 
                                $prezzo = get_post_meta($product->get_id() , '_regular_price', true );
                                echo wc_price($prezzo);
                            ?>
                        </div>
                    <?php //} ?>
                    <!-- <div class="p-scontato"> -->
                        <?php //echo '' . wc_price($product->get_price()); ?>
                    <!-- </div> -->
                <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    //carico le variations
    function caricaVariations($product, $sconto) {
        //SORT: inizio
        //------------
        $variations = $product->get_available_variations();
        $var_id = '';
        $var_sku = '';
        $a_prodotti = [];
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
        $variations_ids = $product->get_children();
        $n=0;
        //foreach( $variations_ids as $var_id) {
        foreach( $a_prodotti as $prod) { 
            $n=$n+1;
            $var_id = $prod['id'];
            $variation = wc_get_product($var_id);
            //echo '<pre>'; print_r($variation); echo '</pre>';
            rigaProdotto($variation, $sconto);
            if ( $n < count($a_prodotti))
                rigaProdottoSepaVariation();
        }
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
        <div class="fantini-admin-catalogo-articolo-sepavariation">
        </div>
        <?php
    }

    //crea la barra menu Catalogo
    function creaBarraMenuCatalogo($cataloghi, $categorie, $cfg_file) {
        global $curr_cat;
        global $curr_load_img;
        ?>
        <div class="fantini-admin-catalogo-barra">
            <input type="hidden" name="cgf_file_name" class="cgf_file_name" value="<?php echo $cfg_file; ?>">
            <!-- form -->
            <form action="" method="post" onSubmit="if(!confirm('Vuoi procedere?')){return false;}">
                <input type="hidden" name="action" value="admin-cat-load">
                <!-- SEZIONE 1 -->
                <div class="fantini-barra-sezione sez1">
                    <!-- select catalogo -->
                    <div class="riga-campi">
                        <label for="catalogo-sel">Catalogo</label>
                        <select name="catalogo" id="catalogo-sel">
                            <option value="-1">--Seleziona Catalogo--</option>
                                <?php
                                foreach( $cataloghi as $cat ) {
                                    ?>
                                    <option value="<?php echo $cat['id']; ?> "  <?php if($curr_cat == $cat['id']) echo "selected='selected'"; ?> >
                                        <?php echo $cat['desc']; ?>
                                    </option>
                                    <?php
                                }
                                ?>
                        </select>
                    </div>
                    <!-- select Carica Immagini -->
                    <div class="riga-campi">
                    <label for="load-img">Carica Immagini</label>
                        <select name="load-img" id="cataload-img">
                            <option value="SI" <?php if($curr_load_img == 'SI') echo "selected='selected'"; ?> >Si</option>
                            <option value="NO" <?php if($curr_load_img == 'NO') echo "selected='selected'"; ?> >No</option>
                        </select>
                    </div>
                    <!-- btn Carica -->
                    <div class="riga-campi">
                        <input type="submit" name="submit" value="Carica" class="barra-btn" />
                    </div>
                </div>
                <!-- SEZIONE 2 -->
                <div class="fantini-barra-sezione sez2">
                    <?php
                        foreach($categorie as $categoria) {
                            $sez_tag = "sezione_id_" . strval($categoria['id']);
                    ?>
                            <input type="button" data-tipo="sezione" data-sezione="<?php echo $sez_tag; ?>" value="<?php echo $categoria['nome']; ?>" class="barra-btn sel tab" />
                    <?php
                        }
                    ?>
                </div>
                <!-- SEZIONE 3 -->
                <div class="fantini-barra-sezione sez3">
                    <!-- btn A Catalogo -->
                    <div class="riga-campi">
                        <input type="button" name="btn_a_catalogo" data-tipo="a_catalogo" data-sezione="" value="A Catalogo" class="barra-btn tab dis" />
                    </div>
                    <!-- btn Pdf Salto Pagina -->
                    <div class="riga-campi">
                        <input type="button" name="btn_salto_pagina" data-tipo="salto_pagina" data-sezione="" value="Pdf Salto Pagina" class="barra-btn tab dis" />
                    </div>

                    <!-- btn Cambia Immagine -->
                    <div class="riga-campi">
                        <input type="button" name="btn_cambia_img" data-tipo="cambia-img" data-sezione="" value="Cambia Immagine" class="barra-btn tab dis" />
                    </div>
                </div>
               <!-- SEZIONE 3 - Catalogo -->
                <div class="fantini-barra-sezione sez3-cat"  style="display: none;" >
                    <!-- Salva -->
                    <div class="riga-campi">
                        <div class="btn-save-label">
                            A catalogo
                        </div>
                        <input type="button" name="btn_salva_a_catalogo" data-tipo="save_a_catalogo" data-sezione="sez" value="Salva" class="barra-btn verde save_a_catalogo" />
                        <div class="fantini-attendi" style="display: none;" >Salvataggio in corso...</div>
                    </div>
                </div>
               <!-- SEZIONE 3 - Pdf -->
               <div class="fantini-barra-sezione sez3-pdf"  style="display: none;" >
                    <!-- Salva -->
                    <div class="riga-campi">
                        <div class="btn-save-label">
                            Salto Pagina
                        </div>
                        <input type="button" name="btn_salva_pdf" data-tipo="save_pdf" data-sezione="sez" value="Salva" class="barra-btn verde save_pdf" />                        
                        <div class="fantini-attendi" style="display: none;" >Salvataggio in corso...</div>
                    </div>
                </div>
            </form>
        </div>

        <!-- jQuery -->
        <script type="text/javascript">
            jQuery.noConflict();
			jQuery(document).ready(function($) {

                //conto gli articoli selezionati
                function conta_check(){
                    cerca = ".chk_pdf.articolo";
                    conta = 0;
                    $(cerca).each(function(index){
                            check = $(this).is(":checked");
                            if(check == true) {
                                conta = 1;
                            } else {
                                conta = conta + 1;
                            }
                            $(this).next(".chk_conta").html(''+conta);
                    });
                }
                jQuery(".view_pdf input.chk_pdf.articolo").click(function(e){
                    //e.preventDefault();
                    conta_check();
                });

                //se la barra esiste
                if ($(".fantini-admin-catalogo-barra").length > 0) {
                    //MAIN
                    cerca = ".fantini-barra-sezione.sez3-cat";
                    cerca = ".view_a_cat";
                    //FUNZIONI
                    //Bottoni Mostra/Nascondi
                    jQuery(".fantini-admin-catalogo-barra .sez2 .barra-btn, .fantini-admin-catalogo-barra .sez3 .barra-btn").click(function(e){
					    e.preventDefault();
                        //console.log('---bottone', e);
                        var tipo = jQuery(this).data("tipo");
                        var sez_id = jQuery(this).data("sezione");
                        var operazione="";
                        if (jQuery(this).hasClass( "sel" )) {
                            jQuery(this).removeClass("sel");
                            jQuery(this).addClass("dis");
                            operazione="nascondi";
                            if (tipo == 'immagini') jQuery(this).removeClass("verde");
                        } else {
                            jQuery(this).addClass("sel");
                            jQuery(this).removeClass("dis");
                            operazione="visualizza";
                            if (tipo == 'immagini') jQuery(this).addClass("verde");
                        }
                        //sezioni
                        if (tipo == 'sezione') {
                            var cerca = "." + sez_id + " > .fantini-catalogo-sezione-articoli";
                            //console.log('cerca', cerca);
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();
                            } else {
                                jQuery(cerca).hide();
                            }
                        }
                        //a catalogo
                        if (tipo == 'a_catalogo') {
                            //console.log('--------');
                            var cerca = ".fantini-barra-sezione.sez3-cat";
                            //console.log('cerca', cerca);
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();
                            } else {
                                jQuery(cerca).hide();
                            }
                            cerca = ".view_a_cat";
                            //console.log('cerca', cerca);
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();
                            } else {
                                jQuery(cerca).hide();
                            }
                        }
                        
                        //pdf salto pagina
                        if (tipo == 'salto_pagina') {
                            var cerca = ".fantini-barra-sezione.sez3-pdf";
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();
                            } else {
                                jQuery(cerca).hide();
                            }
                            cerca = ".view_pdf";
                            //console.log('cerca', cerca);
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();
                            } else {
                                jQuery(cerca).hide();
                            }
                        }

                        //pdf salto pagina
                        if (tipo == 'cambia-img') {
                            //console.log('-cambio img-------');
                            var cerca = ".change-img";
                            //console.log('cerca', cerca);
                            if (operazione == 'visualizza'){
                                jQuery(cerca).show();
                            } else {
                                jQuery(cerca).hide();
                            }
                        }
                        return false;
				    });
                }
    
                //BOTTONE SALVA A CATALOGO
                jQuery(".save_a_catalogo").click(function(e){
                    e.preventDefault();
                    attendi = jQuery(".fantini-attendi");
                    jQuery(".save_a_catalogo").prop( "disabled", true );
                    //console.log('---salvo a catalogo');
                    if (confirm('Confermi il Salvataggio?')) {
                        attendi.show();
                        //salvo
                        var sValori='';
                        var sValoriSezioni='';
                        var conta=0;
                        var contaSezioni=0;
                        cerca = ".view_a_cat input";
                        $(cerca).each(function(index){
                            //console.log('---salvo', this);
                            check = $(this).is(":checked");
                            tipo = $(this).data('tipo');
                            //console.log('tipo', tipo);
                            if (tipo == 'sezione'){
                                //sezione
                                if(check == true) {
                                    contaSezioni = contaSezioni + 1;
                                    if (contaSezioni > 1 ) {
                                        sValoriSezioni = sValoriSezioni + '|';
                                    }
                                    sValoriSezioni = sValoriSezioni + $(this).data("id");
                                }
                            } else {
                                //prodotto
                                if(check == true) {
                                    conta = conta + 1;
                                    if (conta > 1 ) {
                                        sValori = sValori + '|';
                                    }
                                    sValori = sValori + $(this).data("sku");
                                }
                            }
                        });
                        //console.log('lista valori', sValori);
                        var file_cfg=$('.cgf_file_name').val();
                        //console.log('file:', file_cfg);
                        //salvo
                        ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ) ?>';
                        jQuery.ajax({
                            type: "POST",
                            url : ajaxurl,
                            data: {
                                action: 'cataloghi_save_cfg',
                                tipo: 'nascondi',
                                file: file_cfg,
                                products: sValori,
                                sezioni: sValoriSezioni
                            },
                            success: function( data ) {
                                // Azioni da eseguire in caso di successo chiamata
                                console.log('salvataggio ok', data);
                                attendi.hide();
                                jQuery(".save_a_catalogo").prop( "disabled", false );
                            },
                            error: function( error ) {
                                console.log('errore salvataggio');
                                attendi.hide();
                                jQuery(".save_a_catalogo").prop( "disabled", false );
                            }
                        });
                    }
                });

                //BOTTONE SALVA PDF
                jQuery(".save_pdf").click(function(e){
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
                        //console.log('lista valori Prodotti', sValori);
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
                        //salvo
                        ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ) ?>';
                        jQuery.ajax({
                            type: "POST",
                            url : ajaxurl,
                            data: {
                                action: 'cataloghi_save_cfg',
                                tipo: 'salto_pagina',
                                file: file_cfg,
                                products: sValori,
                                sezioni: sValoriSezioni,
                                sezioniNomi: sValoriSezioniNomi
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
                });

                //BOTTONE CAMBIA IMMAGINE
                ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ) ?>'; // get ajaxurl
                jQuery(".fantini-catalogo-articolo-col .btn-change-img").click(function(e){
                    e.preventDefault();
                    //console.log('---cambia img');
                    var prod_id = jQuery(this).data("prod-id");
                    //console.log('prod id:' ,  prod_id);
                    var $button = $(this);
                    // Create the media frame.
                    var file_frame = wp.media.frames.file_frame = wp.media({
                        title: 'Select or upload image',
                        library: { // remove these to show all
                            type: 'image' // specific mime
                        },
                        button: {
                            text: 'Select'
                        },
                        multiple: false  // Set to true to allow multiple files to be selected
                    });
                    // When an image is selected, run a callback.
                    file_frame.on('select', function () {
                        // We set multiple to false so only get one image from the uploader
                        var attachment = file_frame.state().get('selection').first().toJSON();
                        //$button.siblings('input').val(attachment.url).change();
                        //console.log('attachment.url', attachment.url);
                        //console.log('attachment.id', attachment.id);
                        var prod_sel = 'prod_' + prod_id;
                        var cerca = "." + prod_sel + " img";
                        //console.log('cerca: ', cerca);
                        //console.log('el', jQuery(cerca));
                        //salvo la nuova immagine
                        jQuery.ajax({
                            type: "POST",
                            url : ajaxurl,
                            data: {
                                action: 'cataloghi_save_cfg',
                                tipo: 'nuova_immagine',
                                prod_id: prod_id,
                                img_url: attachment.url, 
                                img_id: attachment.id
                            },
                            success: function( data ) {
                                // Azioni da eseguire in caso di successo chiamata
                                console.log('salvataggio ok', data);
                                //attendi.hide();
                                //jQuery(".save_pdf").prop( "disabled", false );
                                jQuery(cerca).attr('src',attachment.url);
                            },
                            error: function( error ) {
                                console.log('errore salvataggio');
                                //attendi.hide();
                                //jQuery(".save_pdf").prop( "disabled", false );
                            }
                        });
                    });
                    // Finally, open the modal
                    file_frame.open();
                });
            });
        </script>
        <?php
    }

    //UTENTE NON ABILITATO
    function utenteNonAbilitato() {
        $page_object = get_page_by_title( 'My account' );
        $page_id = $page_object->ID;
        $permalink = get_permalink( $page_id );
        //echo "link: " . $permalink;
        ?>
        <div class="fantini-cataloghi-utente">
            <h2>Area riservata</h2>
            <h3>Accesso riservato agli amministratori</h3>
            <div class="login-btn">
                <a href="<?php echo $permalink; ?>" class="btn" role="button">
                    Accedi
                </a>
            </div>
        </div>
        <?php
    }

    //RENDER PAGE
    function renderPageAdmin($atts) {
        global $curr_cat;
        global $curr_operazione;
        global $curr_load_img;
        global $cfg_config;
        global $cfg_prod_hide;
        global $cfg_prod_pdf;
        global $cfg_section_pdf;
        global $cfg_section_hide;
        
        //controllo accesso
        $current_user = wp_get_current_user();
        if ( in_array('cataloghiadmin', $current_user->roles) ) {
            //ok
        } else {
            utenteNonAbilitato();
            return;
        }
        //POST
        if(isset($_POST['submit']) and $_POST['action']=='admin-cat-load'){
            $curr_cat = 0;
            $s = trim('' . $_POST['catalogo']);
            if ($s == '') {
                $curr_cat = 0;
            } else {
                $curr_cat = intval($s);
            }
            $operazione = $_POST['submit'];
            $curr_load_img = $_POST['load-img'];
        }
        //cataloghi
        //echo "cataloghi-inizio <br>";
        $cataloghi=[];
        $cataloghi=listaCataloghi();

        //recupero le sottocategorie
        $categorie=[];
        if ($curr_cat > 0){
            //echo "....carico categorie..  <br>";
            $liv = 0;
            $categorie=listaCategorie($curr_cat, $liv ,'0');
        }
        //carico i setting
        $category = get_term_by( 'term_id', $curr_cat, 'product_cat', ARRAY_A );
        $cat_slug = $category['slug'];
        //echo "load setting: " . $cat_slug . "<br>";
        $cfg_config = loadSetting($cat_slug);
        $cfg_prod_hide = explode('|',$cfg_config['hide']['products']);
        $cfg_section_hide = explode('|',$cfg_config['hide']['sections']);
        $cfg_prod_pdf = explode('|',$cfg_config['newpage']['products']);
        $cfg_section_pdf = explode('|',$cfg_config['newpage']['sections']);
        //echo '<pre>'; print_r($cfg_prod_hide); echo '</pre>';
        //echo '<pre>'; print_r($cfg_prod_pdf); echo '</pre>';
        //echo '<pre>'; print_r($cfg_section_pdf); echo '</pre>';
        //echo '<pre>'; print_r($cfg_prod_hide); echo '</pre>';
        //echo '<pre>'; print_r($cfg_section_hide); echo '</pre>';

        $cfg_file = cfg_get_file_name($cat_slug);


        //BARRA MENU
        creaBarraMenuCatalogo($cataloghi, $categorie, $cfg_file);

        //se Catalogo nn selezionato mi fermo
        if ($curr_cat == 0) return;

        //CARICAMENTO ARTICOLI
        ?>  
        <div class="fantini-admin-catalogo-container">
        <?php
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
        foreach ($categorie as $categoria) {
            $prodotti=[];

            //sottocategorie
            $children = get_categories( array ('taxonomy' => 'product_cat', 'parent' => $categoria['id'] ));
            $aSotto = [];
            foreach ($children as $child) {
                $aSotto[] = $child->term_id;
            }
            //echo '<pre>'; print_r($children); echo '</pre>';
            //prodotti
            $query = new WC_Product_Query(array(
                'limit' => -1,
                'category' => array($categoria['slug']),
                'orderby' => 'meta_value',
                'meta_key' => '_sku',
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
            ));
            $prodotti = $query->get_products();
            //echo 'prodotti: ' . strval(count($prodotti)) . '<br>';
            $sez_tag = "sezione_id_" . strval($categoria['id']);
            ?>  
            <div class="fantini-catalogo-sezione <?php echo $sez_tag; ?>">
                <div class="fantini-catalogo-sezione-titolo admin">

                    <!-- Colonna Mostra a Catalogo -->
                    <div class="sezione-salta-pagina view_a_cat" style="display: none;">
                        <?php
                            $selezionato = '';
                            $sez_id = strval($categoria['id']);
                            if (in_array($sez_id, $cfg_section_hide)) {
                                $selezionato = 'checked';
                            } else {
                                $selezionato = '';
                            }
                        ?>
                        <label class="grid-lbl-checkbox">
                            <input type="checkbox"
                                id="chk_<?php echo $categoria['id']; ?>" 
                                name="chk_<?php echo $categoria['nome']; ?>"  
                                class="chk-a-catalogo sezione"
                                data-id="<?php echo $categoria['id']; ?>"
                                data-tipo="sezione"
                                data-sezionenome="<?php echo $categoria['nome']; ?>" 
                                <?php echo $selezionato; ?>
                            />
                        Nascondi</label>
                    </div>

                    <!-- Colonna Pdf Salto Pagina -->
                    <div class="sezione-salta-pagina view_pdf" style="display: none;">
                        <?php
                            $selezionato = '';
                            $sez_id = strval($categoria['id']);
                            if (in_array($sez_id, $cfg_section_pdf)) {
                                $selezionato = 'checked';
                            } else {
                                $selezionato = '';
                            }
                        ?>
                        <label class="grid-lbl-checkbox">
                            <input type="checkbox" 
                                id="chk_<?php echo $categoria['id']; ?>" 
                                name="chk_<?php echo $categoria['nome']; ?>"  
                                class="chk_pdf sezione"
                                data-id="<?php echo $categoria['id']; ?>"
                                data-tipo="sezione"
                                data-sezionenome="<?php echo $categoria['nome']; ?>" 
                                <?php echo $selezionato; ?>
                            />
                        Cambia pag.</label>

                    </div>
                    <!-- Sezione DESC -->
                    <h2><?php echo $categoria['nome']; ?></h2>
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
                            rigaProdottoTitoli();
                        }
                        rigaProdotto($prodotto, $sconto);
                        if ($prodotto->get_type() == 'simple') {
                            rigaProdottoSepa();
                        }
                        //se prodotto variabile, carico le variations
                        if ($prodotto->get_type() == 'variable') {
                            caricaVariations($prodotto, $sconto);
                            rigaProdottoSepa();
                        }
                    }
                    ?>
                </div> 
            </div>  <!-- <div class="fantini-catalogo-sezione"> -->
            <?php
        }
        ?> 
        </div> <!-- <div class="fantini-catalogo-container"> -->
        <?php
        return;
    }
?> 
