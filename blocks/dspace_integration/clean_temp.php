<?php
// blocks/dspace_integration/clean_temp.php
$temp_dir = __DIR__ . '/temp';
if (!is_dir($temp_dir)) {
    exit;
}

$files = glob($temp_dir . '/*.epub');
$now = time();

foreach ($files as $file) {
    // Eliminar archivos con más de 2 horas
    if (is_file($file) && ($now - filemtime($file)) > 7200) {
        unlink($file);
    }
}

error_log("DSpace temp files cleaned: " . count($files) . " processed");
?>