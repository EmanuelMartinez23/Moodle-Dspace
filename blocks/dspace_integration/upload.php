<?php
require_once('../../config.php');
require_once('dspace_client.php');
require_login();
global $USER, $PAGE;

try {
    $collection_id = required_param('collection_id', PARAM_TEXT);
    $title = required_param('title', PARAM_TEXT);
    $uploader = required_param('uploader', PARAM_TEXT);
    $dateissued = required_param('dateissued', PARAM_TEXT);

    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        throw new Exception("❌ No se seleccionaron archivos.");
    }

    // Validaciones básicas de campos no vacíos.
    if (trim($collection_id) === '' || trim($title) === '' || trim($uploader) === '' || trim($dateissued) === '') {
        throw new Exception('❌ Campos requeridos vacíos. Verifique colección, título, usuario y fecha.');
    }
    // Validar formato de fecha simple (YYYY-MM-DD).
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateissued)) {
        throw new Exception('❌ Formato de fecha inválido. Use YYYY-MM-DD.');
    }

    $server = get_config('block_dspace_integration', 'server');
    $email = get_config('block_dspace_integration', 'email');
    $password = get_config('block_dspace_integration', 'password');

    $client = new dspace_client($server, $email, $password);

    // Incorporar usuario y fecha en el título enviado a DSpace, según requerimiento.
    $combinedtitle = trim($title . ' — ' . $uploader . ' — ' . $dateissued);
    $metadata = [
        "dc.title" => [["value" => $combinedtitle, "language" => "es"]],
        "dc.description" => [["value" => "Subida desde Moodle", "language" => "es"]],
        "dc.contributor.author" => [["value" => $uploader, "language" => "es"]],
        "dc.date.issued" => [["value" => $dateissued, "language" => "es"]]
    ];

    $item_id = $client->create_archived_item($collection_id, $metadata);
    $bundle_uuid = $client->get_original_bundle($item_id);
    if (!$bundle_uuid) {
        $bundle_uuid = $client->create_original_bundle($item_id);
    }

    $uploaded_files = [];
    foreach ($_FILES['files']['tmp_name'] as $index => $tmpName) {
        if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK) {
            error_log("DSpace Error: Falló la subida del archivo {$_FILES['files']['name'][$index]} con código {$_FILES['files']['error'][$index]}");

            continue;
        }
        $filename = $_FILES['files']['name'][$index];
        $client->upload_bitstream($bundle_uuid, $tmpName, $filename);
        $uploaded_files[] = $filename;
    }

    // Redirigir a la portada (Home) tras subir.
    $url = new moodle_url('/');
    $message = '✅ Archivos subidos correctamente: ' . implode(', ', $uploaded_files);
    redirect($url, $message, 5, \core\output\notification::NOTIFY_SUCCESS);

} catch (Exception $e) {
    error_log('DSpace Error: ' . $e->getMessage());
    // Redirigir a la portada (Home) también en error, mostrando notificación.
    $url = new moodle_url('/');
    $message = "❌ Hubo un problema al subir los archivos. Contacte al administrador.";
    redirect($url, $message, 5, \core\output\notification::NOTIFY_ERROR);


}
