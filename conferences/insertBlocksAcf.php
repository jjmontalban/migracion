<?php
namespace migration\conferences;
require_once __DIR__ . '/parseLayoutBlock.php';
/**
 * Inserta los bloques ACF (campo flexible "noticia_cuerpo") en el post destino.
 *
 * Se mantienen los bloques fijos (hero-text, introducción, extracto) y se integra el proceso
 * de migración para la imagen destacada. Para migrar la imagen se obtiene la URL del post origen,
 * se migra la imagen y se actualiza el meta _thumbnail_id con el nuevo ID.
 *
 */
function insertBlocksIntoACF($post_id, $post_data, $metaData, $origin_conn, $orig_prefix) {
    $flexField = 'noticia_cuerpo';

    $metaData['__post_title'] = $post_data['post_title'];
    $captions = [];
    $blocks = [];

    // Bloque fijo: hero-ellipse
    $blocks[] = [
        'acf_fc_layout' => 'hero-ellipse',
        'b10_category'      => "Conferencias",
        'b10_title'     => $post_data['post_title'],
        'b10_content'     =>$post_data['post_excerpt']
    ];

    // Bloque fijo: texto de introducción
    if (!empty($metaData['noticia_texto_introduccion'])) {
        $blocks[] = [
            'acf_fc_layout' => 'text-multiple-columns',
            'b29_columns'   => '1',
            'b29_content'   => $metaData['noticia_texto_introduccion']
        ];
    }

    // Bloque fijo: imagen destacada
    if (!empty($metaData['_thumbnail_id'])) {
        $old_thumb_id  = $metaData['_thumbnail_id'];
        $old_image_url = get_old_image_url($old_thumb_id, $origin_conn, $orig_prefix);
        
        if ($old_image_url) {
            $new_thumb_id = migrate_image($old_image_url, $post_id);
            if ($new_thumb_id) {
                update_post_meta($post_id, '_thumbnail_id', $new_thumb_id);
                $metaData['_thumbnail_id'] = $new_thumb_id;
            }
        }
        
        $blocks[] = [
            'acf_fc_layout' => 'image',
            'b20_title'     => $post_data['post_title'],
            'b20_image'     => $metaData['_thumbnail_id']
        ];
    }

    // Bloque fijo: extracto
    if (!empty($post_data['post_excerpt'])) {
        $blocks[] = [
            'acf_fc_layout' => 'text-multiple-columns',
            'b29_columns'   => '1',
            'b29_content'   => $post_data['post_excerpt']
        ];
    }

    // Procesar layouts flexibles
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

    // Guardar los bloques usando la API de ACF (campo 'c1_blocks')
    update_field('c1_blocks', $blocks, $post_id);

}
