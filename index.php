<?php
// ===================================================
// CONFIGURACIÓN Y EJECUCIÓN PRINCIPAL
// ===================================================

// Configuración de las bases de datos
$host = 'localhost';
$user = 'root';
$pass = '';
$db_orig = 'wi';
$db_dest = 'web-wi';

$orig_prefix = 'wi_';
$dest_prefix = 'ftwi_';

$orig_conn = getConnection($host, $user, $pass, $db_orig);
$dest_conn = getConnection($host, $user, $pass, $db_dest);

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todo';

require_once 'noticias/migrateNews.php';
//require_once 'conferencias/migrateConferences.php';
//require_once 'exposiciones/migrateExhibitions.php';

switch ($tipo) {
    case 'noticias':
        flush(); ob_flush();
        migrateNews($orig_conn, $dest_conn, $orig_prefix, $dest_prefix);
        break;

    case 'conferencias':
        //migrateConferences($orig_conn, $dest_conn, $orig_prefix, $dest_prefix);
        break;

    case 'exposiciones':
        //migrateExhibitions($orig_conn, $dest_conn, $orig_prefix, $dest_prefix);
        break;

    case 'todo':
    default:
        migrateNews($orig_conn, $dest_conn, $orig_prefix, $dest_prefix);
        //migrateConferences($orig_conn, $dest_conn, $orig_prefix, $dest_prefix);
        //migrateExhibitions($orig_conn, $dest_conn, $orig_prefix, $dest_prefix);
        break;
}

closeConnection($orig_conn);
closeConnection($dest_conn);

echo "Migración finalizada.";

function getConnection($host, $user, $pass, $db) {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("Error de conexión a BD ($db): " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function closeConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}


/**
 * Log para debug migracion.log
 */
function writeLog($message) {
    $logFile = __DIR__ . '/migracion.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
}