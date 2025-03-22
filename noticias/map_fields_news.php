<?php
/**
 * Mapeo de metadatos de noticias
 */

/**
 * Definimos un mapeo simple de meta keys origen->destino para metadatos básicos
 * (por ejemplo, _thumbnail_id).
 */
$metaMapping = [
    '_thumbnail_id' => '_thumbnail_id',
];

/**
 * Obtiene todos los metadatos de una noticia desde la tabla origen "{$orig_prefix}postmeta".
 * 
 * @param int $post_id       ID del post en origen
 * @param mysqli $conn       Conexión a la BD de origen
 * @param string $orig_prefix  Prefijo de tablas en origen (por ej. "wi_")
 * @return array  Array asociativo: [ 'meta_key' => 'meta_value', ... ]
 */
function getMetaData($post_id, $conn, $orig_prefix) {
    $metaData = [];
    $sql = "SELECT meta_key, meta_value 
            FROM {$orig_prefix}postmeta
            WHERE post_id = $post_id";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $meta_key = $row['meta_key'];
            $meta_value = $row['meta_value'];
            $metaData[$meta_key] = $meta_value;
        }
    }
    return $metaData;
}

/**
 * Aplica el mapeo de metadatos (ej: _thumbnail_id).
 * 
 * @param array $metaData  Metadatos completos del post (key => value)
 * @return array           Solo los meta que queramos migrar directamente
 */
function mapMetaFields($metaData) {
    global $metaMapping;
    $mappedMeta = [];
    foreach ($metaData as $key => $value) {
        if (isset($metaMapping[$key])) {
            // Por ejemplo, _thumbnail_id => _thumbnail_id
            $destKey = $metaMapping[$key];
            $mappedMeta[$destKey] = $value;
        }
    }
    return $mappedMeta;
}

/**
 * Inserta los metadatos en la base de datos destino "{$dest_prefix}postmeta".
 * 
 * @param int $post_id
 * @param array $metaData  Ej: [ '_thumbnail_id' => '12' ]
 * @param mysqli $conn
 * @param string $dest_prefix
 */
function insertMetaData($post_id, $metaData, $conn, $dest_prefix) {
    foreach ($metaData as $key => $value) {
        $escaped_val = $conn->real_escape_string($value);
        $sql = "
            INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
            VALUES ($post_id, '$key', '$escaped_val')
        ";
        $conn->query($sql);
    }
}

/**
 * origen "titulo_corto" / "descripcion_corta"
 * DESTINO  "c4_title" / "c4_excerpt".
 * 
 *   - c4_title (field_67d02372ea16f)
 *   - c4_excerpt (field_67d02390ea170)
 */
function insertShortTextACF($post_id, $metaData, $conn, $dest_prefix) {

    // 1) Tomar el valor del origen
    //    Si no existen, se usan cadenas vacías
    $valor_titulo_corto = isset($metaData['titulo_corto']) ? $metaData['titulo_corto'] : '';
    $valor_descrip_corta = isset($metaData['descripcion_corta']) ? $metaData['descripcion_corta'] : '';

    // 2) Guardar en destino usando las keys "c4_title" y "c4_excerpt"
    //    y sus field keys de ACF
    $escaped_titulo = $conn->real_escape_string($valor_titulo_corto);
    $escaped_descr  = $conn->real_escape_string($valor_descrip_corta);

    // Insert c4_title
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, 'c4_title', '$escaped_titulo')
    ");
    // Field key = 'field_67d02372ea16f' (según tu JSON)
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, '_c4_title', 'field_67d02372ea16f')
    ");

    // Insert c4_excerpt
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, 'c4_excerpt', '$escaped_descr')
    ");
    // Field key = 'field_67d02390ea170'
    $conn->query("
        INSERT INTO {$dest_prefix}postmeta (post_id, meta_key, meta_value)
        VALUES ($post_id, '_c4_excerpt', 'field_67d02390ea170')
    ");

}


/**
 * Función para migrar taxonomías (categorías) de la noticia.
 * Simplificada: asume que quieres migrar "category" y NO migrar "post_tag".
 */
function migrateNewsTaxonomies($orig_id, $new_post_id, $origin_conn, $dest_conn, $orig_prefix, $dest_prefix) {
    // Obtener todas las relaciones de taxonomía del post en origen
    $sql = "
        SELECT tr.object_id, tr.term_taxonomy_id, tt.taxonomy, tt.term_id
        FROM {$orig_prefix}term_relationships AS tr
        JOIN {$orig_prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tr.object_id = $orig_id
    ";
    $res = $origin_conn->query($sql);
    if (!$res || $res->num_rows === 0) {
        echo "No tiene taxonomías asociadas";
        return;
    }

    while ($row = $res->fetch_assoc()) {
        $taxonomy = $row['taxonomy'];
        $term_id  = (int)$row['term_id'];

        // Procesamos solo las taxonomías "category" y "post_tag"
        if ($taxonomy === 'category' || $taxonomy === 'post_tag') {
            // Obtenemos la información del término en origen
            $sql_term = "SELECT * FROM {$orig_prefix}terms WHERE term_id = $term_id";
            $res_term = $origin_conn->query($sql_term);
            if ($res_term && $res_term->num_rows > 0) {
                $term_data = $res_term->fetch_assoc();
                $term_name = $dest_conn->real_escape_string($term_data['name']);
                $term_slug = $dest_conn->real_escape_string($term_data['slug']);

                // Si es una etiqueta (post_tag) y su slug es "hashtag", la omitimos
                if ($taxonomy === 'post_tag' && strtolower($term_slug) === 'hashtag') {
                    continue;
                }

                // Verificamos si el término ya existe en destino para esa taxonomía
                $check_sql = "
                    SELECT t.term_id
                    FROM {$dest_prefix}terms AS t
                    JOIN {$dest_prefix}term_taxonomy AS tt ON t.term_id = tt.term_id
                    WHERE t.slug = '$term_slug'
                      AND tt.taxonomy = '$taxonomy'
                    LIMIT 1
                ";
                $check_res = $dest_conn->query($check_sql);
                if ($check_res && $check_res->num_rows > 0) {
                    $existing = $check_res->fetch_assoc();
                    $dest_term_id = $existing['term_id'];
                } else {
                    // Si no existe, se crea en destino
                    $dest_conn->query("
                        INSERT INTO {$dest_prefix}terms (name, slug, term_group)
                        VALUES ('$term_name', '$term_slug', 0)
                    ");
                    $dest_term_id = $dest_conn->insert_id;

                    // Insertar en term_taxonomy con la taxonomía correspondiente
                    $dest_conn->query("
                        INSERT INTO {$dest_prefix}term_taxonomy (term_id, taxonomy, description, parent, count)
                        VALUES ($dest_term_id, '$taxonomy', '', 0, 0)
                    ");
                }

                // Obtenemos el term_taxonomy_id en destino
                $tt_sql = "SELECT term_taxonomy_id
                           FROM {$dest_prefix}term_taxonomy
                           WHERE term_id = $dest_term_id AND taxonomy = '$taxonomy'
                           LIMIT 1";
                $tt_res = $dest_conn->query($tt_sql);
                if ($tt_res && $tt_res->num_rows > 0) {
                    $tt_data = $tt_res->fetch_assoc();
                    $dest_tt_id = $tt_data['term_taxonomy_id'];

                    // Vinculamos el término al post en destino
                    $dest_conn->query("
                        INSERT IGNORE INTO {$dest_prefix}term_relationships (object_id, term_taxonomy_id, term_order)
                        VALUES ($new_post_id, $dest_tt_id, 0)
                    ");
                }
            }
        }
    }
}


