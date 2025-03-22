'b37_title'       => $post_data['post_title'],

...


                $b37_title = $conn->real_escape_string($block['b37_title']);


                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b37_title', '$b37_title')
                ");










                // ---------------------------------------------
            // M06_especial => 'bloque_especial_fondo_verde'
            //    => cabecera + text-multiple-columns (subflex)
            // ---------------------------------------------
            elseif ($layoutName === 'bloque_especial_fondo_verde') {
                $titleKey   = "{$flexField}_{$i}_bloque_noticias_detalle_especial_titulo";
                $imgKey     = "{$flexField}_{$i}_bloque_noticias_detalle_especial_imagen";
                $subFlexKey = "{$flexField}_{$i}_bloque_cuerpo_fondo_verde";

                $titulo_especial = $metaData[$titleKey] ?? '';
                $imgVal = $metaData[$imgKey] ?? '';
                $subVal = $metaData[$subFlexKey] ?? '';

                // 1) Bloque Cabecera
                $block_data[] = [
                    'type'                => 'cabecera',
                    'b27_alignment'       => 'left',
                    'b27_title'           => $titulo_especial,
                    'b27_show_content_cta'=> '0',
                    'b27_content'         => '',
                    'b27_cta'             => ''
                ];
                writeLog("{$post_id}: -> Se crea bloque cabecera (M06_especial) con título=$titulo_especial");

                // Parse imagen
                $imgId = 0;
                if (is_numeric($imgVal)) {
                    $imgId = (int)$imgVal;
                } else {
                    $maybeArr = @unserialize($imgVal);
                    if (is_array($maybeArr) && !empty($maybeArr['id'])) {
                        $imgId = (int)$maybeArr['id'];
                    }
                }

                // 2) Sub-flex
                $subLayouts = @unserialize($subVal);
                if (is_array($subLayouts) && !empty($subLayouts)) {
                    $indexSub = 0;
                    foreach ($subLayouts as $j => $subLayoutName) {
                        writeLog("{$post_id}: -> Subfila $j => $subLayoutName (M06_especial)");
                        if ($subLayoutName === 'bloque_cuerpo_fondo_verde_texto') {
                            $textoKey = "{$flexField}_{$i}_bloque_cuerpo_fondo_verde_{$j}_texto";
                            $textoVal = $metaData[$textoKey] ?? '';

                            if ($indexSub === 0 && $imgId > 0) {
                                // Incrustar la imagen
                                $textoVal .= "\n<p><img src='[ID:$imgId]' alt='' /></p>";
                            }
                            $block_data[] = [
                                'type'        => 'text-multiple-columns',
                                'b29_columns' => '2',
                                'b29_content' => $textoVal
                            ];
                            writeLog("{$post_id}: -> Se agrega text-multiple-columns M06_subflex, con imagen en la 1ª fila?=$indexSub=0?");
                            $indexSub++;
                        }
                    }
                } else {
                    writeLog("{$post_id}: -> M06_especial sin subflex o subflex vacío. Key=$subFlexKey => $subVal");
                }
            }



            // -----------------------------------------------------------------
            case 'cabecera':
                $alignment = $conn->real_escape_string($block['b27_alignment']);
                $title     = $conn->real_escape_string($block['b27_title']);
                $show_cta  = $conn->real_escape_string($block['b27_show_content_cta']);
                $content   = $conn->real_escape_string($block['b27_content']);
                $cta       = $conn->real_escape_string($block['b27_cta']);
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_alignment', '$alignment')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_title', '$title')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_show_content_cta', '$show_cta')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_content', '$content')
                ");
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_b27_cta', '$cta')
                ");
                break;