<?php

require_once __DIR__ . '/mapBlocksNews.php';

/**
 * Convierte un layout ACF del origen en uno o varios bloques ACF destino.
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
            return buildTextMultipleColumns($i, $metaData, $flexField);

        case 'noticia_bloque_imagen':
            return buildImageBlock($i, $metaData, $flexField);

        case 'noticia_bloque_video':
            return buildVideoBlock($i, $metaData, $flexField);

        case 'bloque_scripts':
            return buildIframeBlock($i, $metaData, $flexField);

        case 'noticia_bloque_cita':
            return buildImageTextSlider($i, $metaData, $flexField);

        case 'bloque_especial_fondo_verde':
            return buildHeadingTextMultipleColumns($i, $metaData, $flexField);

        case 'bloque_para_slider':
            $result = buildGallerySlider($i, $metaData, $flexField);
            if ($result && isset($result['acf_block'])) {
                // Añadir las leyendas
                $captions = array_merge($captions, $result['captions']);
                return $result['acf_block'];
            }
            return null;

        default:
            return null;
    }
}
