<?php
require_once('../../config.php');
require_once('dspace_client.php');
require_login();
global $USER, $PAGE;

try {
    $collection_id = required_param('collection_id', PARAM_TEXT);
    $title = required_param('title', PARAM_TEXT);

    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        throw new Exception("❌ No se seleccionaron archivos.");
    }

    $server = get_config('block_dspace_integration', 'server');
    $email = get_config('block_dspace_integration', 'email');
    $password = get_config('block_dspace_integration', 'password');

    $client = new dspace_client($server, $email, $password);

    $metadata = [
        "dc.title" => [["value" => $title, "language" => "es"]],
        "dc.description" => [["value" => "Subida desde Moodle", "language" => "es"]],
        "dc.contributor.author" => [["value" => $USER->firstname . ' ' . $USER->lastname, "language" => "es"]],
        "dc.date.issued" => [["value" => date("Y-m-d"), "language" => "es"]]
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

    $url = new moodle_url('/blocks/dspace_integration/upload_form.php');
    $message = '✅ Archivos subidos correctamente: ' . implode(', ', $uploaded_files);
    redirect($url, $message, 5, \core\output\notification::NOTIFY_SUCCESS);

} catch (Exception $e) {
    error_log('DSpace Error: ' . $e->getMessage());
    $url = new moodle_url('/blocks/dspace_integration/upload_form.php');
    $message = "❌ Hubo un problema al subir los archivos. Contacte al administrador.";
    redirect($url, $message, 5, \core\output\notification::NOTIFY_ERROR);


}
