<?php
/**
 * Script de migración para Noticias
 */

// Requiere la lógica de metadatos y bloques ACF
require_once __DIR__ . '/map_fields_news.php';
require_once __DIR__ . '/map_blocks_news.php';

/**
 * Función principal para migrar noticias.
 *
 * @param mysqli $origin_conn  Conexión a la BD de origen
 * @param mysqli $dest_conn    Conexión a la BD de destino
 * @param string $orig_prefix  Prefijo de tablas en origen 
 * @param string $dest_prefix  Prefijo de tablas en destino
 */
function migrate_news($origin_conn, $dest_conn, $orig_prefix, $dest_prefix) {

    // IDs de origen
    $sql = "SELECT ID 
            FROM {$orig_prefix}posts 
            WHERE post_type = 'noticias' 
              AND post_status = 'publish' 
              AND ID = 334885
            LIMIT 40";

    $result = $origin_conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        echo "No hay noticias para migrar.\n";
        return;
    }

    echo "Se encontraron " . $result->num_rows . " noticias";
    flush(); ob_flush();

    // 2) Recorrer cada noticia y migrarla
    while ($row = $result->fetch_assoc()) {
        $orig_id = (int)$row['ID'];

        // Obtener datos principales del post (tabla posts)
        $sql_post = "SELECT * FROM {$orig_prefix}posts WHERE ID = $orig_id";
        $res_post = $origin_conn->query($sql_post);
        if (!$res_post || $res_post->num_rows === 0) {
            echo "Error al obtener datos de la noticia $orig_id: " . $origin_conn->error . "\n";
            continue;
        }
        $post_data = $res_post->fetch_assoc();

        // Comprobar si la noticia ya existe en la BD de destino, según su slug
        $slug = $dest_conn->real_escape_string($post_data['post_name']);


        // Insertar el post en la tabla destino
        $title   = $dest_conn->real_escape_string($post_data['post_title']);
        $excerpt = $dest_conn->real_escape_string($post_data['post_excerpt']);
        $content = $dest_conn->real_escape_string($post_data['post_content']);

        $sql_insert = "
            INSERT INTO {$dest_prefix}posts 
                (post_content, post_title, post_excerpt, post_name, post_status, post_type, post_date)
            VALUES 
                ('$content', '$title', '$excerpt', '$slug', '{$post_data['post_status']}', 'news', '{$post_data['post_date']}')
        ";

        if ($dest_conn->query($sql_insert)) {
            $new_post_id = $dest_conn->insert_id;

            // (A) Migrar metadatos simples (por ejemplo _thumbnail_id)
            //     y también recuperamos todos los meta del post origen
            $metaData = getMetaData($orig_id, $origin_conn, $orig_prefix);

            // Primero mapeamos meta tipo _thumbnail_id
            $mapped = mapMetaFields($metaData);
            insertMetaData($new_post_id, $mapped, $dest_conn, $dest_prefix);

            // (B) Migrar taxonomías categorías y etiquetas
            migrateNewsTaxonomies($orig_id, $new_post_id, $origin_conn, $dest_conn, $orig_prefix, $dest_prefix);

            // (C) Inserta campos Listados
            insertShortTextACF($new_post_id, $metaData, $dest_conn, $dest_prefix);


            // (D) Inserta bloque ACF (c4_blocks)
            insertBlocksIntoACF($new_post_id, $post_data, $metaData, $dest_conn, $dest_prefix);

        } else {
            echo "Error al migrar la noticia $orig_id: " . $dest_conn->error . "\n";
        }
    }

    echo "Migración de Noticias completada.";
}