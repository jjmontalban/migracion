<?php

/**
 * map_blocks_news.php
 *
 * Inserta TODOS los bloques en el flexible "c4_blocks" de la misma forma,
 * sin meter references de layout key ni subcampos _field_xxx en los que antes daban problemas.
 */

function writeLog($message) {
    $logFile = __DIR__ . "/migracion.log";
    file_put_contents($logFile, date("Y-m-d H:i:s") . " - " . $message . "\n", FILE_APPEND);
}

/**
 * Función principal para insertar c4_blocks:
 * - Bloques "fijos" (hero-text, etc.)
 * - Bloques del flexible "noticia_cuerpo" (M01_texto, M02_imagen, etc.)
 * Se guardan TODOS sin references layout_XXX ni _field_XXX.
 */
function insertBlocksIntoACF($post_id, $post_data, $metaData, $conn, $dest_prefix) {

    // 1) Borrar metadatos previos c4_blocks en destino
    $conn->query("DELETE FROM {$dest_prefix}postmeta
                  WHERE post_id = $post_id
                    AND meta_key LIKE 'c4_blocks%'");

    // 2) Array con todos los bloques
    $block_data = [];

    // (A) Bloques fijos
    $block_data[] = [
        'type'      => 'hero-text',
        'b12_date'  => date('d.m.Y', strtotime($post_data['post_date'])),
        'b12_title' => $post_data['post_title']
    ];

    // text-multiple(1) => introducción
    $intro = $metaData['noticia_texto_introduccion'] ?? '';
    if (!empty($intro)) {
        $block_data[] = [
            'type'        => 'text-multiple-columns',
            'b29_columns' => '1',
            'b29_content' => $intro
        ];
    }

    // imagen destacada
    $featured = $metaData['_thumbnail_id'] ?? '';
    if (!empty($featured)) {
        $block_data[] = [
            'type'      => 'image',
            'b20_title' => $post_data['post_title'],
            'b20_image' => $featured
        ];
    }

    // excerpt => text-multiple(1)
    $excerpt = $post_data['post_excerpt'] ?? '';
    if (!empty($excerpt)) {
        $block_data[] = [
            'type'        => 'text-multiple-columns',
            'b29_columns' => '1',
            'b29_content' => $excerpt
        ];
    }

    // (B) Leer el flexible "noticia_cuerpo" (layouts M01, M02, etc.)
    $flexField = 'noticia_cuerpo';
    $layouts = @unserialize($metaData[$flexField] ?? '') ?: [];
    if (!empty($layouts)) {
        foreach ($layouts as $i => $layout) {

            switch ($layout) {

                // M01_texto => text-multiple-columns(2)
                case 'noticia_bloque_texto':
                    $ck = "{$flexField}_{$i}_noticias_texto";
                    $cv = $metaData[$ck] ?? '';
                    if (!empty($cv)) {
                        $block_data[] = [
                            'type'        => 'text-multiple-columns',
                            'b29_columns' => '2',
                            'b29_content' => $cv
                        ];
                    }
                    break;

                // M02_imagen => image
                case 'noticia_bloque_imagen':
                    $imgK = "{$flexField}_{$i}_noticia_imagen";
                    $pieK = "{$flexField}_{$i}_noticia_imagen_pie";
                    $imgVal= $metaData[$imgK] ?? '';
                    $pieVal= $metaData[$pieK] ?? '';
                    $imgId = 0;
                    if (is_numeric($imgVal)) {
                        $imgId = (int)$imgVal;
                    } else {
                        $arr = @unserialize($imgVal);
                        if (is_array($arr) && isset($arr['id'])) {
                            $imgId = (int)$arr['id'];
                        }
                    }
                    if ($imgId > 0) {
                        $block_data[] = [
                            'type'      => 'image',
                            'b20_title' => $pieVal,
                            'b20_image' => $imgId
                        ];
                    }
                    break;

                // M03_video => video(grid)
                case 'noticia_bloque_video':
                    $k = "{$flexField}_{$i}_bloque_noticias_detalle_video";
                    $v= $metaData[$k] ?? '';
                    if (!empty($v)) {
                        $block_data[] = [
                            'type'       => 'video',
                            'b37_design' => 'grid',
                            'b37_url'    => $v
                        ];
                    }
                    break;

                // M04_scripts => iframe
                case 'bloque_scripts':
                    $stK = "{$flexField}_{$i}_bq_elige_script";
                    $idK = "{$flexField}_{$i}_bq_id";
                    $altK= "{$flexField}_{$i}_bq_alt";
                    $typK= "{$flexField}_{$i}_bq_tipo";

                    $b31_script = $metaData[$stK] ?? '';
                    $b31_id     = $metaData[$idK] ?? '';
                    $b31_alt    = $metaData[$altK] ?? '';
                    $b31_type   = $metaData[$typK] ?? '';

                    $block_data[] = [
                        'type'       => 'iframe',
                        'b31_script' => $b31_script,
                        'b31_id'     => $b31_id,
                        'b31_alt'    => $b31_alt,
                        'b31_type'   => $b31_type
                    ];
                    break;

                // M05_cita => slider-testimonios
                case 'noticia_bloque_cita':
                    $tKey= "{$flexField}_{$i}_noticia_cita_texto";
                    $aKey= "{$flexField}_{$i}_noticia_cita_autor";
                    $rKey= "{$flexField}_{$i}_noticia_cita_autor_rol";
                    $iKey= "{$flexField}_{$i}_noticia_cita_autor_imagen";

                    $qVal= $metaData[$tKey] ?? '';
                    $nVal= $metaData[$aKey] ?? '';
                    $jVal= $metaData[$rKey] ?? '';
                    $imV= $metaData[$iKey] ?? '';
                    $imID=0;
                    if (is_numeric($imV)) {
                        $imID = (int)$imV;
                    } else {
                        $arr= @unserialize($imV);
                        if (is_array($arr) && isset($arr['id'])) {
                            $imID= (int)$arr['id'];
                        }
                    }
                    writeLog("[LOG] M05 Testimonios => autor='$nVal', quote='$qVal'");
                    $block_data[] = [
                        'type'      => 'image-text-slider',
                        'b25_title' => ' ', 
                        'b25_items' => [[
                            'b25i_quote' => $qVal,
                            'b25i_name'  => $nVal,
                            'b25i_job'   => $jVal,
                            'b25i_image' => $imID
                        ]]
                    ];
                    break;

                // M06_especial => cabecera + multiples text-multiple
                case 'bloque_especial_fondo_verde':
                    $titleK= "{$flexField}_{$i}_bloque_noticias_detalle_especial_titulo";
                    $imgK  = "{$flexField}_{$i}_bloque_noticias_detalle_especial_imagen";
                    $subFK= "{$flexField}_{$i}_bloque_cuerpo_fondo_verde";

                    $titulo= $metaData[$titleK] ?? '';
                    $imgVal= $metaData[$imgK]   ?? '';
                    writeLog("[LOG] M06 Cabecera => titulo='$titulo'");

                    // Bloque cabecera (mismo estilo que iframe)
                    $block_data[] = [
                        'type'                => 'heading',
                        'b27_alignment'       => 'left',
                        'b27_title'           => $titulo,
                        'b27_show_content_cta'=> '0',
                        'b27_content'         => '',
                        'b27_cta'             => ''
                    ];

                    // parse imagen
                    $imgId=0;
                    if (is_numeric($imgVal)) {
                        $imgId = (int)$imgVal;
                    } else {
                        $ar= @unserialize($imgVal);
                        if (is_array($ar) && isset($ar['id'])) {
                            $imgId= (int)$ar['id'];
                        }
                    }

                    // Sub-flex => text-multiple(2)
                    $subVal= $metaData[$subFK] ?? '';
                    $subArr= @unserialize($subVal) ?: [];
                    $countSub=0;
                    foreach ($subArr as $jj => $subLayout) {
                        if ($subLayout==='bloque_cuerpo_fondo_verde_texto') {
                            $tk="{$flexField}_{$i}_bloque_cuerpo_fondo_verde_{$jj}_texto";
                            $tv= $metaData[$tk] ?? '';
                            if($countSub===0 && $imgId>0) {
                                $tv.="\n<p><img src='[ID:$imgId]' alt='' /></p>";
                            }
                            if(!empty($tv)) {
                                $block_data[]=[
                                    'type'=>'text-multiple-columns',
                                    'b29_columns'=>'2',
                                    'b29_content'=>$tv
                                ];
                            }
                            $countSub++;
                        }
                    }
                    break;

                // M07_galeria => slider-galeria
                case 'bloque_para_slider':
                    $rk="{$flexField}_{$i}_bloque_noticias_detalle_slider_imagenes";
                    $rc=(int)($metaData[$rk]??0);
                    writeLog("[LOG] M07 Slider-galeria => repCount=$rc");
                    if($rc>0) {
                        $imgs=[];
                        for($r=0; $r<$rc; $r++){
                            $ik="{$flexField}_{$i}_bloque_noticias_detalle_slider_imagenes_{$r}_bloque_noticias_detalle_imagen";
                            $iv=$metaData[$ik]??'';
                            $imgID=0;
                            if(is_numeric($iv)) {
                                $imgID=(int)$iv;
                            } else {
                                $a=@unserialize($iv);
                                if(is_array($a)&&isset($a['id'])){
                                    $imgID=(int)$a['id'];
                                }
                            }
                            if($imgID>0){
                                $imgs[]=['b24i_image'=>$imgID];
                            }
                        }
                        if(!empty($imgs)){
                            $block_data[]=[
                                'type'=>'gallery-slider',
                                'b24_image_width'=>'sameWidth',
                                'b24_images'=>$imgs
                            ];
                        }
                    }
                    break;
            } // switch($layout)
        } // foreach layouts
    } // if layouts

    // 4) Insert c4_blocks => array de 'type'
    $types= array_column($block_data,'type');
    $ser  = serialize($types);

    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, 'c4_blocks', '" . $conn->real_escape_string($ser) . "')
    ");
    // Field key del flexible principal
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, '_c4_blocks', 'field_67b4aa33a6101')
    ");

    // 5) Insertar cada bloque sin references
    foreach($block_data as $index=>$block) {
        $type = $block['type'];
        // c4_blocks_{index} => 'iframe', 'cabecera', 'slider-galeria', etc.
        $conn->query("
            INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
            VALUES ($post_id, 'c4_blocks_{$index}', '{$type}')
        ");

        switch($type){
            // hero-text
            case 'hero-text':
                $date=$conn->real_escape_string($block['b12_date']);
                $tit= $conn->real_escape_string($block['b12_title']);
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b12_date', '$date')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b12_title', '$tit')
                ");
                break;

            // text-multiple-columns
            case 'text-multiple-columns':
                $cols=$conn->real_escape_string($block['b29_columns']);
                $txt =$conn->real_escape_string($block['b29_content']);
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b29_columns', '$cols')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b29_content', '$txt')
                ");
                break;

            // image
            case 'image':
                $pie=$conn->real_escape_string($block['b20_title']);
                $im =$conn->real_escape_string($block['b20_image']);
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b20_title', '$pie')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b20_image', '$im')
                ");
                break;

            // video
            case 'video':
                $u= $conn->real_escape_string($block['b37_url']);
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b37_design', 'grid')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b37_url', '$u')
                ");
                break;

            // iframe
            case 'iframe':
                $sc=$conn->real_escape_string($block['b31_script']);
                $id=$conn->real_escape_string($block['b31_id']);
                $al=$conn->real_escape_string($block['b31_alt']);
                $tp=$conn->real_escape_string($block['b31_type']);
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b31_script', '$sc')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b31_id', '$id')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b31_alt', '$al')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b31_type', '$tp')
                ");
                break;

            // slider-testimonios
            case 'slider-testimonios':
                writeLog("[LOG] Insert slider-testimonios block => index=$index");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b25_title', '" . $conn->real_escape_string($block['b25_title']) . "')
                ");
                $items=$block['b25_items'];
                $nItems=count($items);
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b25_items', '$nItems')
                ");

                foreach($items as $r=>$it){
                    $q = $conn->real_escape_string($it['b25i_quote']);
                    $n = $conn->real_escape_string($it['b25i_name']);
                    $j = $conn->real_escape_string($it['b25i_job']);
                    $m = $conn->real_escape_string($it['b25i_image']);

                    $conn->query("
                        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                        VALUES ($post_id, 'c4_blocks_{$index}_b25_items_{$r}_b25i_quote', '$q')
                    ");
                    $conn->query("
                        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                        VALUES ($post_id, 'c4_blocks_{$index}_b25_items_{$r}_b25i_name', '$n')
                    ");
                    $conn->query("
                        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                        VALUES ($post_id, 'c4_blocks_{$index}_b25_items_{$r}_b25i_job', '$j')
                    ");
                    $conn->query("
                        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                        VALUES ($post_id, 'c4_blocks_{$index}_b25_items_{$r}_b25i_image', '$m')
                    ");
                }
                break;

            // cabecera
            case 'cabecera':
                writeLog("[LOG] Insert cabecera => index=$index, title=".$block['b27_title']);
                $align=$conn->real_escape_string($block['b27_alignment']);
                $tit  =$conn->real_escape_string($block['b27_title']);
                $show =$block['b27_show_content_cta']?'1':'0';
                $cont =$conn->real_escape_string($block['b27_content']);
                $cta  =$conn->real_escape_string($block['b27_cta']);

                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_alignment', '$align')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_title', '$tit')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_show_content_cta', '$show')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_content', '$cont')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_cta', '$cta')
                ");
                break;

            // slider-galeria
            case 'slider-galeria':
                writeLog("[LOG] Insert slider-galeria => index=$index");
                $width=$conn->real_escape_string($block['b24_image_width']);
                $ims  =$block['b24_images'];
                $nIms =count($ims);

                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b24_image_width', '$width')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b24_images', '$nIms')
                ");

                foreach($ims as $r=>$rw){
                    $imgid=$conn->real_escape_string($rw['b24i_image']);
                    $conn->query("
                        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                        VALUES ($post_id, 'c4_blocks_{$index}_b24_images_{$r}_b24i_image', '$imgid')
                    ");
                }
                break;
        } // switch
    } // foreach block_data

    writeLog("Migración c4_blocks finalizada para post $post_id => ".count($block_data)." bloques");
}
