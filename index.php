<?php
/**
 * Script de migración de contenidos directamente integrado en WordPress
 */

// Autoload de WordPress (para poder usar funciones WP y ACF)
require_once __DIR__ . '/wp-load.php';

require_once __DIR__ . '/migracion/noticias/migrateNews.php';

// Tipo a migrar por parámetro GET (ejemplo: index.php?tipo=noticias)
$tipo = isset($_GET['tipo']) ? sanitize_text_field($_GET['tipo']) : 'todo';

// Conexión remota a DB origen
$origin_conn = new mysqli('localhost', 'root', '', 'wi');
if ($origin_conn->connect_error) {
    die("Error de conexión a la base de datos origen: " . $origin_conn->connect_error);
}
$origin_conn->set_charset("utf8mb4");
$orig_prefix = 'wi_';

switch ($tipo) {
    case 'noticias':
        migrateNews($origin_conn, $orig_prefix);
        break;

    case 'conferencias':
        echo "Migración de conferencias aún no implementada.";
        break;

    case 'exposiciones':
        echo "Migración de exposiciones aún no implementada.";
        break;

    case 'todo':
    default:
        migrateNews($origin_conn, $orig_prefix);
        echo "Conferencias y exposiciones aún no implementadas.";
        break;
}

$origin_conn->close();

echo "Migración finalizada correctamente.";
