<?php
// ===================================================
// CONFIGURACIÓN Y EJECUCIÓN PRINCIPAL
// ===================================================

// Configuración de la base de datos
$host = 'localhost';
$user = 'root';
$pass = '';
/* $db_orig = 'backup_pro'; */
$db_orig = 'wi';
$db_dest = 'web-wi';

// Prefijos de tablas en origen y en destino
$orig_prefix = 'wi_';
$dest_prefix = 'ftwi_';


// Conectar a BD origen y destino
$orig_conn = getConnection($host, $user, $pass, $db_orig);
$dest_conn = getConnection($host, $user, $pass, $db_dest);

// Determinar qué migrar según argumento en la URL
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

// Cerrar conexiones
closeConnection($orig_conn);
closeConnection($dest_conn);

echo "Migración finalizada.";

/**
 * Conexión/desconexión a la base de datos
 */
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
