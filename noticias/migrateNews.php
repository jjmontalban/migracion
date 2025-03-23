<?php
/**
 * Script de migración para Noticias
 */

require_once __DIR__ . '/mapFieldsNews.php';
require_once __DIR__ . '/mapBlocksNews.php';
require_once __DIR__ . '/parseLayoutBlock.php';
require_once __DIR__ . '/insertBlocksAcf.php';

/**
 * Función principal para migrar noticias.
 *
 * @param mysqli $origin_conn  Conexión a la BD de origen
 * @param mysqli $dest_conn    Conexión a la BD de destino
 * @param string $orig_prefix  Prefijo de tablas en origen
 * @param string $dest_prefix  Prefijo de tablas en destino
 */
function migrateNews($origin_conn, $dest_conn, $orig_prefix, $dest_prefix) {

    // 1) Obtener IDs de posts tipo 'noticias'
    $sql = "SELECT ID
            FROM {$orig_prefix}posts
            WHERE post_type = 'noticias'
              AND post_status = 'publish'
              AND ID = 328325
              LIMIT 10";

    $result = $origin_conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        echo "No hay noticias para migrar.\n";
        return;
    }

    echo "Se encontraron " . $result->num_rows . " noticias\n";
    flush(); ob_flush();

    // Recorrer cada noticia
    while ($row = $result->fetch_assoc()) {
        $orig_id = (int)$row['ID'];

        // Obtener datos principales del post
        $sql_post = "SELECT * FROM {$orig_prefix}posts WHERE ID = $orig_id";
        $res_post = $origin_conn->query($sql_post);
        if (!$res_post || $res_post->num_rows === 0) {
            echo "Error al obtener datos del post $orig_id: " . $origin_conn->error . "\n";
            continue;
        }
        $post_data = $res_post->fetch_assoc();

        // Insertar el post en tabla destino (tipo 'news')
        $title   = $dest_conn->real_escape_string($post_data['post_title']);
        $excerpt = $dest_conn->real_escape_string($post_data['post_excerpt']);
        $content = $dest_conn->real_escape_string($post_data['post_content']);
        $slug    = $dest_conn->real_escape_string($post_data['post_name']);
        $status  = $post_data['post_status'];
        $date    = $post_data['post_date'];

        $sql_insert = "
            INSERT INTO {$dest_prefix}posts
                (post_content, post_title, post_excerpt, post_name, post_status, post_type, post_date)
            VALUES
                ('$content', '$title', '$excerpt', '$slug', '$status', 'news', '$date')
        ";

        if ($dest_conn->query($sql_insert)) {
            $new_post_id = $dest_conn->insert_id;

            // Migrar metadatos
            $metaData = getMetaData($orig_id, $origin_conn, $orig_prefix);
            insertMetaData($new_post_id, $metaData, $dest_conn, $dest_prefix);

            // Migrar taxonomías
            migrateNewsTaxonomies($orig_id, $new_post_id, $origin_conn, $dest_conn, $orig_prefix, $dest_prefix);

            // Campos ACF específicos
            insertShortTextACF($new_post_id, $metaData, $dest_conn, $dest_prefix);

            // Bloques ACF (flexible c4_blocks)
            insertBlocksIntoACF($new_post_id, $post_data, $metaData, $dest_conn, $dest_prefix);

        } else {
            echo "Error al insertar la noticia $orig_id: " . $dest_conn->error . "\n";
        }
    }

    echo "Migración de noticias completada.\n";
}
