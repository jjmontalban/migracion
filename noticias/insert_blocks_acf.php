<?php

require_once __DIR__ . '/parse_layout_block.php';

/**
 * Inserta los bloques ACF (campo flexible c4_blocks) en el post destino.
 */
function insertBlocksIntoACF($post_id, $post_data, $metaData, $conn, $dest_prefix) {
    $flexField = 'noticia_cuerpo';
    $metaData['__post_title'] = $post_data['post_title'];
    // 1) Borrar bloques anteriores
    $conn->query("DELETE FROM {$dest_prefix}postmeta WHERE post_id = $post_id AND meta_key LIKE 'c4_blocks%'");

    $blocks = [];
    $captions = [];

    // 2) Bloques fijos (hero, intro, featured image, excerpt)
    $blocks[] = [
        'type' => 'hero-text',
        'b12_date' => date('d.m.Y', strtotime($post_data['post_date'])),
        'b12_title' => $post_data['post_title']
    ];

    if (!empty($metaData['noticia_texto_introduccion'])) {
        $blocks[] = [
            'type'        => 'text-multiple-columns',
            'b29_columns' => '1',
            'b29_content' => $metaData['noticia_texto_introduccion']
        ];
    }

    if (!empty($metaData['_thumbnail_id'])) {
        $blocks[] = [
            'type'      => 'image',
            'b20_title' => $post_data['post_title'],
            'b20_image' => $metaData['_thumbnail_id']
        ];
    }

    if (!empty($post_data['post_excerpt'])) {
        $blocks[] = [
            'type'        => 'text-multiple-columns',
            'b29_columns' => '1',
            'b29_content' => $post_data['post_excerpt']
        ];
    }

    // 3) Layouts flexibles
    $layouts = @unserialize($metaData[$flexField] ?? '') ?: [];
    foreach ($layouts as $i => $layout) {
        $result = parseLayoutBlock($layout, $i, $metaData, $flexField, $captions);
        if ($result) {
            // Si es un array de varios bloques
            if (is_array($result) && isset($result[0])) {
                foreach ($result as $block) {
                    if ($block) $blocks[] = $block;
                }
            } else {
                $blocks[] = $result;
            }
        }
    }

    // 4) Guardar c4_blocks
    $layoutKeys = array_column($blocks, 'type');
    $serialized = serialize($layoutKeys);

    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, 'c4_blocks', '" . $conn->real_escape_string($serialized) . "')
    ");
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, '_c4_blocks', 'field_67b4aa33a6101')
    ");

    // 5) Guardar cada bloque
    foreach ($blocks as $index => $block) {
        $type = $conn->real_escape_string($block['type']);
        $conn->query("
            INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
            VALUES ($post_id, 'c4_blocks_{$index}', '$type')
        ");

        foreach ($block as $key => $value) {
            if ($key === 'type') continue;

            if (is_array($value)) {
                // Repetidor
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_{$key}', '" . count($value) . "')
                ");
                foreach ($value as $r => $row) {
                    foreach ($row as $subk => $subval) {
                        $subval = $conn->real_escape_string((string)$subval);
                        $conn->query("
                            INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                            VALUES ($post_id, 'c4_blocks_{$index}_{$key}_{$r}_{$subk}', '$subval')
                        ");
                    }
                }
            } else {
                $val = $conn->real_escape_string((string)$value);
                $conn->query("
                    INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
                    VALUES ($post_id, 'c4_blocks_{$index}_{$key}', '$val')
                ");
            }
        }
    }

    // 6) Actualizar leyendas de la galería
    foreach ($captions as $c) {
        $img_id  = (int)$c['id'];
        $caption = $conn->real_escape_string((string)$c['caption']);
        if ($img_id > 0 && !empty($caption)) {
            $conn->query("
                UPDATE {$dest_prefix}posts
                SET post_excerpt = '$caption'
                WHERE ID = $img_id
            ");
        }
    }

}
