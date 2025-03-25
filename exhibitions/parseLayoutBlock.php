<?php
namespace migration\exhibitions;

require_once __DIR__ . '/mapBlocksExhibitions.php';

/**
 * Convierte un layout ACF del origen en uno o varios bloques ACF destino para Exhibitions.
 *
 */
function parseLayoutBlock($layout, $i, $metaData, $flexField, &$captions, $post_id, $origin_conn, $orig_prefix) {
    switch ($layout) {
        case 'ex_b_text':
            $block = buildTextMultipleColumns($i, $metaData, $flexField);
            break;
        case 'ex_b_image':
            $block = buildImageBlock($i, $metaData, $flexField, $post_id, $origin_conn, $orig_prefix);
            break;
        case 'ex_b_video':
            $block = buildVideoBlock($i, $metaData, $flexField);
            break;
        case 'ex_b_iframe':
            $block = buildIframeBlock($i, $metaData, $flexField);
            break;
        case 'ex_b_carrousel':
            $result = buildGallerySlider($i, $metaData, $flexField, $post_id, $origin_conn, $orig_prefix);
            if ($result && isset($result['acf_block'])) {
                $captions = array_merge($captions, $result['captions']);
                $block = $result['acf_block'];
            } else {
                $block = null;
            }
            break;
        default:
            $block = null;
            break;
    }
    if (!$block) {
        return null;
    }
    // Asegurarse de que cada bloque tenga la clave 'acf_fc_layout'
    if (isset($block[0])) {
        foreach ($block as &$b) {
            $b['acf_fc_layout'] = $b['type'];
            unset($b['type']);
        }
    } else {
        $block['acf_fc_layout'] = $block['type'];
        unset($block['type']);
    }
    return $block;
}
