<?php
namespace migration\exhibitions;

require_once __DIR__ . '/parseLayoutBlock.php';

/**
 * Inserta los bloques ACF en el post destino para una Exhibition.
 */
function insertBlocksIntoACF($post_id, $post_data, $metaData, $origin_conn, $orig_prefix) {
    // Nombre del flexible content en el origen y en el destino
    $flexField = 'ex_blocks';
    //$destinationFlexField = 'c3_blocks';
    $metaData['__post_title'] = $post_data['post_title'];

    $captions = [];
    $blocks = [];


    // Bloque fijo: B038 - Exposición (siempre en la posición inicial)
    // fechas de inicio y fin
    $fechas = getDateStartEnd($metaData, $post_id);

    $blocks[] = [
        'acf_fc_layout'    => 'exhibition',
        'b38_title'        => $post_data['post_title'],         // Título de la exposición
        'b38_place'        => $metaData['ex_place'] ?? '',        // Lugar de la exposición
        'b38_date_start'   => $fechas['start'],
        'b38_date_end'     => $fechas['end'],
        'b38_description'  => $metaData['ex_date'] ?? '',         // Se usa la fecha como contenido
        'b38_image'        => $metaData['_thumbnail_id'] ?? '',   // Imagen destacada migrada
    ];

// Bloque fijo: B030 - Links (por cada ítem del repeater ex_links)
$linksCount = (int)($metaData['ex_links'] ?? 0);
if ($linksCount > 0) {
    // Equivalencia de iconos
    $iconMapping = [
        'icon-download' => 'download',
        'icon-buy'      => 'goto'
    ];
    // Recorre cada fila del repeater ex_links
    for ($i = 0; $i < $linksCount; $i++) {
        // Extrae el tipo e interpreta el icono
        $typeVal = $metaData["ex_links_{$i}_ex_l_type"] ?? '';
        $icon    = $iconMapping[$typeVal] ?? '';

        // Extrae el título
        $title   = $metaData["ex_links_{$i}_ex_l_title"] ?? '';

        // Repetidor interno ex_l_links
        $subLinksCount = (int)($metaData["ex_links_{$i}_ex_l_links"] ?? 0);
        $links_array   = [];
        for ($j = 0; $j < $subLinksCount; $j++) {
            // Cada subfila de ex_l_links
            // ex_links_0_ex_l_links_0_ex_l_link => array con 'title', 'url', 'target'
            $subLink = $metaData["ex_links_{$i}_ex_l_links_{$j}_ex_l_link"] ?? null;
            if (!empty($subLink) && is_array($subLink)) {
                // $subLink['title'], $subLink['url'], $subLink['target']
                $links_array[] = [
                    'b30i_link' => $subLink,
                    'b30i_icon' => $icon
                ];
            }
        }

        // Crear el bloque B030 - Links para este item
        $blocks[] = [
            'acf_fc_layout' => 'links', // layout "links" en tu grupo destino
            'b30_title'     => $title,
            'b30_content'   => ' ', // en blanco
            'b30_links'     => $links_array
        ];
    }
}



    // Procesar bloques flexibles definidos en el campo origen 'ex_blocks'
    $layouts = maybe_unserialize($metaData[$flexField] ?? []) ?: [];
    foreach ($layouts as $i => $layout) {
        $captions = []; // TODO Para capturar leyendas de imagen, si las hubiera
        $result = parseLayoutBlock($layout, $i, $metaData, $flexField, $captions, $post_id, $origin_conn, $orig_prefix);
        if ($result) {
            if (isset($result[0])) {
                foreach ($result as $block) {
                    if ($block) {
                        $blocks[] = $block;
                    }
                }
            } else {
                $blocks[] = $result;
            }
        }
    }

    // Guardar los bloques usando la API de ACF (campo'c4_blocks')
    update_field('c3_blocks', $blocks, $post_id);

    // leyendas de imágenes (captions)
    if (!empty($captions) && is_array($captions)) {
        foreach ($captions as $c) {
            $img_id  = (int)$c['id'];
            $caption = sanitize_text_field($c['caption']);
            if ($img_id > 0 && !empty($caption)) {
                wp_update_post([
                    'ID'           => $img_id,
                    'post_excerpt' => $caption
                ]);
            }
        }
    }
}