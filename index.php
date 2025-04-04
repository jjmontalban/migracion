<?php
/**
 * Script de migración de contenidos
 */

require_once __DIR__ . '/../wp-load.php';

require_once __DIR__ . '/news/migrateNews.php';
require_once __DIR__ . '/conferences/migrateConferences.php';
require_once __DIR__ . '/exhibitions/migrateExhibitions.php';

use function migration\news\migrateNews;
use function migration\conferences\migrateConferences;
use function migration\exhibitions\migrateExhibitions;

// Tipo a migrar por parámetro GET (ejemplo: /index.php?type=news)
$type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';

// Conexión remota a DB origen
$origin_conn = new mysqli('localhost', 'root', '', 'backup_pro');
if ($origin_conn->connect_error) {
    die("Error de conexión a la base de datos origen: " . $origin_conn->connect_error);
}
$origin_conn->set_charset("utf8mb4");
$orig_prefix = 'wi_';

switch ($type) {
    case 'news':
        migrateNews($origin_conn, $orig_prefix);
        break;

    case 'conferences':
        migrateConferences($origin_conn, $orig_prefix);
        break;

    case 'exhibitions':
        migrateExhibitions($origin_conn, $orig_prefix);
        break;
    
    case 'all':
        default:
        migrateConferences($origin_conn, $orig_prefix);
        migrateExhibitions($origin_conn, $orig_prefix);
        migrateNews($origin_conn, $orig_prefix);
        break;
}

$origin_conn->close();

echo "Migración finalizada correctamente.";
