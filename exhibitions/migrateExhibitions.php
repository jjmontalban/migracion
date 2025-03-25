<?php
namespace migration\exhibitions;
/**
 * Migración de Exposiciones usando WordPress y ACF.
 */

require_once __DIR__ . '/parseLayoutBlock.php';
require_once __DIR__ . '/insertBlocksAcf.php';

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

function migrateExhibitions($origin_conn, $orig_prefix) {
    $sql = "SELECT ID FROM {$orig_prefix}posts
            WHERE post_type = 'exposiciones'
              AND post_status = 'publish' AND ID IN ( 213366,213371 , 213380, 213386, 213304)";

    $result = $origin_conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        echo "No hay exposiciones para migrar.<br>";
        return;
    }
    echo "Se encontraron " . $result->num_rows . " exposiciones<br>";

    while ($row = $result->fetch_assoc()) {
        $orig_id = (int)$row['ID'];

        // Obtener datos del post origen
        $sql_post = "SELECT * FROM {$orig_prefix}posts WHERE ID = $orig_id";
        $res_post = $origin_conn->query($sql_post);

        if (!$res_post || $res_post->num_rows === 0) {
            echo "Error al obtener datos del post $orig_id: " . $origin_conn->error . "<br>";
            continue;
        }
        $post_data = $res_post->fetch_assoc();

        $new_post = [
            'post_title'   => wp_slash($post_data['post_title']),
            'post_content' => wp_slash($post_data['post_content']),
            'post_excerpt' => wp_slash($post_data['post_excerpt']),
            'post_name'    => $post_data['post_name'],
            'post_status'  => $post_data['post_status'],
            'post_type'    => 'exhibitions',
            'post_date'    => $post_data['post_date']
        ];

        $new_post_id = wp_insert_post($new_post);

        if (is_wp_error($new_post_id)) {
            echo "Error al insertar la exposición $orig_id: " . $new_post_id->get_error_message() . "<br>";
            continue;
        }

        // Migrar metadatos
        $metaData = getMetaData($orig_id, $origin_conn, $orig_prefix);
        foreach ($metaData as $key => $value) {
            update_post_meta($new_post_id, $key, maybe_unserialize($value));
        }

        // Migrar taxonomías: de 'categorias_exposiciones' a 'exhibitions-category'
        migrateTaxonomies($orig_id, $new_post_id, $origin_conn, $orig_prefix);

        // Migrar campos ACF específicos
        //  c3_title -> titulo_corto
        //  c3_excerpt -> descripcion_corta
        //  c3_date_start -> noticia_fecha
        // c3_image_list -> imagen_destacada
        $fechas = getDateStartEnd($metaData, $new_post_id);

        update_field('c3_title', $metaData['titulo_corto'] ?? '', $new_post_id);
        update_field('c3_excerpt', $metaData['descripcion_corta'] ?? '', $new_post_id);
        update_field('c3_date_start', $fechas['start'] ?? '', $new_post_id);
        update_field('c3_date_end', $fechas['end'] ?? '', $new_post_id);
        
        getDateStartEnd($metaData);

        if (!empty($metaData['_thumbnail_id'])) {
            $old_thumb_id  = $metaData['_thumbnail_id'];
            $old_image_url = get_old_image_url($old_thumb_id, $origin_conn, $orig_prefix);
            if ($old_image_url) {
                $new_thumb_id = migrate_image($old_image_url, $new_post_id);
                if ($new_thumb_id) {
                    update_post_meta($new_post_id, '_thumbnail_id', $new_thumb_id);
                    $metaData['_thumbnail_id'] = $new_thumb_id;
                }
            }
            update_field('c3_image_list', $metaData['_thumbnail_id'], $new_post_id);
        }

        // Migrar bloques ACF (Flexible Content) desde el campo 'c3_blocks'
        insertBlocksIntoACF($new_post_id, $post_data, $metaData, $origin_conn, $orig_prefix);
    }
    echo "Migración de exposiciones completada.<br>";
}

/**
 * Obtener metadatos desde "{$orig_prefix}postmeta"
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
 * Migrar taxonomías: de 'categorias_exposiciones' a 'exhibitions-category'
 */
function migrateTaxonomies($orig_id, $new_post_id, $origin_conn, $orig_prefix) {
    $sql = "
        SELECT t.name, t.slug, tt.taxonomy
        FROM {$orig_prefix}term_relationships AS tr
        JOIN {$orig_prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$orig_prefix}terms AS t ON tt.term_id = t.term_id
        WHERE tr.object_id = $orig_id 
        AND tt.taxonomy = 'categorias_exposiciones'
    ";
    $res = $origin_conn->query($sql);
    if (!$res || $res->num_rows === 0) return;
    $terms = [];
    while ($row = $res->fetch_assoc()) {
        $dest_taxonomy = 'exhibitions-category';
        ensureTermExists($row['name'], $row['slug'], $dest_taxonomy);
        $terms[] = $row['slug'];
    }
    if (!empty($terms)) {
        wp_set_object_terms($new_post_id, $terms, 'exhibitions-category');
    }
}

/**
 * Asegurar que el término existe en la taxonomía destino
 */
function ensureTermExists($name, $slug, $taxonomy) {
    if (!term_exists($slug, $taxonomy)) {
        wp_insert_term($name, $taxonomy, ['slug' => $slug]);
    }
}

/**
 * Obtiene la URL de la imagen
 */
function get_old_image_url($old_image_id, $conn, $orig_prefix) {
    $sql = "SELECT guid FROM {$orig_prefix}posts WHERE ID = $old_image_id";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row['guid'];
    }
    return false;
}

/**
 * Migra una imagen
 *
 */
function migrate_image($image_url, $post_id) {
    static $migrated_images = []; // cache de imagenes migradas

    // Si ya se ha migrado esta imagen, devolver el ID
    if (isset($migrated_images[$image_url])) {
        return $migrated_images[$image_url];
    }

    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        return false;
    }

    $file_array = [
        'name'     => basename($image_url),
        'tmp_name' => $tmp
    ];

    $attachment_id = media_handle_sideload($file_array, $post_id);
    if (is_wp_error($attachment_id)) {
        @unlink($file_array['tmp_name']);
        return false;
    }

    // Guardar en la caché
    $migrated_images[$image_url] = $attachment_id;

    return $attachment_id;
}
