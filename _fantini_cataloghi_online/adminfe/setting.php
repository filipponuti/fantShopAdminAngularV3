<?php
    //get cfg array init
    function cfg_get_array_init(){
        $a_cfg_rec = array(
            'hide' =>  array(
                'products' =>  '',
                'sections' =>  '',
            ),
            'newpage' =>  array(
                'products' =>  '',
                'sections' =>  '',
                'sectionsNames' =>  '',
                'productsSezione' =>  '',
            )
        );
        return $a_cfg_rec;
    }

    //get nome file cfg
    function cfg_get_file_name($slug){
        $dir = plugin_dir_path( __DIR__ );
        $file_cfg = $dir . 'pdf/' . $slug . '.cfg';
        //echo 'file cfg: ' . $file_cfg . '<br>';
        return $file_cfg;
    }

    //LOAD SETTING
    function loadSetting($slug) {
        $config=[];
        //nome del file
        $file_cfg = cfg_get_file_name($slug);
        //echo "setting nome file: " . $file_cfg . "<br>";
        if ( file_exists( $file_cfg ) ) {
            //echo 'file  esiste <br>';
            $config = parse_ini_file($file_cfg, true);
            //echo '<pre>'; print_r($config); echo '</pre>';
        } else {
            //echo 'file NON esiste <br>';
            //lo creo
            $a_cfg_rec = cfg_get_array_init();
            $file_open = fopen($file_cfg,"w+");
            fclose($file_open);
            update_ini_file($a_cfg_rec, $file_cfg);
            $config = parse_ini_file($file_cfg, true);
        }
        return $config;
    }

    //SCRIVO Il FILE INI
    function update_ini_file($data, $filepath) { 
        $content = ""; 
        //parse the ini file to get the sections
        //parse the ini file using default parse_ini_file() PHP function
        $parsed_ini = parse_ini_file($filepath, true);
        foreach($data as $section=>$values){
            //append the section 
            $content .= "[".$section."]\n"; 
            //append the values
            foreach($values as $key=>$value){
                $content .= $key."=".'"'.$value.'"'."\n"; 
            }
        }
        //write it into file
        if (!$handle = fopen($filepath, 'w')) { 
            return false; 
        }
        $success = fwrite($handle, $content);
        fclose($handle); 
        return $success; 
    }

    //REST - salvo INI
    function rest_salva_ini($_req){
        $result = [];
        $result['success'] = 'ok';
        //action
        $action = trim('' . $_REQUEST["action"]);
        //cataloghi_save_cfg
        if ($action == 'cataloghi_save_cfg') {
            $tipo = trim('' . $_REQUEST["tipo"]);
            $file = trim('' . $_REQUEST["file"]);
            $prodotti = trim('' . $_REQUEST["products"]);
            $sezioni = trim('' . $_REQUEST["sezioni"]);
            $sezioniNomi = trim('' . $_REQUEST["sezioniNomi"]);
            $sezioneCorrente = trim('' . $_REQUEST["sezioneCorrente"]);
            $config = parse_ini_file($file, true);
            $test = parse_ini_file($file, true);

            if ($tipo == 'nascondi') {
                $config['hide']['products'] = $prodotti;
                $config['hide']['sections'] = $sezioni;
                $result['dati'] = $config;
                update_ini_file($config, $file);
                $result['salvataggio'] = 'ok';
            }

            if ($tipo == 'salto_pagina') {
                //$config['newpage']['products'] = $prodotti;
                $config['newpage']['sections'] = $sezioni;
                $config['newpage']['sectionsNames'] = $sezioniNomi;
                $result['dati'] = $config;
                update_ini_file($config, $file);
                $result['salvataggio'] = 'ok';
                //$result['test'] = $test;
            }

            if ($tipo == 'salto_pagina_anteprima') {
                $config['newpage']['products'] = $prodotti;
                //$config['file'] = $file;
                $sezione = 'sez-' . $sezioneCorrente;
                $salti = array('salti' =>  $prodotti);
                $config[$sezione] = $salti;
                $elenco = '';
                $sezioni = explode("|", $config['newpage']['sections']);
                foreach( $sezioni as $sez ) {
                    $sezione = 'sez-' . $sez;
                    if ($config[$sezione]){
                        if ($config[$sezione]['salti']){
                            $s = trim ('' . $config[$sezione]['salti']);
                            if ($s != '') {
                                if ($elenco != '')
                                    $elenco = $elenco . '|';
                                $elenco = $elenco . $s;
                            }
                        }
                    }
                }
                $config['newpage']['products'] = $elenco;
                //$config['newpage']['sections'] = $sezioni;
                //$config['newpage']['sectionsNames'] = $sezioniNomi;
                $result['elenco'] = $elenco;
                $result['dati'] = $config;
                update_ini_file($config, $file);
                $result['salvataggio'] = 'ok';
            }

            if ($tipo == 'nuova_immagine') {
                $prod_id = trim('' . $_REQUEST["prod_id"]);
                $img_url = trim('' . $_REQUEST["img_url"]);
                $img_id = trim('' . $_REQUEST["img_id"]);
                //prodotto
                //$product = wc_get_product($prod_id);
                set_post_thumbnail($prod_id, $img_id);
            }
        }
        $_REQUEST["file"];
        echo json_encode( $result );
        //echo json_encode( $_REQUEST );
        //echo json_encode( $_req );
    }
?> 
