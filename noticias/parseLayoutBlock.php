<?php

require_once __DIR__ . '/mapBlocksNews.php';

/**
 * Convierte un layout ACF del origen en uno o varios bloques ACF destino (estructura adaptada para ACF).
 *
 * @param string  $layout
 * @param int     $i
 * @param array   $metaData
 * @param string  $flexField
 * @param array  &$captions  (referencia) para leyendas de imágenes
 * @return array|array[]|null
 */
function parseLayoutBlock($layout, $i, $metaData, $flexField, &$captions = []) {
    switch ($layout) {
        case 'noticia_bloque_texto':
            $block = buildTextMultipleColumns($i, $metaData, $flexField);
            break;

        case 'noticia_bloque_imagen':
            $block = buildImageBlock($i, $metaData, $flexField);
            break;

        case 'noticia_bloque_video':
            $block = buildVideoBlock($i, $metaData, $flexField);
            break;

        case 'bloque_scripts':
            $block = buildIframeBlock($i, $metaData, $flexField);
            break;

        case 'noticia_bloque_cita':
            $block = buildImageTextSlider($i, $metaData, $flexField);
            break;

        case 'bloque_especial_fondo_verde':
            $block = buildHeadingTextMultipleColumns($i, $metaData, $flexField);
            break;

        case 'bloque_para_slider':
            $result = buildGallerySlider($i, $metaData, $flexField);
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

    // Asegurar que el bloque o bloques devueltos contengan 'acf_fc_layout'
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
