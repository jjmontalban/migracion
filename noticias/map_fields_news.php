<?php
/**
 * Obtiene todos los metadatos de una noticia desde la tabla origen "{$orig_prefix}postmeta".
 */
function getMetaData($post_id, $conn, $orig_prefix) {
    $metaData = [];
    $sql = "SELECT meta_key, meta_value 
            FROM {$orig_prefix}postmeta
            WHERE post_id = $post_id";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // para evitar avisos en real_escape_string
            $metaData[$row['meta_key']] = $row['meta_value'];
        }
    }
    return $metaData;
}

/**
 * Inserta los metadatos en la base de datos destino "{$dest_prefix}postmeta".
 */
function insertMetaData($post_id, $metaData, $conn, $dest_prefix) {
    foreach ($metaData as $key => $value) {
        // Evitar avisos si $value es null
        $escaped_val = $conn->real_escape_string((string)$value);
        $sql = "
            INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
            VALUES ($post_id, '$key', '$escaped_val')
        ";
        $conn->query($sql);
    }
}

/**
 * Inserta campos ACF personalizados: "c4_title" y "c4_excerpt"
 *  - c4_title (field_67d02372ea16f)
 *  - c4_excerpt (field_67d02390ea170)
 */
function insertShortTextACF($post_id, $metaData, $conn, $dest_prefix) {
    $titulo_corto = $metaData['titulo_corto'] ?? '';
    $descripcion_corta = $metaData['descripcion_corta'] ?? '';

    $titulo_corto = $conn->real_escape_string((string)$titulo_corto);
    $descripcion_corta = $conn->real_escape_string((string)$descripcion_corta);

    // titulo corto
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, 'c4_title', '$titulo_corto')
    ");
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, '_c4_title', 'field_67d02372ea16f')
    ");

    // descripcion corta
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, 'c4_excerpt', '$descripcion_corta')
    ");
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, '_c4_excerpt', 'field_67d02390ea170')
    ");
}

/**
 * Migrar categorías y etiquetas del post.
 */
function migrateNewsTaxonomies($orig_id, $new_post_id, $origin_conn, $dest_conn, $orig_prefix, $dest_prefix) {
    $sql = "
        SELECT tr.object_id, tr.term_taxonomy_id, tt.taxonomy, tt.term_id
        FROM {$orig_prefix}term_relationships AS tr
        JOIN {$orig_prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tr.object_id = $orig_id
    ";
    $res = $origin_conn->query($sql);
    if (!$res || $res->num_rows === 0) return;

    while ($row = $res->fetch_assoc()) {
        $taxonomy = $row['taxonomy'];
        $term_id  = (int)$row['term_id'];

        // Solo migramos category y post_tag
        if ($taxonomy === 'category' || $taxonomy === 'post_tag') {
            $sql_term = "SELECT * FROM {$orig_prefix}terms WHERE term_id = $term_id";
            $res_term = $origin_conn->query($sql_term);
            if ($res_term && $res_term->num_rows > 0) {
                $term_data = $res_term->fetch_assoc();
                $term_name = $dest_conn->real_escape_string($term_data['name']);
                $term_slug = $dest_conn->real_escape_string($term_data['slug']);

                // Omite 'hashtag'
                if ($taxonomy === 'post_tag' && strtolower($term_slug) === 'hashtag') {
                    continue;
                }

                // Buscar término en destino
                $check_sql = "
                    SELECT t.term_id
                    FROM {$dest_prefix}terms AS t
                    JOIN {$dest_prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
                    WHERE t.slug = '$term_slug' AND tt.taxonomy = '$taxonomy'
                    LIMIT 1
                ";
                $check_res = $dest_conn->query($check_sql);

                if ($check_res && $check_res->num_rows > 0) {
                    // Ya existe
                    $existing = $check_res->fetch_assoc();
                    $dest_term_id = $existing['term_id'];
                } else {
                    // Crear término
                    $dest_conn->query("
                        INSERT INTO {$dest_prefix}terms (name, slug, term_group)
                        VALUES ('$term_name', '$term_slug', 0)
                    ");
                    $dest_term_id = $dest_conn->insert_id;

                    $dest_conn->query("
                        INSERT INTO {$dest_prefix}term_taxonomy (term_id, taxonomy, description, parent, count)
                        VALUES ($dest_term_id, '$taxonomy', '', 0, 0)
                    ");
                }

                // Vincular término al post
                $tt_sql = "
                    SELECT term_taxonomy_id
                    FROM {$dest_prefix}term_taxonomy
                    WHERE term_id = $dest_term_id AND taxonomy = '$taxonomy'
                    LIMIT 1
                ";
                $tt_res = $dest_conn->query($tt_sql);
                if ($tt_res && $tt_res->num_rows > 0) {
                    $tt_data = $tt_res->fetch_assoc();
                    $dest_tt_id = $tt_data['term_taxonomy_id'];
                    $dest_conn->query("
                        INSERT IGNORE INTO {$dest_prefix}term_relationships (object_id, term_taxonomy_id, term_order)
                        VALUES ($new_post_id, $dest_tt_id, 0)
                    ");
                }
            }
        }
    }
}
