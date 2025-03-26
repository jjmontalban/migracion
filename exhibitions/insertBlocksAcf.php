<?php
namespace migration\exhibitions;

require_once __DIR__ . '/parseLayoutBlock.php';

/**
 * Inserta los bloques ACF en el post destino para una Exhibition.
 */
function insertBlocksIntoACF($post_id, $post_data, $metaData, $origin_conn, $orig_prefix) {
    // Nombre del flexible content en el origen y en el destino
    $flexField = 'ex_blocks';
    $metaData['__post_title'] = $post_data['post_title'];
    $captions = [];
    $blocks = [];

    // Bloque fijo: B038 - (exhibition)
    
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

    // Bloque fijo: B030 - Links (ex_links)
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

    // Guardar los bloques usando la API de ACF (campo'c3_blocks')
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

/**
 * Extrae las fechas de inicio y fin de la exposición.
 */
function getDateStartEnd($metaData, $post_id): array {
    $origDate = $metaData['ex_date'] ?? '';
    $cleanDate = strip_tags(trim($origDate));
    $cleanDate = preg_replace('/\s+/u', ' ', $cleanDate);
    $cleanDate = str_replace(['–', '—', '―', '−'], '-', $cleanDate); // Normaliza guiones largos

    $months = [
        'enero'=>1, 'febrero'=>2, 'marzo'=>3, 'abril'=>4, 'mayo'=>5, 'junio'=>6,
        'julio'=>7, 'agosto'=>8, 'septiembre'=>9, 'setiembre'=>9, 'octubre'=>10,
        'noviembre'=>11, 'diciembre'=>12,
        'ene'=>1, 'feb'=>2, 'mar'=>3, 'abr'=>4, 'may'=>5, 'jun'=>6,
        'jul'=>7, 'ago'=>8, 'sep'=>9, 'oct'=>10, 'nov'=>11, 'dic'=>12,
    ];

    $yearFromPost = (int) get_the_date('Y', $post_id);

    $patterns = [
        '/(\d{1,2})(?:\s+de)?\s+([a-zA-Záéíóúñ]+)\s+(\d{4})\s*-\s*(\d{1,2})(?:\s+de)?\s+([a-zA-Záéíóúñ]+)\s+(\d{4})/iu',
        '/(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+(?:de\s+)?(\d{4})\s+(?:[-–—]|al)\s+(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+(?:de\s+)?(\d{4})/i',
        '/(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+(?:[-–—]|al)\s+(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+(\d{4})/i',
        '/(\d{1,2})\s+([a-záéíóúñ]+)\s+(?:[-–—]|al)\s+(\d{1,2})\s+([a-záéíóúñ]+)\s+(\d{4})/i',
        '/(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+(?:[-–—]|al)\s+(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+(\d{4})/i',
        '/(\d{1,2})\s+([a-záéíóúñ]+)\s+(\d{4})\s+(?:[-–—]|al)\s+(\d{1,2})\s+([a-záéíóúñ]+)\s+(\d{4})/i',
        '/del\s+(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+al\s+(\d{1,2})\s+de\s+([a-záéíóúñ]+)/i',
        '/desde\s+el\s+(\d{1,2})\s+([a-záéíóúñ]+)\s+(?:al|-)\s+(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+(\d{4})/i',
        '/del\s+(\d{1,2})\s+([a-záéíóúñ]+)\s+(\d{4})\s+al\s+(\d{1,2})\s+([a-záéíóúñ]+)\s+(\d{4})/i',
        '/a\s+partir\s+del\s+(\d{1,2})\s+([a-záéíóúñ]+)\s+(\d{4})/i',
        '/([a-záéíóúñ]+)\s+de\s+(\d{4})/i',
    ];

    foreach ($patterns as $i => $pattern) {
        if (preg_match($pattern, $cleanDate, $m)) {
            $d1 = $m1 = $y1 = $d2 = $m2 = $y2 = null;

            switch ($i) {
                case 0:
                case 1:
                case 4:
                case 5:
                case 7:
                case 8:
                    [$d1, $m1, $y1, $d2, $m2, $y2] = array_pad(array_slice($m, 1), 6, null);
                    break;
                case 2:
                case 3:
                    [$d1, $m1, $d2, $m2, $y2] = array_slice($m, 1);
                    $y1 = $y2;
                    break;
                case 6:
                    [$d1, $m1, $d2, $m2] = array_slice($m, 1);
                    $y1 = $y2 = $yearFromPost;
                    break;
                case 9:
                    [$d1, $m1, $y1] = array_slice($m, 1);
                    $d2 = $d1;
                    $m2 = $m1;
                    $y2 = $y1;
                    break;
                case 10:
                    [$m1, $y1] = array_slice($m, 1);
                    $d1 = 1;
                    $m2 = $m1;
                    $y2 = $y1;
                    $d2 = null;
                    break;
            }

            // Normalizar meses
            $m1n = isset($m1) ? ($months[mb_strtolower($m1)] ?? 0) : 0;
            $m2n = isset($m2) ? ($months[mb_strtolower($m2)] ?? 0) : 0;

            // Convertir a enteros
            $d1 = isset($d1) ? (int) $d1 : 0;
            $y1 = isset($y1) ? (int) $y1 : 0;
            $d2 = isset($d2) ? (int) $d2 : 0;
            $y2 = isset($y2) ? (int) $y2 : 0;

            // Validar fechas
            $start = ($y1 && $m1n && $d1 && checkdate($m1n, $d1, $y1)) ? sprintf('%04d%02d%02d', $y1, $m1n, $d1) : '';
            $end   = ($y2 && $m2n && $d2 && checkdate($m2n, $d2, $y2)) ? sprintf('%04d%02d%02d', $y2, $m2n, $d2) : '';

            return ['start' => $start, 'end' => $end];
        }
    }

    return ['start' => '', 'end' => ''];
}
