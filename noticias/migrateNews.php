<?php
/**
 * Migración de Noticias usando WordPress y ACF.
 */

require_once __DIR__ . '/mapFieldsNews.php';
require_once __DIR__ . '/parseLayoutBlock.php';
require_once __DIR__ . '/insertBlocksAcf.php';

function migrateNews($origin_conn, $orig_prefix) {
    // Obtener noticias desde origen
    $sql = "SELECT ID FROM {$orig_prefix}posts
            WHERE post_type = 'noticias'
              AND post_status = 'publish'
              LIMIT 10";

    $result = $origin_conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        wp_cli_log("No hay noticias para migrar.");
        return;
    }

    wp_cli_log("Se encontraron {$result->num_rows} noticias.");

    while ($row = $result->fetch_assoc()) {
        $orig_id = (int)$row['ID'];

        // Obtener datos del post origen
        $sql_post = "SELECT * FROM {$orig_prefix}posts WHERE ID = $orig_id";
        $res_post = $origin_conn->query($sql_post);

        if (!$res_post || $res_post->num_rows === 0) {
            wp_cli_log("Error al obtener datos del post {$orig_id}.");
            continue;
        }

        $post_data = $res_post->fetch_assoc();

        // Crear el post directamente en WordPress
        $new_post_id = wp_insert_post([
            'post_title'    => wp_strip_all_tags($post_data['post_title']),
            'post_excerpt'  => wp_strip_all_tags($post_data['post_excerpt']),
            'post_content'  => $post_data['post_content'],
            'post_name'     => $post_data['post_name'],
            'post_status'   => $post_data['post_status'],
            'post_type'     => 'news',
            'post_date'     => $post_data['post_date']
        ]);

        if (is_wp_error($new_post_id)) {
            wp_cli_log("Error al insertar noticia {$orig_id}: " . $new_post_id->get_error_message());
            continue;
        }

        // Migrar metadatos
        $metaData = getMetaData($orig_id, $origin_conn, $orig_prefix);
        foreach ($metaData as $key => $value) {
            update_post_meta($new_post_id, $key, maybe_unserialize($value));
        }

        // Migrar taxonomías (categorías y etiquetas)
        migrateTaxonomies($orig_id, $new_post_id, $origin_conn, $orig_prefix);

        // Migrar campos ACF específicos
        update_field('c4_title', $metaData['titulo_corto'] ?? '', $new_post_id);
        update_field('c4_excerpt', $metaData['descripcion_corta'] ?? '', $new_post_id);

        // Migrar bloques ACF (Flexible Content)
        insertBlocksIntoACF($new_post_id, $post_data, $metaData);

        wp_cli_log("Migrada noticia ID {$orig_id} a nuevo post ID {$new_post_id}.");
    }

    wp_cli_log("Migración de noticias completada.");
}

/**
 * Migrar categorías y etiquetas desde origen
 */
function migrateTaxonomies($orig_id, $new_post_id, $origin_conn, $orig_prefix) {
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
        if ($row['taxonomy'] === 'post_tag' && strtolower($row['slug']) === 'hashtag') {
            continue;
        }
        $terms_by_taxonomy[$row['taxonomy']][] = $row['slug'];
        ensureTermExists($row['name'], $row['slug'], $row['taxonomy']);
    }

    foreach ($terms_by_taxonomy as $taxonomy => $terms) {
        wp_set_object_terms($new_post_id, $terms, $taxonomy);
    }
}

/**
 * Crea el término si no existe.
 */
function ensureTermExists($name, $slug, $taxonomy) {
    if (!term_exists($slug, $taxonomy)) {
        wp_insert_term($name, $taxonomy, ['slug' => $slug]);
    }
}

/**
 * Función simple para log con WP-CLI (si se usa WP-CLI).
 */
function wp_cli_log($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($message);
    } else {
        error_log($message);
    }
}
