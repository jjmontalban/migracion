<?php

/**
 * Trae los metadatos desde "{$orig_prefix}postmeta"
 */
function getMetaData($post_id, $conn, $orig_prefix) {
    $metaData = [];
    $sql = "SELECT meta_key, meta_value 
            FROM {$orig_prefix}postmeta
            WHERE post_id = $post_id";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $metaData[$row['meta_key']] = maybe_unserialize($row['meta_value']);
        }
    }
    return $metaData;
}

/**
 * Inserta los metadatos usando funciones nativas de WP.
 */
function insertMetaData($post_id, $metaData) {
    foreach ($metaData as $key => $value) {
        update_post_meta($post_id, $key, $value);
    }
}

/**
 * Inserta campos ACF usando update_field().
 *  - c4_title (titulo_corto)
 *  - c4_excerpt (descripcion_corta)
 */
function insertShortTextACF($post_id, $metaData) {
    update_field('c4_title', $metaData['titulo_corto'] ?? '', $post_id);
    update_field('c4_excerpt', $metaData['descripcion_corta'] ?? '', $post_id);
}

/**
 * Migra categorías y etiquetas usando funciones nativas de WordPress.
 */
function migrateNewsTaxonomies($orig_id, $new_post_id, $origin_conn, $orig_prefix) {
    $sql = "
        SELECT tt.taxonomy, t.name, t.slug
        FROM {$orig_prefix}term_relationships AS tr
        JOIN {$orig_prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$orig_prefix}terms AS t ON tt.term_id = t.term_id
        WHERE tr.object_id = $orig_id
    ";
    $res = $origin_conn->query($sql);

    if (!$res || $res->num_rows === 0) return;

    $terms_by_taxonomy = [];

    while ($row = $res->fetch_assoc()) {
        // Omitir etiqueta específica "hashtag"
        if ($row['taxonomy'] === 'post_tag' && strtolower($row['slug']) === 'hashtag') {
            continue;
        }

        // Asegurar existencia del término
        ensureTermExists($row['name'], $row['slug'], $row['taxonomy']);

        // Agrupar términos por taxonomía
        $terms_by_taxonomy[$row['taxonomy']][] = $row['slug'];
    }

    // Asociar términos al nuevo post
    foreach ($terms_by_taxonomy as $taxonomy => $terms) {
        wp_set_object_terms($new_post_id, $terms, $taxonomy);
    }
}

/**
 * Crea un término si no existe previamente.
 */
function ensureTermExists($name, $slug, $taxonomy) {
    if (!term_exists($slug, $taxonomy)) {
        wp_insert_term($name, $taxonomy, ['slug' => $slug]);
    }
}
