<?php
namespace migration\exhibitions;

/**
 * Funciones para construir los bloques ACF a partir de los layouts originales de Exhibiciones.
 * Cada función retorna un array con la estructura base usando la clave "type", que luego se transforma
 * en "acf_fc_layout" en el parseo de bloques.
 */

/**
 * Extrae el ID de una imagen a partir del valor recibido.
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
 * Bloque: Texto varias columnas (layout ex_b_text)
 */
function buildTextMultipleColumns($i, $metaData, $flexField) {
    // Se usa el nombre del subcampo: ex_bt_text
    $key = "{$flexField}_{$i}_ex_bt_text";
    $content = $metaData[$key] ?? '';
    if (empty($content)) {
        return null;
    }
    return [
        'type'        => 'text-multiple-columns',
        'b29_columns' => '2',
        'b29_content' => $content
    ];
}

/**
 * Bloque: Imagen (layout ex_b_image)
 */
function buildImageBlock($i, $metaData, $flexField, $post_id, $origin_conn, $orig_prefix) {
    // Se usan los nombres: ex_bi_image y ex_bi_footer
    $imgKey = "{$flexField}_{$i}_ex_bi_image";
    $captionKey = "{$flexField}_{$i}_ex_bi_footer";
    $old_imgId = extractImageId($metaData[$imgKey] ?? '');
    $caption = $metaData[$captionKey] ?? '';
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
        'b20_title' => $caption,
        'b20_image' => $new_img_id
    ];
}

/**
 * Bloque: Video (layout ex_b_video)
 */
function buildVideoBlock($i, $metaData, $flexField) {
    // Se usa el subcampo: ex_bv_video
    $key = "{$flexField}_{$i}_ex_bv_video";
    $url = $metaData[$key] ?? '';
    if (empty($url)) {
        return null;
    }
    return [
        'type'       => 'video',
        'b37_design' => 'grid',
        'b37_url'    => $url,
        'b37_title'  => $metaData['post_title'] ?? ''
    ];
}

/**
 * Bloque: Iframe (layout ex_b_iframe)
 */
function buildIframeBlock($i, $metaData, $flexField) {
    // Se asumen los subcampos: ex_bif_script, ex_bif_id, ex_bif_alt, ex_bif_type
    $scriptKey = "{$flexField}_{$i}_ex_bif_script";
    $idKey     = "{$flexField}_{$i}_ex_bif_id";
    $altKey    = "{$flexField}_{$i}_ex_bif_alt";
    $typeKey   = "{$flexField}_{$i}_ex_bif_type";
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
 * Bloque: Galería/Carrousel (layout ex_b_carrousel)
 */
function buildGallerySlider($i, $metaData, $flexField, $post_id, $origin_conn, $orig_prefix) {
    // Se usa el repeater con el nombre: ex_bc_images
    $repKey = "{$flexField}_{$i}_ex_bc_images";
    $rows = maybe_unserialize($metaData[$repKey] ?? []);
    if (empty($rows) || !is_array($rows)) {
         return null;
    }
    $count = count($rows);
    $items = [];
    $captions = [];
    for ($r = 0; $r < $count; $r++) {
         // Se esperan los subcampos: ex_bc_image y ex_bc_footer
         $imgKey = "{$repKey}_{$r}_ex_bc_image";
         $pieKey = "{$repKey}_{$r}_ex_bc_footer";
         $old_imgId = extractImageId($metaData[$imgKey] ?? '');
         $pieVal = $metaData[$pieKey] ?? '';
         if ($old_imgId > 0) {
             $old_image_url = \migration\exhibitions\get_old_image_url($old_imgId, $origin_conn, $orig_prefix);
             if (!$old_image_url) {
                 continue;
             }
             $new_img_id = \migration\exhibitions\migrate_image($old_image_url, $post_id);
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
