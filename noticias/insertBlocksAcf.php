<?php

require_once __DIR__ . '/parseLayoutBlock.php';


function insertBlocksIntoACF($post_id, $post_data, $metaData) {
    $flexField = 'noticia_cuerpo';
    // para reutilizarlo en algunos bloques
    $metaData['__post_title'] = $post_data['post_title'];

    $blocks = [];
    $captions = [];

    // Bloques fijos

    // 1 Hero-text
    $blocks[] = [
        'acf_fc_layout' => 'hero-text',
        'b12_date' => date('d.m.Y', strtotime($post_data['post_date'])),
        'b12_title' => $post_data['post_title']
    ];

    // 2 texto de introducción
    if (!empty($metaData['noticia_texto_introduccion'])) {
        $blocks[] = [
            'acf_fc_layout' => 'text-multiple-columns',
            'b29_columns'   => '1',
            'b29_content'   => $metaData['noticia_texto_introduccion']
        ];
    }

    // 3 imagen destacada
    if (!empty($metaData['_thumbnail_id'])) {
        $blocks[] = [
            'acf_fc_layout' => 'image',
            'b20_title'     => $post_data['post_title'],
            'b20_image'     => $metaData['_thumbnail_id']
        ];
    }

    // 4 extracto 
    if (!empty($post_data['post_excerpt'])) {
        $blocks[] = [
            'acf_fc_layout' => 'text-multiple-columns',
            'b29_columns'   => '1',
            'b29_content'   => $post_data['post_excerpt']
        ];
    }

    // Layouts flexibles

    $layouts = maybe_unserialize($metaData[$flexField] ?? []) ?: [];

    foreach ($layouts as $i => $layout) {
        $result = parseLayoutBlock($layout, $i, $metaData, $flexField, $captions);

        if ($result) {
            if (isset($result[0])) { // Múltiples bloques
                foreach ($result as $block) {
                    if ($block) {
                        $blocks[] = $block;
                    }
                }
            } else { // Único bloque
                $blocks[] = $result;
            }
        }
    }

    // Guardar los bloques usando la API de ACF
    update_field('c4_blocks', $blocks, $post_id);

    // 4) Actualizar leyendas de imágenes (captions)
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