<?php
require_once('../../config.php');
require_once('dspace_client.php');
require_login();
global $USER, $OUTPUT, $PAGE;
$PAGE->requires->css(new moodle_url('/blocks/dspace_integration/styles/styles.css'));


// Configuración DSpace
$server = get_config('block_dspace_integration', 'server');
$email = get_config('block_dspace_integration', 'email');
$password = get_config('block_dspace_integration', 'password');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/dspace_integration/upload_form.php'));
$PAGE->set_title('Subir Archivo a DSpace');

echo $OUTPUT->header();

try {
    $client = new dspace_client($server, $email, $password);
    $communities = $client->get_communities();

    if (empty($communities)) {
        throw new moodle_exception("⚠️ No se encontraron comunidades en DSpace.");
    }

    echo '<div class="dspace-upload-container">';
    echo '<h3 class="dspace-title">📤 Subir Archivos a DSpace</h3>';
    echo '<form id="uploadForm" enctype="multipart/form-data" class="dspace-form" action="upload.php" method="post">';
    
    echo '<div class="form-group">';
    echo '<label for="collection">Seleccione la colección:</label>';
    echo '<select name="collection_id" class="form-control" required>';
    foreach ($communities as $community) {
        $collections = $client->get_collections($community['uuid']);
        foreach ($collections as $col) {
            $name = htmlspecialchars($col['name']);
            $uuid = $col['uuid'];
            echo "<option value='$uuid'>$name (Comunidad: {$community['name']})</option>";
        }
    }
    echo '</select>';
    echo '</div>';
    echo '<label for="title">Título del Item:</label>';
    echo '<input type="text" name="title" id="title" required style="width:100%; margin:0 0; border:1px solid #ccc; border-radius:5px;"><br><br>';

    echo '<div class="form-group">';
    echo '<label for="file">Selecciona uno o varios archivos:</label>';
    echo '<input type="file" name="files[]" class="form-control-file" multiple required>';
    echo '</div>';

    echo '<button type="submit" class="btn btn-primary">🚀 Subir a DSpace</button>';
    echo '</form>';
    echo '</div>';

} catch (Exception $e) {
    $userMessage = '❌ Hubo un problema al conectar con DSpace. Por favor contacte al administrador.';
    error_log('DSpace Error: ' . $e->getMessage());
    $url = new moodle_url('/');
    redirect($url, $userMessage, 60, \core\output\notification::NOTIFY_ERROR);
}


echo $OUTPUT->footer();
