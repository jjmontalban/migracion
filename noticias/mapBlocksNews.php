<?php


/**
 * Funciones para construir los bloques ACF a partir de los layouts originales de noticias.
 * Cada función retorna un array con la estructura base usando la clave "type", la cual se transformará
 * a "acf_fc_layout" en parseLayoutBlock().
 */

/**
 * Extrae el ID de una imagen
 */
function extractImageId($value) {
    if (is_numeric($value)) {
        return (int)$value;
    }
    $arr = @unserialize($value);
    if (is_array($arr) && isset($arr['id'])) {
        return (int)$arr['id'];
    }
    return 0;
}

/**
 * Bloque: text-multiple-columns (2 columnas)
 */
function buildTextMultipleColumns($i, $metaData, $flexField) {
    $key = "{$flexField}_{$i}_noticias_texto";
    $content = $metaData[$key] ?? '';

    if (empty($content)) {
        return null;
    }

    // Si encuentra un <a> con clase "btn-buy btn_green" las reemplaza por
    // "bta bta--light bta-icon bta-icon--right bta-icon--right--arrow-right".
    $pattern = '/<a([^>]*)class="(?:btn-buy\s+btn_green|btn_green\s+btn-buy)"([^>]*)>/i';
    $replacement = '<a$1class="bta bta--light bta-icon bta-icon--right bta-icon--right--arrow-right"$2>';
    $content = preg_replace($pattern, $replacement, $content);

    return [
        'type'        => 'text-multiple-columns',
        'b29_columns' => '2',
        'b29_content' => $content
    ];
}

/**
 * Bloque: image
 */
function buildImageBlock($i, $metaData, $flexField, $post_id, $origin_conn, $orig_prefix) {
    $imgKey     = "{$flexField}_{$i}_noticia_imagen";
    $captionKey = "{$flexField}_{$i}_noticia_imagen_pie";
    $old_imgId  = extractImageId($metaData[$imgKey] ?? '');
    $caption    = $metaData[$captionKey] ?? '';
    if ($old_imgId <= 0) {
        return null;
    }
    $old_image_url = get_old_image_url($old_imgId, $origin_conn, $orig_prefix);
    if (!$old_image_url) {
        return null;
    }
    $new_img_id = migrate_image($old_image_url, $post_id);
    if (!$new_img_id) {
        return null;
    }
    return [
        'type'      => 'image',
        'b20_title' => $caption,  // Se usará para Título y Leyenda
        'b20_image' => $new_img_id
    ];
}

/**
 * Bloque: video
 */
function buildVideoBlock($i, $metaData, $flexField) {
    $key = "{$flexField}_{$i}_bloque_noticias_detalle_video";
    $url = $metaData[$key] ?? '';
    if (empty($url)) {
        return null;
    }

    return [
        'type'       => 'video',
        'b37_design' => 'grid',
        'b37_url'    => $url,
        'b37_title'  => $metaData['__post_title']
    ];
}

/**
 * Bloque: iframe
 */
function buildIframeBlock($i, $metaData, $flexField) {
    $scriptKey = "{$flexField}_{$i}_bq_elige_script";
    $idKey     = "{$flexField}_{$i}_bq_id";
    $altKey    = "{$flexField}_{$i}_bq_alt";
    $typeKey   = "{$flexField}_{$i}_bq_tipo";

    $script = $metaData[$scriptKey] ?? '';
    $id     = $metaData[$idKey] ?? '';
    $alt    = $metaData[$altKey] ?? '';
    $type   = $metaData[$typeKey] ?? '';

    if (empty($script) && empty($id)) {
        return null;
    }

    return [
        'type'       => 'iframe',
        'b31_script' => $script,
        'b31_id'     => $id,
        'b31_alt'    => $alt,
        'b31_type'   => $type
    ];
}

/**
 * Bloque: image-text-slider (testimonios)
*/
function buildImageTextSlider($i, $metaData, $flexField, $post_id, $origin_conn, $orig_prefix) {
    $quoteKey = "{$flexField}_{$i}_noticia_cita_texto";
    $nameKey  = "{$flexField}_{$i}_noticia_cita_autor";
    $jobKey   = "{$flexField}_{$i}_noticia_cita_autor_rol";
    $imgKey   = "{$flexField}_{$i}_noticia_cita_autor_imagen";
    $quote = $metaData[$quoteKey] ?? '';
    $name  = $metaData[$nameKey] ?? '';
    $job   = $metaData[$jobKey] ?? '';
    $old_imgId = extractImageId($metaData[$imgKey] ?? '');
    $new_img_id = 0;
    if($old_imgId > 0) {
         $old_image_url = get_old_image_url($old_imgId, $origin_conn, $orig_prefix);
         if($old_image_url) {
             $new_img_id = migrate_image($old_image_url, $post_id);
         }
    }
    if (empty($quote) && empty($name)) {
        return null;
    }
    return [
        'type'      => 'image-text-slider',
        'b25_title' => ' ',
        'b25_items' => [[
            'b25i_quote' => $quote,
            'b25i_name'  => $name,
            'b25i_job'   => $job,
            'b25i_image' => $new_img_id
        ]]
    ];
}


/**
 * Bloque especial: heading + N x text-multiple-columns
 */
function buildHeadingTextMultipleColumns($i, $metaData, $flexField, $post_id, $origin_conn, $orig_prefix) {
    $titleKey = "{$flexField}_{$i}_bloque_noticias_detalle_especial_titulo";
    $imageKey = "{$flexField}_{$i}_bloque_noticias_detalle_especial_imagen";
    $subField = "{$flexField}_{$i}_bloque_cuerpo_fondo_verde";
    $title   = $metaData[$titleKey] ?? '';
    $old_imgId  = extractImageId($metaData[$imageKey] ?? '');
    $new_img_id = 0;
    if($old_imgId > 0) {
         $old_image_url = get_old_image_url($old_imgId, $origin_conn, $orig_prefix);
         if($old_image_url) {
             $new_img_id = migrate_image($old_image_url, $post_id);
         }
    }
    $subArr  = maybe_unserialize($metaData[$subField] ?? '') ?: [];
    $textBlocks = [];
    foreach ($subArr as $j => $layoutName) {
        if ($layoutName === 'bloque_cuerpo_fondo_verde_texto') {
            $txtKey = "{$subField}_{$j}_texto";
            $txtVal = $metaData[$txtKey] ?? '';
            if (!empty($txtVal)) {
                $textBlocks[] = $txtVal;
            }
        }
    }
    if (empty($textBlocks)) {
        return null;
    }
    $blocks = [[
        'type'                => 'heading',
        'b27_alignment'       => 'left',
        'b27_title'           => $title,
        'b27_show_content_cta'=> '0',
        'b27_content'         => '',
        'b27_cta'             => ''
    ]];
    foreach ($textBlocks as $idx => $text) {
        if ($idx === 0 && $new_img_id > 0) {
            $text .= "\n<p><img src='[ID:$new_img_id]' alt='' /></p>";
        }
        $blocks[] = [
            'type'        => 'text-multiple-columns',
            'b29_columns' => '2',
            'b29_content' => $text
        ];
    }
    return $blocks;
}
/**
 * Bloque galería: gallery-slider
 */
function buildGallerySlider($i, $metaData, $flexField, $post_id, $origin_conn, $orig_prefix) {
    $repKey = "{$flexField}_{$i}_bloque_noticias_detalle_slider_imagenes";
    $count  = (int)($metaData[$repKey] ?? 0);
    if ($count <= 0) {
        return null;
    }

    $items = [];
    $captions = [];

    for ($r = 0; $r < $count; $r++) {
        $imgKey  = "{$repKey}_{$r}_bloque_noticias_detalle_imagen";
        $pieKey  = "{$repKey}_{$r}_bloque_noticias_detalle_pie";
        $old_imgId = extractImageId($metaData[$imgKey] ?? '');
        $pieVal  = $metaData[$pieKey] ?? '';

        if ($old_imgId > 0) {
            // Aquí se usan $origin_conn y $orig_prefix en el orden correcto:
            $old_image_url = get_old_image_url($old_imgId, $origin_conn, $orig_prefix);
            if (!$old_image_url) {
                continue;
            }
            $new_img_id = migrate_image($old_image_url, $post_id);
            if (!$new_img_id) {
                continue;
            }
            $items[] = [ 'b24i_image' => $new_img_id ];
            if (!empty($pieVal)) {
                $captions[] = [
                    'id'      => $new_img_id,
                    'caption' => $pieVal
                ];
            }
        }
    }

    if (empty($items)) {
        return null;
    }

    return [
        'acf_block' => [
            'type'            => 'gallery-slider',
            'b24_image_width' => 'sameWidth',
            'b24_images'      => $items
        ],
        'captions' => $captions
    ];
}



